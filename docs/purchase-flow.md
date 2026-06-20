# Purchase Flow — POS System

## Overview

Nothing enters inventory without a **purchase transaction**. This includes:

- New mobile phones
- Used mobile phones
- Accessories (model-specific or generic)
- Opening stock (existing inventory when the system starts)

Every physical unit is individually tracked via `stock_items` — no quantity-based inventory.

---

## 1. Database Schema

### 1.1 Categories

```php
Schema::create('categories', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->enum('type', ['mobile', 'accessory', 'tablet', 'other'])->default('other');
    $table->timestamps();
});
```

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | string | e.g. "Smartphones", "Cases", "Audio" |
| type | enum | For filtering: mobile / accessory / tablet / other |
| timestamps | | created_at, updated_at |

### 1.2 Products (Catalog)

```php
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->foreignId('category_id')->constrained();
    $table->string('name');
    $table->string('brand')->nullable();
    $table->string('model')->nullable();
    $table->enum('condition', ['new', 'excellent', 'good', 'fair'])->default('new');
    $table->decimal('default_selling_price', 12, 2)->default(0);
    $table->text('description')->nullable();
    $table->timestamps();
});
```

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| category_id | FK → categories | |
| name | string | e.g. "Samsung Galaxy S25" |
| brand | string? | e.g. "Samsung", "Apple" |
| model | string? | e.g. "SM-S931B" |
| condition | enum | new / excellent / good / fair |
| default_selling_price | decimal | Suggested retail price |
| description | text? | |

**Examples:**

| id | category_id | name | brand | condition | type (via category) |
|---|---|---|---|---|---|
| 1 | 1 (Phones) | Samsung Galaxy S25 | Samsung | new | mobile |
| 2 | 1 (Phones) | Samsung Galaxy S25 (Used) | Samsung | excellent | mobile |
| 3 | 1 (Phones) | iPhone 16 Pro | Apple | new | mobile |
| 4 | 2 (Cases) | Premium Silicone Case S25 | Spigen | new | accessory |
| 5 | 2 (Audio) | Wireless Bluetooth Headphones | Sony | new | accessory |

### 1.3 Product Compatibility (Catalog-Level Linking)

Links model-specific accessories to the phone models they fit.

```php
Schema::create('product_compatibility', function (Blueprint $table) {
    $table->foreignId('accessory_id')->constrained('products')->onDelete('cascade');
    $table->foreignId('device_id')->constrained('products')->onDelete('cascade');
    $table->primary(['accessory_id', 'device_id']);
});
```

| Column | Type | Notes |
|---|---|---|
| accessory_id | FK → products | The accessory (e.g. case, screen protector) |
| device_id | FK → products | The phone model it fits |

**Logic:** If a product has type `accessory` and has zero compatibility entries, it is considered **generic** (works with any device — e.g. headphones, chargers).

**Example data:**

| accessory_id | accessory name | device_id | device name |
|---|---|---|---|
| 4 (Case S25) | Premium Silicone Case S25 | 1 | Samsung Galaxy S25 |
| 4 (Case S25) | Premium Silicone Case S25 | 2 | Samsung Galaxy S25 (Used) |
| 5 (Headphones) | Wireless Bluetooth Headphones | — | *(no entries = generic)* |

### 1.4 Suppliers

```php
Schema::create('suppliers', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('phone')->nullable();
    $table->string('email')->nullable();
    $table->text('address')->nullable();
    $table->timestamps();
});
```

### 1.5 Purchases (Transaction Header)

```php
Schema::create('purchases', function (Blueprint $table) {
    $table->id();
    $table->string('reference_no')->unique();
    $table->foreignId('supplier_id')->nullable()->constrained();
    $table->foreignId('user_id')->constrained();
    $table->enum('type', ['purchase', 'opening_stock'])->default('purchase');
    $table->decimal('total_cost', 12, 2)->default(0);
    $table->text('notes')->nullable();
    $table->timestamps();
});
```

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| reference_no | string unique | Auto-generated: PUR-20260620-0001 |
| supplier_id | FK → suppliers? | null for opening_stock |
| user_id | FK → users | Who created the purchase |
| type | enum | purchase / opening_stock |
| total_cost | decimal | Sum of all item costs |
| notes | text? | |

### 1.6 Purchase Items (Line Items)

```php
Schema::create('purchase_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('purchase_id')->constrained()->onDelete('cascade');
    $table->foreignId('product_id')->constrained();
    $table->integer('quantity');
    $table->decimal('unit_cost', 12, 2);
    $table->timestamps();
});
```

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| purchase_id | FK → purchases | |
| product_id | FK → products | Which catalog item was bought |
| quantity | integer | Number of units |
| unit_cost | decimal | Cost per unit from supplier |

### 1.7 Stock Items (Individual Units)

