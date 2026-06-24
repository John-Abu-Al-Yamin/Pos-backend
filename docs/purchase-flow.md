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
    $table->foreignId('category_id')->constrained()->onDelete('cascade');
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
    $table->string('reference')->nullable();
    $table->string('reference_code')->nullable();
    $table->date('date');
    $table->decimal('total', 10, 2)->default(0);
    $table->enum('type', ['purchase', 'opening_stock'])->default('purchase');
    $table->timestamps();
});
```

| Column         | Type            | Notes                                |
| -------------- | --------------- | ------------------------------------ |
| id             | bigint PK       |                                      |
| supplier_id    | FK → suppliers? | nullable, nullOnDelete               |
| reference      | string nullable | Optional external reference/document |
| reference_code | string nullable | Auto-generated (e.g. `BY-PURCHASE-2026-0001`) |
| date           | date            | Purchase date                        |
| total          | decimal(10,2)   | Total cost (recalculated from line items) |
| type           | enum            | purchase / opening_stock             |

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
| line_total         | decimal(10,2)         | quantity × unit_cost (calculated server-side) |
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
    $table->enum('status', ['available', 'sold', 'reserved', 'damaged', 'returned', 'voided'])->default('available');
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
| status           | enum                  | available / sold / reserved / damaged / returned / **voided** |

> The `voided` status was added via migration `2026_06_24_000001_add_voided_status_to_stock_items.php` but is currently unused. Quantity reductions now hard-delete excess stock items rather than setting `status = 'voided'`.

---

## 2. Relationships

```php
class Category extends Model
{
    protected $fillable = ['name'];

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
    protected $fillable = ['name', 'phone'];

    public function purchaseHeaders() { return $this->hasMany(PurchaseHeader::class); }
}

class PurchaseHeader extends Model
{
    protected $fillable = [
        'supplier_id', 'date', 'total', 'type',
        'reference', 'reference_code',
    ];

    protected static function booted(): void
    {
        static::creating(function (PurchaseHeader $purchaseHeader) {
            if (empty($purchaseHeader->reference_code)) {
                $purchaseHeader->reference_code = static::generateReferenceCode($purchaseHeader->type);
            }
        });
    }

    public static function generateReferenceCode(string $type): string
    {
        $year = now()->year;
        $typeCode = Str::upper(Str::replace(' ', '_', $type));
        $prefix = "BY-{$typeCode}-{$year}-";

        $lastRecord = static::where('reference_code', 'like', "{$prefix}%")
            ->orderBy('reference_code', 'desc')
            ->first();

        $nextNumber = $lastRecord
            ? (int) Str::afterLast($lastRecord->reference_code, '-') + 1
            : 1;

        return $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

    public function supplier()        { return $this->belongsTo(Supplier::class); }
    public function purchaseItems()   { return $this->hasMany(PurchaseItem::class); }

    public function recalculateTotal(): void
    {
        $this->total = $this->purchaseItems()->sum('line_total');
        $this->saveQuietly();
    }
}

class PurchaseItem extends Model
{
    protected $fillable = [
        'purchase_header_id', 'product_id', 'quantity',
        'unit_cost', 'line_total', 'condition',
    ];

    public function purchaseHeader() { return $this->belongsTo(PurchaseHeader::class); }
    public function product()        { return $this->belongsTo(Product::class); }
    public function stockItems()     { return $this->hasMany(StockItem::class); }
}

class StockItem extends Model
{
    protected $fillable = [
        'product_id', 'purchase_item_id', 'serial_number',
        'cost_price', 'condition', 'status',
    ];

