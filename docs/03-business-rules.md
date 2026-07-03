# Mobile Shop POS System - Business Rules

## Business Flow, Backend Rules & Purchase Architecture

> **Version:** 1.1  
> **Project Type:** Mobile Shop POS and Store Management System  
> **Backend:** Laravel  
> **Inventory Model:** Hybrid Inventory — Quantity Based + Item Based

---

# 3. Master Data

## 3.1 Categories

Suggested Core Categories:

- Mobiles
- Accessories
- Spare Parts

Separate categories are NOT used under the names:

- New Mobiles
- Used Mobiles

Because the device condition (`new` or `used`) belongs to the individual item in inventory, not to the product catalog classification.

Repair/maintenance services are not considered products; they are stored in a separate table.

## 3.2 Brands

Examples:

- Apple
- Samsung
- Xiaomi
- Oppo
- Huawei
- Anker
- Baseus

## 3.3 Products

The `products` table represents the product catalog only.

Adding a new Product:

- Does not increase inventory.
- Does not create a Stock Movement.
- Does not create a Cash Movement.
- The product starts with zero quantity or zero associated devices.

Laravel Migration Example:

```php
Schema::create('products', function (Blueprint $table) {
    $table->id();

    $table->foreignId('category_id')
        ->constrained()
        ->restrictOnDelete();

    $table->foreignId('brand_id')
        ->nullable()
        ->constrained()
        ->nullOnDelete();

    $table->string('name');

    $table->enum('type', [
        'mobile',
        'accessory',
        'spare_part',
    ]);

    $table->string('sku')->unique();

    $table->string('barcode')->nullable()->unique();

    $table->unsignedSmallInteger('min_stock')->default(5);

    $table->decimal('default_selling_price', 12, 2)->default(0);

    $table->boolean('is_active')->default(true);

    $table->timestamps();
});
```

## 3.4 Product Type

The following column is used within the products table:

```php
type
```

Values:

```text
mobile
accessory
spare_part
```

Its purpose is to determine the product type and inventory tracking behavior.

| Product Type | Inventory Method |
|---|---|
| mobile | Item Based |
| accessory | Quantity Based |
| spare_part | Quantity Based |

The user does not select the inventory method during the purchase invoice process.

The system automatically reads the product type from:

```php
$product->type
```

## 3.5 Suppliers

Supplier data includes:

- Name.
- Phone number.
- Address.
- Notes.
- Current balance.
- Purchases.
- Returns.
- Payments.
- Account statement.

## 3.6 Customers

Customer data includes:

- Name.
- Phone number.
- Address.
- Sales (Purchases made by customer).
- Returns.
- Repair tickets.
- Payments.
- Notes.

## 3.7 Repair Services

Independent table for repair services.

Examples:

- Screen replacement.
- Battery replacement.
- Charging port repair.
- Network repair.
- Face ID repair.
- Software maintenance/flashing.

## 3.8 Expense Categories

Examples:

- Rent.
- Electricity.
- Salaries.
- Transport/Shipping.
- Shop maintenance.
- Administrative expenses.

---

# 4. Import From Excel

The system must support importing data from Excel.

The import includes:

- Products.
- Categories.
- Suppliers.
- Customers.

## Import Rules

Before saving, the system must:

1. Read the file completely.
2. Run validation on all rows.
3. Consolidate all validation errors.
4. Display the row number, column, and the error message.
5. Do not save any record if the file contains any errors.
6. Execute the saving operation inside a Database Transaction.
7. Prevent duplication of SKU, Barcode, or other unique fields.
8. Verify that the category and brand exist when importing products.

Error Example:

```text
Row 8: category_id does not exist.
Row 13: sku already exists.
Row 21: product type must be mobile, accessory or spare_part.
```

---

# 5. Opening Stock

Not all inventory will come from purchase operations.

There might be stock physically present in the shop before deploying the system, so an `Opening Stock` screen must be provided.

## Quantity-Based Products

The following are entered:

