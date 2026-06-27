# Purchase Flow — POS System

## Overview

Nothing enters inventory without a **purchase transaction**. This includes:

- New mobile phones
- Used mobile phones
- Accessories (model-specific or generic)
- Opening stock (existing inventory when the system starts)

Stock exits inventory via **sales transactions**. Returns can bring stock back in or mark it as damaged. The system also supports a **Point-of-Sale (POS)** flow that ties directly into sales.

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

### 1.4 Customers

```php
Schema::create('customers', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('phone')->nullable()->unique();
    $table->timestamps();
});
```

| Column | Type           | Notes                         |
| ------ | -------------- | ----------------------------- |
| id     | bigint PK      |                               |
| name   | string         | Customer name                 |
| phone  | string unique? | Nullable, Egyptian mobile format |

### 1.5 Purchase Headers

```php
Schema::create('purchase_headers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
    $table->string('reference')->nullable();
    $table->string('reference_code')->nullable();
    $table->date('date');
    $table->decimal('total', 10, 2)->default(0);
    $table->enum('type', ['purchase', 'opening_stock'])->default('purchase');
    $table->softDeletes();
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
| deleted_at     | timestamp       | Soft deletes (nullable)              |

### 1.6 Purchase Items (Line Items)

```php
Schema::create('purchase_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('purchase_header_id')->constrained()->restrictOnDelete();
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
| purchase_header_id | FK → purchase_headers | RESTRICT on delete               |
| product_id         | FK → products         | Which catalog item was bought    |
| quantity           | integer               | Number of units                  |
| unit_cost          | decimal(10,2)         | Cost per unit from supplier      |
| line_total         | decimal(10,2)         | quantity × unit_cost (calculated server-side) |
| condition          | enum                  | new / excellent / good / fair    |

### 1.7 Stock Items (Individual Units)

```php
Schema::create('stock_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_id')->constrained();
    $table->foreignId('purchase_item_id')->nullable()->constrained()->nullOnDelete();
    $table->string('serial_number')->nullable()->unique();
    $table->decimal('cost_price', 10, 2);
    $table->enum('condition', ['new', 'excellent', 'good', 'fair'])->default('new');
    $table->enum('status', ['available', 'sold', 'reserved', 'damaged', 'returned', 'voided'])->default('available');
    $table->unsignedTinyInteger('battery_health')->nullable();
    $table->enum('screen_condition', ['perfect', 'good', 'scratched', 'cracked', 'broken'])->nullable();
    $table->enum('body_condition', ['perfect', 'good', 'scratched', 'dented', 'worn'])->nullable();
    $table->boolean('face_id_working')->nullable();
    $table->boolean('fingerprint_working')->nullable();
    $table->boolean('camera_working')->nullable();
    $table->boolean('speaker_working')->nullable();
    $table->string('accessories')->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();
});
```

| Column              | Type                  | Notes                                       |
| ------------------- | --------------------- | ------------------------------------------- |
| id                  | bigint PK             |                                             |
| product_id          | FK → products         |                                             |
| purchase_item_id    | FK → purchase_items?  | nullable, nullOnDelete                      |
| serial_number       | string unique?        | Auto-generated for mobiles, null for accessories |
| cost_price          | decimal(10,2)         | Unit cost from supplier                     |
| condition           | enum                  | new / excellent / good / fair               |
| status              | enum                  | available / sold / reserved / damaged / returned / voided |
| battery_health      | tinyint nullable      | 0–100 (only for used serialized products)   |
| screen_condition    | enum nullable         | perfect / good / scratched / cracked / broken |
| body_condition      | enum nullable         | perfect / good / scratched / dented / worn  |
| face_id_working     | boolean nullable      | Face ID functionality status                |
| fingerprint_working | boolean nullable      | Fingerprint sensor status                   |
| camera_working      | boolean nullable      | Camera functionality status                 |
| speaker_working     | boolean nullable      | Speaker functionality status                |
| accessories         | string nullable       | e.g. "charger, box, cable"                  |
| notes               | text nullable         | Additional device-specific notes            |

> All device detail fields (`battery_health`, `screen_condition`, `body_condition`, `face_id_working`, `fingerprint_working`, `camera_working`, `speaker_working`, `accessories`, `notes`) were included in the original `stock_items` migration.

### 1.8 Sales (Transactions)

```php
Schema::create('sales', function (Blueprint $table) {
    $table->id();
    $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
    $table->date('date');
    $table->decimal('total', 10, 2)->default(0);
    $table->enum('payment_method', ['cash', 'card', 'transfer', 'installment'])->default('cash');
    $table->string('reference_code')->nullable()->unique();
    $table->timestamps();
});
```

| Column         | Type            | Notes                                |
| -------------- | --------------- | ------------------------------------ |
| id             | bigint PK       |                                      |
| customer_id    | FK → customers? | nullable, nullOnDelete               |
| user_id        | FK → users?     | The employee who processed the sale  |
| date           | date            | Sale date                            |
| total          | decimal(10,2)   | Total sale amount (from line items)  |
| payment_method | enum            | cash / card / transfer / installment |
| reference_code | string unique?  | Auto-generated (e.g. `SALE-20260624-0001`) |

### 1.9 Sale Items (Line Items)

```php
Schema::create('sale_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
    $table->foreignId('product_id')->constrained();
    $table->integer('quantity');
    $table->decimal('unit_price', 10, 2);
    $table->decimal('line_total', 10, 2);
    $table->timestamps();
});
```

| Column     | Type            | Notes                            |
| ---------- | --------------- | -------------------------------- |
| id         | bigint PK       |                                  |
| sale_id    | FK → sales      |                                  |
| product_id | FK → products   | Which catalog item was sold      |
| quantity   | integer         | Number of units                  |
| unit_price | decimal(10,2)   | Selling price per unit           |
| line_total | decimal(10,2)   | quantity × unit_price (calculated server-side) |

### 1.10 Sale Item ↔ Stock Item (Pivot)

```php
Schema::create('sale_item_stock_item', function (Blueprint $table) {
    $table->foreignId('sale_item_id')->constrained()->cascadeOnDelete();
    $table->foreignId('stock_item_id')->constrained()->cascadeOnDelete();
    $table->primary(['sale_item_id', 'stock_item_id']);
});
```

Links each sale line item to the specific stock item(s) that were sold.

### 1.11 Returns (Return Transactions)

```php
Schema::create('returns', function (Blueprint $table) {
    $table->id();
    $table->foreignId('sale_id')->constrained()->restrictOnDelete();
    $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
    $table->date('return_date');
    $table->enum('refund_method', ['cash', 'card', 'bank_transfer']);
    $table->decimal('refund_total', 10, 2);
    $table->decimal('restocking_fee', 10, 2)->default(0);
    $table->text('reason')->nullable();
    $table->text('notes')->nullable();
    $table->string('reference_code')->nullable()->unique();
    $table->timestamps();
});
```

| Column         | Type            | Notes                                |
| -------------- | --------------- | ------------------------------------ |
| id             | bigint PK       |                                      |
| sale_id        | FK → sales      | RESTRICT on delete                   |
| customer_id    | FK → customers? | nullable, nullOnDelete               |
| user_id        | FK → users?     | Employee who processed the return    |
| return_date    | date            | Date of return                       |
| refund_method  | enum            | cash / card / bank_transfer          |
| refund_total   | decimal(10,2)   | Total refunded amount                |
| restocking_fee | decimal(10,2)   | Optional fee deducted from refund    |
| reason         | text nullable   | Reason for return                    |
| notes          | text nullable   | Additional notes                     |
| reference_code | string unique?  | Auto-generated (e.g. `RET-20260626-0001`) |

### 1.12 Return Items (Line Items)

```php
Schema::create('return_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('return_id')->constrained()->cascadeOnDelete();
    $table->foreignId('sale_item_id')->constrained()->restrictOnDelete();
    $table->foreignId('stock_item_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('product_id')->constrained()->restrictOnDelete();
    $table->integer('quantity');
    $table->decimal('refund_amount', 10, 2);
    $table->enum('condition_after_inspection', ['new', 'excellent', 'good', 'fair', 'damaged'])->nullable();
    $table->boolean('restock')->default(true);
    $table->text('reason')->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();
});
```

| Column                     | Type            | Notes                                       |
| -------------------------- | --------------- | ------------------------------------------- |
| id                         | bigint PK       |                                             |
| return_id                  | FK → returns    | CASCADE on delete                           |
| sale_item_id               | FK → sale_items | RESTRICT on delete                          |
| stock_item_id              | FK → stock_items? | nullable, nullOnDelete (null for accessories) |
| product_id                 | FK → products   | RESTRICT on delete                          |
| quantity                   | integer         | Number of units returned                    |
| refund_amount              | decimal(10,2)   | Amount refunded for this line item          |
| condition_after_inspection | enum nullable   | new / excellent / good / fair / damaged     |
| restock                    | boolean         | Whether to return to inventory or mark damaged |
| reason                     | text nullable   | Reason for this item's return               |
| notes                      | text nullable   | Item-specific notes                         |

### 1.13 Users

```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password');
    $table->enum('role', ['employee', 'admin'])->default('employee');
    $table->rememberToken();
    $table->timestamps();
});
```

| Column    | Type          | Notes                     |
| --------- | ------------- | ------------------------- |
| id        | bigint PK     |                           |
| name      | string        |                           |
| email     | string unique |                           |
| password  | string        | Hashed                    |
| role      | enum          | employee / admin          |

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

class Customer extends Model
{
    protected $fillable = ['name', 'phone'];
    public function sales() { return $this->hasMany(Sale::class); }
}

class PurchaseHeader extends Model
{
    use SoftDeletes;

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
        'battery_health', 'screen_condition', 'body_condition',
        'face_id_working', 'fingerprint_working', 'camera_working', 'speaker_working',
        'accessories', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'battery_health' => 'integer',
            'face_id_working' => 'boolean',
            'fingerprint_working' => 'boolean',
            'camera_working' => 'boolean',
            'speaker_working' => 'boolean',
        ];
    }

    public function product()      { return $this->belongsTo(Product::class); }
    public function purchaseItem() { return $this->belongsTo(PurchaseItem::class); }
    public function saleItems()    { return $this->belongsToMany(SaleItem::class, 'sale_item_stock_item'); }
    public function returnItems()  { return $this->hasMany(ReturnItem::class); }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }
}

