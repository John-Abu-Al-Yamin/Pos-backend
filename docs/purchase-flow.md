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
    $table->boolean('is_serialized')->default(true); // true = mobile, false = accessory
    $table->timestamps();
});
```

| Column        | Type            | Notes                                   |
| ------------- | --------------- | --------------------------------------- |
| id            | bigint PK       |                                         |
| category_id   | FK → categories |                                         |
| name          | string          | e.g. "Samsung Galaxy S25"               |
| is_serialized | boolean         | `true` = mobile (IMEI), `false` = accessory |

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
    $table->enum('condition', ['new', 'excellent', 'good', 'fair'])->default('new');
    $table->timestamps();
});
```

| Column             | Type                  | Notes                            |
| ------------------ | --------------------- | -------------------------------- |
| id                 | bigint PK             |                                  |
| purchase_header_id | FK → purchase_headers |                                  |
| product_id         | FK → products         | Which catalog item was bought    |
| quantity           | integer               | Number of units                  |
| unit_cost          | decimal(10,2)         | Cost per unit from supplier      |
| line_total         | decimal(10,2)         | quantity × unit_cost             |
| condition          | enum                  | new / excellent / good / fair    |

### 1.6 Stock Items (Individual Units)

```php
Schema::create('stock_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_id')->constrained();
    $table->foreignId('purchase_item_id')->nullable()->constrained()->nullOnDelete();
    $table->string('serial_number')->nullable()->unique();
    $table->decimal('cost_price', 10, 2);
    $table->enum('condition', ['new', 'excellent', 'good', 'fair'])->default('new');
    $table->enum('status', ['available', 'sold', 'reserved', 'damaged', 'returned'])->default('available');
    $table->timestamps();
});
```

| Column           | Type                  | Notes                                       |
| ---------------- | --------------------- | ------------------------------------------- |
| id               | bigint PK             |                                             |
| product_id       | FK → products         |                                             |
| purchase_item_id | FK → purchase_items?  | nullable, nullOnDelete                      |
| serial_number    | string unique?        | Auto-generated for mobiles, null for accessories |
| cost_price       | decimal(10,2)         | Unit cost from supplier                     |
| condition        | enum                  | new / excellent / good / fair               |
| status           | enum                  | available / sold / reserved / damaged / returned |

---

## 2. Relationships

```php
class Category extends Model
{
    public function products() { return $this->hasMany(Product::class); }
}

class Product extends Model
{
    protected $fillable = ['name', 'category_id', 'is_serialized'];

    public function category()      { return $this->belongsTo(Category::class); }
    public function purchaseItems() { return $this->hasMany(PurchaseItem::class); }
    public function stockItems()    { return $this->hasMany(StockItem::class); }
}

class Supplier extends Model
{
    public function purchaseHeaders() { return $this->hasMany(PurchaseHeader::class); }
}

class PurchaseHeader extends Model
{
    public function supplier()     { return $this->belongsTo(Supplier::class); }
    public function purchaseItems(){ return $this->hasMany(PurchaseItem::class); }
}

class PurchaseItem extends Model
{
    protected $fillable = ['purchase_header_id', 'product_id', 'quantity', 'unit_cost', 'line_total', 'condition'];

    public function purchaseHeader() { return $this->belongsTo(PurchaseHeader::class); }
    public function product()        { return $this->belongsTo(Product::class); }
    public function stockItems()     { return $this->hasMany(StockItem::class); }
}

class StockItem extends Model
{
    protected $fillable = ['product_id', 'purchase_item_id', 'serial_number', 'cost_price', 'condition', 'status'];

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
Step 6   System generates stock_items  → 1 row per unit (via StockItemService)
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

All endpoints return a single item (not arrays). `line_total` is calculated dynamically as `quantity × unit_cost` on the backend. When a purchase item is created, `StockItemService` automatically generates one stock item per unit.

#### Create

```
POST /api/purchase-items
```

```json
{
    "purchase_header_id": 1,
    "product_id": 1,
    "quantity": 30,
    "unit_cost": 4500.00,
    "condition": "new"
}
```

| Field              | Description                              |
| ------------------ | ---------------------------------------- |
| purchase_header_id | The purchase header this item belongs to |
| product_id         | The catalog product being purchased      |
| quantity           | How many units                           |
| unit_cost          | Price per unit from supplier             |
| condition          | (optional) new / excellent / good / fair |

**StockItems generated automatically:**
- **Mobile** (`is_serialized = true`): Each unit gets a unique serial number (e.g. `SN-0001-20260621-A7X2`) via `SerialNumberService`.
- **Accessory** (`is_serialized = false`): `serial_number` is left `null`.
- `condition` is copied from the purchase item to each stock item.
- Response includes the `stockItems` relation.

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
    "unit_cost": 4200.00,
    "condition": "excellent"
}
```

`line_total` recalculated automatically when `quantity` or `unit_cost` changes. Partial update supported.

> **Note:** Updating a purchase item does **not** retroactively modify already-created stock items.

#### Delete

```
DELETE /api/purchase-items/{id}
```

### 3.4 Stock Items CRUD

