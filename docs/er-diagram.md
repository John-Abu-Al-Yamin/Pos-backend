# ER Diagram — POS System

```mermaid
erDiagram
    categories ||--o{ products : "has many"
    products ||--o{ stock_items : "has many"
    products ||--o{ purchase_items : "appears in"
    products ||--o{ product_compatibility : "is device for"
    products ||--o{ product_compatibility : "is accessory for"

    product_compatibility }o--|| products : "accessory_id"
    product_compatibility }o--|| products : "device_id"

    suppliers ||--o{ purchases : "has many"
    users ||--o{ purchases : "creates"

    purchases ||--o{ purchase_items : "contains"
    purchases }o--|| suppliers : "belongs to (nullable)"
    purchases }o--|| users : "belongs to"

    purchase_items ||--o{ stock_items : "produces"
    purchase_items }o--|| purchases : "belongs to"
    purchase_items }o--|| products : "references"

    stock_items }o--|| purchase_items : "belongs to"
    stock_items }o--|| products : "is instance of"
    stock_items ||--o{ stock_items : "parent/child (accessory→device)"

    categories {
        bigint id PK
        string name
        enum type "mobile | accessory | tablet | other"
        datetime created_at
        datetime updated_at
    }

    products {
        bigint id PK
        bigint category_id FK
        string name
        string brand "nullable"
        string model "nullable"
        enum condition "new | excellent | good | fair"
        decimal default_selling_price "12,2"
        text description "nullable"
        boolean tracks_serial "default false"
        datetime created_at
        datetime updated_at
    }

    product_compatibility {
        bigint accessory_id FK "FK→products"
        bigint device_id FK "FK→products"
    }

    suppliers {
        bigint id PK
        string name
        string phone "nullable"
        string email "nullable"
        text address "nullable"
        datetime created_at
        datetime updated_at
    }

    users {
        bigint id PK
        string name
        string email
        datetime created_at
        datetime updated_at
    }

    purchases {
        bigint id PK
        string reference_no "unique"
        bigint supplier_id FK "nullable"
        bigint user_id FK
        enum type "purchase | opening_stock"
        decimal total_cost "12,2"
        text notes "nullable"
        datetime created_at
        datetime updated_at
    }

    purchase_items {
        bigint id PK
        bigint purchase_id FK
        bigint product_id FK
        integer quantity
        decimal unit_cost "12,2"
        datetime created_at
        datetime updated_at
    }

    stock_items {
        bigint id PK
        uuid uuid "unique"
        bigint purchase_item_id FK
        bigint product_id FK
        string serial_number "nullable, unique"
        decimal cost_price "12,2"
        enum condition "new | excellent | good | fair"
        enum status "available | sold | damaged | returned"
        bigint sale_item_id FK "nullable"
        bigint parent_stock_item_id FK "nullable, self-ref"
        datetime sold_at "nullable"
        datetime created_at
        datetime updated_at
    }
```

## Purchase Flow Summary

| Step | Action | Endpoint |
|---|---|---|
| 1 | Define categories | `POST /api/categories` |
| 2 | Define products | `POST /api/products` |
| 3 | Link compatibility | `POST /api/product-compatibility` |
| 4 | Define supplier | `POST /api/suppliers` |
| 5 | Create purchase (header + items) | `POST /api/purchases` |
| 6 | Stock items auto-generated | 1 row per unit |
| 7 | Sell individual items | `PATCH /api/stock-items/{id}/status` |

## Key Design Decisions

- **Individual unit tracking**: `stock_items` has one row per physical item, not quantity-based
- **Accessory linking**: At catalog level via `product_compatibility`; at unit level via `stock_items.parent_stock_item_id`
- **Opening stock**: Uses the same `purchases` flow with `type: opening_stock` and `supplier_id: null`
- **Serial/IMEI**: Optional; stored on `stock_items.serial_number` with unique constraint