class Sale extends Model
{
    protected $fillable = [
        'customer_id', 'user_id', 'date', 'total',
        'payment_method', 'reference_code',
    ];

    protected static function booted(): void
    {
        static::creating(function (Sale $sale) {
            if (empty($sale->reference_code)) {
                $sale->reference_code = static::generateReferenceCode();
            }
        });
    }

    public static function generateReferenceCode(): string
    {
        $prefix = 'SALE-' . now()->format('Ymd') . '-';
        $lastRecord = static::where('reference_code', 'like', "{$prefix}%")
            ->orderBy('reference_code', 'desc')
            ->first();
        $nextNumber = $lastRecord
            ? (int) Str::afterLast($lastRecord->reference_code, '-') + 1
            : 1;
        return $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

    public function customer()  { return $this->belongsTo(Customer::class); }
    public function user()      { return $this->belongsTo(User::class); }
    public function saleItems() { return $this->hasMany(SaleItem::class); }
    public function returns()   { return $this->hasMany(Returns::class); }

    public function recalculateTotal(): void
    {
        $this->total = $this->saleItems()->sum('line_total');
        $this->saveQuietly();
    }
}

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id', 'product_id', 'quantity',
        'unit_price', 'line_total',
    ];

    public function sale()       { return $this->belongsTo(Sale::class); }
    public function product()    { return $this->belongsTo(Product::class); }
    public function stockItems() { return $this->belongsToMany(StockItem::class, 'sale_item_stock_item'); }
    public function returnItems(){ return $this->hasMany(ReturnItem::class); }
}