    protected function casts(): array
    {
        return ['cost_price' => 'decimal:2'];
    }

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
Step 7   Purchase header total    → recalculated automatically from line items
```

### 3.2 API Request: Create Purchase Header

```
POST /api/purchase-headers
```

```json
{
    "supplier_id": 1,
    "date": "2026-06-20",
    "type": "purchase",
    "reference": "INV-2026-001"
}
```

| Field       | Description                                 |
| ----------- | ------------------------------------------- |
| supplier_id | The supplier providing the stock (nullable) |
| date        | Purchase date                               |
| type        | `purchase` or `opening_stock`               |
| reference   | (optional) External reference or document number |

**Auto-generated fields:**
- `reference_code`: Automatically generated on create using format `BY-{TYPE}-{YEAR}-{NNNN}` (e.g. `BY-PURCHASE-2026-0001`).

**Response includes:** `supplier`, `purchaseItems` (with nested `product` and `stockItems`).

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
| condition          | (optional, mobiles only) new / excellent / good / fair |

**What happens on create:**
1. `line_total` is calculated as `quantity × unit_cost`.
2. If the product is an **accessory** (`is_serialized = false`), `condition` is forced to `'new'` regardless of what the client sends.
3. The purchase item is persisted.
4. `StockItemService::createFromPurchaseItem()` prepares all stock item records in an array and inserts them in a single bulk query:
   - **Mobile** (`is_serialized = true`): Each unit gets a unique serial number (e.g. `SN-0001-20260621-A7X2`) via `SerialNumberService`; intra-batch collisions are prevented with a local set.
   - **Accessory** (`is_serialized = false`): `serial_number` is left `null`.
   - `condition` is copied from the purchase item to each stock item.
   - `status` defaults to `'available'`.
4. `PurchaseHeader::recalculateTotal()` updates the header's total from all line items.
5. Response includes the `stockItems` relation.

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

Updating a purchase item triggers **transactional stock-item reconciliation** via `PurchaseItemUpdateService`. All changes — quantity, cost, condition, and header total recalculation — happen atomically within a single database transaction with row-level locking (`SELECT ... FOR UPDATE`) to prevent race conditions.

**Behavior by field:**

| Field changed | What happens to stock_items |
|---|---|
| `unit_cost` | Existing `available` stock_items get their `cost_price` updated to the new value. `sold`/`reserved`/`damaged`/`returned` items are **not** modified (retroactively changing COGS on completed transactions is prevented). If all units are already non-available, the purchase_item record updates but no stock_items change — the response communicates this. |
| `condition` | Same propagation rule as cost — only `available` stock_items are updated. For accessories (`is_serialized = false`), `condition` is always forced to `'new'` regardless of input. |
| `quantity` (increase) | New stock_items are generated using the *current* `unit_cost` and `condition` at the time of edit (not the original values). Serialized products get new unique serial numbers. |
| `quantity` (decrease — enough available) | The required number of `available` stock_items are **hard-deleted** from the database. The most-recently-created items are removed first (deterministic, by `id DESC`). |
| `quantity` (decrease — NOT enough available) | **Request is rejected** with HTTP 409 and a specific error: "Cannot reduce quantity to N — X unit(s) need to be removed, but only Y are available. Minimum quantity is Z." `damaged` and `returned` items are treated as non-removable (they represent historical events, not removable stock). |
| `product_id` | Blocked — the product on a purchase item with existing stock_items cannot be changed. |

**Combined edits** (e.g. `unit_cost` + `quantity` increase in one request): all rules apply together inside the same transaction. Newly created stock_items get the new cost; existing `available` items also get the new cost; existing moved items are untouched.

**Partial-success response:** When a field change only partially applies (e.g. cost updated on 7 available units, 3 sold units left unchanged), the response includes an `update_messages` array that explicitly lists what happened:

```json
{
    "success": true,
    "status": 200,
    "message": "تم تحديث عنصر الشراء بنجاح",
    "data": { ... },
    "update_messages": [
        "cost updated on 7 available unit(s); 3 non-available unit(s) left unchanged."
    ]
}
```

The parent header's `total` is recalculated inside the same transaction after the item update.

#### Delete

```
DELETE /api/purchase-items/{id}
```

Deleting a purchase item **hard-deletes all associated stock_items** inside a transaction with row-level locking (`SELECT ... FOR UPDATE`), regardless of their status. There is no deletion guard — any purchase item can be deleted at any time.

The parent purchase header's total is recalculated in the same transaction after the deletion.

### 3.4 Stock Items

| Method | Endpoint                 | Description  |
| ------ | ------------------------ | ------------ |
| GET    | `/api/stock-items`       | Paginated list |
| GET    | `/api/stock-items/{id}`  | Single item  |

Stock items are primarily created automatically via `StockItemService` when purchase items are created. Only read (index/show) endpoints are exposed through the API — manual creation, update, or deletion of stock items is not available via API.

### 3.5 Validation Rules

#### Purchase Header

| Field        | Rule                            |
| ------------ | ------------------------------- |
| supplier_id  | `nullable\|exists:suppliers,id` |
| date         | `required\|date`                |
| type         | `required\|in:purchase,opening_stock` |
| reference    | `nullable\|string\|max:255`     |

> **Note:** `total` is not accepted from the client. It is recalculated automatically from line items via `PurchaseHeader::recalculateTotal()`.

#### Purchase Item

| Field              | Rule                                           |
| ------------------ | ---------------------------------------------- |
| purchase_header_id | `required\|exists:purchase_headers,id`         |
| product_id         | `required\|exists:products,id`                 |
| quantity           | `required\|integer\|min:1`                     |
| unit_cost          | `required\|numeric\|min:0`                     |
| condition          | `nullable\|in:new,excellent,good,fair`         |

> **Notes:**
> - `line_total` is not accepted from the client. It is calculated server-side as `quantity × unit_cost`.
> - `condition` is only applied from the client when the product is serialized (mobile). For accessories (`is_serialized = false`), it is always forced to `'new'`.

#### Stock Item

| Field             | Rule                                               |
| ----------------- | -------------------------------------------------- |
| product_id        | `required\|exists:products,id`                     |
| purchase_item_id  | `nullable\|exists:purchase_items,id`               |
| serial_number     | `nullable\|string\|unique:stock_items,serial_number` |
| cost_price        | `required\|numeric\|min:0`                         |
| condition         | `nullable\|in:new,excellent,good,fair`             |
| status            | `nullable\|in:available,sold,reserved,damaged,returned,voided` |

#### Product

| Field         | Rule                            |
| ------------- | ------------------------------- |
| name          | `required\|string`              |
| category_id   | `required`                      |
| is_serialized | `boolean`                       |

### 3.6 API Request: Opening Stock

Uses the same endpoint with `type: opening_stock` and `supplier_id` omitted (nullable).

```json
POST /api/purchase-headers
{
  "date": "2026-06-20",
  "type": "opening_stock"
}
```

---

## 4. API Routes

```php
// PUBLIC
Route::post('/login', [AuthController::class, 'login']);

// AUTHENTICATED
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

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

