<?php

namespace App\Imports;

use App\Services\Product\ProductImportService;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;

class ProductsImport implements ToCollection, WithHeadingRow
{
    public function __construct(private readonly ProductImportService $service)
    {
    }

    public function collection(Collection $rows): void
    {
        $this->service->importRows($rows);
    }
}
