<?php

use App\Console\Commands\GenerateDailyReportSummaries;
use App\Models\Expense;
use App\Models\User;
use App\Services\Expense\ExpenseService;
use App\Services\Reports\ExpenseReportService;
use App\Services\Reports\InventoryReportService;
use App\Services\Reports\MaintenanceReportService;
use App\Services\Reports\ProfitLossService;
use App\Services\Reports\PurchaseReportService;
use App\Services\Reports\SalaryReportService;
use App\Services\Reports\SalesReportService;
use App\Services\PurchaseReturn\PurchaseReturnService;
use App\Services\SalesReturn\SalesReturnService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Carbon::setTestNow('2026-07-26 10:00:00');
    resetReportingTables();
    createReportingTables();
});

afterEach(function () {
    Carbon::setTestNow();
});

it('calculates sales net revenue with invoice discounts and returns exactly once', function () {
    seedSalesScenario();

    $summary = app(SalesReportService::class)->getSummary(
        Carbon::parse('2026-07-10')->startOfDay(),
        Carbon::parse('2026-07-10')->endOfDay(),
    );

    expect($summary['total_revenue'])->toBe(300.0)
        ->and($summary['total_discount'])->toBe(30.0)
        ->and($summary['total_returns'])->toBe(100.0)
        ->and($summary['net_revenue'])->toBe(170.0)
        ->and($summary['total_cogs'])->toBe(100.0)
        ->and($summary['gross_profit'])->toBe(70.0)
        ->and($summary['total_quantity_sold'])->toBe(2.0);
});

it('backfills historical sales return unit costs from original sale items', function () {
    seedSalesScenario(returnUnitCost: 0);

    $migration = require database_path('migrations/2026_07_26_000003_backfill_sales_return_item_unit_cost.php');
    $migration->up();

    $returnItem = DB::table('sales_return_items')->where('id', 1)->first();
    $invalidZeroCostReturns = DB::table('sales_return_items')
        ->join('sales_items', 'sales_items.id', '=', 'sales_return_items.sales_item_id')
        ->where('sales_return_items.unit_cost', 0)
        ->where('sales_items.unit_cost', '!=', 0)
        ->count();

    $summary = app(SalesReportService::class)->getSummary(
        Carbon::parse('2026-07-10')->startOfDay(),
        Carbon::parse('2026-07-10')->endOfDay(),
    );

    expect((float) $returnItem->unit_cost)->toBe(40.0)
        ->and($invalidZeroCostReturns)->toBe(0)
        ->and($summary['return_cogs'])->toBe(40.0)
        ->and($summary['total_cogs'])->toBe(100.0)
        ->and($summary['gross_profit'])->toBe(70.0);
});

it('caps sales return refunds to the discounted net unit selling price', function () {
    seedSalesScenario(includeReturn: false);
    seedInventoryQuantityForCable();
    actingAsAdmin();

    $return = app(SalesReturnService::class)->processReturn([
        'sales_header_id' => 1,
        'customer_id' => 1,
        'return_date' => '2026-07-10',
        'items' => [
            [
                'sales_item_id' => 2,
                'quantity' => 1,
                'unit_refund_amount' => 100,
            ],
        ],
    ]);

    $returnItem = DB::table('sales_return_items')
        ->where('sales_return_header_id', $return->id)
        ->first();

    $summary = app(SalesReportService::class)->getSummary(
        Carbon::parse('2026-07-10')->startOfDay(),
        Carbon::parse('2026-07-10')->endOfDay(),
    );

    expect((float) $returnItem->unit_refund_amount)->toBe(90.0)
        ->and((float) $returnItem->total_refund)->toBe(90.0)
        ->and($summary['total_discount'])->toBe(30.0)
        ->and($summary['total_returns'])->toBe(90.0)
        ->and($summary['net_revenue'])->toBe(180.0)
        ->and($summary['total_cogs'])->toBe(100.0)
        ->and($summary['gross_profit'])->toBe(80.0);
});

it('calculates sales return refunds server side when there is no invoice discount', function () {
    seedSalesScenario(includeReturn: false, discountAmount: 0);
    seedInventoryQuantityForCable();
    actingAsAdmin();

    $return = app(SalesReturnService::class)->processReturn([
        'sales_header_id' => 1,
        'customer_id' => 1,
        'return_date' => '2026-07-10',
        'items' => [
            [
                'sales_item_id' => 2,
                'quantity' => 1,
                'unit_refund_amount' => 0,
            ],
        ],
    ]);

    $returnItem = DB::table('sales_return_items')
        ->where('sales_return_header_id', $return->id)
        ->first();

    $summary = app(SalesReportService::class)->getSummary(
        Carbon::parse('2026-07-10')->startOfDay(),
        Carbon::parse('2026-07-10')->endOfDay(),
    );

    expect((float) $returnItem->unit_refund_amount)->toBe(100.0)
        ->and((float) $returnItem->total_refund)->toBe(100.0)
        ->and($summary['total_discount'])->toBe(0.0)
        ->and($summary['total_returns'])->toBe(100.0)
        ->and($summary['net_revenue'])->toBe(200.0);
});

