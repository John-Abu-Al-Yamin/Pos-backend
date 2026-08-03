<?php

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Expense;
use App\Models\InventoryQuantity;
use App\Models\MaintenanceHeader;
use App\Models\MarkupSetting;
use App\Models\Product;
use App\Models\PurchaseHeader;
use App\Models\PurchaseItem;
use App\Models\SalaryAssignment;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Expense\ExpenseService;
use App\Services\Maintenance\MaintenancePartService;
use App\Services\Maintenance\MaintenanceStatusService;
use App\Services\Maintenance\MaintenanceTicketService;
use App\Services\Purchase\PurchaseHeaderService;
use App\Services\Salary\SalaryPaymentService;
use App\Services\Sales\SalesCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('schedules audit pruning automatically', function () {
    Artisan::call('schedule:list');

    expect(Artisan::output())->toContain('audit:prune');
});

it('caches the audit table existence check across audit writes', function () {
    [$admin] = auditSeedCatalog();
    Sanctum::actingAs($admin);
    AuditLogService::forgetAuditTableExistence();

    $schemaQueries = 0;

    DB::listen(function ($query) use (&$schemaQueries) {
        $sql = strtolower($query->sql);

        if (
            str_contains($sql, 'information_schema')
            || str_contains($sql, 'sqlite_master')
            || str_contains($sql, 'sqlite_schema')
        ) {
            $schemaQueries++;
        }
    });

    app(AuditLogService::class)->record(module: 'auth', action: 'login_success', auditable: $admin);
    app(AuditLogService::class)->record(module: 'auth', action: 'logout', auditable: $admin);
    app(AuditLogService::class)->record(module: 'auth', action: 'login_success', auditable: $admin);

    expect($schemaQueries)->toBeLessThanOrEqual(1);
});

it('does not rollback sales or purchases when audit logging is unavailable', function () {
    [$admin, $product] = auditSeedCatalog();
    Sanctum::actingAs($admin);

    InventoryQuantity::create([
        'product_id' => $product->id,
        'quantity' => 5,
        'cost_price' => 40,
    ]);

    Schema::dropIfExists('audit_logs');

    $sale = app(SalesCheckoutService::class)->checkout([
        'discount_amount' => 0,
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 80,
        ]],
    ]);

    expect($sale->total_amount)->toEqual('160.00')
        ->and((int) InventoryQuantity::where('product_id', $product->id)->value('quantity'))->toBe(3);

    $supplier = Supplier::create([
        'name' => 'Audit Supplier',
        'phone' => '01000000000',
    ]);

    $purchase = PurchaseHeader::create([
        'supplier_id' => $supplier->id,
        'created_by' => $admin->id,
        'status' => 'draft',
        'total_amount' => 120,
        'purchaseHeader_number' => 'PO-AUDIT-001',
    ]);

    PurchaseItem::create([
        'purchase_header_id' => $purchase->id,
        'product_id' => $product->id,
        'quantity' => 3,
        'unit_price' => 40,
        'total_price' => 120,
    ]);

    $completedPurchase = app(PurchaseHeaderService::class)->complete($purchase);

    expect($completedPurchase->status)->toBe('completed')
        ->and((int) InventoryQuantity::where('product_id', $product->id)->value('quantity'))->toBe(6);
});

it('records meaningful sale and purchase business events without duplicate generic logs', function () {
    [$admin, $product] = auditSeedCatalog();
    Sanctum::actingAs($admin);

    AuditLog::withoutMutationGuard(fn () => AuditLog::query()->delete());

    InventoryQuantity::create([
        'product_id' => $product->id,
        'quantity' => 5,
        'cost_price' => 40,
    ]);

    AuditLog::withoutMutationGuard(fn () => AuditLog::query()->delete());

    $sale = app(SalesCheckoutService::class)->checkout([
        'discount_amount' => 5,
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 80,
        ]],
    ]);

    $actions = AuditLog::pluck('action')->all();

    expect($actions)->toContain('sale_completed')
        ->and($actions)->toContain('stock_adjusted')
        ->and(AuditLog::where('action', 'created')->where('auditable_type', $sale->getMorphClass())->count())->toBe(0);

    $saleLog = AuditLog::where('action', 'sale_completed')->firstOrFail();

    expect($saleLog->metadata['invoice_number'])->toBe($sale->invoice_number)
        ->and((float) $saleLog->metadata['total_amount'])->toBe(75.0);

    $purchase = AuditLogService::withoutModelEvents(function () use ($admin, $product) {
        $supplier = Supplier::create([
            'name' => 'Business Supplier',
            'phone' => '01111111111',
        ]);

        $purchase = PurchaseHeader::create([
            'supplier_id' => $supplier->id,
            'created_by' => $admin->id,
            'status' => 'draft',
            'total_amount' => 80,
            'purchaseHeader_number' => 'PO-AUDIT-002',
        ]);

        PurchaseItem::create([
            'purchase_header_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 40,
            'total_price' => 80,
        ]);

        return $purchase;
    });

    AuditLog::withoutMutationGuard(fn () => AuditLog::query()->delete());

    $completedPurchase = app(PurchaseHeaderService::class)->complete($purchase);

    expect(AuditLog::where('action', 'purchase_completed')->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'updated')->where('auditable_type', $completedPurchase->getMorphClass())->count())->toBe(0);
});

