<?php

use App\Exports\OpeningStockTemplateExport;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\InventoryQuantity;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelFormat;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;

uses(RefreshDatabase::class);

function openingStockCsv(array $rows): UploadedFile
{
    $headers = [
        'product_id',
        'product_name',
        'quantity',
        'serial_number',
        'internal_serial',
        'unit_cost',
        'cost_price',
        'battery_health',
        'screen_condition',
        'body_condition',
        'fingerprint_working',
        'face_id_working',
        'notes',
    ];

    $csvRows = [implode(',', $headers)];

    foreach ($rows as $row) {
        $csvRows[] = implode(',', array_map(
            fn ($header) => $row[$header] ?? '',
            $headers
        ));
    }

    return UploadedFile::fake()->createWithContent('opening-stock.csv', implode("\n", $csvRows));
}

function openingStockCsvWithHeaders(array $headers, array $rows): UploadedFile
{
    $csvRows = [implode(',', $headers)];

    foreach ($rows as $row) {
        $csvRows[] = implode(',', array_map(
            fn ($header) => $row[$header] ?? '',
            $headers
        ));
    }

    return UploadedFile::fake()->createWithContent('opening-stock.csv', implode("\n", $csvRows));
}

function openingStockGeneratedIdentifierIsValid(?string $identifier): bool
{
    if (! is_string($identifier) || preg_match('/^\d{15}$/', $identifier) !== 1) {
        return false;
    }

    $sum = 0;

    foreach (str_split(strrev($identifier)) as $index => $digit) {
        $value = (int) $digit;

        if ($index % 2 === 1) {
            $value *= 2;
            $value = $value > 9 ? $value - 9 : $value;
        }

        $sum += $value;
    }

    return $sum % 10 === 0;
}

test('admin can import quantity and serialized opening stock from separate templates', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::create(['name' => 'Inventory']);

    $accessory = Product::create([
        'category_id' => $category->id,
        'name' => 'USB Cable',
        'type' => 'accessory',
        'min_stock' => 5,
    ]);

    $mobile = Product::create([
        'category_id' => $category->id,
        'name' => 'iPhone 15',
        'type' => 'mobile',
        'min_stock' => 1,
    ]);

    $quantityResponse = $this
        ->actingAs($admin)
        ->post('/api/opening-stock/import', [
            'file' => openingStockCsv([
                [
                    'product_id' => $accessory->id,
                    'quantity' => 10,
                    'unit_cost' => 120,
                    'notes' => 'Initial shelf count',
                ],
            ]),
            'template_type' => OpeningStockTemplateExport::TYPE_QUANTITY,
        ]);

    $mobileResponse = $this
        ->actingAs($admin)
        ->post('/api/opening-stock/import', [
            'file' => openingStockCsv([
                [
                    'product_id' => $mobile->id,
                    'serial_number' => 'SN-001',
                    'unit_cost' => 25000,
                    'battery_health' => 100,
                    'fingerprint_working' => 'yes',
                    'face_id_working' => 'no',
                ],
            ]),
            'template_type' => OpeningStockTemplateExport::TYPE_MOBILE,
        ]);

    $quantityResponse
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.total_rows', 1)
        ->assertJsonPath('data.quantity_units', 10)
        ->assertJsonPath('data.serialized_units', 0)
        ->assertJsonPath('data.stock_movements', 1);

    $mobileResponse
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.total_rows', 1)
        ->assertJsonPath('data.quantity_units', 0)
        ->assertJsonPath('data.serialized_units', 1)
        ->assertJsonPath('data.stock_movements', 1);

    expect(InventoryQuantity::where('product_id', $accessory->id)->value('quantity'))->toBe(10);
    expect(InventoryQuantity::where('product_id', $accessory->id)->value('cost_price'))->toBe('120.00');

    $inventoryItem = InventoryItem::where('internal_serial', 'SN-001')->first();

    expect($inventoryItem)->not->toBeNull()
        ->and($inventoryItem->product_id)->toBe($mobile->id)
        ->and($inventoryItem->cost_price)->toBe('25000.00')
        ->and($inventoryItem->battery_health)->toBe(100)
        ->and($inventoryItem->fingerprint_working)->toBeTrue()
        ->and($inventoryItem->face_id_working)->toBeFalse();

    expect(StockMovement::where('movement_type', 'opening_stock')->count())->toBe(2);
});