it('handles partial full and multiple sales returns without exceeding discounted paid value', function () {
    seedSalesScenario(includeReturn: false);
    seedInventoryQuantityForCable();
    actingAsAdmin();

    app(SalesReturnService::class)->processReturn([
        'sales_header_id' => 1,
        'customer_id' => 1,
        'return_date' => '2026-07-10',
        'items' => [
            [
                'sales_item_id' => 2,
                'quantity' => 1,
                'unit_refund_amount' => 100,
            ],
        ],
    ]);

    app(SalesReturnService::class)->processReturn([
        'sales_header_id' => 1,
        'customer_id' => 1,
        'return_date' => '2026-07-10',
        'items' => [
            [
                'sales_item_id' => 2,
                'quantity' => 1,
                'unit_refund_amount' => 100,
            ],
        ],
    ]);

    expect(fn () => app(SalesReturnService::class)->processReturn([
        'sales_header_id' => 1,
        'customer_id' => 1,
        'return_date' => '2026-07-10',
        'items' => [
            [
                'sales_item_id' => 2,
                'quantity' => 1,
                'unit_refund_amount' => 100,
            ],
        ],
    ]))->toThrow(RuntimeException::class, 'Return quantity exceeds remaining returnable quantity.');

    $summary = app(SalesReportService::class)->getSummary(
        Carbon::parse('2026-07-10')->startOfDay(),
        Carbon::parse('2026-07-10')->endOfDay(),
    );

    expect($summary['total_returns'])->toBe(180.0)
        ->and($summary['net_revenue'])->toBe(90.0)
        ->and($summary['total_cogs'])->toBe(60.0)
        ->and($summary['gross_profit'])->toBe(30.0)
        ->and($summary['total_quantity_sold'])->toBe(1.0);
});

it('applies product category brand customer and user filters equally to sales returns', function () {
    seedSalesScenario();

    $summary = app(SalesReportService::class)->getSummary(
        Carbon::parse('2026-07-10')->startOfDay(),
        Carbon::parse('2026-07-10')->endOfDay(),
        categoryId: 2,
        brandId: 2,
        productId: 2,
        customerId: 1,
        userId: 1,
    );

    expect($summary['total_revenue'])->toBe(200.0)
        ->and($summary['total_discount'])->toBe(20.0)
        ->and($summary['total_returns'])->toBe(100.0)
        ->and($summary['net_revenue'])->toBe(80.0)
        ->and($summary['total_cogs'])->toBe(40.0)
        ->and($summary['gross_profit'])->toBe(40.0);
});

it('uses corrected sales revenue in profit and loss', function () {
    seedSalesScenario();
    seedMaintenanceScenario();
    seedExpenseScenario();
    seedSalaryScenario();

    $report = app(ProfitLossService::class)->generate([
        'date_from' => '2026-07-10',
        'date_to' => '2026-07-10',
    ]);

    expect($report['profit_formula']['sales_revenue'])->toBe(170.0)
        ->and($report['profit_formula']['minus_cogs'])->toBe(100.0)
        ->and($report['profit_formula']['minus_expenses'])->toBe(20.0)
        ->and($report['profit_formula']['minus_salaries'])->toBe(50.0)
        ->and($report['profit_formula']['plus_maintenance_profit'])->toBe(40.0)
        ->and($report['net_profit'])->toBe(40.0);
});

it('generates daily summaries from the same report service calculations', function () {
    seedSalesScenario();
    seedPurchaseScenario();
    seedMaintenanceScenario();
    seedExpenseScenario();
    seedSalaryScenario();

    Artisan::call(GenerateDailyReportSummaries::class, ['--date' => '2026-07-10']);

    $daily = DB::table('daily_report_summaries')->where('date', '2026-07-10')->first();

    expect((float) $daily->sales_revenue)->toBe(300.0)
        ->and((float) $daily->sales_discount)->toBe(30.0)
        ->and((float) $daily->sales_net_revenue)->toBe(170.0)
        ->and((float) $daily->sales_cogs)->toBe(100.0)
        ->and((float) $daily->sales_profit)->toBe(70.0)
        ->and((float) $daily->purchase_total)->toBe(500.0)
        ->and((float) $daily->purchase_return_refund)->toBe(200.0)
        ->and((float) $daily->maintenance_profit)->toBe(40.0)
        ->and((float) $daily->expense_paid)->toBe(20.0)
        ->and((float) $daily->salary_confirmed)->toBe(50.0);
});

it('nets purchase returns in purchase summaries and breakdowns', function () {
    seedPurchaseScenario();

    $service = app(PurchaseReportService::class);
    $summary = $service->getSummary(
        Carbon::parse('2026-07-10')->startOfDay(),
        Carbon::parse('2026-07-10')->endOfDay(),
    );
    $byProduct = $service->getByProduct(
        Carbon::parse('2026-07-10')->startOfDay(),
        Carbon::parse('2026-07-10')->endOfDay(),
    );

    expect($summary['total_purchase_amount'])->toBe(500.0)
        ->and($summary['total_return_refund'])->toBe(200.0)
        ->and($summary['net_purchase_amount'])->toBe(300.0)
        ->and($byProduct[0]['total_amount'])->toBe(300.0)
        ->and($byProduct[0]['total_quantity'])->toBe(3.0);
});

