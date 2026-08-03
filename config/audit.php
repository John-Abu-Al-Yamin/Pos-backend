<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\InventoryItem;
use App\Models\InventoryQuantity;
use App\Models\MaintenanceDevice;
use App\Models\MaintenanceHeader;
use App\Models\MaintenanceOperation;
use App\Models\MaintenanceUsedPart;
use App\Models\MarkupSetting;
use App\Models\Product;
use App\Models\PurchaseHeader;
use App\Models\PurchaseItem;
use App\Models\PurchaseReturnHeader;
use App\Models\PurchaseReturnItem;
use App\Models\SalaryAssignment;
use App\Models\SalaryPayment;
use App\Models\SalaryPaymentItem;
use App\Models\SalesHeader;
use App\Models\SalesItem;
use App\Models\SalesReturnHeader;
use App\Models\SalesReturnItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\UsedDevicePurchaseHeader;
use App\Models\UsedDevicePurchaseItem;
use App\Models\User;

return [
    'enabled' => env('AUDIT_LOGS_ENABLED', true),

    'retention_days' => (int) env('AUDIT_LOGS_RETENTION_DAYS', 1095),

    'max_per_page' => 100,

    'ignored_fields' => [
        'created_at',
        'updated_at',
        'email_verified_at',
    ],

    'hidden_fields' => [
        'password',
        'remember_token',
        'token',
        'plainTextToken',
        'api_key',
        'secret',
        'authorization',
    ],

    'label_fields' => [
        'name',
        'invoice_number',
        'purchaseHeader_number',
        'supplier_invoice_number',
        'reference',
        'email',
        'expense_category',
        'movement_type',
        'status',
    ],

    'models' => [
        Brand::class => 'brands',
        Category::class => 'categories',
        Customer::class => 'customers',
        Supplier::class => 'suppliers',
        Product::class => 'products',
        MarkupSetting::class => 'pricing',
        PurchaseHeader::class => 'purchases',
        PurchaseItem::class => 'purchases',
        UsedDevicePurchaseHeader::class => 'purchases',
        UsedDevicePurchaseItem::class => 'purchases',
        PurchaseReturnHeader::class => 'purchase_returns',
        PurchaseReturnItem::class => 'purchase_returns',
        SalesHeader::class => 'sales',
        SalesItem::class => 'sales',
        SalesReturnHeader::class => 'sales_returns',
        SalesReturnItem::class => 'sales_returns',
        InventoryQuantity::class => 'inventory',
        InventoryItem::class => 'inventory',
        StockMovement::class => 'inventory',
        Expense::class => 'expenses',
        SalaryAssignment::class => 'salaries',
        SalaryPayment::class => 'salaries',
        SalaryPaymentItem::class => 'salaries',
        MaintenanceDevice::class => 'maintenance',
        MaintenanceHeader::class => 'maintenance',
        MaintenanceOperation::class => 'maintenance',
        MaintenanceUsedPart::class => 'maintenance',
        User::class => 'users_roles',
    ],
];