test('opening stock import generates valid unique mobile identifiers when serial and internal serial are missing', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::create(['name' => 'Inventory']);

    $mobile = Product::create([
        'category_id' => $category->id,
        'name' => 'iPhone 15',
        'type' => 'mobile',
        'min_stock' => 1,
    ]);

    $response = $this
        ->actingAs($admin)
        ->post('/api/opening-stock/import', [
            'file' => openingStockCsv([
                [
                    'product_id' => $mobile->id,
                    'unit_cost' => 25000,
                ],
                [
                    'product_id' => $mobile->id,
                    'unit_cost' => 26000,
                ],
            ]),
            'template_type' => OpeningStockTemplateExport::TYPE_MOBILE,
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.total_rows', 2)
        ->assertJsonPath('data.serialized_units', 2)
        ->assertJsonPath('data.stock_movements', 2);

    $generatedIdentifiers = InventoryItem::where('product_id', $mobile->id)
        ->pluck('internal_serial');

    expect($generatedIdentifiers)->toHaveCount(2)
        ->and($generatedIdentifiers->unique())->toHaveCount(2);

    foreach ($generatedIdentifiers as $identifier) {
        expect(openingStockGeneratedIdentifierIsValid($identifier))->toBeTrue();
    }

    expect(StockMovement::where('movement_type', 'opening_stock')->count())->toBe(2);
});

test('opening stock import rolls back when any row is invalid', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::create(['name' => 'Inventory']);

    $accessory = Product::create([
        'category_id' => $category->id,
        'name' => 'USB Cable',
        'type' => 'accessory',
        'min_stock' => 5,
    ]);

    $mobile = Product::create([
        'category_id' => $category->id,
        'name' => 'iPhone 15',
        'type' => 'mobile',
        'min_stock' => 1,
    ]);

    $response = $this
        ->actingAs($admin)
        ->post('/api/opening-stock/import', [
            'file' => openingStockCsv([
                [
                    'product_id' => $accessory->id,
                    'quantity' => 10,
                    'unit_cost' => 120,
                ],
                [
                    'product_id' => $mobile->id,
                    'unit_cost' => 25000,
                ],
            ]),
            'template_type' => OpeningStockTemplateExport::TYPE_MOBILE,
        ]);

    $response
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    expect(InventoryQuantity::count())->toBe(0);
    expect(InventoryItem::count())->toBe(0);
    expect(StockMovement::count())->toBe(0);
});

test('opening stock import cannot be completed twice', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::create(['name' => 'Inventory']);

    $accessory = Product::create([
        'category_id' => $category->id,
        'name' => 'USB Cable',
        'type' => 'accessory',
        'min_stock' => 5,
    ]);

    $firstResponse = $this
        ->actingAs($admin)
        ->post('/api/opening-stock/import', [
            'file' => openingStockCsv([
                [
                    'product_id' => $accessory->id,
                    'quantity' => 10,
                    'unit_cost' => 120,
                ],
            ]),
            'template_type' => OpeningStockTemplateExport::TYPE_QUANTITY,
        ]);

    $firstResponse->assertOk();

    $secondResponse = $this
        ->actingAs($admin)
        ->post('/api/opening-stock/import', [
            'file' => openingStockCsv([
                [
                    'product_id' => $accessory->id,
                    'quantity' => 10,
                    'unit_cost' => 120,
                ],
            ]),
            'template_type' => OpeningStockTemplateExport::TYPE_QUANTITY,
        ]);

    $secondResponse
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('errors.0.message', 'Accessories & spare parts opening stock has already been completed. Importing it again is not allowed.');

    expect(InventoryQuantity::where('product_id', $accessory->id)->value('quantity'))->toBe(10);
    expect(StockMovement::where('movement_type', 'opening_stock')->count())->toBe(1);
});