| Method   | Endpoint                | Description     |
| -------- | ----------------------- | --------------- |
| GET      | `/api/stock-items`      | Paginated list  |
| POST     | `/api/stock-items`      | Create manually |
| GET      | `/api/stock-items/{id}` | Single item     |
| PUT      | `/api/stock-items/{id}` | Update item     |
| DELETE   | `/api/stock-items/{id}` | Delete item     |

Manually creating a stock item via `POST /api/stock-items` also auto-generates the serial number when the product is serialized and no `serial_number` is provided.

### 3.5 Validation Rules

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
| condition          | `nullable\|in:new,excellent,good,fair`         |

> **Note:** `line_total` is not accepted from the client. It is calculated server-side as `quantity × unit_cost`.

#### Stock Item

| Field             | Rule                                               |
| ----------------- | -------------------------------------------------- |
| product_id        | `required\|exists:products,id`                     |
| purchase_item_id  | `nullable\|exists:purchase_items,id`               |
| serial_number     | `nullable\|string\|unique:stock_items,serial_number` |
| cost_price        | `required\|numeric\|min:0`                         |
| condition         | `nullable\|in:new,excellent,good,fair`             |
| status            | `nullable\|in:available,sold,reserved,damaged,returned` |

#### Product

| Field         | Rule                            |
| ------------- | ------------------------------- |
| name          | `required\|string`              |
| category_id   | `required\|exists:categories,id`|
| is_serialized | `boolean`                       |

### 3.6 API Request: Opening Stock

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
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::get('/categories/{id}', [CategoryController::class, 'show']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

    // Products
    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);

    // Suppliers
    Route::get('/suppliers', [SupplierController::class, 'index']);
    Route::post('/suppliers', [SupplierController::class, 'store']);
    Route::get('/suppliers/{id}', [SupplierController::class, 'show']);
    Route::put('/suppliers/{id}', [SupplierController::class, 'update']);
    Route::delete('/suppliers/{id}', [SupplierController::class, 'destroy']);

    // Purchase Headers
    Route::get('/purchase-headers', [PurchaseHeaderController::class, 'index']);
    Route::post('/purchase-headers', [PurchaseHeaderController::class, 'store']);
    Route::get('/purchase-headers/{id}', [PurchaseHeaderController::class, 'show']);
    Route::put('/purchase-headers/{id}', [PurchaseHeaderController::class, 'update']);
    Route::delete('/purchase-headers/{id}', [PurchaseHeaderController::class, 'destroy']);

    // Purchase Items
    Route::get('/purchase-items', [PurchaseItemController::class, 'index']);
    Route::post('/purchase-items', [PurchaseItemController::class, 'store']);
    Route::get('/purchase-items/{id}', [PurchaseItemController::class, 'show']);
    Route::put('/purchase-items/{id}', [PurchaseItemController::class, 'update']);
    Route::delete('/purchase-items/{id}', [PurchaseItemController::class, 'destroy']);

    // Stock Items
    Route::get('/stock-items', [StockItemController::class, 'index']);
    Route::post('/stock-items', [StockItemController::class, 'store']);
    Route::get('/stock-items/{id}', [StockItemController::class, 'show']);
    Route::put('/stock-items/{id}', [StockItemController::class, 'update']);
    Route::delete('/stock-items/{id}', [StockItemController::class, 'destroy']);
});
```

---

## 5. Service Layer

### 5.1 SerialNumberService

Generates unique serial numbers for serialized products (mobiles). Located at `app/Services/SerialNumberService.php`.

```
Format: SN-{product_id_padded}-{YYYYMMDD}-{random4}
Example: SN-0001-20260621-A7X2
```

Uses a `do-while` loop to guarantee uniqueness against existing `stock_items.serial_number` values.

### 5.2 StockItemService

Handles all stock item creation logic. Located at `app/Services/StockItemService.php`.

#### `createFromPurchaseItem(PurchaseItem $purchaseItem)`

Called automatically when a purchase item is created:

1. Looks up the product's `is_serialized` flag.
2. Loops `quantity` times, creating one `StockItem` per unit.
3. For **mobiles** (`is_serialized = true`): generates a unique serial number via `SerialNumberService`.
4. For **accessories** (`is_serialized = false`): leaves `serial_number = null`.
5. Copies `condition` from the purchase item to each stock item.
6. Sets `cost_price = unit_cost` and `status = 'available'`.

---

## 6. Summary

| Concept              | Implementation                            | Purpose                                               |
| -------------------- | ----------------------------------------- | ----------------------------------------------------- |
| Product catalog      | `products` table                          | What you sell — definitions only                      |
| Categories           | `categories` table                        | Organize products                                     |
| Suppliers            | `suppliers` table                         | Who you buy from                                      |
| Purchase transaction | `purchase_headers` + `purchase_items`     | What came in, from whom, which products, at what cost |
| Purchase line items  | `purchase_items` table                    | Per-product breakdown of a purchase                   |
| Individual units     | `stock_items` table                       | One row per physical item in inventory                |
| Opening stock        | `purchase_headers.type = 'opening_stock'` | Initial inventory, same flow                          |
| Serial number gen.   | `SerialNumberService`                     | Auto-generates unique IMEI-like codes for mobiles     |
| Stock item creation  | `StockItemService`                        | Creates stock items from purchase items with all logic |