it('accepts partial full and multiple purchase returns at original unit cost', function () {
    seedPurchaseScenario(includeReturn: false);
    seedInventoryQuantityForCable(quantity: 5, costPrice: 100);
    actingAsAdmin();

    app(PurchaseReturnService::class)->processReturn([
        'purchase_header_id' => 1,
        'supplier_id' => 1,
        'return_date' => '2026-07-10',
        'items' => [
            [
                'purchase_item_id' => 1,
                'quantity' => 2,
                'unit_refund_amount' => 100,
            ],
        ],
    ]);

    app(PurchaseReturnService::class)->processReturn([
        'purchase_header_id' => 1,
        'supplier_id' => 1,
        'return_date' => '2026-07-10',
        'items' => [
            [
                'purchase_item_id' => 1,
                'quantity' => 3,
                'unit_refund_amount' => 100,
            ],
        ],
    ]);

    $summary = app(PurchaseReportService::class)->getSummary(
        Carbon::parse('2026-07-10')->startOfDay(),
        Carbon::parse('2026-07-10')->endOfDay(),
    );

    expect($summary['total_purchase_amount'])->toBe(500.0)
        ->and($summary['total_return_refund'])->toBe(500.0)
        ->and($summary['net_purchase_amount'])->toBe(0.0)
        ->and($summary['total_returned_quantity'])->toBe(5.0);
});

it('allows purchase returns for completed purchases', function () {
    seedPurchaseScenario(includeReturn: false);
    seedInventoryQuantityForCable(quantity: 5, costPrice: 100);
    actingAsAdmin();

    $return = app(PurchaseReturnService::class)->processReturn([
        'purchase_header_id' => 1,
        'supplier_id' => 1,
        'return_date' => '2026-07-10',
        'items' => [
            [
                'purchase_item_id' => 1,
                'quantity' => 1,
                'unit_refund_amount' => 100,
            ],
        ],
    ]);

    expect($return->total_refund_amount)->toBe('100.00')
        ->and(DB::table('purchase_return_headers')->count())->toBe(1)
        ->and((float) DB::table('inventory_quantities')->where('product_id', 2)->value('quantity'))->toBe(4.0);
});

it('rejects purchase returns for draft purchases', function () {
    seedPurchaseScenario(includeReturn: false, status: 'draft', completedAt: null);
    seedInventoryQuantityForCable(quantity: 5, costPrice: 100);
    actingAsAdmin();

    expect(fn () => app(PurchaseReturnService::class)->processReturn([
        'purchase_header_id' => 1,
        'supplier_id' => 1,
        'return_date' => '2026-07-10',
        'items' => [
            [
                'purchase_item_id' => 1,
                'quantity' => 1,
                'unit_refund_amount' => 100,
            ],
        ],
    ]))->toThrow(DomainException::class, 'Only completed purchases can be returned.');

    expect(DB::table('purchase_return_headers')->count())->toBe(0)
        ->and((float) DB::table('inventory_quantities')->where('product_id', 2)->value('quantity'))->toBe(5.0);
});

it('returns a business validation response for draft purchase returns', function () {
    seedPurchaseScenario(includeReturn: false, status: 'draft', completedAt: null);
    seedInventoryQuantityForCable(quantity: 5, costPrice: 100);
    actingAsAdmin();

    $this->postJson('/api/purchase-returns', [
        'purchase_header_id' => 1,
        'supplier_id' => 1,
        'return_date' => '2026-07-10',
        'items' => [
            [
                'purchase_item_id' => 1,
                'quantity' => 1,
                'unit_refund_amount' => 100,
            ],
        ],
    ])
        ->assertStatus(422)
        ->assertJson([
            'success' => false,
            'status' => 422,
            'message' => 'Only completed purchases can be returned.',
        ]);

    expect(DB::table('purchase_return_headers')->count())->toBe(0);
});

it('rejects purchase returns for cancelled purchases', function () {
    seedPurchaseScenario(includeReturn: false, status: 'cancelled', completedAt: null);
    seedInventoryQuantityForCable(quantity: 5, costPrice: 100);
    actingAsAdmin();

    expect(fn () => app(PurchaseReturnService::class)->processReturn([
        'purchase_header_id' => 1,
        'supplier_id' => 1,
        'return_date' => '2026-07-10',
        'items' => [
            [
                'purchase_item_id' => 1,
                'quantity' => 1,
                'unit_refund_amount' => 100,
            ],
        ],
    ]))->toThrow(DomainException::class, 'Only completed purchases can be returned.');

    expect(DB::table('purchase_return_headers')->count())->toBe(0)
        ->and((float) DB::table('inventory_quantities')->where('product_id', 2)->value('quantity'))->toBe(5.0);
});