- Product.
- Quantity.
- Average purchase cost.
- Default selling price.

## Item-Based Products

For mobiles, the following are entered:

- Product.
- Number of devices.
- Cost of each device.
- Selling price of each device.
- Device condition.
- Required data to create each individual Inventory Item.

## After Completion

Upon completing Opening Stock:

- Create a Stock Movement of type `OPENING_STOCK`.
- Increase stock quantities.
- Create Inventory Items for mobiles.
- No Purchase record is created.
- The process is not linked to any supplier.
- The operation is not considered a purchase.
- The user and timestamp are recorded in the Audit Trail.

## Rule

Opening Stock is used once when first launching the system, or restricted to a very specific administrative permission.

---

# 6. Opening Cash

It must be possible to enter the existing cash balance in the safe before starting to use the system.

Example:

```text
Opening Cash = 20,000 EGP
```

After saving:

- Create a Cash Movement of type `OPENING_CASH`.
- Increase the safe balance.
- The operation is not considered a Sale.
- The operation is not considered a Supplier Payment.
- The operation is recorded in the Audit Trail.

---

# 7. Hybrid Inventory Model

The system relies on a hybrid inventory model.

## 7.1 Quantity-Based Inventory

Used with:

- Accessories.
- Spare parts.

Example:

```text
Case = 100 pieces
Charger = 50 pieces
iPhone 13 Screen = 12 pieces
```

The following are tracked:

- Current quantity.
- Average cost.
- Minimum stock limit.
- Stock movement.

Suggested Table Schema:

```text
inventory_quantities
- id
- product_id
- quantity
- average_cost
- updated_at
```

## 7.2 Item-Based Inventory

Used with:

- New mobile phones.
- Used mobile phones.

Each physical device represents an independent record.

Example:

```text
iPhone 13 #1
iPhone 13 #2
```

Each device contains:

- Internal ID.
- Internal Serial Number.
- IMEI / Serial.
- Product ID.
- Condition.
- Status.
- Cost Price.
- Selling Price.
- Purchase Reference.
- Sale Reference.
- Notes.
- Timestamps.

Suggested Table Schema:

```text
inventory_items
- id
- product_id
- internal_serial
- imei
- condition
- status
- cost_price
- selling_price
- purchase_id
- sale_id
- notes
- timestamps
```

## Important IMEI Rule

According to the project requirements:

- The user does not enter the internal serial number manually.
- The Backend generates a Unique Internal Serial.
- Duplication is prohibited.
- Each device receives a Unique Internal ID.

Technically, it is preferred to distinguish between:

```text
internal_serial
```

and:

```text
actual_imei
```

Because the actual IMEI is issued by the manufacturer, while the number generated by the system is an internal tracking number.

---

# 8. Product Creation Rules

Adding a product does not increase inventory.

Each new Product starts with:

- Quantity = 0 for Quantity-Based products.
- No Inventory Items for Item-Based products.

Inventory only increases through:

- Opening Stock.
- Completed Purchases.
- Accepted Sales Returns.
- Positive Stock Adjustments.
- Used Device Purchases.

Inventory only decreases through:

- Sales.
- Purchase Returns.
- Repair/Maintenance Usages.
- Damaged stock.
- Lost stock.
- Negative Stock Adjustments (if supported later).
- Used Device Sales.

---

# 13. Average Cost for Quantity Products

For accessories and spare parts, Weighted Average Cost can be used.

Equation:

```text
New Average Cost =
(
    Current Quantity × Current Average Cost
    +
    Purchased Quantity × Purchase Cost
)
/
(
    Current Quantity + Purchased Quantity
)
```

The calculation is executed inside `InventoryService`.

Average Cost is not manually modified from the Controller.

---

# 23. Damaged Inventory

Any product damaged inside the shop:

- Screen, case, headphone, charger, spare part, mobile phone.

After saving:

- Reduce the available stock.
- Create a Stock Movement of type `DAMAGED`.
- Record the reason for damage.
- Record the employee.
- Record the value of the loss.
- Save images if available.
- Audit Trail.