class Returns extends Model
{
    protected $table = 'returns';

    protected $fillable = [
        'sale_id', 'customer_id', 'user_id',
        'return_date', 'refund_method', 'refund_total',
        'restocking_fee', 'reason', 'notes', 'reference_code',
    ];

    protected function casts(): array
    {
        return [
            'return_date' => 'date:Y-m-d',
            'refund_total' => 'decimal:2',
            'restocking_fee' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Returns $return) {
            if (empty($return->reference_code)) {
                $return->reference_code = static::generateReferenceCode();
            }
        });
    }

    public static function generateReferenceCode(): string
    {
        $prefix = 'RET-' . now()->format('Ymd') . '-';
        $lastRecord = static::where('reference_code', 'like', "{$prefix}%")
            ->orderBy('reference_code', 'desc')
            ->first();
        $nextNumber = $lastRecord
            ? (int) Str::afterLast($lastRecord->reference_code, '-') + 1
            : 1;
        return $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

    public function sale()        { return $this->belongsTo(Sale::class); }
    public function customer()    { return $this->belongsTo(Customer::class); }
    public function user()        { return $this->belongsTo(User::class); }
    public function returnItems() { return $this->hasMany(ReturnItem::class, 'return_id'); }
}

class ReturnItem extends Model
{
    protected $fillable = [
        'return_id', 'sale_item_id', 'stock_item_id', 'product_id',
        'quantity', 'refund_amount', 'condition_after_inspection',
        'restock', 'reason', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'refund_amount' => 'decimal:2',
            'restock' => 'boolean',
        ];
    }

    public function returnHeader() { return $this->belongsTo(Returns::class, 'return_id'); }
    public function saleItem()     { return $this->belongsTo(SaleItem::class); }
    public function stockItem()    { return $this->belongsTo(StockItem::class); }
    public function product()      { return $this->belongsTo(Product::class); }
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
| device_details     | (optional) Array of per-unit device details for used phones |

**`device_details` format (only for used serialized products):**