it('reports completed used-device purchases in purchase totals and breakdowns', function () {
    seedPurchaseScenario(includeReturn: false);
    seedUsedDevicePurchaseScenario();

    $service = app(PurchaseReportService::class);
    $dateFrom = Carbon::parse('2026-07-10')->startOfDay();
    $dateTo = Carbon::parse('2026-07-10')->endOfDay();

    $summary = $service->getSummary($dateFrom, $dateTo);
    $byProduct = collect($service->getByProduct($dateFrom, $dateTo))->keyBy('product_id');
    $byPeriod = $service->getByPeriod($dateFrom, $dateTo);
    $bySupplier = collect($service->getBySupplier($dateFrom, $dateTo))->keyBy('supplier_name');

    expect($summary['total_purchase_amount'])->toBe(1700.0)
        ->and($summary['net_purchase_amount'])->toBe(1700.0)
        ->and($summary['total_quantity_purchased'])->toBe(7.0)
        ->and($summary['transaction_count'])->toBe(2)
        ->and($byProduct[1]['total_amount'])->toBe(1200.0)
        ->and($byProduct[1]['total_quantity'])->toBe(2.0)
        ->and($byPeriod[0]['total_amount'])->toBe(1700.0)
        ->and($byPeriod[0]['transaction_count'])->toBe(2)
        ->and($bySupplier['Used Device Purchases']['total_amount'])->toBe(1200.0)
        ->and($service->getSummary($dateFrom, $dateTo, supplierId: 1)['total_purchase_amount'])->toBe(500.0);
});

it('rejects purchase return refunds above original purchase value', function () {
    seedPurchaseScenario(includeReturn: false);
    seedInventoryQuantityForCable(quantity: 5, costPrice: 100);
    actingAsAdmin();

    expect(fn () => app(PurchaseReturnService::class)->processReturn([
        'purchase_header_id' => 1,
        'supplier_id' => 1,
        'return_date' => '2026-07-10',
        'items' => [
            [
                'purchase_item_id' => 1,
                'quantity' => 1,
                'unit_refund_amount' => 1000,
            ],
        ],
    ]))->toThrow(RuntimeException::class, 'Refund amount exceeds original purchase unit cost.');

    $summary = app(PurchaseReportService::class)->getSummary(
        Carbon::parse('2026-07-10')->startOfDay(),
        Carbon::parse('2026-07-10')->endOfDay(),
    );

    expect($summary['total_return_refund'])->toBe(0.0)
        ->and($summary['net_purchase_amount'])->toBe(500.0);
});

it('updates expenses safely and reports cash and accrual bases correctly', function () {
    seedExpenseScenario(includePending: true);

    $pending = Expense::where('status', 'pending')->first();
    $paid = app(ExpenseService::class)->pay($pending);

    expect($paid->status)->toBe('paid')
        ->and($paid->payment_date->toDateString())->toBe('2026-07-26');

    $service = app(ExpenseReportService::class);
    $accrual = $service->getSummary(
        Carbon::parse('2026-07-10')->startOfDay(),
        Carbon::parse('2026-07-10')->endOfDay(),
    );
    $cash = $service->getSummary(
        Carbon::parse('2026-07-10')->startOfDay(),
        Carbon::parse('2026-07-26')->endOfDay(),
        basis: 'cash',
    );

    expect($accrual['total_amount'])->toBe(50.0)
        ->and($cash['total_amount'])->toBe(50.0)
        ->and($cash['pending_amount'])->toBe(0.0);
});

it('uses repaired_at for repaired maintenance used parts without delivery date', function () {
    seedMaintenanceScenario(repairedOnly: true);

    DB::table('maintenance_headers')->where('id', 1)->update(['updated_at' => '2026-07-20 12:00:00']);

    $details = app(MaintenanceReportService::class)->getUsedPartsDetails(
        Carbon::parse('2026-07-10')->startOfDay(),
        Carbon::parse('2026-07-10')->endOfDay(),
        'repaired',
    );

    expect($details)->toHaveCount(1)
        ->and($details[0]['total_quantity'])->toBe(1.0)
        ->and($details[0]['total_cost'])->toBe(20.0);
});

it('reports current salary statuses supported by the business logic', function () {
    seedSalaryScenario(includeDraftAndCancelled: true);

    $summary = app(SalaryReportService::class)->getSummary(
        Carbon::parse('2026-07-10')->startOfDay(),
        Carbon::parse('2026-07-10')->endOfDay(),
    );

    expect($summary['confirmed_amount'])->toBe(50.0)
        ->and($summary['draft_amount'])->toBe(30.0)
        ->and($summary['cancelled_amount'])->toBe(10.0)
        ->and($summary['paid_amount'])->toBe(0.0);
});