test('invalid serialized rows do not create false duplicate serial errors', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::create(['name' => 'Inventory']);

    $mobile = Product::create([
        'category_id' => $category->id,
        'name' => 'iPhone 15',
        'type' => 'mobile',
        'min_stock' => 1,
    ]);

    $response = $this
        ->actingAs($admin)
        ->post('/api/opening-stock/import', [
            'file' => openingStockCsv([
                [
                    'product_id' => $mobile->id,
                    'quantity' => 2,
                    'serial_number' => 'SN-DUP',
                    'unit_cost' => 25000,
                ],
                [
                    'product_id' => $mobile->id,
                    'serial_number' => 'SN-DUP',
                    'unit_cost' => 25000,
                ],
            ]),
            'template_type' => OpeningStockTemplateExport::TYPE_MOBILE,
        ]);

    $response
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    $messages = collect($response->json('errors'))->pluck('message');

    expect($messages)->toContain('Mobile opening stock rows must represent one device. Use one row per serial number.');
    expect($messages->contains(fn ($message) => str_contains($message, 'Duplicate serial number in import file')))->toBeFalse();
    expect(InventoryItem::count())->toBe(0);
    expect(StockMovement::count())->toBe(0);
});

test('opening stock import matches product names case insensitively', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::create(['name' => 'Inventory']);

    Product::create([
        'category_id' => $category->id,
        'name' => 'iPhone 13',
        'type' => 'mobile',
        'min_stock' => 1,
    ]);

    $response = $this
        ->actingAs($admin)
        ->post('/api/opening-stock/import', [
            'file' => openingStockCsv([
                [
                    'product_name' => 'iphone 13',
                    'serial_number' => 'SN-CASE-001',
                    'unit_cost' => 18000,
                ],
            ]),
            'template_type' => OpeningStockTemplateExport::TYPE_MOBILE,
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.serialized_units', 1);

    expect(InventoryItem::where('internal_serial', 'SN-CASE-001')->exists())->toBeTrue();
    expect(StockMovement::where('movement_type', 'opening_stock')->count())->toBe(1);
});

test('opening stock import rejects invalid boolean values', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::create(['name' => 'Inventory']);

    $mobile = Product::create([
        'category_id' => $category->id,
        'name' => 'iPhone 15',
        'type' => 'mobile',
        'min_stock' => 1,
    ]);

    $response = $this
        ->actingAs($admin)
        ->post('/api/opening-stock/import', [
            'file' => openingStockCsv([
                [
                    'product_id' => $mobile->id,
                    'serial_number' => 'SN-BOOL-001',
                    'unit_cost' => 25000,
                    'fingerprint_working' => 'maybe',
                    'face_id_working' => 'unknown',
                ],
            ]),
            'template_type' => OpeningStockTemplateExport::TYPE_MOBILE,
        ]);

    $response
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    $messages = collect($response->json('errors'))->pluck('message');

    expect($messages)->toContain('fingerprint_working must be Yes or No.');
    expect($messages)->toContain('face_id_working must be Yes or No.');
    expect(InventoryItem::count())->toBe(0);
    expect(StockMovement::count())->toBe(0);
});

test('opening stock import accepts valid boolean values', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::create(['name' => 'Inventory']);

    $mobile = Product::create([
        'category_id' => $category->id,
        'name' => 'iPhone 15',
        'type' => 'mobile',
        'min_stock' => 1,
    ]);

    $response = $this
        ->actingAs($admin)
        ->post('/api/opening-stock/import', [
            'file' => openingStockCsv([
                [
                    'product_id' => $mobile->id,
                    'serial_number' => 'SN-BOOL-002',
                    'unit_cost' => 25000,
                    'fingerprint_working' => 'Yes',
                    'face_id_working' => 'No',
                ],
            ]),
            'template_type' => OpeningStockTemplateExport::TYPE_MOBILE,
        ]);

    $response->assertOk();

    $inventoryItem = InventoryItem::where('internal_serial', 'SN-BOOL-002')->first();

    expect($inventoryItem)->not->toBeNull()
        ->and($inventoryItem->fingerprint_working)->toBeTrue()
        ->and($inventoryItem->face_id_working)->toBeFalse();
});