```php
Schema::create('stock_items', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->unique();
    $table->foreignId('purchase_item_id')->constrained()->onDelete('cascade');
    $table->foreignId('product_id')->constrained();
    $table->string('serial_number')->nullable()->unique();
    $table->decimal('cost_price', 12, 2);
    $table->enum('condition', ['new', 'excellent', 'good', 'fair'])->default('new');
    $table->enum('status', ['available', 'sold', 'damaged', 'returned'])->default('available');
    $table->foreignId('sale_item_id')->nullable()->constrained();
    $table->foreignId('parent_stock_item_id')->nullable()->constrained('stock_items');
    $table->timestamp('sold_at')->nullable();
    $table->timestamps();
});
```

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| uuid | uuid unique | Public-facing identifier for invoices |
| purchase_item_id | FK → purchase_items | Which purchase line produced this |
| product_id | FK → products | What product this unit is |
| serial_number | string unique? | IMEI for phones (nullable) |
| cost_price | decimal | What was paid for this specific unit |
| condition | enum | Physical condition (can override product default) |
| status | enum | available / sold / damaged / returned |
| sale_item_id | FK → sale_items? | Filled when sold |
| parent_stock_item_id | FK → stock_items? | Link accessory unit → device unit |
| sold_at | timestamp? | |

---

## 2. Relationships

```php
class Product extends Model
{
    public function category() { return $this->belongsTo(Category::class); }

    // Catalog-level: which accessories fit this device
    public function compatibleAccessories()
    {
        return $this->belongsToMany(Product::class, 'product_compatibility', 'device_id', 'accessory_id');
    }

    // Catalog-level: which devices this accessory fits
    public function compatibleDevices()
    {
        return $this->belongsToMany(Product::class, 'product_compatibility', 'accessory_id', 'device_id');
    }

    public function isGenericAccessory(): bool
    {
        return $this->category->type === 'accessory' && $this->compatibleDevices()->count() === 0;
    }

    public function stockItems() { return $this->hasMany(StockItem::class); }
}

class Purchase extends Model
{
    public function items()    { return $this->hasMany(PurchaseItem::class); }
    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function user()     { return $this->belongsTo(User::class); }
}

class PurchaseItem extends Model
{
    public function purchase()   { return $this->belongsTo(Purchase::class); }
    public function product()    { return $this->belongsTo(Product::class); }
    public function stockItems() { return $this->hasMany(StockItem::class); }
}

class StockItem extends Model
{
    public function product()          { return $this->belongsTo(Product::class); }
    public function purchaseItem()     { return $this->belongsTo(PurchaseItem::class); }
    public function parentStockItem()  { return $this->belongsTo(StockItem::class, 'parent_stock_item_id'); }
    public function childStockItems()  { return $this->hasMany(StockItem::class, 'parent_stock_item_id'); }
}
```

---

## 3. Purchase Flow

### 3.1 Standard Purchase (New Stock from Supplier)

```
Step 1   Define categories             → POST /api/categories
Step 2   Define products (catalog)     → POST /api/products
Step 3   Define compatibility (if any) → POST /api/product-compatibility
Step 4   Define supplier               → POST /api/suppliers
Step 5   Create purchase               → POST /api/purchases
Step 6   System generates stock_items  → 1 row per unit
Step 7   Sell individual items         → Mark stock_item as sold
```

### 3.2 API Request: Create Purchase

```
POST /api/purchases
```

```json
{
  "supplier_id": 1,
  "type": "purchase",
  "reference_no": "PUR-20260620-0001",
  "notes": "July stock order",
  "items": [
    {
      "product_id": 1,
      "quantity": 30,
      "unit_cost": 4500.00,
      "serials": ["IMEI001", "IMEI002", "IMEI003", "..."]
    },
    {
      "product_id": 4,
      "quantity": 30,
      "unit_cost": 25.00
    },
    {
      "product_id": 5,
      "quantity": 20,
      "unit_cost": 150.00
    }
  ]
}
```

| Field | Description |
|---|---|
| items[].product_id | The catalog product being purchased |
| items[].quantity | How many units |
| items[].unit_cost | Price per unit from supplier |
| items[].serials[] | Optional IMEI/serial numbers (one per unit) |

### 3.3 Backend Logic

```php
DB::transaction(function () use ($data) {
    $purchase = Purchase::create([
        'reference_no' => $data['reference_no'] ?? $this->generateReferenceNo(),
        'supplier_id'  => $data['supplier_id'],
        'user_id'      => auth()->id(),
        'type'         => $data['type'] ?? 'purchase',
        'notes'        => $data['notes'] ?? null,
    ]);

    $totalCost = 0;

    foreach ($data['items'] as $itemData) {
        $purchaseItem = $purchase->items()->create([
            'product_id' => $itemData['product_id'],
            'quantity'   => $itemData['quantity'],
            'unit_cost'  => $itemData['unit_cost'],
        ]);

        for ($i = 0; $i < $itemData['quantity']; $i++) {
            StockItem::create([
                'purchase_item_id' => $purchaseItem->id,
                'product_id'       => $itemData['product_id'],
                'uuid'             => (string) Str::uuid(),
                'serial_number'    => $itemData['serials'][$i] ?? null,
                'cost_price'       => $itemData['unit_cost'],
                'condition'        => $itemData['condition'] ?? 'new',
                'status'           => 'available',
            ]);
        }

        $totalCost += $itemData['unit_cost'] * $itemData['quantity'];
    }

    $purchase->update(['total_cost' => $totalCost]);

    return $purchase->load('items.stockItems');
});
```

