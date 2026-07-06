# Mobile Shop POS System - Purchase Module

## Business Flow, Backend Rules & Purchase Architecture

> **Version:** 1.1  
> **Project Type:** Mobile Shop POS and Store Management System  
> **Backend:** Laravel  
> **Inventory Model:** Hybrid Inventory — Quantity Based + Item Based

---

# 9. Purchase Architecture

## 9.1 Final Design Decision

A single purchase screen is used for normal purchases from suppliers.

The screen supports:

- New mobile devices.
- Accessories.
- Spare parts.

A single supplier invoice may contain more than one product type.

Example:

| Product | Type | Quantity | Cost |
|---|---|---:|---:|
| iPhone 16 Pro | mobile | 1 | 48,000 |
| Apple Charger | accessory | 20 | 400 |
| iPhone 13 Screen | spare_part | 10 | 1,200 |

These items are not split into three invoices merely because their product types differ.

## 9.2 Used Device Purchase

Used-device purchases have a separate screen and module named:

```text
Used Device Purchase
```

Reasons:

- The seller is usually an individual rather than a traditional supplier.
- The device requires a detailed inspection.
- Every device has a different condition.
- Battery, screen, fingerprint, Face ID, and other inspection data must be recorded.
- Every device is priced individually.
- The purchase process differs from a normal supplier invoice.

## 9.3 Purchase Type Field

Do not add:

```text
purchases.type
```

with values such as:

```text
accessories
new_mobile
spare_part
```

A single supplier invoice may contain more than one type. The type of each line is determined through:

```text
purchase_items.product_id
    ↓
products.type
```

Therefore:

- The invoice does not need a type.
- Each purchase item derives its behavior from the product type.
- The user does not select the type manually.
- This reduces inconsistencies and errors.

## 9.4 Purchase Price

The purchase price is stored in:

```text
purchase_items.cost_price
```

It is not stored as a fixed purchase price in `products`.

The same product can be purchased at different prices on different invoices.

Example:

```text
Purchase #1001
iPhone 16 Cost = 48,000

Purchase #1002
iPhone 16 Cost = 47,500
```

Storing the price in `purchase_items` preserves the cost history of every purchase.

For mobile devices, the device cost is also copied to:

```text
inventory_items.cost_price
```

because each device may have a different cost.

## 9.5 Purchase Screen

The normal purchase screen contains:

### Header

- Supplier.
- Supplier Invoice Number.
- Purchase Date.
- Notes.
- Payment Method.
- Paid Amount.
- Due Amount.
- Status.

### Items

- Product.
- Quantity.
- Cost Price.
- Selling Price.
- Discount, if applicable.
- Subtotal.

## 9.6 Dynamic UI Behavior

When a product is selected, the system reads:

```php
$product->type
```

### Mobile

Display:

- Product.
- Number of Devices.
- Cost Price.
- Selling Price.
- Generate Items.

The system creates a separate record for every device.

### Accessory

Display:

- Product.
- Quantity.
- Cost Price.
- Selling Price.

### Spare Part

Display:

- Product.
- Quantity.
- Cost Price.
- Selling Price.

The type sent by the user interface must not be treated as the source of truth.

The backend reloads the product from the database and determines the required behavior.

Example:

```php
$product = Product::query()->findOrFail($itemData['product_id']);

match ($product->type) {
    'mobile' => $this->handleMobileItem($product, $itemData),
    'accessory' => $this->handleQuantityItem($product, $itemData),
    'spare_part' => $this->handleQuantityItem($product, $itemData),
};
```

---

# 11. Purchase Flow

## Goal

Purchase goods from a supplier and update:

- Inventory.
- Cash.
- Stock movements.
- The supplier account.
- The audit trail.

## Step 1: Create Purchase Draft

Create only the purchase header.

```text
status = draft
total = 0
```

While the purchase is a draft:

- Inventory does not change.
- Cash does not change.
- No supplier ledger entry is created.
- Items can be added, removed, or edited.
- The invoice can be cancelled.

## Step 2: Add Purchase Items

Add the products.

### Accessory

