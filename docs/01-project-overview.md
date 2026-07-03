# Mobile Shop POS System - Project Overview

## Business Flow, Backend Rules & Purchase Architecture

> **Version:** 1.1  
> **Project Type:** Mobile Shop POS and Store Management System  
> **Backend:** Laravel  
> **Inventory Model:** Hybrid Inventory — Quantity Based + Item Based

---

# 1. Project Overview

The system is designed to manage a mobile phone shop completely, not just for executing sales transactions.

The system includes:

- Selling new mobile phones.
- Selling accessories.
- Selling spare parts.
- Buying and selling used devices.
- Mobile phone repair (maintenance) management.
- Inventory management.
- Cash management.
- Supplier and customer management.
- Expense management.
- Stock counts and adjustments.
- Reporting.
- Comprehensive audit logs (`Audit Trail`).

---

# 2. Main Modules

The system consists of the following core modules:

1. Master Data
2. Products
3. Suppliers
4. Customers
5. Opening Stock
6. Opening Cash
7. Purchases
8. Purchase Returns
9. Used Device Purchases
10. Sales
11. Sales Returns
12. Used Device Sales
13. Repairs
14. Expenses
15. Inventory
16. Cash Management
17. Reports
18. Audit Trail
19. Users, Roles and Permissions

---

# 35. Laravel Backend Architecture

Suggested Flow:

```text
Form Request
    ↓
Controller
    ↓
Application Service
    ↓
Domain / Business Rules
    ↓
Inventory Service
    ↓
Cash Service
    ↓
Ledger Service
    ↓
Models
    ↓
Database
```

## Services

```text
PurchaseService
PurchaseReturnService
UsedDevicePurchaseService
SaleService
SalesReturnService
InventoryService
CashService
SupplierLedgerService
CustomerLedgerService
RepairService
ExpenseService
AuditService
```

## Controllers

```text
PurchaseController
PurchaseReturnController
UsedDevicePurchaseController
SaleController
SalesReturnController
RepairController
InventoryController
ExpenseController
```

## Important Rule

Controllers must be thin/lightweight.

No inventory or cash logic should be placed inside a Controller.

---

# 37. Concurrency and Bug Prevention

To prevent duplication or conflict of operations:

- Use `DB::transaction`.
- Use `lockForUpdate` when completing an invoice.
- Use Unique Constraints.
- Prevent double-submits from the frontend.
- Use an Idempotency Key for sensitive operations if possible.
- Re-read the `Product Type` from the database on the backend.
- Recalculate the invoice total on the backend server.
- Prevent completing the same purchase twice.
- Prevent returns of quantities greater than what is available (or remaining).
- Prevent selling inventory items that are not in an `available` status.

---

# 40. Final Architecture Rule

```text
Every Operation = Transaction Header + Transaction Items
```

```text
No Direct Stock Update
```

```text
No Direct Cash Update
```

```text
No Direct Editing of Completed Purchases
```

```text
Product Type Determines Inventory Behavior
```

```text
Normal Supplier Purchase and Used Device Purchase Are Separate Workflows
```
