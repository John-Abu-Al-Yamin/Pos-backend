# Purchase Flow — POS System

## Overview

Nothing enters inventory without a **purchase transaction**. This includes:

- New mobile phones
- Used mobile phones
- Accessories (model-specific or generic)
- Opening stock (existing inventory when the system starts)

---

## 1. Database Schema

### 1.1 Categories

```php
Schema::create('categories', function (Blueprint $table) {
    $table->id();
    $table->string('name')->unique();
    $table->timestamps();
});
```

| Column | Type          | Notes                                |
| ------ | ------------- | ------------------------------------ |
| id     | bigint PK     |                                      |
| name   | string unique | e.g. "Smartphones", "Cases", "Audio" |

### 1.2 Products (Catalog)

```php
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->foreignId('category_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->timestamps();
});
```

| Column      | Type            | Notes                     |
| ----------- | --------------- | ------------------------- |
| id          | bigint PK       |                           |
| category_id | FK → categories |                           |
| name        | string          | e.g. "Samsung Galaxy S25" |

### 1.3 Suppliers

```php
Schema::create('suppliers', function (Blueprint $table) {
    $table->id();
    $table->string('name')->unique();
    $table->string('phone')->unique();
    $table->timestamps();
});
```

| Column | Type          | Notes                      |
| ------ | ------------- | -------------------------- |
| id     | bigint PK     |                            |
| name   | string unique | e.g. "Samsung Distributor" |
| phone  | string unique |                            |

### 1.4 Purchase Headers

```php
Schema::create('purchase_headers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
    $table->date('date');
    $table->decimal('total', 10, 2)->default(0);
    $table->enum('type', ['purchase', 'opening_stock'])->default('purchase');
    $table->timestamps();
});
```

| Column      | Type            | Notes                     |
| ----------- | --------------- | ------------------------- |
| id          | bigint PK       |                           |
| supplier_id | FK → suppliers? | nullable, nullOnDelete    |
| date        | date            | Purchase date             |
| total       | decimal(10,2)   | Total cost, defaults to 0 |
| type        | enum            | purchase / opening_stock  |

### 1.5 Purchase Items (Line Items)

```php
Schema::create('purchase_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('purchase_header_id')->constrained()->cascadeOnDelete();
    $table->foreignId('product_id')->constrained();
    $table->integer('quantity');
    $table->decimal('unit_cost', 10, 2);
    $table->decimal('line_total', 10, 2);
    $table->timestamps();
});
```

| Column             | Type                  | Notes                         |
| ------------------ | --------------------- | ----------------------------- |
| id                 | bigint PK             |                               |
| purchase_header_id | FK → purchase_headers |                               |
| product_id         | FK → products         | Which catalog item was bought |
| quantity           | integer               | Number of units               |
| unit_cost          | decimal(10,2)         | Cost per unit from supplier   |
| line_total         | decimal(10,2)         | quantity × unit_cost          |

### 1.6 Stock Items (Individual Units)

```php
Schema::create('stock_items', function (Blueprint $table) {
    $table->id();

    $table->foreignId('purchase_item_id')
          ->nullable()
          ->constrained()
          ->nullOnDelete();

    $table->foreignId('product_id')
          ->constrained();

    $table->string('serial_number')
          ->nullable()
          ->unique();

    $table->decimal('cost_price', 12, 2);

    $table->enum('condition', [
        'new', 'excellent', 'good', 'fair'
    ])->default('new');

    $table->enum('status', [
        'available', 'sold', 'damaged', 'returned'
    ])->default('available');

    $table->foreignId('sale_item_id')
          ->nullable()
          ->constrained()
          ->nullOnDelete();

    $table->timestamp('sold_at')->nullable();

    $table->json('attributes')->nullable();

    $table->timestamps();
});
```

| Column           | Type              | Notes                                     |
| ---------------- | ----------------- | ----------------------------------------- |
| id               | bigint PK         |                                           |
| purchase_item_id | FK → purchase_items? | nullable, nullOnDelete                  |
| product_id       | FK → products     |                                           |
| serial_number    | string unique?    | IMEI for phones, null for generic items   |
| cost_price       | decimal(12,2)     | Unit cost from supplier                   |
| condition        | enum              | new / excellent / good / fair             |
| status           | enum              | available / sold / damaged / returned     |
| sale_item_id     | FK → sale_items?  | nullable, filled when sold                |
| sold_at          | timestamp?        | When the item was sold                    |
| attributes       | json?             | Flexible per-type metadata                |

---

## 2. Relationships

```php
class Category extends Model
{
    public function products() { return $this->hasMany(Product::class); }
}

class Product extends Model
{
    public function category() { return $this->belongsTo(Category::class); }
    public function purchaseItems() { return $this->hasMany(PurchaseItem::class); }
}

class Supplier extends Model
{
    public function purchaseHeaders() { return $this->hasMany(PurchaseHeader::class); }
}

class PurchaseHeader extends Model
{
    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function purchaseItems() { return $this->hasMany(PurchaseItem::class); }
}

class PurchaseItem extends Model
{
    public function purchaseHeader() { return $this->belongsTo(PurchaseHeader::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function stockItems() { return $this->hasMany(StockItem::class); }
}

class StockItem extends Model
{
    public function product()      { return $this->belongsTo(Product::class); }
    public function purchaseItem() { return $this->belongsTo(PurchaseItem::class); }
}
```

