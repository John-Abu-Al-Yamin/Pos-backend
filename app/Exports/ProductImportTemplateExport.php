<?php

namespace App\Exports;

use App\Models\Brand;
use App\Models\Category;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductImportTemplateExport implements FromArray, WithHeadings, WithEvents
{
    private const TYPE_OPTIONS = ['mobile', 'accessory', 'spare_part'];
    private const TYPE_COLUMN = 'B';
    private const CATEGORY_COLUMN = 'C';
    private const BRAND_COLUMN = 'D';
    private const FIRST_DATA_ROW = 2;
    private const LAST_DATA_ROW = 1000;
    private const LIST_SHEET_TITLE = 'Template Lists';

    public function headings(): array
    {
        return ['name', 'type', 'category_name', 'brand_name', 'min_stock'];
    }

    public function array(): array
    {
        return [
            ['iPhone 15', 'mobile', 'Mobile Phones', 'Apple', 5],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $spreadsheet = $event->sheet->getDelegate()->getParent();
                $listSheet = $this->createListSheet($spreadsheet);

                $typeRange = $this->writeList($listSheet, 'A', self::TYPE_OPTIONS);
                $categoryRange = $this->writeList(
                    $listSheet,
                    'B',
                    Category::query()->orderBy('name')->pluck('name')->all()
                );
                $brandRange = $this->writeList(
                    $listSheet,
                    'C',
                    Brand::query()->orderBy('name')->pluck('name')->all()
                );

                for ($row = self::FIRST_DATA_ROW; $row <= self::LAST_DATA_ROW; $row++) {
                    $sheet = $event->sheet->getDelegate();

                    $sheet->getCell(self::TYPE_COLUMN . $row)
                        ->setDataValidation($this->listValidation($typeRange, false, 'Product type', 'Select mobile, accessory, or spare_part.'));

                    $sheet->getCell(self::CATEGORY_COLUMN . $row)
                        ->setDataValidation($this->listValidation($categoryRange, false, 'Category', 'Select an existing category.'));

                    $sheet->getCell(self::BRAND_COLUMN . $row)
                        ->setDataValidation($this->listValidation($brandRange, true, 'Brand', 'Select an existing brand, or leave blank.'));
                }
            },
        ];
    }

    private function createListSheet(Spreadsheet $spreadsheet): Worksheet
    {
        $sheet = new Worksheet($spreadsheet, self::LIST_SHEET_TITLE);
        $spreadsheet->addSheet($sheet);
        $sheet->setSheetState(Worksheet::SHEETSTATE_VERYHIDDEN);

        return $sheet;
    }

    private function writeList(Worksheet $sheet, string $column, array $values): string
    {
        $values = array_values(array_filter($values, fn($value) => $value !== null && $value !== ''));

        if (empty($values)) {
            $values = [''];
        }

        foreach ($values as $index => $value) {
            $sheet->setCellValue($column . ($index + 1), $value);
        }

        $lastRow = count($values);

        return sprintf("'%s'!$%s$1:$%s$%d", self::LIST_SHEET_TITLE, $column, $column, $lastRow);
    }

    private function listValidation(string $formula, bool $allowBlank, string $title, string $prompt): DataValidation
    {
        $validation = new DataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank($allowBlank);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setShowDropDown(true);
        $validation->setErrorTitle('Invalid value');
        $validation->setError('Please select a value from the drop-down list.');
        $validation->setPromptTitle($title);
        $validation->setPrompt($prompt);
        $validation->setFormula1($formula);

        return $validation;
    }
}