it('audits purchase deletion with a complete child item snapshot', function () {
    [$admin, $product] = auditSeedCatalog();
    Sanctum::actingAs($admin);

    $supplier = Supplier::create([
        'name' => 'Delete Supplier',
        'phone' => '01222222222',
    ]);

    $purchase = AuditLogService::withoutModelEvents(function () use ($admin, $product, $supplier) {
        $purchase = PurchaseHeader::create([
            'supplier_id' => $supplier->id,
            'created_by' => $admin->id,
            'status' => 'draft',
            'total_amount' => 120,
            'purchaseHeader_number' => 'PO-DELETE-001',
        ]);

        PurchaseItem::create([
            'purchase_header_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 40,
            'total_price' => 120,
        ]);

        return $purchase;
    });

    AuditLog::withoutMutationGuard(fn () => AuditLog::query()->delete());

    app(PurchaseHeaderService::class)->deleteDraft($purchase);

    $log = AuditLog::where('action', 'purchase_deleted')->firstOrFail();

    expect(PurchaseHeader::find($purchase->id))->toBeNull()
        ->and(PurchaseItem::where('purchase_header_id', $purchase->id)->exists())->toBeFalse()
        ->and($log->metadata['reference_number'])->toBe('PO-DELETE-001')
        ->and($log->metadata['items_count'])->toBe(1)
        ->and($log->old_values['items'][0]['product_id'])->toBe($product->id)
        ->and((float) $log->old_values['items'][0]['quantity'])->toBe(3.0);
});

it('masks sensitive audit metadata recursively', function () {
    [$admin] = auditSeedCatalog();
    Sanctum::actingAs($admin);

    app(AuditLogService::class)->record(
        module: 'users_roles',
        action: 'role_changed',
        metadata: [
            'password' => 'secret',
            'current_password' => 'old-secret',
            'nested' => [
                'api_key' => 'key-secret',
                'safe_value' => 'visible',
            ],
        ],
    );

    $metadata = AuditLog::where('action', 'role_changed')->firstOrFail()->metadata;

    expect($metadata['password'])->toBe('[hidden]')
        ->and($metadata['current_password'])->toBe('[hidden]')
        ->and($metadata['nested']['api_key'])->toBe('[hidden]')
        ->and($metadata['nested']['safe_value'])->toBe('visible');
});

it('keeps sensitive fields out of changed fields', function () {
    AuditLog::withoutMutationGuard(fn () => AuditLog::query()->delete());

    User::create([
        'name' => 'Sensitive User',
        'email' => 'sensitive.audit@example.com',
        'password' => 'secret-password',
        'role' => 'employee',
    ]);

    $log = AuditLog::where('module', 'users_roles')->where('action', 'created')->firstOrFail();

    expect($log->changed_fields)->not->toContain('password')
        ->and($log->new_values['password'])->toBe('[hidden]');
});

it('records direct role changes as business events instead of generic updates', function () {
    [$admin] = auditSeedCatalog();
    Sanctum::actingAs($admin);

    AuditLog::withoutMutationGuard(fn () => AuditLog::query()->delete());

    $admin->update(['role' => 'employee']);

    expect(AuditLog::where('action', 'role_changed')->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'updated')->where('auditable_type', $admin->getMorphClass())->count())->toBe(0);
});

it('does not emit removed dead disabled events', function () {
    [$admin, $product] = auditSeedCatalog();
    Sanctum::actingAs($admin);

    AuditLog::withoutMutationGuard(fn () => AuditLog::query()->delete());

    $admin->update(['name' => 'Admin Renamed']);
    $product->update(['name' => 'Audit Cable Renamed']);

    expect(AuditLog::where('action', 'user_disabled')->exists())->toBeFalse()
        ->and(AuditLog::where('action', 'product_disabled')->exists())->toBeFalse();
});