it('values serialized and bulk inventory from the correct cost sources', function () {
    seedInventoryScenario();

    $report = app(InventoryReportService::class)->getStockValue();
    $summary = app(InventoryReportService::class)->getStockSummary();

    expect($report['total_stock_value'])->toBe(270.0)
        ->and($report['mobile_devices']['value'])->toBe(220.0)
        ->and($report['bulk_products']['value'])->toBe(50.0)
        ->and($summary['products'][0]['stock_value'])->toBe(220.0)
        ->and($summary['products'][1]['stock_value'])->toBe(50.0);
});

it('blocks employees from sensitive report endpoints', function () {
    $employee = User::create([
        'name' => 'Employee',
        'email' => 'employee@example.com',
        'password' => 'secret',
        'role' => 'employee',
    ]);

    Sanctum::actingAs($employee);

    $this->getJson('/api/reports/inventory')
        ->assertForbidden();
});

function resetReportingTables(): void
{
    foreach ([
        'daily_report_summaries',
        'stock_movements',
        'inventory_items',
        'inventory_quantities',
        'salary_payments',
        'expenses',
        'maintenance_used_parts',
        'maintenance_headers',
        'purchase_return_items',
        'purchase_return_headers',
        'used_device_purchase_items',
        'used_device_purchase_headers',
        'purchase_items',
        'purchase_headers',
        'sales_return_items',
        'sales_return_headers',
        'sales_items',
        'sales_headers',
        'products',
        'brands',
        'categories',
        'customers',
        'suppliers',
        'users',
    ] as $table) {
        Schema::dropIfExists($table);
    }
}