```json
"device_details": [
    {
        "battery_health": 85,
        "screen_condition": "scratched",
        "body_condition": "good",
        "face_id_working": true,
        "fingerprint_working": null,
        "camera_working": null,
        "speaker_working": null,
        "accessories": "charger, box",
        "notes": "scratches on back"
    }
]
```

The array length **must equal** `quantity`. If provided, each element maps to one stock item. If omitted, all device detail fields default to `null`.

**What happens on create:**
1. `line_total` is calculated as `quantity × unit_cost`.
2. If the product is an **accessory** (`is_serialized = false`), `condition` is forced to `'new'` regardless of what the client sends.
3. The purchase item is persisted.
4. `StockItemService::createFromPurchaseItem()` prepares all stock item records in an array and inserts them in a single bulk query:
   - **Mobile** (`is_serialized = true`): Each unit gets a unique serial number (e.g. `SN-0001-20260621-A7X2`) via `SerialNumberService`; intra-batch collisions are prevented with a local set.
   - **Accessory** (`is_serialized = false`): `serial_number` is left `null`.
   - `condition` is copied from the purchase item to each stock item.
   - `status` defaults to `'available'`.
   - **Device details** (if provided): `battery_health`, `screen_condition`, `body_condition`, `face_id_working`, `fingerprint_working`, `camera_working`, `speaker_working`, `accessories`, and `notes` are applied per-unit.
5. `PurchaseHeader::recalculateTotal()` updates the header's total from all line items.
6. Response includes the `stockItems` relation.

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

Updating a purchase item triggers **transactional stock-item reconciliation** via `PurchaseItemUpdateService`. All changes — quantity, cost, condition, device details, and header total recalculation — happen atomically within a single database transaction with row-level locking (`SELECT ... FOR UPDATE`) to prevent race conditions.

**Behavior by field:**

| Field changed | What happens to stock_items |
|---|---|
| `unit_cost` | Existing `available` stock_items get their `cost_price` updated to the new value. `sold`/`reserved`/`damaged`/`returned` items are **not** modified (retroactively changing COGS on completed transactions is prevented). If all units are already non-available, the purchase_item record updates but no stock_items change — the response communicates this. |
| `condition` | Same propagation rule as cost — only `available` stock_items are updated. For accessories (`is_serialized = false`), `condition` is always forced to `'new'` regardless of input. |
| `device_details` | Propagated to all available stock items. Fields not present in the payload keep their existing values. |
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

Deleting a purchase item has a **deletion guard**: if any associated stock items have a status other than `available` (sold, damaged, returned, etc.), the request is rejected with HTTP 422: "لا يمكن حذف عنصر الشراء لأن بعض عناصر المخزون تم بيعها بالفعل."

If all stock items are `available`, they are locked with `lockForUpdate()`, only `available` items are hard-deleted, and the purchase item itself is deleted. The parent purchase header's total is recalculated in the same transaction after the deletion.

### 3.4 Stock Items

| Method | Endpoint                          | Description                              |
| ------ | --------------------------------- | ---------------------------------------- |
| GET    | `/api/stock-items`                | Paginated list (all statuses)            |
| GET    | `/api/stock-items/{id}`           | Single item                              |
| GET    | `/api/stock-items/available`      | Paginated list of `available` items only |

**`available` endpoint:**
- Filters `StockItem::available()` (status = `'available'`)
- Supports `search` param (matches product name or serial_number)
- Supports `category_id` param (filters by product category)
- Eager-loads `product.category` relation
- Default `per_page`: 50

Stock items are primarily created automatically via `StockItemService` when purchase items are created. Only read endpoints are exposed through the API — manual creation, update, or deletion of stock items is not available via API.

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
| device_details     | `nullable\|array`                              |
| device_details.*.battery_health | `nullable\|integer\|min:0\|max:100` |
| device_details.*.screen_condition | `nullable\|in:perfect,good,scratched,cracked,broken` |
| device_details.*.body_condition | `nullable\|in:perfect,good,scratched,dented,worn` |
| device_details.*.face_id_working | `nullable\|boolean`                  |
| device_details.*.fingerprint_working | `nullable\|boolean`            |
| device_details.*.camera_working | `nullable\|boolean`                 |
| device_details.*.speaker_working | `nullable\|boolean`               |
| device_details.*.accessories | `nullable\|string\|max:500`                   |
| device_details.*.notes | `nullable\|string\|max:1000`                       |

> **Notes:**
> - `line_total` is not accepted from the client. It is calculated server-side as `quantity × unit_cost`.
> - `condition` is only applied from the client when the product is serialized (mobile). For accessories (`is_serialized = false`), it is always forced to `'new'`.
> - `device_details` array length must equal `quantity`. Only applicable for used serialized products.

#### Stock Item