    // Stock Items (read-only via API)
    Route::get('/stock-items', [StockItemController::class, 'index']);
    Route::get('/stock-items/{id}', [StockItemController::class, 'show']);

    // Admin-only
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::post('/create-user', [AuthController::class, 'createUser']);
    });
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

Uses constructor injection for `SerialNumberService`:

```php
class StockItemService
{
    public function __construct(
        private readonly SerialNumberService $serialNumberService
    ) {}
    // ...
}
```

#### `createFromPurchaseItem(PurchaseItem $purchaseItem): int`

Called automatically when a purchase item is created. Returns the number of stock items inserted.

1. Looks up the product's `is_serialized` flag.
2. Builds an array of all stock item records in memory.
3. For **mobiles** (`is_serialized = true`): generates a unique serial number for each unit via `SerialNumberService`; prevents intra-batch collisions with a local set.
4. For **accessories** (`is_serialized = false`): leaves `serial_number = null`.
5. Copies `condition` from the purchase item to each record.
6. Sets `cost_price = unit_cost` and `status = 'available'`.
7. Performs a single bulk `StockItem::insert()` for all records (1 query regardless of quantity).

### 5.3 PurchaseItemUpdateService

Handles the transactional update of purchase items with stock-item reconciliation. Located at `app/Services/PurchaseItemUpdateService.php`.

Injects `SerialNumberService` for generating serials on quantity increases:

```php
class PurchaseItemUpdateService
{
    public function __construct(
        private readonly SerialNumberService $serialNumberService
    ) {}
    // ...
}
```

#### `update(PurchaseItem $item, array $data): array`

Called by `PurchaseItemController@update`. Returns an array with the updated `item` and a `messages` array for partial-success communication.

**Internal flow (all inside a single DB transaction):**

1. **Row-level lock**: Re-reads the purchase_item with `lockForUpdate()` to get latest values and prevent concurrent-write races. Also locks all associated stock_items.

2. **Quantity decrease (if applicable)**: Checks if enough `available` stock_items exist. If not, throws `PurchaseItemUpdateException` (caught in the controller as HTTP 409). Otherwise, hard-deletes the required number of items (most-recently-created first by `id DESC`).

3. **Quantity increase (if applicable)**: Generates new stock_items using the *current* `unit_cost` and `condition` (supplied in the request). Reuses `SerialNumberService` for serialized products; bulk-inserts via `StockItem::insert()`.

4. **Cost/condition propagation (if applicable)**: Updates `cost_price` and/or `condition` on `available` stock_items only. `sold`/`reserved`/`damaged`/`returned` items are never modified.

5. **Purchase item update**: Persists the new `quantity`, `unit_cost`, `condition`, and `line_total` on the purchase_item record.

6. **Header recalculation**: Calls `$item->purchaseHeader->recalculateTotal()`.

### 5.4 Total Recalculation

`PurchaseHeader::recalculateTotal()` is called after any purchase item is created, updated, or deleted. It sums the `line_total` of all associated purchase items and saves the result quietly. For updates and deletions, this runs inside the same transaction as the modifying operation.

---

## 6. Summary

| Concept                   | Implementation                              | Purpose                                                    |
| ------------------------- | ------------------------------------------- | ---------------------------------------------------------- |
| Product catalog           | `products` table                            | What you sell — definitions only                           |
| Categories                | `categories` table                          | Organize products                                          |
| Suppliers                 | `suppliers` table                           | Who you buy from                                           |
| Purchase transaction      | `purchase_headers` + `purchase_items`       | What came in, from whom, which products, at what cost      |
| Purchase line items       | `purchase_items` table                      | Per-product breakdown of a purchase                        |
| Individual units          | `stock_items` table                         | One row per physical item in inventory                     |
| Opening stock             | `purchase_headers.type = 'opening_stock'`   | Initial inventory, same flow                               |
| Serial number gen.        | `SerialNumberService`                       | Auto-generates unique IMEI-like codes for mobiles          |
| Stock item creation       | `StockItemService`                          | Creates stock items from purchase items with all logic     |
| Stock item reconciliation | `PurchaseItemUpdateService`                 | Transactional update of stock_items on purchase-item edits |
| Cascade delete on destroy | `PurchaseItemController@destroy`            | Hard-deletes all stock_items when purchase item is deleted  |
| Reference code            | `PurchaseHeader::generateReferenceCode()`   | Auto-generates `BY-{TYPE}-{YEAR}-{NNNN}` on create         |
| Total recalculation       | `PurchaseHeader::recalculateTotal()`        | Updates header total from line item sums                   |
| Update exception          | `PurchaseItemUpdateException`               | Human-readable rejection when reconciliation isn't possible |