```text
product_id
quantity
cost_price
selling_price
```

### Spare Part

```text
product_id
quantity
cost_price
selling_price
```

### New Mobile

```text
product_id
number_of_devices
cost_price
selling_price
```

After completion, every device becomes a separate inventory item.

## Step 3: Validate Before Completion

Verify that:

- The supplier exists.
- At least one item exists.
- Every product is active.
- `quantity > 0`.
- `cost_price >= 0`.
- A product type supplied by the user is not treated as the source of truth.
- The invoice total is correct.
- The paid amount is valid.
- Sufficient cash is available when the payment method is cash.
- The invoice is neither completed nor cancelled.
- The operation has not already been processed.

## Step 4: Complete Purchase

When Complete is selected:

1. Start a `DB::transaction`.
2. Lock the purchase record with `lockForUpdate`.
3. Confirm that its status is `draft`.
4. Calculate all totals on the server.
5. Process every purchase item.
6. Update quantity-based inventory for accessories and spare parts.
7. Create inventory items for mobile devices.
8. Create stock movements.
9. Create a cash movement if a cash payment was made.
10. Create a supplier ledger entry.
11. Change the purchase status to `completed`.
12. Store `completed_at`.
13. Create an audit log.
14. Commit the transaction.

If any step fails:

```text
Rollback All Changes
```

## Purchase Completion Flow

```text
Draft Purchase
    ↓
Add Products
    ↓
Validate Items
    ↓
Begin Database Transaction
    ↓
Lock Purchase
    ↓
Recalculate Totals
    ↓
Process Each Item by products.type
    ↓
Update Inventory
    ↓
Create Stock Movements
    ↓
Create Cash Movement / Supplier Debt
    ↓
Create Supplier Ledger
    ↓
Complete Purchase
    ↓
Audit Log
    ↓
Commit
```

---

# 12. Purchase Inventory Effects

## 12.1 Accessory

When the purchase is completed:

- Increase `inventory_quantities.quantity`.
- Create a positive stock movement.

Example:

```text
type = PURCHASE
product_id = 20
quantity = +50
```

## 12.2 Spare Part

Use the same processing as for accessories:

- Increase the quantity.
- Update the average cost.
- Create a stock movement.

## 12.3 New Mobile

When the purchase is completed:

- Create an inventory item for every device.
- Set its status to `available`.
- Store the device cost.
- Store the selling price.
- Generate an internal serial number.
- Create a stock movement for each device, or one grouped movement with device references.

Example:

```text
Inventory Item #501
Product = iPhone 16
Cost = 48,000
Status = Available
```

---

# 14. Purchase Cash and Supplier Logic

## Cash Purchase

If the full amount is paid in cash:

- Cash decreases.
- The supplier balance does not increase.
- Create a cash movement.
- Create supplier ledger payment and purchase entries according to the accounting design.

## Credit Purchase

If the full amount is not paid:

- Cash decreases only by the amount paid.
- The supplier balance increases by the outstanding amount.
- Create a supplier ledger entry.

## Partial Payment

Example:

```text
Grand Total = 20,000
Paid Amount = 8,000
Due Amount = 12,000
```

Result:

- Cash decreases by 8,000.
- The supplier balance increases by 12,000.

---

# 15. Used Device Purchase Module

Purchasing a used device is a separate process.

## 15.1 Header

```text
used_device_purchases
- id
- seller_name
- seller_phone
- seller_national_id
- purchase_date
- total
- paid_amount
- notes
- status
- created_by
- timestamps
```

## 15.2 Device Data

For every used device:

- Product / Model.
- Storage.
- Color.
- Device Condition.
- Battery Health.
- Screen Condition.
- Body Condition.
- Camera Condition.
- Speaker Condition.
- Microphone Condition.
- Charging Condition.
- Network Condition.
- Fingerprint Status.
- Face ID Status.
- Accessories Included.
- Cost Price.
- Expected Selling Price.
- Notes.

## 15.3 Suggested Status Values

```text
available
reserved
sold
returned
under_repair
damaged
```

## 15.4 Used Device Purchase Completion