| Field             | Rule                                               |
| ----------------- | -------------------------------------------------- |
| product_id        | `required\|exists:products,id`                     |
| purchase_item_id  | `nullable\|exists:purchase_items,id`               |
| serial_number     | `nullable\|string\|unique:stock_items,serial_number` |
| cost_price        | `required\|numeric\|min:0`                         |
| condition         | `nullable\|in:new,excellent,good,fair`             |
| status            | `nullable\|in:available,sold,reserved,damaged,returned,voided` |
| battery_health    | `nullable\|integer\|min:0\|max:100`                |
| screen_condition  | `nullable\|in:perfect,good,scratched,cracked,broken` |
| body_condition    | `nullable\|in:perfect,good,scratched,dented,worn`  |
| accessories       | `nullable\|string`                                  |
| notes             | `nullable\|string`                                  |

#### Product

| Field         | Rule                            |
| ------------- | ------------------------------- |
| name          | `required\|string`              |
| category_id   | `required`                      |
| is_serialized | `boolean`                       |

#### Customer

| Field  | Rule                              |
| ------ | --------------------------------- |
| name   | `required\|string`                |
| phone  | `nullable\|string\|unique:customers,phone` |

#### Sale

| Field                        | Rule                              |
| ---------------------------- | --------------------------------- |
| customer_id                  | `nullable\|exists:customers,id`   |
| date                         | `sometimes\|date`                 |
| payment_method               | `sometimes\|in:cash,card,transfer,installment` |
| items                        | `required\|array\|min:1`          |
| items.*.product_id           | `required\|exists:products,id`    |
| items.*.quantity             | `sometimes\|integer\|min:1`       |
| items.*.unit_price           | `required\|numeric\|min:0`        |
| items.*.stock_item_ids       | `sometimes\|array` (for serialized products) |

#### Return

| Field                        | Rule                              |
| ---------------------------- | --------------------------------- |
| sale_id                      | `required\|exists:sales,id`       |
| refund_method                | `required\|in:cash,card,bank_transfer` |
| restocking_fee               | `nullable\|numeric\|min:0`        |
| reason                       | `nullable\|string`                |
| notes                        | `nullable\|string`                |
| items                        | `required\|array\|min:1`          |
| items.*.sale_item_id         | `required\|exists:sale_items,id`  |
| items.*.stock_item_id        | `nullable\|exists:stock_items,id` |
| items.*.quantity             | `required\|integer\|min:1`        |
| items.*.refund_amount        | `required\|numeric\|min:0`        |
| items.*.condition_after_inspection | `nullable\|in:new,excellent,good,fair,damaged` |
| items.*.restock              | `nullable\|boolean`               |
| items.*.reason               | `nullable\|string`                |
| items.*.notes                | `nullable\|string`                |

### 3.6 API Request: Opening Stock

Uses the same endpoint with `type: opening_stock` and `supplier_id` omitted (nullable).

```json
POST /api/purchase-headers
{
  "date": "2026-06-20",
  "type": "opening_stock"
}
```

### 3.7 Purchase Header Deletion

```
DELETE /api/purchase-headers/{id}
```

Deleting a purchase header first checks if any of its purchase items have non-available stock (sold, damaged, etc.). If so, the request is rejected with HTTP 422: "لا يمكن حذف هذا الشراء لأن بعض عناصر المخزون تم بيعها بالفعل."

If all stock items are `available`, the available stock items are hard-deleted, then the purchase header itself is **soft-deleted** (the `deleted_at` column is set).

---

## 4. Sale Flow

### 4.1 Standard Sale

```
Step 1   Define customer        → POST /api/customers (optional, can create inline)
Step 2   Choose products        → POST /api/sales (with items array)
Step 3   System marks stock     → status changed to 'sold' (via SaleService)
Step 4   Sale total recalculated → from line item sums
```

### 4.2 API Request: Create Sale

```
POST /api/sales
```

```json
{
    "customer_id": 1,
    "date": "2026-06-24",
    "payment_method": "cash",
    "items": [
        {
            "product_id": 1,
            "quantity": 1,
            "unit_price": 7500.00,
            "stock_item_ids": [1]
        }
    ]
}
```

| Field          | Description                                 |
| -------------- | ------------------------------------------- |
| customer_id    | (optional) The customer buying              |
| date           | (optional) Defaults to today                |
| payment_method | (optional) cash / card / transfer / installment |
| items          | Array of line items                         |

**What happens on create (inside a DB transaction via `SaleService::createSale()`):**

1. The sale header is created (including `user_id` from the authenticated user).
2. For each item:
   - `line_total` is calculated as `quantity × unit_price`.
   - A `SaleItem` record is created.
   - **Serialized product**: The specific `stock_item_ids` must be provided (exactly 1 per item). Each stock item is locked with `lockForUpdate()`, validated as `available`, then marked `sold`.
   - **Non-serialized product**: The first N `available` stock items (ordered by `id`) are locked, marked `sold`, and attached to the sale item via the pivot table. If insufficient stock exists, the transaction is rolled back.
