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

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | string unique | e.g. "Smartphones", "Cases", "Audio" |

### 1.2 Products (Catalog)

```php
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->foreignId('category_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->timestamps();
});
```

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| category_id | FK → categories | |
| name | string | e.g. "Samsung Galaxy S25" |

### 1.3 Suppliers

```php
Schema::create('suppliers', function (Blueprint $table) {
    $table->id();
    $table->string('name')->unique();
    $table->string('phone')->unique();
    $table->timestamps();
});
```

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | string unique | e.g. "Samsung Distributor" |
| phone | string unique | |

### 1.4 Purchase Headers

```php
Schema::create('purchase_headers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
    $table->date('date');
    $table->decimal('total', 10, 2)->default(0);
    $table->enum('source_type', ['supplier', 'unknown', 'opening'])->default('supplier');
    $table->enum('type', ['purchase', 'opening_stock'])->default('purchase');
    $table->timestamps();
});
```

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| supplier_id | FK → suppliers? | nullable, nullOnDelete |
| date | date | Purchase date |
| total | decimal(10,2) | Total cost, defaults to 0 |
| source_type | enum | supplier / unknown / opening |
| type | enum | purchase / opening_stock |

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
}

class Supplier extends Model
{
    public function purchaseHeaders() { return $this->hasMany(PurchaseHeader::class); }
}

class PurchaseHeader extends Model
{
    public function supplier() { return $this->belongsTo(Supplier::class); }
}
```

---

## 3. Purchase Flow

### 3.1 Standard Purchase (New Stock from Supplier)

```
Step 1   Define categories      → POST /api/categories
Step 2   Define products        → POST /api/products
Step 3   Define supplier        → POST /api/suppliers
Step 4   Create purchase header → POST /api/purchase-headers
```

### 3.2 API Request: Create Purchase Header

```
POST /api/purchase-headers
```

```json
{
  "supplier_id": 1,
  "date": "2026-06-20",
  "total": 135000.00,
  "type": "purchase"
}
```

| Field | Description |
|---|---|---|
| supplier_id | The supplier providing the stock (nullable) |
| date | Purchase date |
| total | Total cost of the purchase |
| source_type | `supplier`, `unknown`, or `opening` |
| type | `purchase` or `opening_stock` |

### 3.3 Validation Rules

| Field | Rule |
|---|---|
| supplier_id | `nullable\|exists:suppliers,id` |
| date | `required\|date` |
| total | `required\|numeric` |
| source_type | `required` |
| type | `required` |

### 3.4 API Request: Opening Stock

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

});
```

---

## 5. Summary

| Concept | Implementation | Purpose |
|---|---|---|
| Product catalog | `products` table | What you sell — definitions only |
| Categories | `categories` table | Organize products |
| Suppliers | `suppliers` table | Who you buy from |
| Purchase transaction | `purchase_headers` table | What came in, from whom, at what cost |
| Opening stock | `purchase_headers.type = 'opening_stock'` | Initial inventory, same flow |