function createReportingTables(): void
{
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->string('role')->default('employee');
        $table->timestamps();
    });

    Schema::create('customers', fn (Blueprint $table) => $table->id());
    Schema::create('suppliers', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });
    Schema::create('categories', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });
    Schema::create('brands', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });
    Schema::create('products', function (Blueprint $table) {
        $table->id();
        $table->unsignedInteger('category_id')->nullable();
        $table->unsignedInteger('brand_id')->nullable();
        $table->string('name');
        $table->string('type');
        $table->integer('min_stock')->default(0);
    });

    Schema::create('sales_headers', function (Blueprint $table) {
        $table->id();
        $table->string('invoice_number')->nullable();
        $table->unsignedInteger('customer_id')->nullable();
        $table->decimal('subtotal', 12, 2)->default(0);
        $table->decimal('discount_amount', 12, 2)->default(0);
        $table->decimal('total_amount', 12, 2)->default(0);
        $table->unsignedInteger('created_by')->nullable();
        $table->timestamps();
    });
    Schema::create('sales_items', function (Blueprint $table) {
        $table->id();
        $table->unsignedInteger('sales_header_id');
        $table->unsignedInteger('product_id');
        $table->unsignedInteger('inventory_item_id')->nullable();
        $table->decimal('quantity', 10, 2);
        $table->decimal('unit_price', 12, 2)->default(0);
        $table->decimal('unit_cost', 12, 2)->nullable();
        $table->decimal('total_price', 12, 2);
        $table->timestamps();
    });
    Schema::create('sales_return_headers', function (Blueprint $table) {
        $table->id();
        $table->unsignedInteger('sales_header_id')->nullable();
        $table->string('return_number')->nullable();
        $table->unsignedInteger('customer_id')->nullable();
        $table->unsignedInteger('user_id')->nullable();
        $table->decimal('total_refund_amount', 12, 2)->default(0);
        $table->text('reason')->nullable();
        $table->date('return_date');
        $table->timestamps();
    });
    Schema::create('sales_return_items', function (Blueprint $table) {
        $table->id();
        $table->unsignedInteger('sales_return_header_id');
        $table->unsignedInteger('sales_item_id')->nullable();
        $table->unsignedInteger('product_id');
        $table->unsignedInteger('inventory_item_id')->nullable();
        $table->decimal('quantity', 10, 2);
        $table->decimal('unit_refund_amount', 12, 2)->default(0);
        $table->decimal('unit_cost', 12, 2)->nullable();
        $table->decimal('total_refund', 12, 2);
        $table->timestamps();
    });

    Schema::create('purchase_headers', function (Blueprint $table) {
        $table->id();
        $table->unsignedInteger('supplier_id');
        $table->string('status');
        $table->unsignedInteger('created_by')->nullable();
        $table->decimal('total_amount', 12, 2)->default(0);
        $table->timestamp('completed_at')->nullable();
        $table->timestamps();
    });
    Schema::create('purchase_items', function (Blueprint $table) {
        $table->id();
        $table->unsignedInteger('purchase_header_id');
        $table->unsignedInteger('product_id');
        $table->decimal('quantity', 10, 2);
        $table->decimal('unit_price', 12, 2)->default(0);
        $table->decimal('total_price', 12, 2);
        $table->timestamps();
    });
    Schema::create('purchase_return_headers', function (Blueprint $table) {
        $table->id();
        $table->unsignedInteger('purchase_header_id')->nullable();
        $table->string('return_number')->nullable();
        $table->unsignedInteger('supplier_id')->nullable();
        $table->unsignedInteger('user_id')->nullable();
        $table->decimal('total_refund_amount', 12, 2)->default(0);
        $table->text('reason')->nullable();
        $table->date('return_date');
        $table->timestamps();
    });
    Schema::create('purchase_return_items', function (Blueprint $table) {
        $table->id();
        $table->unsignedInteger('purchase_return_header_id');
        $table->unsignedInteger('purchase_item_id')->nullable();
        $table->unsignedInteger('product_id');
        $table->unsignedInteger('inventory_item_id')->nullable();
        $table->decimal('quantity', 10, 2);
        $table->decimal('unit_refund_amount', 12, 2)->default(0);
        $table->decimal('unit_cost', 12, 2)->default(0);
        $table->decimal('total_refund', 12, 2);
        $table->timestamps();
    });

    Schema::create('used_device_purchase_headers', function (Blueprint $table) {
        $table->id();
        $table->string('purchase_number')->nullable();
        $table->unsignedInteger('customer_id')->nullable();
        $table->string('status');
        $table->decimal('total_amount', 12, 2)->default(0);
        $table->unsignedInteger('created_by')->nullable();
        $table->timestamp('completed_at')->nullable();
        $table->timestamps();
    });
    Schema::create('used_device_purchase_items', function (Blueprint $table) {
        $table->id();
        $table->unsignedInteger('used_device_purchase_header_id');
        $table->unsignedInteger('product_id');
        $table->decimal('quantity', 10, 2);
        $table->decimal('unit_price', 12, 2)->default(0);
        $table->decimal('total_price', 12, 2);
        $table->timestamps();
    });

    Schema::create('maintenance_headers', function (Blueprint $table) {
        $table->id();
        $table->string('status');
        $table->unsignedInteger('customer_id')->nullable();
        $table->date('received_date');
        $table->date('delivery_date')->nullable();
        $table->timestamp('repaired_at')->nullable();
        $table->decimal('total_cost', 12, 2)->default(0);
        $table->decimal('advance_payment', 12, 2)->default(0);
        $table->timestamps();
    });
    Schema::create('maintenance_used_parts', function (Blueprint $table) {
        $table->id();
        $table->unsignedInteger('maintenance_header_id');
        $table->unsignedInteger('product_id');
        $table->decimal('quantity', 10, 2);
        $table->decimal('cost_price', 12, 2);
        $table->decimal('total_price', 12, 2);
    });

    Schema::create('expenses', function (Blueprint $table) {
        $table->id();
        $table->string('expense_category');
        $table->decimal('amount', 12, 2);
        $table->date('expense_date');
        $table->date('payment_date')->nullable();
        $table->string('status')->default('pending');
        $table->text('notes')->nullable();
        $table->timestamps();
    });

    Schema::create('salary_payments', function (Blueprint $table) {
        $table->id();
        $table->unsignedInteger('user_id');
        $table->decimal('total_amount', 12, 2);
        $table->date('payment_date')->nullable();
        $table->date('period_start')->nullable();
        $table->date('period_end')->nullable();
        $table->string('status');
        $table->timestamps();
    });

    Schema::create('inventory_items', function (Blueprint $table) {
        $table->id();
        $table->unsignedInteger('product_id');
        $table->string('status');
        $table->decimal('cost_price', 12, 2);
        $table->timestamps();
    });
    Schema::create('inventory_quantities', function (Blueprint $table) {
        $table->id();
        $table->unsignedInteger('product_id');
        $table->decimal('quantity', 10, 2);
        $table->decimal('cost_price', 12, 2);
        $table->timestamps();
    });
    Schema::create('stock_movements', function (Blueprint $table) {
        $table->id();
        $table->unsignedInteger('product_id')->nullable();
        $table->unsignedInteger('inventory_item_id')->nullable();
        $table->string('movement_type');
        $table->string('movement');
        $table->decimal('quantity', 10, 2);
        $table->decimal('unit_cost', 12, 2);
        $table->string('reference_type')->nullable();
        $table->unsignedInteger('reference_id')->nullable();
        $table->text('notes')->nullable();
        $table->unsignedInteger('created_by')->nullable();
        $table->timestamps();
    });

    Schema::create('daily_report_summaries', function (Blueprint $table) {
        $table->id();
        $table->date('date')->unique();
        foreach ([
            'sales_revenue', 'sales_discount', 'sales_net_revenue', 'sales_cogs', 'sales_profit',
            'sales_return_refund', 'sales_return_cogs', 'purchase_total', 'purchase_return_refund',
            'maintenance_labor_revenue', 'maintenance_parts_revenue', 'maintenance_parts_cost',
            'maintenance_total_revenue', 'maintenance_profit', 'expense_total', 'expense_paid',
            'expense_pending', 'salary_total', 'salary_confirmed',
        ] as $column) {
            $table->decimal($column, 15, 2)->default(0);
        }
        $table->unsignedInteger('sales_invoice_count')->default(0);
        $table->unsignedInteger('purchase_invoice_count')->default(0);
        $table->unsignedInteger('maintenance_ticket_count')->default(0);
        $table->unsignedInteger('maintenance_delivered_count')->default(0);
        $table->timestamps();
    });
}