---

## 3. Purchase Flow

### 3.1 Standard Purchase (New Stock from Supplier)

```
Step 1   Define categories        → POST /api/categories
Step 2   Define products          → POST /api/products
Step 3   Define supplier          → POST /api/suppliers
Step 4   Create purchase header   → POST /api/purchase-headers
Step 5   Add purchase items       → POST /api/purchase-items
Step 6   System generates stock_items  → 1 row per unit
```

### 3.2 API Request: Create Purchase Header

```
POST /api/purchase-headers
```

```json
{
    "supplier_id": 1,
    "date": "2026-06-20",
    "total": 135000.0,
    "type": "purchase"
}
```

| Field       | Description                                 |
| ----------- | ------------------------------------------- |
| supplier_id | The supplier providing the stock (nullable) |
| date        | Purchase date                               |
| total       | Total cost of the purchase                  |
| type        | `purchase` or `opening_stock`               |

### 3.3 API: Purchase Items CRUD

All endpoints return a single item (not arrays). `line_total` is calculated dynamically as `quantity × unit_cost` on the backend.

#### Create

```
POST /api/purchase-items
```

```json
{
    "purchase_header_id": 1,
    "product_id": 1,
    "quantity": 30,
    "unit_cost": 4500.00
}
```

| Field              | Description                              |
| ------------------ | ---------------------------------------- |
| purchase_header_id | The purchase header this item belongs to |
| product_id         | The catalog product being purchased      |
| quantity           | How many units                           |
| unit_cost          | Price per unit from supplier             |

#### Read

| Method | Endpoint                 | Description  |
| ------ | ------------------------ | ------------ |
| GET    | `/api/purchase-items`    | Paginated list |
| GET    | `/api/purchase-items/{id}` | Single item  |

#### Update

```
PUT /api/purchase-items/{id}
```

```json
{
    "quantity": 35,
    "unit_cost": 4200.00
}
```

`line_total` recalculated automatically when `quantity` or `unit_cost` changes. Partial update supported.

#### Delete

```
DELETE /api/purchase-items/{id}
```

### 3.4 Validation Rules

#### Purchase Header

| Field       | Rule                            |
| ----------- | ------------------------------- |
| supplier_id | `nullable\|exists:suppliers,id` |
| date        | `required\|date`                |
| type        | `required`                      |

> **Note:** `total` is not currently validated (auto-calculated or set in controller logic).

#### Purchase Item

| Field              | Rule                                           |
| ------------------ | ---------------------------------------------- |
| purchase_header_id | `required\|exists:purchase_headers,id`         |
| product_id         | `required\|exists:products,id`                 |
| quantity           | `required\|integer\|min:1`                     |
| unit_cost          | `required\|numeric\|min:0`                     |

> **Note:** `line_total` is not accepted from the client. It is calculated server-side as `quantity × unit_cost`.

### 3.5 API Request: Opening Stock

Uses the same endpoint with `type: opening_stock` and `supplier_id` omitted (nullable).

```json
POST /api/purchase-headers
{
  "date": "2026-06-20",
  "total": 0,
  "type": "opening_stock"
}
```

---

## 4. API Routes

```php
Route::middleware('auth:sanctum')->group(function () {

    // Categories
    Route::apiResource('categories', CategoryController::class);

    // Products
    Route::apiResource('products', ProductController::class);

    // Suppliers
    Route::apiResource('suppliers', SupplierController::class);

    // Purchase Headers
    Route::apiResource('purchase-headers', PurchaseHeaderController::class);

    // Purchase Items
    Route::get('/purchase-items', [PurchaseItemController::class, 'index']);
    Route::post('/purchase-items', [PurchaseItemController::class, 'store']);
    Route::get('/purchase-items/{id}', [PurchaseItemController::class, 'show']);
    Route::put('/purchase-items/{id}', [PurchaseItemController::class, 'update']);
    Route::delete('/purchase-items/{id}', [PurchaseItemController::class, 'destroy']);


});
```

---

## 5. Summary

| Concept              | Implementation                            | Purpose                                               |
| -------------------- | ----------------------------------------- | ----------------------------------------------------- |
| Product catalog      | `products` table                          | What you sell — definitions only                      |
| Categories           | `categories` table                        | Organize products                                     |
| Suppliers            | `suppliers` table                         | Who you buy from                                      |
| Purchase transaction | `purchase_headers` + `purchase_items`     | What came in, from whom, which products, at what cost |
| Purchase line items  | `purchase_items` table                    | Per-product breakdown of a purchase                   |
| Individual units     | `stock_items` table                       | One row per physical item in inventory                |
| Opening stock        | `purchase_headers.type = 'opening_stock'` | Initial inventory, same flow                          |
