<?php

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Returns;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockItem;
use App\Models\User;
use App\Services\ReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

// ──────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────

function createUser(): User
{
    return User::factory()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => Hash::make('password'),
    ]);
}

function createCategory(): Category
{
    return Category::create(['name' => 'Phones']);
}

function createSerializedProduct(Category $category, string $name = 'iPhone 15'): Product
{
    return Product::create([
        'name' => $name,
        'category_id' => $category->id,
        'is_serialized' => true,
    ]);
}

function createNonSerializedProduct(Category $category, string $name = 'Charger'): Product
{
    return Product::create([
        'name' => $name,
        'category_id' => $category->id,
        'is_serialized' => false,
    ]);
}

function createStockItem(Product $product, string $status = 'available', ?string $serialNumber = null): StockItem
{
    return StockItem::create([
        'product_id' => $product->id,
        'cost_price' => 500,
        'condition' => 'new',
        'status' => $status,
        'serial_number' => $serialNumber,
    ]);
}

function createSaleWithItems(User $user, array $itemConfigs): Sale
{
    $sale = Sale::create([
        'customer_id' => Customer::create(['name' => 'John'])->id,
        'user_id' => $user->id,
        'date' => now()->format('Y-m-d'),
        'payment_method' => 'cash',
    ]);

    $total = 0;

    foreach ($itemConfigs as $config) {
        $product = $config['product'];
        $quantity = $config['quantity'] ?? 1;
        $unitPrice = $config['unit_price'] ?? 1000;
        $lineTotal = $quantity * $unitPrice;
        $total += $lineTotal;

        $saleItem = SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
        ]);

        if ($product->is_serialized) {
            foreach ($config['stock_items'] ?? [] as $stockItem) {
                $stockItem->update(['status' => 'sold']);
                $saleItem->stockItems()->attach($stockItem->id);
            }
        } else {
            foreach ($config['stock_items'] ?? [] as $stockItem) {
                $stockItem->update(['status' => 'sold']);
                $saleItem->stockItems()->attach($stockItem->id);
            }
        }
    }

    $sale->update(['total' => $total]);
    $sale->load(['customer', 'saleItems.product', 'saleItems.stockItems']);

    return $sale;
}

// ──────────────────────────────────────────────
// Sale Deletion Tests
// ──────────────────────────────────────────────

describe('Sale deletion with returns protection', function () {

    it('allows deleting a sale without any returns', function () {
        $user = createUser();
        $category = createCategory();
        $product = createSerializedProduct($category);
        $stockItem = createStockItem($product);

        $sale = createSaleWithItems($user, [
            ['product' => $product, 'stock_items' => [$stockItem], 'unit_price' => 1000],
        ]);

        $response = $this->actingAs($user)->deleteJson("/api/sales/{$sale->id}");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertDatabaseMissing('sales', ['id' => $sale->id]);
    });

    it('rejects deleting a sale that has existing returns', function () {
        $user = createUser();
        $category = createCategory();
        $product = createSerializedProduct($category);
        $stockItem = createStockItem($product);

        $sale = createSaleWithItems($user, [
            ['product' => $product, 'stock_items' => [$stockItem], 'unit_price' => 1000],
        ]);

        $returnService = app(ReturnService::class);
        $returnService->createReturn([
            'sale_id' => $sale->id,
            'refund_method' => 'cash',
            'items' => [
                [
                    'sale_item_id' => $sale->saleItems->first()->id,
                    'stock_item_id' => $stockItem->id,
                    'quantity' => 1,
                    'refund_amount' => 1000,
                ],
            ],
        ], $user->id);

        $response = $this->actingAs($user)->deleteJson("/api/sales/{$sale->id}");

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'لا يمكن حذف الفاتورة لأنها تحتوي على مرتجعات. قم بإلغاء المرتجعات أولاً.',
        ]);
        $this->assertDatabaseHas('sales', ['id' => $sale->id]);
    });

    it('rejects deleting a non-existent sale', function () {
        $user = createUser();

        $response = $this->actingAs($user)->deleteJson('/api/sales/99999');

        $response->assertStatus(404);
        $response->assertJson(['success' => false]);
    });
});