it('records maintenance salary and expense business flows without generic duplicates', function () {
    Carbon::setTestNow('2026-08-01 10:00:00');

    [$admin, $product] = auditSeedCatalog();
    Sanctum::actingAs($admin);

    InventoryQuantity::create([
        'product_id' => $product->id,
        'quantity' => 5,
        'cost_price' => 20,
    ]);

    MarkupSetting::create([
        'product_type' => 'accessory',
        'profit_percentage' => 25,
    ]);

    AuditLog::withoutMutationGuard(fn () => AuditLog::query()->delete());

    $ticket = app(MaintenanceTicketService::class)->createTicket([
        'device_type' => 'Phone',
        'brand' => 'Samsung',
        'model' => 'A56',
        'problem_description' => 'Screen issue',
        'received_date' => '2026-08-01',
        'advance_payment' => 0,
    ]);

    $part = app(MaintenancePartService::class)->addPart($ticket, [
        'product_id' => $product->id,
        'quantity' => 1,
    ]);

    app(MaintenancePartService::class)->updatePart($ticket->fresh(), $part, [
        'quantity' => 2,
    ]);

    app(MaintenanceStatusService::class)->transition($ticket->fresh(), 'under_repair');

    expect(AuditLog::where('action', 'maintenance_created')->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'spare_parts_used')->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'spare_parts_updated')->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'maintenance_status_changed')->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'created')->where('auditable_type', (new MaintenanceHeader)->getMorphClass())->count())->toBe(0);

    AuditLog::withoutMutationGuard(fn () => AuditLog::query()->delete());

    $employee = User::create([
        'name' => 'Payroll Employee',
        'email' => 'payroll.audit@example.com',
        'password' => 'password',
        'role' => 'employee',
    ]);

    SalaryAssignment::create([
        'user_id' => $employee->id,
        'base_salary' => 1000,
        'payment_frequency' => 'monthly',
        'created_by' => $admin->id,
    ]);

    AuditLog::withoutMutationGuard(fn () => AuditLog::query()->delete());

    $payment = app(SalaryPaymentService::class)->createPayment([
        'user_id' => $employee->id,
        'notes' => 'August salary',
    ]);

    app(SalaryPaymentService::class)->confirmPayment($payment);

    expect(AuditLog::where('action', 'salary_payment_created')->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'salary_payment_confirmed')->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'created')->where('auditable_type', $payment->getMorphClass())->count())->toBe(0);

    AuditLog::withoutMutationGuard(fn () => AuditLog::query()->delete());

    $paidExpense = AuditLogService::withoutModelEvents(fn () => Expense::create([
        'expense_category' => 'internet',
        'amount' => 150,
        'expense_date' => '2026-08-01',
        'status' => 'pending',
    ]));

    $cancelledExpense = AuditLogService::withoutModelEvents(fn () => Expense::create([
        'expense_category' => 'rent',
        'amount' => 300,
        'expense_date' => '2026-08-01',
        'status' => 'pending',
    ]));

    app(ExpenseService::class)->pay($paidExpense);
    app(ExpenseService::class)->cancel($cancelledExpense);

    expect(AuditLog::where('action', 'expense_paid')->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'expense_cancelled')->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'updated')->where('module', 'expenses')->count())->toBe(0);

    Carbon::setTestNow();
});

it('restores maintenance part stock before deleting a pending ticket and audits it', function () {
    [$admin, $product] = auditSeedCatalog();
    Sanctum::actingAs($admin);

    InventoryQuantity::create([
        'product_id' => $product->id,
        'quantity' => 5,
        'cost_price' => 20,
    ]);

    MarkupSetting::create([
        'product_type' => 'accessory',
        'profit_percentage' => 25,
    ]);

    $ticket = app(MaintenanceTicketService::class)->createTicket([
        'device_type' => 'Phone',
        'brand' => 'Samsung',
        'model' => 'A56',
        'problem_description' => 'Battery issue',
        'received_date' => '2026-08-01',
    ]);

    app(MaintenancePartService::class)->addPart($ticket, [
        'product_id' => $product->id,
        'quantity' => 2,
    ]);

    expect((int) InventoryQuantity::where('product_id', $product->id)->value('quantity'))->toBe(3);

    AuditLog::withoutMutationGuard(fn () => AuditLog::query()->delete());

    app(MaintenanceTicketService::class)->deletePending($ticket);

    expect((int) InventoryQuantity::where('product_id', $product->id)->value('quantity'))->toBe(5)
        ->and(AuditLog::where('action', 'maintenance_deleted')->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'stock_adjusted')->where('module', 'inventory')->exists())->toBeTrue()
        ->and(StockMovement::where('movement_type', 'stock_adjustment')->where('notes', 'Stock restored before maintenance ticket deletion')->where('quantity', 2)->exists())->toBeTrue();
});

