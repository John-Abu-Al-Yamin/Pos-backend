<?php

namespace App\Imports;

use App\Services\OpeningStock\OpeningStockImportService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class OpeningStockImport implements ToCollection, WithHeadingRow
{
    public function __construct(
        private readonly OpeningStockImportService $service,
        private readonly string $templateType
    )
    {
    }

    public function collection(Collection $rows): void
    {
        $this->service->importRows($rows, $this->templateType);
    }
}