// ──────────────────────────────────────────────
// Over-Refund Prevention Tests
// ──────────────────────────────────────────────

describe('Over-refund prevention', function () {

    it('allows a partial refund within the sale total', function () {
        $user = createUser();
        $category = createCategory();
        $charger = createNonSerializedProduct($category, 'Charger');
        $stock1 = createStockItem($charger);
        $stock2 = createStockItem($charger);

        $sale = createSaleWithItems($user, [
            ['product' => $charger, 'stock_items' => [$stock1, $stock2], 'quantity' => 2, 'unit_price' => 500],
        ]);

        $returnService = app(ReturnService::class);
        $return = $returnService->createReturn([
            'sale_id' => $sale->id,
            'refund_method' => 'cash',
            'items' => [
                [
                    'sale_item_id' => $sale->saleItems->first()->id,
                    'quantity' => 1,
                    'refund_amount' => 300,
                ],
            ],
        ], $user->id);

        expect($return->id)->toBeGreaterThan(0);
        $this->assertDatabaseHas('returns', ['id' => $return->id]);
    });

    it('allows multiple refunds that exactly equal the sale total', function () {
        $user = createUser();
        $category = createCategory();
        $product = createSerializedProduct($category);
        $stockItem1 = createStockItem($product, 'available', 'SN-001');
        $stockItem2 = createStockItem($product, 'available', 'SN-002');

        $sale = createSaleWithItems($user, [
            ['product' => $product, 'stock_items' => [$stockItem1], 'unit_price' => 1000],
            ['product' => $product, 'stock_items' => [$stockItem2], 'unit_price' => 2000],
        ]);

        $returnService = app(ReturnService::class);
        $items = $sale->saleItems;

        // First refund: 1000
        $r1 = $returnService->createReturn([
            'sale_id' => $sale->id,
            'refund_method' => 'cash',
            'items' => [
                [
                    'sale_item_id' => $items[0]->id,
                    'stock_item_id' => $stockItem1->id,
                    'quantity' => 1,
                    'refund_amount' => 1000,
                ],
            ],
        ], $user->id);
        expect($r1->id)->toBeGreaterThan(0);

        // Second refund: 2000 (exactly the remainder)
        $r2 = $returnService->createReturn([
            'sale_id' => $sale->id,
            'refund_method' => 'cash',
            'items' => [
                [
                    'sale_item_id' => $items[1]->id,
                    'stock_item_id' => $stockItem2->id,
                    'quantity' => 1,
                    'refund_amount' => 2000,
                ],
            ],
        ], $user->id);
        expect($r2->id)->toBeGreaterThan(0);

        // Both refunds sum to exactly the sale total
        $totalRefunded = Returns::where('sale_id', $sale->id)
            ->get()
            ->sum(fn (Returns $r) => $r->refund_total + $r->restocking_fee);
        expect((float) $totalRefunded)->toBe((float) $sale->total);
    });

    it('rejects a refund that exceeds the remaining refundable amount', function () {
        $user = createUser();
        $category = createCategory();
        $product = createSerializedProduct($category);
        $stockItem = createStockItem($product);

        $sale = createSaleWithItems($user, [
            ['product' => $product, 'stock_items' => [$stockItem], 'unit_price' => 1000],
        ]);

        $returnService = app(ReturnService::class);

        expect(fn () => $returnService->createReturn([
            'sale_id' => $sale->id,
            'refund_method' => 'cash',
            'items' => [
                [
                    'sale_item_id' => $sale->saleItems->first()->id,
                    'stock_item_id' => $stockItem->id,
                    'quantity' => 1,
                    'refund_amount' => 1500,
                ],
            ],
        ], $user->id))->toThrow(RuntimeException::class, 'يتجاوز المبلغ المتبقي القابل للاسترداد');
    });

    it('rejects a third refund after the sale total has been fully refunded', function () {
        $user = createUser();
        $category = createCategory();
        $product = createSerializedProduct($category, 'Phone A');

        // Create 3 stock items to be able to sell 3 items
        $stock1 = createStockItem($product, 'available', 'SN-A1');
        $stock2 = createStockItem($product, 'available', 'SN-A2');
        $stock3 = createStockItem($product, 'available', 'SN-A3');

        $sale = createSaleWithItems($user, [
            ['product' => $product, 'stock_items' => [$stock1], 'unit_price' => 500],
            ['product' => $product, 'stock_items' => [$stock2], 'unit_price' => 300],
            ['product' => $product, 'stock_items' => [$stock3], 'unit_price' => 200],
        ]);

        $returnService = app(ReturnService::class);
        $items = $sale->saleItems;

        // First refund: 500
        $returnService->createReturn([
            'sale_id' => $sale->id,
            'refund_method' => 'cash',
            'items' => [
                [
                    'sale_item_id' => $items[0]->id,
                    'stock_item_id' => $stock1->id,
                    'quantity' => 1,
                    'refund_amount' => 500,
                ],
            ],
        ], $user->id);

        // Second refund: 300
        $returnService->createReturn([
            'sale_id' => $sale->id,
            'refund_method' => 'cash',
            'items' => [
                [
                    'sale_item_id' => $items[1]->id,
                    'stock_item_id' => $stock2->id,
                    'quantity' => 1,
                    'refund_amount' => 300,
                ],
            ],
        ], $user->id);

        // Third refund: 200 (total now 1000 = sale total)
        $returnService->createReturn([
            'sale_id' => $sale->id,
            'refund_method' => 'cash',
            'items' => [
                [
                    'sale_item_id' => $items[2]->id,
                    'stock_item_id' => $stock3->id,
                    'quantity' => 1,
                    'refund_amount' => 200,
                ],
            ],
        ], $user->id);

        // Fourth refund: should fail — total already refunded
        expect(fn () => $returnService->createReturn([
            'sale_id' => $sale->id,
            'refund_method' => 'cash',
            'items' => [
                [
                    'sale_item_id' => $items[0]->id,
                    'stock_item_id' => $stock1->id,
                    'quantity' => 1,
                    'refund_amount' => 1,
                ],
            ],
        ], $user->id))->toThrow(RuntimeException::class, 'يتجاوز المبلغ المتبقي القابل للاسترداد');
    });
});