test('opening stock import rejects invalid fixed option values', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::create(['name' => 'Inventory']);

    $mobile = Product::create([
        'category_id' => $category->id,
        'name' => 'iPhone 15',
        'type' => 'mobile',
        'min_stock' => 1,
    ]);

    $response = $this
        ->actingAs($admin)
        ->post('/api/opening-stock/import', [
            'file' => openingStockCsv([
                [
                    'product_id' => $mobile->id,
                    'serial_number' => 'SN-OPTION-001',
                    'unit_cost' => 25000,
                    'screen_condition' => 'Mint',
                    'body_condition' => 'Unknown',
                ],
            ]),
            'template_type' => OpeningStockTemplateExport::TYPE_MOBILE,
        ]);

    $response
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    $messages = collect($response->json('errors'))->pluck('message');

    expect($messages->contains(fn ($message) => str_starts_with($message, 'screen_condition must be one of:')))->toBeTrue();
    expect($messages->contains(fn ($message) => str_starts_with($message, 'body_condition must be one of:')))->toBeTrue();
    expect(InventoryItem::count())->toBe(0);
});

test('opening stock import does not skip real data matching the old example row', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::create(['name' => 'Inventory']);

    Product::create([
        'category_id' => $category->id,
        'name' => 'iPhone 15',
        'type' => 'mobile',
        'min_stock' => 1,
    ]);

    $response = $this
        ->actingAs($admin)
        ->post('/api/opening-stock/import', [
            'file' => openingStockCsv([
                [
                    'product_name' => 'iPhone 15',
                    'serial_number' => 'SN-EXAMPLE-001',
                    'unit_cost' => 25000,
                ],
            ]),
            'template_type' => OpeningStockTemplateExport::TYPE_MOBILE,
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.total_rows', 1)
        ->assertJsonPath('data.serialized_units', 1);

    expect(InventoryItem::where('internal_serial', 'SN-EXAMPLE-001')->exists())->toBeTrue();
    expect(StockMovement::where('movement_type', 'opening_stock')->count())->toBe(1);
});

test('opening stock csv import supports utf8 bom headers', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::create(['name' => 'Inventory']);

    $accessory = Product::create([
        'category_id' => $category->id,
        'name' => 'USB Cable',
        'type' => 'accessory',
        'min_stock' => 5,
    ]);

    $bomProductId = "\xEF\xBB\xBFproduct_id";

    $response = $this
        ->actingAs($admin)
        ->post('/api/opening-stock/import', [
            'file' => openingStockCsvWithHeaders(
                [$bomProductId, 'quantity', 'unit_cost'],
                [
                    [
                        $bomProductId => $accessory->id,
                        'quantity' => ' 7 ',
                        'unit_cost' => ' 95 ',
                    ],
                ]
            ),
            'template_type' => OpeningStockTemplateExport::TYPE_QUANTITY,
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.quantity_units', 7);

    expect(InventoryQuantity::where('product_id', $accessory->id)->value('quantity'))->toBe(7);
});

test('opening stock import ignores generated example rows', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::create(['name' => 'Inventory']);

    $mobile = Product::create([
        'category_id' => $category->id,
        'name' => 'iPhone 15',
        'type' => 'mobile',
        'min_stock' => 1,
    ]);

    $accessory = Product::create([
        'category_id' => $category->id,
        'name' => 'USB Cable',
        'type' => 'accessory',
        'min_stock' => 5,
    ]);

    $mobileResponse = $this
        ->actingAs($admin)
        ->post('/api/opening-stock/import', [
            'file' => openingStockCsv([
                [
                    'product_name' => 'Samsung Galaxy A56',
                    'serial_number' => 'SN123456789',
                    'unit_cost' => 12000,
                    'battery_health' => 100,
                    'screen_condition' => 'Excellent',
                    'body_condition' => 'Excellent',
                    'fingerprint_working' => 'Yes',
                    'face_id_working' => 'No',
                    'notes' => 'Sample only',
                ],
                [
                    'product_id' => $mobile->id,
                    'serial_number' => 'SN-REAL-001',
                    'unit_cost' => 25000,
                ],
            ]),
            'template_type' => OpeningStockTemplateExport::TYPE_MOBILE,
        ]);

    $quantityResponse = $this
        ->actingAs($admin)
        ->post('/api/opening-stock/import', [
            'file' => openingStockCsv([
                [
                    'product_name' => 'Samsung 25W Charger',
                    'quantity' => 20,
                    'unit_cost' => 180,
                    'notes' => 'Sample only',
                ],
                [
                    'product_id' => $accessory->id,
                    'quantity' => 7,
                    'unit_cost' => 95,
                ],
            ]),
            'template_type' => OpeningStockTemplateExport::TYPE_QUANTITY,
        ]);

    $mobileResponse
        ->assertOk()
        ->assertJsonPath('data.total_rows', 1)
        ->assertJsonPath('data.serialized_units', 1);

    $quantityResponse
        ->assertOk()
        ->assertJsonPath('data.total_rows', 1)
        ->assertJsonPath('data.quantity_units', 7);

    expect(InventoryItem::where('internal_serial', 'SN123456789')->exists())->toBeFalse();
    expect(InventoryItem::where('internal_serial', 'SN-REAL-001')->exists())->toBeTrue();
    expect(InventoryQuantity::where('product_id', $accessory->id)->value('quantity'))->toBe(7);
});

test('employees cannot import opening stock', function () {
    $employee = User::factory()->create(['role' => 'employee']);

    $response = $this
        ->actingAs($employee)
        ->post('/api/opening-stock/import', [
            'file' => openingStockCsv([]),
            'template_type' => OpeningStockTemplateExport::TYPE_MOBILE,
        ]);

    $response->assertStatus(403);
});

test('admin can download opening stock templates', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $mobileResponse = $this
        ->actingAs($admin)
        ->get('/api/opening-stock/template?template_type=mobile');

    $quantityResponse = $this
        ->actingAs($admin)
        ->get('/api/opening-stock/template?template_type=quantity');

    $mobileResponse
        ->assertOk()
        ->assertHeader(
            'content-disposition',
            'attachment; filename=opening_stock_mobile_template.xlsx'
        );

    $quantityResponse
        ->assertOk()
        ->assertHeader(
            'content-disposition',
            'attachment; filename=opening_stock_accessories_spare_parts_template.xlsx'
        );

    expect(OpeningStockTemplateExport::MOBILE_HEADINGS)->toBe([
        'product_name',
        'serial_number',
        'internal_serial',
        'cost_price',
        'battery_health',
        'screen_condition',
        'body_condition',
        'fingerprint_working',
        'face_id_working',
        'notes',
    ]);

    expect(OpeningStockTemplateExport::QUANTITY_HEADINGS)->toBe([
        'product_name',
        'quantity',
        'unit_cost',
        'notes',
    ]);

    expect(OpeningStockTemplateExport::MOBILE_EXAMPLE_ROW)->toBe([
        'Samsung Galaxy A56',
        'SN123456789',
        'INT-0001',
        12000,
        100,
        'Excellent',
        'Excellent',
        'Yes',
        'No',
        'Sample only',
    ]);

    expect(OpeningStockTemplateExport::QUANTITY_EXAMPLE_ROW)->toBe([
        'Samsung 25W Charger',
        20,
        180,
        'Sample only',
    ]);
});