function seedBaseCatalog(): void
{
    DB::table('users')->insertOrIgnore(['id' => 1, 'name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'secret', 'role' => 'admin']);
    DB::table('customers')->insertOrIgnore(['id' => 1]);
    DB::table('suppliers')->insertOrIgnore(['id' => 1, 'name' => 'Supplier']);
    DB::table('categories')->insertOrIgnore([
        ['id' => 1, 'name' => 'Phones'],
        ['id' => 2, 'name' => 'Accessories'],
    ]);
    DB::table('brands')->insertOrIgnore([
        ['id' => 1, 'name' => 'Brand A'],
        ['id' => 2, 'name' => 'Brand B'],
    ]);
    DB::table('products')->insertOrIgnore([
        ['id' => 1, 'category_id' => 1, 'brand_id' => 1, 'name' => 'Phone', 'type' => 'mobile', 'min_stock' => 1],
        ['id' => 2, 'category_id' => 2, 'brand_id' => 2, 'name' => 'Cable', 'type' => 'accessory', 'min_stock' => 2],
    ]);
}

function seedSalesScenario(bool $includeReturn = true, float $returnUnitCost = 40, float $discountAmount = 30): void
{
    seedBaseCatalog();
    DB::table('sales_headers')->insert([
        'id' => 1,
        'invoice_number' => 'SAL-000001',
        'customer_id' => 1,
        'subtotal' => 300,
        'discount_amount' => $discountAmount,
        'total_amount' => 300 - $discountAmount,
        'created_by' => 1,
        'created_at' => '2026-07-10 09:00:00',
        'updated_at' => '2026-07-10 09:00:00',
    ]);
    DB::table('sales_items')->insert([
        ['id' => 1, 'sales_header_id' => 1, 'product_id' => 1, 'quantity' => 1, 'unit_price' => 100, 'unit_cost' => 60, 'total_price' => 100, 'created_at' => '2026-07-10 09:00:00', 'updated_at' => '2026-07-10 09:00:00'],
        ['id' => 2, 'sales_header_id' => 1, 'product_id' => 2, 'quantity' => 2, 'unit_price' => 100, 'unit_cost' => 40, 'total_price' => 200, 'created_at' => '2026-07-10 09:00:00', 'updated_at' => '2026-07-10 09:00:00'],
    ]);

    if ($includeReturn) {
        DB::table('sales_return_headers')->insert([
            'id' => 1,
            'sales_header_id' => 1,
            'return_number' => 'SR-000001',
            'customer_id' => 1,
            'user_id' => 1,
            'total_refund_amount' => 100,
            'return_date' => '2026-07-10',
            'created_at' => '2026-07-10 10:00:00',
            'updated_at' => '2026-07-10 10:00:00',
        ]);
        DB::table('sales_return_items')->insert([
            'id' => 1,
            'sales_return_header_id' => 1,
            'sales_item_id' => 2,
            'product_id' => 2,
            'quantity' => 1,
            'unit_refund_amount' => 100,
            'unit_cost' => $returnUnitCost,
            'total_refund' => 100,
            'created_at' => '2026-07-10 10:00:00',
            'updated_at' => '2026-07-10 10:00:00',
        ]);
    }
}

function seedPurchaseScenario(bool $includeReturn = true, string $status = 'completed', ?string $completedAt = '2026-07-10 08:00:00'): void
{
    seedBaseCatalog();
    DB::table('purchase_headers')->insert([
        'id' => 1,
        'supplier_id' => 1,
        'status' => $status,
        'created_by' => 1,
        'total_amount' => 500,
        'completed_at' => $completedAt,
        'created_at' => '2026-07-10 08:00:00',
        'updated_at' => '2026-07-10 08:00:00',
    ]);
    DB::table('purchase_items')->insert([
        'id' => 1,
        'purchase_header_id' => 1,
        'product_id' => 2,
        'quantity' => 5,
        'unit_price' => 100,
        'total_price' => 500,
        'created_at' => '2026-07-10 08:00:00',
        'updated_at' => '2026-07-10 08:00:00',
    ]);

    if ($includeReturn) {
        DB::table('purchase_return_headers')->insert([
            'id' => 1,
            'purchase_header_id' => 1,
            'return_number' => 'PR-000001',
            'supplier_id' => 1,
            'user_id' => 1,
            'total_refund_amount' => 200,
            'return_date' => '2026-07-10',
            'created_at' => '2026-07-10 10:00:00',
            'updated_at' => '2026-07-10 10:00:00',
        ]);
        DB::table('purchase_return_items')->insert([
            'id' => 1,
            'purchase_return_header_id' => 1,
            'purchase_item_id' => 1,
            'product_id' => 2,
            'quantity' => 2,
            'unit_refund_amount' => 100,
            'unit_cost' => 100,
            'total_refund' => 200,
            'created_at' => '2026-07-10 10:00:00',
            'updated_at' => '2026-07-10 10:00:00',
        ]);
    }
}

function seedUsedDevicePurchaseScenario(): void
{
    seedBaseCatalog();
    DB::table('used_device_purchase_headers')->insert([
        'id' => 1,
        'purchase_number' => 'UP-000001',
        'customer_id' => 1,
        'status' => 'completed',
        'total_amount' => 1200,
        'created_by' => 1,
        'completed_at' => '2026-07-10 11:00:00',
        'created_at' => '2026-07-10 11:00:00',
        'updated_at' => '2026-07-10 11:00:00',
    ]);
    DB::table('used_device_purchase_items')->insert([
        'id' => 1,
        'used_device_purchase_header_id' => 1,
        'product_id' => 1,
        'quantity' => 2,
        'unit_price' => 600,
        'total_price' => 1200,
        'created_at' => '2026-07-10 11:00:00',
        'updated_at' => '2026-07-10 11:00:00',
    ]);
}

function seedMaintenanceScenario(bool $repairedOnly = false): void
{
    seedBaseCatalog();
    DB::table('maintenance_headers')->insert([
        'id' => 1,
        'status' => $repairedOnly ? 'repaired' : 'delivered',
        'customer_id' => 1,
        'received_date' => '2026-07-10',
        'delivery_date' => $repairedOnly ? null : '2026-07-10',
        'repaired_at' => '2026-07-10 12:00:00',
        'total_cost' => 60,
        'advance_payment' => 60,
        'created_at' => '2026-07-10 09:00:00',
        'updated_at' => '2026-07-10 09:00:00',
    ]);
    DB::table('maintenance_used_parts')->insert([
        'id' => 1,
        'maintenance_header_id' => 1,
        'product_id' => 2,
        'quantity' => 1,
        'cost_price' => 20,
        'total_price' => 30,
    ]);
}

function seedExpenseScenario(bool $includePending = false): void
{
    DB::table('expenses')->insert([
        'id' => 1,
        'expense_category' => 'rent',
        'amount' => 20,
        'expense_date' => '2026-07-10',
        'payment_date' => '2026-07-10',
        'status' => 'paid',
        'created_at' => '2026-07-10 09:00:00',
        'updated_at' => '2026-07-10 09:00:00',
    ]);

    if ($includePending) {
        DB::table('expenses')->insert([
            'id' => 2,
            'expense_category' => 'internet',
            'amount' => 30,
            'expense_date' => '2026-07-10',
            'payment_date' => null,
            'status' => 'pending',
            'created_at' => '2026-07-10 09:00:00',
            'updated_at' => '2026-07-10 09:00:00',
        ]);
    }
}

function seedSalaryScenario(bool $includeDraftAndCancelled = false): void
{
    seedBaseCatalog();
    DB::table('salary_payments')->insert([
        'id' => 1,
        'user_id' => 1,
        'total_amount' => 50,
        'payment_date' => '2026-07-10',
        'period_start' => '2026-07-01',
        'period_end' => '2026-07-31',
        'status' => 'confirmed',
        'created_at' => '2026-07-10 09:00:00',
        'updated_at' => '2026-07-10 09:00:00',
    ]);

    if ($includeDraftAndCancelled) {
        DB::table('salary_payments')->insert([
            [
                'id' => 2,
                'user_id' => 1,
                'total_amount' => 30,
                'payment_date' => null,
                'period_start' => '2026-07-01',
                'period_end' => '2026-07-31',
                'status' => 'draft',
                'created_at' => '2026-07-10 09:00:00',
                'updated_at' => '2026-07-10 09:00:00',
            ],
            [
                'id' => 3,
                'user_id' => 1,
                'total_amount' => 10,
                'payment_date' => null,
                'period_start' => '2026-07-01',
                'period_end' => '2026-07-31',
                'status' => 'cancelled',
                'created_at' => '2026-07-10 09:00:00',
                'updated_at' => '2026-07-10 09:00:00',
            ],
        ]);
    }
}

function seedInventoryScenario(): void
{
    seedBaseCatalog();
    DB::table('inventory_items')->insert([
        ['id' => 1, 'product_id' => 1, 'status' => 'available', 'cost_price' => 100],
        ['id' => 2, 'product_id' => 1, 'status' => 'available', 'cost_price' => 120],
        ['id' => 3, 'product_id' => 1, 'status' => 'sold', 'cost_price' => 90],
    ]);
    DB::table('inventory_quantities')->insert([
        ['id' => 1, 'product_id' => 2, 'quantity' => 5, 'cost_price' => 10],
    ]);
}

function seedInventoryQuantityForCable(float $quantity = 5, float $costPrice = 40): void
{
    DB::table('inventory_quantities')->updateOrInsert(
        ['product_id' => 2],
        [
            'quantity' => $quantity,
            'cost_price' => $costPrice,
            'created_at' => '2026-07-10 08:00:00',
            'updated_at' => '2026-07-10 08:00:00',
        ],
    );
}

function actingAsAdmin(): void
{
    Sanctum::actingAs(User::findOrFail(1));
}