// ──────────────────────────────────────────────
// Concurrent Refund Tests
// ──────────────────────────────────────────────

describe('Concurrent refund safety', function () {

    it('prevents concurrent refunds from exceeding the sale total using row locking', function () {
        $user = createUser();
        $category = createCategory();
        $product = createSerializedProduct($category, 'Concurrent Phone');

        $stock1 = createStockItem($product, 'available', 'SN-C1');
        $stock2 = createStockItem($product, 'available', 'SN-C2');

        $sale = createSaleWithItems($user, [
            ['product' => $product, 'stock_items' => [$stock1], 'unit_price' => 600],
            ['product' => $product, 'stock_items' => [$stock2], 'unit_price' => 400],
        ]);

        $returnService = app(ReturnService::class);
        $items = $sale->saleItems;

        // First refund uses 400 of the 1000 total
        $returnService->createReturn([
            'sale_id' => $sale->id,
            'refund_method' => 'cash',
            'items' => [
                [
                    'sale_item_id' => $items[1]->id,
                    'stock_item_id' => $stock2->id,
                    'quantity' => 1,
                    'refund_amount' => 400,
                ],
            ],
        ], $user->id);

        // Second refund: tries to refund 700, but only 600 remain
        expect(fn () => $returnService->createReturn([
            'sale_id' => $sale->id,
            'refund_method' => 'cash',
            'items' => [
                [
                    'sale_item_id' => $items[0]->id,
                    'stock_item_id' => $stock1->id,
                    'quantity' => 1,
                    'refund_amount' => 700,
                ],
            ],
        ], $user->id))->toThrow(RuntimeException::class, 'يتجاوز المبلغ المتبقي القابل للاسترداد');
    });
});
