<?php

namespace App\Services\Product;

use App\Models\Brand;
use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class ProductImportService
{
    private array $summary = [
        'total_rows' => 0,
        'created' => 0,
        'skipped' => 0,
        'failed' => 0,
        'errors' => [],
        'skipped_rows' => [],
    ];

    public function __construct(private readonly ProductService $productService)
    {
    }

    public function importRows(Collection $rows): array
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $data = $this->normalizeRow($row->toArray());

            if (!array_filter($data, fn($value) => $value !== null && $value !== '')) {
                continue;
            }

            if ($this->isExampleRow($data)) {
                continue;
            }

            $this->summary['total_rows']++;

            $validator = Validator::make($data, [
                'name' => ['required', 'string', 'max:255'],
                'type' => ['required', Rule::in(['mobile', 'accessory', 'spare_part'])],
                'category_name' => ['required', 'string', 'max:255'],
                'brand_name' => ['nullable', 'string', 'max:255'],
                'min_stock' => ['nullable', 'integer', 'min:0'],
            ]);

            if ($validator->fails()) {
                $this->addValidationErrors($rowNumber, $data, $validator->errors()->toArray());
                continue;
            }

            try {
                $category = Category::firstOrCreate(['name' => $data['category_name']]);
                $brand = $data['brand_name']
                    ? Brand::firstOrCreate(['name' => $data['brand_name']], ['is_active' => true])
                    : null;

                if ($this->productService->duplicateExists($data['name'], $category->id)) {
                    $this->summary['skipped']++;
                    $this->summary['skipped_rows'][] = [
                        'row' => $rowNumber,
                        'product' => $data['name'],
                        'reason' => 'Duplicate product name/category.',
                    ];
                    continue;
                }

                $this->productService->create([
                    'name' => $data['name'],
                    'type' => $data['type'],
                    'category_id' => $category->id,
                    'brand_id' => $brand?->id,
                    'min_stock' => $data['min_stock'] ?? 5,
                ]);

                $this->summary['created']++;
            } catch (Throwable $exception) {
                $this->summary['failed']++;
                $this->summary['errors'][] = [
                    'row' => $rowNumber,
                    'product' => $data['name'] ?? null,
                    'field' => null,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return $this->summary;
    }

    public function summary(): array
    {
        return $this->summary;
    }

    private function normalizeRow(array $row): array
    {
        return [
            'name' => $this->clean($row['name'] ?? null),
            'type' => $this->clean($row['type'] ?? null),
            'category_name' => $this->clean($row['category_name'] ?? null),
            'brand_name' => $this->clean($row['brand_name'] ?? null),
            'min_stock' => $this->clean($row['min_stock'] ?? null),
        ];
    }

    private function clean(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function isExampleRow(array $data): bool
    {
        return $data === [
            'name' => 'iPhone 15',
            'type' => 'mobile',
            'category_name' => 'Mobile Phones',
            'brand_name' => 'Apple',
            'min_stock' => '5',
        ];
    }

    private function addValidationErrors(int $rowNumber, array $data, array $errors): void
    {
        $this->summary['failed']++;

        foreach ($errors as $field => $messages) {
            foreach ($messages as $message) {
                $this->summary['errors'][] = [
                    'row' => $rowNumber,
                    'product' => $data['name'] ?? null,
                    'field' => $field,
                    'message' => $message,
                ];
            }
        }
    }
}