### 3.4 Stock Items Generated

From the example purchase above, the system creates:

| Product | UUID | Serial | Status |
|---|---|---|---|---|
| S25 New | aaaa | IMEI001 | available |
| S25 New | bbbb | IMEI002 | available |
| S25 New | cccc | IMEI003 | available |
| ... | ... | ... | available |
| Case S25 (×30) | uuid-xxx-1 | null | available |
| Case S25 (×30) | uuid-xxx-2 | null | available |
| ... | ... | null | available |
| Headphones (×20) | uuid-yyy-1 | null | available |

**Result:** 30 phones + 30 cases + 20 headphones = **80 stock items** individually tracked.

---

## 4. Opening Stock (Initial Inventory)

Uses the same purchase flow with `type: opening_stock` and `supplier_id: null`.

```json
POST /api/purchases
{
  "type": "opening_stock",
  "notes": "Existing stock before system launch — verified by inventory count",
  "items": [
    {
      "product_id": 1,
      "quantity": 15,
      "unit_cost": 0,
      "serials": ["IMEI100", "IMEI101", "..."]
    },
    {
      "product_id": 4,
      "quantity": 10,
      "unit_cost": 0
    }
  ]
}
```

**Why this works:**
- No special-case code anywhere else in the system
- Reports treat `opening_stock` purchases as real inventory entries
- Cost is zero (or can be estimated cost for margin calculations)
- Trackable in audit: "Where did this item come from?" → "Opening stock entry #1"

---

## 5. Accessory Linking

### 5.1 Catalog Level (Compatibility)

Defined via `product_compatibility` table. Used for:

| Purpose | Implementation |
|---|---|
| UI filtering | "Show cases compatible with S25" |
| Validation | Warn if selling a case for iPhone with an S25 |
| Recommendations | Suggest accessories when viewing a phone |

**API:**

```
POST /api/product-compatibility
{
  "accessory_id": 4,
  "device_ids": [1, 2]
}
```

Accessories are **not linked to specific devices at purchase time**. They enter stock independently like any other product. Linking an accessory to a specific device unit happens **at the sale level** if needed, via `stock_items.parent_stock_item_id`.

---

## 6. Serial Numbers (IMEI)

For mobile phones, IMEI/serial tracking is critical.

- **Optional per product** — not all products need serials
- **Unique constraint** — prevents duplicate IMEI across the system
- **Input at purchase** — provided as an array matching quantity
- **Auto-generation** if not provided — UUID serves as internal identifier

Add a flag on `products` to indicate serial tracking:

```php
// On products table
$table->boolean('tracks_serial')->default(false);
```

Then in purchase logic:

```php
if ($product->tracks_serial && empty($itemData['serials'])) {
    throw new ValidationException("Serials required for {$product->name}");
}
```

---

## 7. API Routes

```php
Route::middleware('auth:sanctum')->group(function () {

    // Suppliers
    Route::apiResource('suppliers', SupplierController::class);

    // Product Compatibility
    Route::post('/product-compatibility', [ProductCompatibilityController::class, 'store']);
    Route::get('/products/{product}/compatible-accessories', [ProductCompatibilityController::class, 'accessories']);
    Route::get('/products/{product}/compatible-devices', [ProductCompatibilityController::class, 'devices']);

    // Purchases
    Route::get('/purchases', [PurchaseController::class, 'index']);
    Route::post('/purchases', [PurchaseController::class, 'store']);
    Route::get('/purchases/{purchase}', [PurchaseController::class, 'show']);

    // Stock Items
    Route::get('/stock-items', [StockItemController::class, 'index']);
    Route::get('/stock-items/available', [StockItemController::class, 'available']);
    Route::patch('/stock-items/{stockItem}/status', [StockItemController::class, 'updateStatus']);

});
```

---

## 8. Summary

| Concept | Implementation | Purpose |
|---|---|---|
| Product catalog | `products` table | What you sell — definitions only |
| New vs Used | `condition` column on products | Different pricing, warranty |
| Categories | `categories` table + `type` enum | Organize: phones, accessories |
| Model-specific accessory | `product_compatibility` | "This case fits S25" |
| Generic accessory | No compatibility entries | "Headphones fit any phone" |
| Purchase transaction | `purchases` + `purchase_items` | What came in, from whom, at what cost |
| Individual units | `stock_items` | One row per physical item |
| Opening stock | `purchases.type = 'opening_stock'` | Initial inventory, same flow |
| Serial/IMEI tracking | `stock_items.serial_number` | Unique per phone |
| Unit-level accessory link | `stock_items.parent_stock_item_id` | This case → this phone |
| Individual sale | Mark `stock_item.status = 'sold'` | Full traceability per unit |