test('opening stock templates contain dropdown validations', function () {
    $category = Category::create(['name' => 'Inventory']);

    Product::create([
        'category_id' => $category->id,
        'name' => 'iPhone 13',
        'type' => 'mobile',
        'min_stock' => 1,
    ]);

    Product::create([
        'category_id' => $category->id,
        'name' => 'USB Cable',
        'type' => 'accessory',
        'min_stock' => 5,
    ]);

    $mobilePath = storage_path('framework/testing/opening_stock_mobile_template.xlsx');
    $quantityPath = storage_path('framework/testing/opening_stock_quantity_template.xlsx');
    file_put_contents($mobilePath, Excel::raw(new OpeningStockTemplateExport(OpeningStockTemplateExport::TYPE_MOBILE), ExcelFormat::XLSX));
    file_put_contents($quantityPath, Excel::raw(new OpeningStockTemplateExport(OpeningStockTemplateExport::TYPE_QUANTITY), ExcelFormat::XLSX));

    $mobileSpreadsheet = IOFactory::load($mobilePath);
    $mobileSheet = $mobileSpreadsheet->getActiveSheet();
    $mobileListSheet = $mobileSpreadsheet->getSheetByName('Opening Stock Lists');
    $quantitySpreadsheet = IOFactory::load($quantityPath);
    $quantitySheet = $quantitySpreadsheet->getActiveSheet();
    $quantityListSheet = $quantitySpreadsheet->getSheetByName('Opening Stock Lists');

    expect($mobileListSheet)->not->toBeNull();
    expect($mobileSheet->getCell('A2')->getValue())->toBe('Samsung Galaxy A56');
    expect($mobileSheet->getCell('B2')->getValue())->toBe('SN123456789');
    expect($mobileSheet->getCell('J2')->getValue())->toBe('Sample only');
    expect($mobileSheet->getStyle('A2')->getFont()->getItalic())->toBeTrue();
    expect($mobileSheet->getStyle('A2')->getFill()->getFillType())->toBe(Fill::FILL_SOLID);
    expect($mobileSheet->getCell('A2')->getDataValidation()->getType())->toBe(DataValidation::TYPE_LIST);
    expect($mobileSheet->getCell('E2')->getDataValidation()->getType())->toBe(DataValidation::TYPE_WHOLE);
    expect($mobileSheet->getCell('F2')->getDataValidation()->getType())->toBe(DataValidation::TYPE_LIST);
    expect($mobileSheet->getCell('G2')->getDataValidation()->getType())->toBe(DataValidation::TYPE_LIST);
    expect($mobileSheet->getCell('H2')->getDataValidation()->getType())->toBe(DataValidation::TYPE_LIST);
    expect($mobileSheet->getCell('I2')->getDataValidation()->getType())->toBe(DataValidation::TYPE_LIST);
    expect($mobileListSheet->getCell('A1')->getValue())->toBe('iPhone 13');
    expect($mobileListSheet->getCell('B1')->getValue())->toBe('Yes');
    expect($mobileListSheet->getCell('B2')->getValue())->toBe('No');

    expect($quantityListSheet)->not->toBeNull();
    expect($quantitySheet->getCell('A2')->getValue())->toBe('Samsung 25W Charger');
    expect($quantitySheet->getCell('B2')->getValue())->toBe(20);
    expect($quantitySheet->getCell('D2')->getValue())->toBe('Sample only');
    expect($quantitySheet->getStyle('A2')->getFont()->getItalic())->toBeTrue();
    expect($quantitySheet->getStyle('A2')->getFill()->getFillType())->toBe(Fill::FILL_SOLID);
    expect($quantitySheet->getCell('A2')->getDataValidation()->getType())->toBe(DataValidation::TYPE_LIST);
    expect($quantitySheet->getCell('B2')->getDataValidation()->getType())->toBe(DataValidation::TYPE_WHOLE);
    expect($quantityListSheet->getCell('A1')->getValue())->toBe('USB Cable');
});

test('employees cannot download opening stock template', function () {
    $employee = User::factory()->create(['role' => 'employee']);

    $response = $this
        ->actingAs($employee)
        ->get('/api/opening-stock/template');

    $response->assertStatus(403);
});