3. `Sale::recalculateTotal()` updates the header total.
4. Response includes `customer`, `saleItems`, `product`, and `stockItems`.

### 4.3 API: Sales CRUD

| Method | Endpoint                 | Description                |
| ------ | ------------------------ | -------------------------- |
| GET    | `/api/sales`             | Paginated list (with customer + saleItems.product) |
| POST   | `/api/sales`             | Create sale (see above)    |
| GET    | `/api/sales/{id}`        | Single sale (with customer, user, saleItems.product, saleItems.stockItems) |
| DELETE | `/api/sales/{id}`        | Delete sale (blocked if returns exist) |
| GET    | `/api/sales/{id}/returnable` | Returnable data for a sale (with returnable quantities) |

> No `update` endpoint is exposed for sales — they are immutable once created. Delete is allowed only if no associated returns exist.

### 4.4 API: Customers CRUD

| Method | Endpoint              | Description                    |
| ------ | --------------------- | ------------------------------ |
| GET    | `/api/customers`      | Paginated list (ordered by name) |
| POST   | `/api/customers`      | Create customer                |
| GET    | `/api/customers/{id}` | Single customer (with sales)   |
| PUT    | `/api/customers/{id}` | Update customer                |
| DELETE | `/api/customers/{id}` | Delete customer                |

### 4.5 API: Returns CRUD

| Method | Endpoint              | Description                               |
| ------ | --------------------- | ----------------------------------------- |
| GET    | `/api/returns`        | Paginated list (with sale, customer, items) |
| POST   | `/api/returns`        | Create return (see below)                 |
| GET    | `/api/returns/{id}`   | Single return (with full relations)       |

**What happens on return creation (inside a DB transaction via `ReturnService::createReturn()`):**

1. Validates the requested refund amount does not exceed the remaining refundable amount on the sale (previous returns are subtracted).
2. Creates the `Returns` header with generated `reference_code` (`RET-{YYYYMMDD}-{NNNN}`).
3. For each item:
   - **Serialized product**: Requires a specific `stock_item_id`. Locked with `lockForUpdate()`, validated as `sold`. If `restock` is true, status is set to `available`; otherwise `damaged`.
   - **Non-serialized product**: Locks all matching `sold` stock items from the sale. If `restock` is true, status is set to `available`; otherwise `damaged`.
   - `condition_after_inspection` can be recorded.
4. `refund_total` is calculated as the sum of refund amounts minus any `restocking_fee`.
5. Dispatches `ReturnProcessed` event.

### 4.6 API Request: Create Return

```
POST /api/returns
```

```json
{
    "sale_id": 1,
    "refund_method": "cash",
    "restocking_fee": 0,
    "reason": "Customer changed mind",
    "items": [
        {
            "sale_item_id": 1,
            "stock_item_id": 1,
            "quantity": 1,
            "refund_amount": 7500.00,
            "condition_after_inspection": "good",
            "restock": true,
            "reason": "Opened box"
        }
    ]
}
```

---

## 5. API Routes

```php
Route::get('/hello', function () {
    return response()->json(['message' => 'Hello, World!']);
});

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

    // Customers
    Route::get('/customers', [CustomerController::class, 'index']);
    Route::post('/customers', [CustomerController::class, 'store']);
    Route::get('/customers/{id}', [CustomerController::class, 'show']);
    Route::put('/customers/{id}', [CustomerController::class, 'update']);
    Route::delete('/customers/{id}', [CustomerController::class, 'destroy']);

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

    // Sales
    Route::get('/sales', [SaleController::class, 'index']);
    Route::post('/sales', [SaleController::class, 'store']);
    Route::get('/sales/{id}', [SaleController::class, 'show']);
    Route::delete('/sales/{id}', [SaleController::class, 'destroy']);

    // Returns
    Route::get('/sales/{id}/returnable', [SaleController::class, 'returnable']);
    Route::get('/returns', [ReturnController::class, 'index']);
    Route::post('/returns', [ReturnController::class, 'store']);
    Route::get('/returns/{id}', [ReturnController::class, 'show']);

    // Stock Items (read-only via API)
    Route::get('/stock-items/available', [StockItemController::class, 'available']);
    Route::get('/stock-items', [StockItemController::class, 'index']);
    Route::get('/stock-items/{id}', [StockItemController::class, 'show']);

    // Admin-only
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::post('/create-user', [AuthController::class, 'createUser']);
    });
});
```

### 5.1 Standard Response Format

All API responses use a consistent format via `ApiResponse` helper class (at `app/Http/Responses/ApiResponse.php`):