When saving:

1. Validate the seller and device data.
2. Begin a database transaction.
3. Create the used-device purchase.
4. Create the inventory item.
5. Generate an internal serial number.
6. Store the inspection data.
7. Set the status to `available`.
8. Create a `USED_DEVICE_PURCHASE` stock movement.
9. Decrease cash.
10. Create an audit log.
11. Commit the transaction.

---

# 16. Purchase Returns

A purchase return is a reverse transaction, not an edit to the original purchase invoice.

## 16.1 Create Purchase Return

Select:

- The original purchase.
- The products or devices being returned.
- The quantities.
- The return reason.
- The refund method.

## 16.2 Rules

- The returned quantity cannot exceed the purchased quantity minus any quantity previously returned.
- A mobile device that has been sold cannot be returned.
- Do not modify the original purchase.
- Create a new purchase return.
- Create a new stock movement.
- Create a cash movement or supplier credit.
- Record the operation in the audit trail.

## 16.3 Quantity-Based Return

For accessories and spare parts:

- Reduce inventory.
- Create a `PURCHASE_RETURN` movement.
- Refund the amount or record supplier credit.

## 16.4 Mobile Return

For mobile devices:

- Select the `inventory_item_id`.
- Verify that the device is available and has not been sold.
- Change its status to `returned`.
- Remove it from available inventory.
- Create a stock movement.

## 16.5 Purchase Return Flow

```text
Select Original Purchase
    ↓
Select Returnable Items
    ↓
Validate Remaining Return Quantity
    ↓
Begin Database Transaction
    ↓
Reduce Inventory
    ↓
Update Inventory Item Status
    ↓
Create Purchase Return Stock Movement
    ↓
Create Cash Refund or Supplier Credit
    ↓
Create Supplier Ledger Entry
    ↓
Audit Log
    ↓
Commit
```

---

# 36. Purchase Service Responsibilities

`PurchaseService` is responsible for:

- Creating a draft.
- Adding items.
- Editing a draft.
- Removing an item from a draft.
- Recalculating totals.
- Completing the invoice.
- Cancelling a draft.
- Preventing changes to a completed purchase.
- Calling the inventory service.
- Calling the cash service.
- Calling the supplier ledger service.
- Creating an audit log.

Example:

```php
final class PurchaseService
{
    public function createDraft(array $data): Purchase
    {
        // Create header only.
    }

    public function addItem(Purchase $purchase, array $data): PurchaseItem
    {
        // Draft only.
    }

    public function complete(Purchase $purchase): Purchase
    {
        return DB::transaction(function () use ($purchase) {
            // Lock.
            // Validate.
            // Process items.
            // Inventory.
            // Cash.
            // Supplier ledger.
            // Audit.
            // Complete.
        });
    }
}
```

---

# 39. Final Purchase Summary

## Normal Purchase

Used for:

- New mobile devices.
- Accessories.
- Spare parts.

```text
Create Draft
    ↓
Select Supplier
    ↓
Add Mixed Products
    ↓
Product Type Read from products.type
    ↓
Cost Stored in purchase_items
    ↓
Complete
    ↓
Stock Increases
    ↓
Cash Decreases or Supplier Debt Increases
    ↓
Stock Movement
    ↓
Cash Movement
    ↓
Supplier Ledger
    ↓
Audit Trail
```

## Used Device Purchase

Used for:

- Purchasing a used device from an individual.
- Recording a detailed device inspection.
- Storing battery, screen, fingerprint, and other condition data.
- Creating a separate inventory item.

```text
Seller Data
    ↓
Device Inspection
    ↓
Cost and Selling Price
    ↓
Create Inventory Item
    ↓
Generate Internal Serial
    ↓
Cash Decreases
    ↓
Stock Movement
    ↓
Audit Trail
```

## Purchase Return

```text
Select Original Purchase
    ↓
Select Products or Devices
    ↓
Validate Returnable Quantity
    ↓
Reduce Stock
    ↓
Cash Refund or Supplier Credit
    ↓
Supplier Ledger
    ↓
Audit Trail
```