it('never rewrites stock movements when maintenance part quantity changes', function () {
    [$admin, $product] = auditSeedCatalog();
    Sanctum::actingAs($admin);

    InventoryQuantity::create([
        'product_id' => $product->id,
        'quantity' => 5,
        'cost_price' => 20,
    ]);

    MarkupSetting::create([
        'product_type' => 'accessory',
        'profit_percentage' => 25,
    ]);

    $ticket = app(MaintenanceTicketService::class)->createTicket([
        'device_type' => 'Phone',
        'brand' => 'Samsung',
        'model' => 'A56',
        'problem_description' => 'Charging issue',
        'received_date' => '2026-08-01',
    ]);

    $part = app(MaintenancePartService::class)->addPart($ticket, [
        'product_id' => $product->id,
        'quantity' => 1,
    ]);

    $originalMovement = StockMovement::where('movement_type', 'repair_usage')->firstOrFail();

    app(MaintenancePartService::class)->updatePart($ticket->fresh(), $part, [
        'quantity' => 3,
    ]);

    expect((float) $originalMovement->fresh()->quantity)->toBe(1.0)
        ->and(StockMovement::where('movement_type', 'stock_adjustment')->where('notes', 'Maintenance spare part quantity correction')->where('movement', 'out')->where('quantity', 2)->exists())->toBeTrue()
        ->and(fn () => $originalMovement->update(['quantity' => 99]))->toThrow(RuntimeException::class)
        ->and(fn () => StockMovement::query()->whereKey($originalMovement->id)->update(['quantity' => 99]))->toThrow(RuntimeException::class);
});

it('records explicit inventory cost changes during purchase receiving', function () {
    [$admin, $product] = auditSeedCatalog();
    Sanctum::actingAs($admin);

    InventoryQuantity::create([
        'product_id' => $product->id,
        'quantity' => 2,
        'cost_price' => 10,
    ]);

    $supplier = Supplier::create([
        'name' => 'Cost Supplier',
        'phone' => '01333333333',
    ]);

    $purchase = AuditLogService::withoutModelEvents(function () use ($admin, $product, $supplier) {
        $purchase = PurchaseHeader::create([
            'supplier_id' => $supplier->id,
            'created_by' => $admin->id,
            'status' => 'draft',
            'total_amount' => 40,
            'purchaseHeader_number' => 'PO-COST-001',
        ]);

        PurchaseItem::create([
            'purchase_header_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 20,
            'total_price' => 40,
        ]);

        return $purchase;
    });

    AuditLog::withoutMutationGuard(fn () => AuditLog::query()->delete());

    app(PurchaseHeaderService::class)->complete($purchase);

    $costLog = AuditLog::where('action', 'inventory_cost_changed')->firstOrFail();

    expect($costLog->metadata['product_id'])->toBe($product->id)
        ->and((float) $costLog->metadata['old_cost'])->toBe(10.0)
        ->and((float) $costLog->metadata['new_cost'])->toBe(15.0)
        ->and($costLog->metadata['reason'])->toBe('purchase');
});

it('summarizes product imports without generic product create logs', function () {
    [$admin] = auditSeedCatalog();
    Sanctum::actingAs($admin);

    AuditLog::withoutMutationGuard(fn () => AuditLog::query()->delete());

    $csv = implode("\n", [
        'name,type,category_name,brand_name,min_stock',
        'Imported Charger,accessory,Chargers,Brand One,3',
    ]);

    $this->post('/api/products/import', [
        'file' => UploadedFile::fake()->createWithContent('products.csv', $csv),
    ])->assertOk();

    expect(AuditLog::where('action', 'products_imported')->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'created')->where('auditable_type', (new Product)->getMorphClass())->count())->toBe(0);
});

it('keeps audit logs admin only', function () {
    [$admin] = auditSeedCatalog();
    $employee = User::create([
        'name' => 'Employee',
        'email' => 'employee.audit@example.com',
        'password' => 'password',
        'role' => 'employee',
    ]);

    Sanctum::actingAs($employee);
    $this->getJson('/api/admin/audit-logs')->assertForbidden();

    Sanctum::actingAs($admin);
    $this->getJson('/api/admin/audit-logs')->assertOk();
});

it('prevents direct audit log updates and deletes', function () {
    [$admin] = auditSeedCatalog();
    Sanctum::actingAs($admin);

    $log = app(AuditLogService::class)->record(
        module: 'auth',
        action: 'login_success',
        auditable: $admin,
    );

    expect(fn () => $log->update(['action' => 'tampered']))->toThrow(RuntimeException::class)
        ->and(fn () => $log->delete())->toThrow(RuntimeException::class)
        ->and(fn () => AuditLog::query()->whereKey($log->id)->update(['action' => 'tampered']))->toThrow(RuntimeException::class)
        ->and(fn () => AuditLog::query()->whereKey($log->id)->delete())->toThrow(RuntimeException::class);
});

function auditSeedCatalog(): array
{
    return AuditLogService::withoutModelEvents(function () {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin.audit@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $category = Category::create([
            'name' => 'Audit Category',
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Audit Cable',
            'type' => 'accessory',
            'min_stock' => 1,
        ]);

        return [$admin, $product];
    });
}