```json
// Success (single item)
{
    "success": true,
    "status": 200,
    "message": "...",
    "data": { ... }
}

// Success (paginated list)
{
    "success": true,
    "status": 200,
    "message": "...",
    "data": [ ... ],
    "pagination": {
        "current_page": 1,
        "per_page": 10,
        "total": 50,
        "last_page": 5,
        "from": 1,
        "to": 10
    }
}

// Error
{
    "success": false,
    "status": 404,
    "message": "...",
    "errors": null
}

// Validation Error
{
    "success": false,
    "status": 422,
    "message": "Validation error",
    "errors": [
        { "field": "name", "message": "The name field is required." }
    ]
}
```

All form requests extend `BaseApiRequest` (at `app/Http/Requests/BaseApiRequest.php`), which overrides `failedValidation()` to return structured JSON errors in this format.

### 5.2 Admin Middleware

The `admin` middleware (at `app/Http/Middleware/AdminMiddleware.php`) checks `$request->user()->role !== 'admin'` and returns a 403 error. Registered as `'admin'` alias in `bootstrap/app.php`.

---

## 6. Service Layer

### 6.1 SerialNumberService

Generates unique serial numbers for serialized products (mobiles). Located at `app/Services/SerialNumberService.php`.

```
Format: SN-{product_id_padded}-{YYYYMMDD}-{random4}
Example: SN-0001-20260621-A7X2
```

Uses a `do-while` loop to guarantee uniqueness against existing `stock_items.serial_number` values.

### 6.2 StockItemService

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

#### `createFromPurchaseItem(PurchaseItem $purchaseItem, array $deviceDetails = []): int`

Called automatically when a purchase item is created. Returns the number of stock items inserted.

1. Looks up the product's `is_serialized` flag.
2. Builds an array of all stock item records in memory.
3. For **mobiles** (`is_serialized = true`): generates a unique serial number for each unit via `SerialNumberService`; prevents intra-batch collisions with a local set.
4. For **accessories** (`is_serialized = false`): leaves `serial_number = null`.
5. Copies `condition` from the purchase item to each record.
6. Sets `cost_price = unit_cost` and `status = 'available'`.
7. Maps `device_details` per-unit: `battery_health`, `screen_condition`, `body_condition`, `face_id_working`, `fingerprint_working`, `camera_working`, `speaker_working`, `accessories`, `notes`.
8. Performs a single bulk `StockItem::insert()` for all records (1 query regardless of quantity).

### 6.3 PurchaseItemUpdateService

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

4. **Cost/condition/device-details propagation (if applicable)**: Updates `cost_price`, `condition`, `battery_health`, `screen_condition`, `body_condition`, `face_id_working`, `fingerprint_working`, `camera_working`, `speaker_working`, `accessories`, and/or `notes` on `available` stock_items only. `sold`/`reserved`/`damaged`/`returned` items are never modified.

5. **Purchase item update**: Persists the new `quantity`, `unit_cost`, `condition`, and `line_total` on the purchase_item record.

6. **Header recalculation**: Calls `$item->purchaseHeader->recalculateTotal()`.

### 6.4 SaleService

Handles the transactional creation of sales with stock-item status management. Located at `app/Services/SaleService.php`.

#### `createSale(array $data): Sale`

Called by `SaleController@store`. Returns the created `Sale` with loaded relations.

**Internal flow (all inside a single DB transaction):**

1. Creates the `Sale` header with `customer_id`, `user_id`, `date`, and `payment_method`.

2. For each item in the request:
   - Looks up the product.
   - Creates a `SaleItem` record (with `quantity`, `unit_price`, `line_total`).
   - **Serialized product**: Lock the specific `stock_item_id` with `lockForUpdate()`, validate its status is `available`, update status to `sold`, and attach via pivot.
   - **Non-serialized product**: Lock all matching `available` stock items (`lockForUpdate()`), validate sufficient quantity, update all to `sold`, and attach via pivot.
   - If any validation fails (insufficient stock, wrong status), throws `RuntimeException` (rolls back the entire transaction).

3. Recalculates the sale total.

4. Returns the sale with `customer`, `saleItems.product`, and `saleItems.stockItems` relations loaded.

### 6.5 ReturnService

Handles the transactional creation of returns with stock-item restocking. Located at `app/Services/ReturnService.php`.

#### `createReturn(array $data, int $userId): Returns`

Called by `ReturnController@store`. Returns the created `Returns` model with loaded relations.

**Internal flow (all inside a single DB transaction):**

