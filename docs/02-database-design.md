# Mobile Shop POS System - Database Design

## Business Flow, Backend Rules & Purchase Architecture

> **Version:** 1.1  
> **Project Type:** Mobile Shop POS and Store Management System  
> **Backend:** Laravel  
> **Inventory Model:** Hybrid Inventory — Quantity Based + Item Based

---

# 10. Purchase Database Design

## 10.1 Purchases Table

```text
purchases
- id
- supplier_id
- invoice_number
- status
- purchase_date
- subtotal
- discount
- tax
- grand_total
- paid_amount
- due_amount
- notes
- created_by
- completed_at
- cancelled_at
- timestamps
```

Suggested Statuses:

```text
draft
completed
cancelled
```

## 10.2 Purchase Items Table

```text
purchase_items
- id
- purchase_id
- product_id
- quantity
- cost_price
- selling_price
- discount
- subtotal
- product_name_snapshot
- sku_snapshot
- timestamps
```

### Quantity-Based Product

Example:

```text
product_id = 10
quantity = 50
cost_price = 100
subtotal = 5,000
```

### Item-Based Product

Can be used:

```text
quantity = 2
```

Then upon completing the invoice, two individual devices are created inside `inventory_items`.

Alternatively, a separate row can be created for each device if each device has a different cost.

## 10.3 Product Snapshot

It is preferred to store snapshots inside `purchase_items` such as:

- Product Name.
- SKU.
- Barcode.
- Product Type.

Goal:

If the product name changes later, the old invoice retains its historical data.

---

# 31. Stock Movement Types

```text
OPENING_STOCK
PURCHASE
SALE
SALES_RETURN
PURCHASE_RETURN
USED_DEVICE_PURCHASE
USED_DEVICE_SALE
USED_DEVICE_RETURN
REPAIR_USAGE
DAMAGED
LOST
STOCK_ADJUSTMENT
```

Each Stock Movement must contain:

- Product ID.
- Inventory Item ID (if applicable).
- Quantity.
- Direction.
- Type.
- Reference Type.
- Reference ID.
- Unit Cost.
- Total Cost.
- User ID.
- Date.
- Notes.

---

# 32. Cash Movement Types

Cash increases from:

- Sales.
- Used Device Sales.
- Repair Payments.
- Purchase Refunds.
- Opening Cash.

Cash decreases from:

- Purchases.
- Expenses.
- Sales Refunds.
- Used Device Purchases.
- Supplier Payments.

Each Cash Movement contains:

- Type.
- Direction.
- Amount.
- Reference Type.
- Reference ID.
- Cash Account.
- User ID.
- Date.
- Notes.