---

# 24. Lost Inventory

If a shortage is found during stock counts:

- Create a Lost Adjustment.
- Reduce stock.
- Create a Stock Movement of type `LOST`.
- Record the reason.
- Record the reference stock count.
- Record the loss value.

---

# 25. Stock Adjustment

If an excess is found during stock counts:

- Increase stock.
- Create a Stock Movement of type `STOCK_ADJUSTMENT`.
- Record the reason.
- Record the user.
- Link the operation to the inventory count report.

Do not use Stock Adjustment to correct normal mistakes without documented justification.

---

# 26. Inventory Count

Compare:

```text
System Quantity
```

with:

```text
Physical Quantity
```

The difference:

- Shortage -> Lost.
- Excess -> Stock Adjustment.

Inventory Count Header and Items are created.

Inventory is not adjusted directly from the stock count screen.

---

# 27. Expenses

Examples:

- Rent.
- Electricity.
- Salaries.
- Transport.

After saving:

- Reduce cash.
- Create a Cash Movement of type `EXPENSE`.
- Link the movement to the expense record.
- Record the user and date/timestamp.

---

# 28. Supplier Information

The Supplier page displays:

- Total purchases.
- Paid purchases.
- Credit (due) purchases.
- Returns.
- Payments.
- Current balance.
- Account statement.

---

# 29. Customer Information

The Customer page displays:

- Sales.
- Returns.
- Repair tickets.
- Payments.
- Purchased devices.
- Transaction history.

---

# 30. Product Information

The Product page displays:

- Name.
- Category.
- Brand.
- Type.
- Default price.
- Current stock.
- Average cost.
- Available items if the product is a Mobile.
- Purchase history.
- Sales history.
- Full inventory movement log.

---

# 33. Reports

The following reports must be provided:

- Sales Report.
- Purchase Report.
- Profit Report.
- Inventory Report.
- Stock Valuation.
- Used Devices Report.
- Purchase Returns.
- Sales Returns.
- Expenses Report.
- Repair Report.
- Cash Report.
- Supplier Balance Report.
- Customer History.
- Inventory Count Report.
- Damaged Inventory Report.
- Lost Inventory Report.
- Low Stock Report.
- Product Profitability.
- Category Profitability.
- Daily Cash Report.

---

# 34. Core Business Rules

1. No manual adjustment of inventory is allowed.
2. No manual adjustment of cash is allowed.
3. Every business operation is considered a Transaction.
4. Every inventory change creates a Stock Movement.
5. Every cash change creates a Cash Movement.
6. Opening Stock is not a Purchase.
7. Opening Cash is not a Sale.
8. The system is a Hybrid Inventory system.
9. Each mobile phone is an independent Inventory Item.
10. The cost price is stored inside the Purchase Item.
11. The cost of the device is copied to the Inventory Item.
12. A Normal Purchase can include New Mobiles + Accessories + Spare Parts in a single invoice.
13. The Used Device Purchase Module is completely independent.
14. No `purchase.type` exists since the invoice permits mixed products.
15. The behavior of a Purchase Item is determined dynamically from `products.type`.
16. The user does not specify the Product Type inside the invoice manually.
17. A Completed Purchase cannot be edited.
18. Purchase Return is an independent transaction.
19. Completed financial or inventory transactions are never deleted.
20. Cancellation or a Reverse Transaction is used instead of deletion.
21. Every Complete operation must execute within a Database Transaction.
22. Totals are recalculated on the Backend.
23. Do not trust any totals or types sent from the Frontend.
24. Every operation is logged in the Audit Trail.

---

# 38. Audit Trail

Each operation must record:

- User ID.
- Action.
- Entity Type.
- Entity ID.
- Old Values.
- New Values.
- Reason.
- IP Address.
- Device/User Agent.
- Created At.

Action Examples:

```text
purchase_created
purchase_completed
purchase_cancelled
purchase_return_completed
stock_adjusted
cash_movement_created
used_device_purchased
```