1. Locks the sale, validates that the requested refund does not exceed the remaining refundable amount (accounting for previous returns).
2. Creates the `Returns` header with auto-generated `reference_code` (`RET-{YYYYMMDD}-{NNNN}`).
3. For each item:
   - **Serialized product**: Requires `stock_item_id`. Locked with `lockForUpdate()`, validated as `sold`. If `restock` is true → status becomes `available`; otherwise → `damaged`.
   - **Non-serialized product**: Acquires matching `sold` stock items from the sale, updates their status.
   - Records `condition_after_inspection` and other metadata.
4. Calculates `refund_total` (sum of refund_amounts minus restocking_fee).
5. Dispatches `ReturnProcessed` event.

### 6.6 Total Recalculation

Both `PurchaseHeader::recalculateTotal()` and `Sale::recalculateTotal()` are called after any line item is created, updated, or deleted. They sum the `line_total` of all associated items and save the result quietly.

---

## 7. Exceptions

### PurchaseItemUpdateException

Located at `app/Exceptions/PurchaseItemUpdateException.php`. Extends `Exception`. Thrown by `PurchaseItemUpdateService` when a quantity reduction is not possible (not enough `available` stock items). Caught in `PurchaseItemController@update` and returned as HTTP 409 with a human-readable message.

---

## 8. Events

### ReturnProcessed

Located at `app/Events/ReturnProcessed.php`. Dispatched by `ReturnService::createReturn()` after a return is successfully processed. Receives the `Returns` model instance.

---

## 9. Summary

| Concept                    | Implementation                               | Purpose                                                    |
| -------------------------- | -------------------------------------------- | ---------------------------------------------------------- |
| Product catalog            | `products` table                             | What you sell — definitions only                           |
| Categories                 | `categories` table                           | Organize products                                          |
| Suppliers                  | `suppliers` table                            | Who you buy from                                           |
| Customers                  | `customers` table                            | Who you sell to                                            |
| Purchase transaction       | `purchase_headers` + `purchase_items`        | What came in, from whom, which products, at what cost      |
| Purchase line items        | `purchase_items` table                       | Per-product breakdown of a purchase                        |
| Sale transaction           | `sales` + `sale_items`                       | What went out, to whom, which products, at what price      |
| Sale line items            | `sale_items` table                           | Per-product breakdown of a sale                            |
| Return transaction         | `returns` + `return_items`                   | What came back, refund amount, restock or damage           |
| Return line items          | `return_items` table                         | Per-product breakdown of a return                          |
| Sale ↔ Stock link          | `sale_item_stock_item` pivot                 | Tracks which specific stock items were sold                |
| Individual units           | `stock_items` table                          | One row per physical item in inventory                     |
| Opening stock              | `purchase_headers.type = 'opening_stock'`    | Initial inventory, same flow                               |
| Users & roles              | `users.role` (employee / admin)              | Authentication and authorization                           |
| Serial number gen.         | `SerialNumberService`                        | Auto-generates unique IMEI-like codes for mobiles          |
| Stock item creation        | `StockItemService`                           | Creates stock items from purchase items with all logic     |
| Stock item reconciliation  | `PurchaseItemUpdateService`                  | Transactional update of stock_items on purchase-item edits |
| Sale processing            | `SaleService`                                | Transactional sale creation with stock reservation         |
| Return processing          | `ReturnService`                              | Transactional return creation with restock/damage logic    |
| Deletion guard (items)     | `PurchaseItemController@destroy`             | Blocks deletion if non-available stock exists              |
| Deletion guard (headers)   | `PurchaseHeaderController@destroy`           | Blocks soft-delete if non-available stock exists           |
| Sale deletion guard        | `SaleController@destroy`                     | Blocks deletion if associated returns exist                |
| Reference code (purchase)  | `PurchaseHeader::generateReferenceCode()`    | Auto-generates `BY-{TYPE}-{YEAR}-{NNNN}` on create         |
| Reference code (sale)      | `Sale::generateReferenceCode()`              | Auto-generates `SALE-{YYYYMMDD}-{NNNN}` on create          |
| Reference code (return)    | `Returns::generateReferenceCode()`           | Auto-generates `RET-{YYYYMMDD}-{NNNN}` on create           |
| Total recalculation        | `PurchaseHeader::recalculateTotal()` / `Sale::recalculateTotal()` | Updates header total from line item sums |
| Update exception           | `PurchaseItemUpdateException`                | Human-readable rejection when reconciliation isn't possible |
| Standard response format   | `ApiResponse` helper                         | Consistent JSON structure for all API responses            |
| Admin middleware            | `AdminMiddleware`                            | Restricts routes to users with `role = 'admin'`            |
| Base form request          | `BaseApiRequest`                             | Structured validation error responses for all form requests |
| Available stock query      | `StockItem::scopeAvailable()`                | Query scope for filtering `status = 'available'` items     |
| Return processed event     | `ReturnProcessed`                            | Dispatched after return is successfully processed          |
