<?php

namespace App\Services\OpeningStock;

use App\Exceptions\OpeningStockImportException;
use App\Exports\OpeningStockTemplateExport;
use App\Imports\OpeningStockImport;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\Inventory\InventoryReceivingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class OpeningStockImportService
{
    private const MOVEMENT_TYPE = 'opening_stock';
    private const REFERENCE_TYPE = 'opening_stock_import';

    private array $summary = [];

    private int $batchReference;

    public function __construct(private readonly InventoryReceivingService $inventoryReceivingService)
    {
        $this->resetSummary();
    }

    public function import(UploadedFile $file, string $templateType): array
    {
        $this->resetSummary();

        DB::transaction(function () use ($file, $templateType) {
            $this->ensureOpeningStockHasNotBeenCompleted($templateType);

            Excel::import(new OpeningStockImport($this, $templateType), $file);
        });

        return $this->summary;
    }

    public function importRows(Collection $rows, string $templateType): array
    {
        $validatedRows = $this->validateRows($rows, $templateType);

        foreach ($validatedRows as $row) {
            /** @var Product $product */
            $product = $row['product'];

            if ($product->type === 'mobile') {
                $this->inventoryReceivingService->receiveSerializedProduct(
                    product: $product,
                    internalSerial: $row['serial_number'],
                    unitCost: (float) $row['unit_cost'],
                    movementType: self::MOVEMENT_TYPE,
                    referenceType: self::REFERENCE_TYPE,
                    referenceId: $this->batchReference,
                    itemAttributes: [
                        'source' => null,
                        'battery_health' => $row['battery_health'],
                        'screen_condition' => $row['screen_condition'],
                        'body_condition' => $row['body_condition'],
                        'fingerprint_working' => $row['fingerprint_working'],
                        'face_id_working' => $row['face_id_working'],
                    ],
                    notes: $this->notesWithRowNumber($row),
                    createdBy: auth()->id()
                );

                $this->summary['serialized_rows']++;
                $this->summary['serialized_units']++;
                $this->summary['stock_movements']++;

                continue;
            }

            $this->inventoryReceivingService->receiveQuantityProduct(
                product: $product,
                quantity: (int) $row['quantity'],
                unitCost: (float) $row['unit_cost'],
                movementType: self::MOVEMENT_TYPE,
                referenceType: self::REFERENCE_TYPE,
                referenceId: $this->batchReference,
                notes: $this->notesWithRowNumber($row),
                createdBy: auth()->id()
            );

            $this->summary['quantity_rows']++;
            $this->summary['quantity_units'] += (int) $row['quantity'];
            $this->summary['stock_movements']++;
        }

        return $this->summary;
    }

    public function summary(): array
    {
        return $this->summary;
    }

    private function resetSummary(): void
    {
        $this->batchReference = (int) now()->format('YmdHis');
        $this->summary = [
            'total_rows' => 0,
            'quantity_rows' => 0,
            'serialized_rows' => 0,
            'quantity_units' => 0,
            'serialized_units' => 0,
            'stock_movements' => 0,
            'batch_reference' => $this->batchReference,
        ];
    }

    private function validateRows(Collection $rows, string $templateType): array
    {
        $validatedRows = [];
        $errors = [];
        $serials = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $data = $this->normalizeRow($row->toArray());

            if (! array_filter($data, fn ($value) => $value !== null && $value !== '')) {
                continue;
            }

            if ($this->isTemplateExampleRow($data, $templateType)) {
                continue;
            }

            $this->summary['total_rows']++;

            $validator = Validator::make($data, [
                'product_id' => ['nullable', 'integer', Rule::exists('products', 'id')],
                'product_name' => ['nullable', 'string', 'max:255'],
                'quantity' => ['nullable', 'integer', 'min:1'],
                'serial_number' => ['nullable', 'string', 'max:255'],
                'unit_cost' => ['required', 'numeric', 'min:0'],
                'battery_health' => ['nullable', 'integer', 'between:0,100'],
                'screen_condition' => ['nullable', 'string', Rule::in(OpeningStockTemplateExport::SCREEN_CONDITION_OPTIONS)],
                'body_condition' => ['nullable', 'string', Rule::in(OpeningStockTemplateExport::BODY_CONDITION_OPTIONS)],
                'fingerprint_working' => ['nullable', 'boolean'],
                'face_id_working' => ['nullable', 'boolean'],
                'notes' => ['nullable', 'string'],
            ], [
                'screen_condition.in' => 'screen_condition must be one of: ' . implode(', ', OpeningStockTemplateExport::SCREEN_CONDITION_OPTIONS) . '.',
                'body_condition.in' => 'body_condition must be one of: ' . implode(', ', OpeningStockTemplateExport::BODY_CONDITION_OPTIONS) . '.',
                'fingerprint_working.boolean' => 'fingerprint_working must be Yes or No.',
                'face_id_working.boolean' => 'face_id_working must be Yes or No.',
            ]);

            if (! $data['product_id'] && ! $data['product_name']) {
                $validator->after(fn ($validator) => $validator->errors()->add(
                    'product',
                    'Either product_id or product_name is required.'
                ));
            }

            if ($validator->fails()) {
                $this->addValidationErrors($errors, $rowNumber, $data, $validator->errors()->toArray());
                continue;
            }

            $product = $this->resolveProduct($data, $rowNumber, $errors);

            if (! $product) {
                continue;
            }

            $rowIsValid = true;

            if (! $this->productMatchesTemplate($product, $templateType)) {
                $errors[] = [
                    'row' => $rowNumber,
                    'product' => $data['product_name'] ?? $data['product_id'],
                    'field' => 'product_id',
                    'message' => $templateType === OpeningStockTemplateExport::TYPE_MOBILE
                        ? 'Mobile opening stock template accepts only mobile products.'
                        : 'Accessories & spare parts opening stock template accepts only accessory and spare_part products.',
                ];
                $rowIsValid = false;
            } elseif ($product->type === 'mobile') {
                $rowIsValid = $this->validateSerializedRow($data, $rowNumber, $errors, $serials);
            } elseif (in_array($product->type, ['accessory', 'spare_part'], true)) {
                $rowIsValid = $this->validateQuantityRow($data, $rowNumber, $errors);
            } else {
                $errors[] = [
                    'row' => $rowNumber,
                    'product' => $data['product_name'] ?? $data['product_id'],
                    'field' => 'product_id',
                    'message' => 'Opening stock supports only mobile, accessory, and spare_part products.',
                ];
                $rowIsValid = false;
            }

            if (! $rowIsValid) {
                continue;
            }

            $validatedRows[] = array_merge($data, ['row_number' => $rowNumber, 'product' => $product]);
        }

        if ($this->summary['total_rows'] === 0) {
            $errors[] = [
                'row' => null,
                'product' => null,
                'field' => 'file',
                'message' => 'The import file does not contain any opening stock rows.',
            ];
        }

        if ($errors) {
            throw new OpeningStockImportException($errors);
        }

        return $validatedRows;
    }

    private function isTemplateExampleRow(array $data, string $templateType): bool
    {
        if (($data['notes'] ?? null) !== 'Sample only') {
            return false;
        }

        if ($templateType === OpeningStockTemplateExport::TYPE_QUANTITY) {
            return $data['product_name'] === 'Samsung 25W Charger'
                && (int) $data['quantity'] === 20
                && (float) $data['unit_cost'] === 180.0;
        }

        return $data['product_name'] === 'Samsung Galaxy A56'
            && $data['serial_number'] === 'SN123456789'
            && (float) $data['unit_cost'] === 12000.0
            && (int) $data['battery_health'] === 100
            && $data['screen_condition'] === 'Excellent'
            && $data['body_condition'] === 'Excellent'
            && $data['fingerprint_working'] === true
            && $data['face_id_working'] === false;
    }

    private function productMatchesTemplate(Product $product, string $templateType): bool
    {
        if ($templateType === OpeningStockTemplateExport::TYPE_MOBILE) {
            return $product->type === 'mobile';
        }

        return in_array($product->type, ['accessory', 'spare_part'], true);
    }

    private function ensureOpeningStockHasNotBeenCompleted(string $templateType): void
    {
        $exists = StockMovement::where('movement_type', self::MOVEMENT_TYPE)
            ->whereHas('product', function ($query) use ($templateType) {
                if ($templateType === OpeningStockTemplateExport::TYPE_MOBILE) {
                    $query->where('type', 'mobile');

                    return;
                }

                $query->whereIn('type', ['accessory', 'spare_part']);
            })
            ->lockForUpdate()
            ->exists();

        if (! $exists) {
            return;
        }

        $message = $templateType === OpeningStockTemplateExport::TYPE_MOBILE
            ? 'Mobile opening stock has already been completed. Importing it again is not allowed.'
            : 'Accessories & spare parts opening stock has already been completed. Importing it again is not allowed.';

        throw new OpeningStockImportException([
            [
                'row' => null,
                'product' => null,
                'field' => 'file',
                'message' => $message,
            ],
        ], $message);
    }

    private function normalizeRow(array $row): array
    {
        $row = $this->normalizeHeaders($row);

        return [
            'product_id' => $this->clean($row['product_id'] ?? null),
            'product_name' => $this->clean($row['product_name'] ?? $row['name'] ?? null),
            'quantity' => $this->clean($row['quantity'] ?? null),
            'serial_number' => $this->clean($row['serial_number'] ?? $row['internal_serial'] ?? $row['imei'] ?? null),
            'unit_cost' => $this->clean($row['unit_cost'] ?? $row['cost_price'] ?? null),
            'battery_health' => $this->clean($row['battery_health'] ?? null),
            'screen_condition' => $this->cleanOption($row['screen_condition'] ?? null, OpeningStockTemplateExport::SCREEN_CONDITION_OPTIONS),
            'body_condition' => $this->cleanOption($row['body_condition'] ?? null, OpeningStockTemplateExport::BODY_CONDITION_OPTIONS),
            'fingerprint_working' => $this->cleanBoolean($row['fingerprint_working'] ?? null),
            'face_id_working' => $this->cleanBoolean($row['face_id_working'] ?? null),
            'notes' => $this->clean($row['notes'] ?? null),
        ];
    }

    private function normalizeHeaders(array $row): array
    {
        $normalized = [];

        foreach ($row as $key => $value) {
            $key = str_replace("\xEF\xBB\xBF", '', (string) $key);
            $normalized[mb_strtolower(trim($key))] = $value;
        }

        return $normalized;
    }

    private function resolveProduct(array $data, int $rowNumber, array &$errors): ?Product
    {
        if ($data['product_id']) {
            return Product::find((int) $data['product_id']);
        }

        $matches = Product::whereRaw('LOWER(name) = ?', [mb_strtolower($data['product_name'])])->get();

        if ($matches->count() === 1) {
            return $matches->first();
        }

        $errors[] = [
            'row' => $rowNumber,
            'product' => $data['product_name'],
            'field' => 'product_name',
            'message' => $matches->isEmpty()
                ? 'Product was not found.'
                : 'Product name is ambiguous. Use product_id for this row.',
        ];

        return null;
    }

    private function validateSerializedRow(array $data, int $rowNumber, array &$errors, array &$serials): bool
    {
        $rowErrors = [];

        if (! $data['serial_number']) {
            $rowErrors[] = [
                'row' => $rowNumber,
                'product' => $data['product_name'] ?? $data['product_id'],
                'field' => 'serial_number',
                'message' => 'Serial number/IMEI is required for mobile products.',
            ];

            array_push($errors, ...$rowErrors);

            return false;
        }

        if ($data['quantity'] !== null && (int) $data['quantity'] !== 1) {
            $rowErrors[] = [
                'row' => $rowNumber,
                'product' => $data['product_name'] ?? $data['product_id'],
                'field' => 'quantity',
                'message' => 'Mobile opening stock rows must represent one device. Use one row per serial number/IMEI.',
            ];
        }

        $serialKey = mb_strtolower($data['serial_number']);

        if (isset($serials[$serialKey])) {
            $rowErrors[] = [
                'row' => $rowNumber,
                'product' => $data['product_name'] ?? $data['product_id'],
                'field' => 'serial_number',
                'message' => "Duplicate serial number/IMEI in import file. First seen on row {$serials[$serialKey]}.",
            ];
        }

        if (InventoryItem::where('internal_serial', $data['serial_number'])->exists()) {
            $rowErrors[] = [
                'row' => $rowNumber,
                'product' => $data['product_name'] ?? $data['product_id'],
                'field' => 'serial_number',
                'message' => 'Serial number/IMEI already exists in inventory.',
            ];
        }

        if ($rowErrors) {
            array_push($errors, ...$rowErrors);

            return false;
        }

        $serials[$serialKey] = $rowNumber;

        return true;
    }

    private function validateQuantityRow(array $data, int $rowNumber, array &$errors): bool
    {
        if (! $data['quantity']) {
            $errors[] = [
                'row' => $rowNumber,
                'product' => $data['product_name'] ?? $data['product_id'],
                'field' => 'quantity',
                'message' => 'Quantity is required for accessory and spare_part products.',
            ];

            return false;
        }

        return true;
    }

    private function addValidationErrors(array &$errors, int $rowNumber, array $data, array $validationErrors): void
    {
        foreach ($validationErrors as $field => $messages) {
            foreach ($messages as $message) {
                $errors[] = [
                    'row' => $rowNumber,
                    'product' => $data['product_name'] ?? $data['product_id'] ?? null,
                    'field' => $field,
                    'message' => $message,
                ];
            }
        }
    }

    private function notesWithRowNumber(array $row): ?string
    {
        $prefix = 'Opening stock import row ' . $row['row_number'];

        return $row['notes'] ? "{$prefix}: {$row['notes']}" : $prefix;
    }

    private function clean(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function cleanBoolean(mixed $value): mixed
    {
        if (is_bool($value)) {
            return $value;
        }

        $value = $this->clean($value);

        if ($value === null) {
            return null;
        }

        $normalized = mb_strtolower((string) $value);

        return match ($normalized) {
            'yes' => true,
            'no' => false,
            default => $value,
        };
    }

    private function cleanOption(mixed $value, array $options): mixed
    {
        $value = $this->clean($value);

        if ($value === null) {
            return null;
        }

        foreach ($options as $option) {
            if (mb_strtolower($value) === mb_strtolower($option)) {
                return $option;
            }
        }

        return $value;
    }
}
