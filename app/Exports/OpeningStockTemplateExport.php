<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OpeningStockTemplateExport implements FromArray, WithHeadings, WithEvents
{
    public const TYPE_MOBILE = 'mobile';
    public const TYPE_QUANTITY = 'quantity';

    public const MOBILE_HEADINGS = [
        'product_name',
        'serial_number',
        'imei',
        'internal_serial',
        'cost_price',
        'battery_health',
        'screen_condition',
        'body_condition',
        'fingerprint_working',
        'face_id_working',
        'notes',
    ];

    public const QUANTITY_HEADINGS = [
        'product_name',
        'quantity',
        'unit_cost',
        'notes',
    ];

    public const HEADINGS = self::MOBILE_HEADINGS;

    public const MOBILE_EXAMPLE_ROW = [
        'Samsung Galaxy A56',
        'SN123456789',
        '356789123456789',
        'INT-0001',
        12000,
        100,
        'Excellent',
        'Excellent',
        'Yes',
        'No',
        'Sample only',
    ];

    public const QUANTITY_EXAMPLE_ROW = [
        'Samsung 25W Charger',
        20,
        180,
        'Sample only',
    ];

    public const BOOLEAN_OPTIONS = ['Yes', 'No'];
    public const SCREEN_CONDITION_OPTIONS = ['Excellent', 'Good', 'Fair', 'Scratched', 'Cracked', 'Damaged'];
    public const BODY_CONDITION_OPTIONS = ['Excellent', 'Good', 'Fair', 'Worn', 'Damaged'];

    private const FIRST_DATA_ROW = 2;
    private const LAST_DATA_ROW = 1000;
    private const LIST_SHEET_TITLE = 'Opening Stock Lists';

    public function __construct(private readonly string $templateType = self::TYPE_MOBILE)
    {
    }

    public function headings(): array
    {
        return $this->templateType === self::TYPE_QUANTITY
            ? self::QUANTITY_HEADINGS
            : self::MOBILE_HEADINGS;
    }

    public function array(): array
    {
        return [
            $this->templateType === self::TYPE_QUANTITY
                ? self::QUANTITY_EXAMPLE_ROW
                : self::MOBILE_EXAMPLE_ROW,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $spreadsheet = $event->sheet->getDelegate()->getParent();
                $listSheet = $this->createListSheet($spreadsheet);

                $products = Product::query()
                    ->when($this->templateType === self::TYPE_MOBILE, fn ($query) => $query->where('type', 'mobile'))
                    ->when($this->templateType === self::TYPE_QUANTITY, fn ($query) => $query->whereIn('type', ['accessory', 'spare_part']))
                    ->orderBy('name');

                $productNameRange = $this->writeList($listSheet, 'A', $products->pluck('name')->all());
                $booleanRange = $this->writeList($listSheet, 'B', self::BOOLEAN_OPTIONS);
                $screenConditionRange = $this->writeList($listSheet, 'C', self::SCREEN_CONDITION_OPTIONS);
                $bodyConditionRange = $this->writeList($listSheet, 'D', self::BODY_CONDITION_OPTIONS);

                $sheet = $event->sheet->getDelegate();
                $this->styleExampleRow($sheet);

                for ($row = self::FIRST_DATA_ROW; $row <= self::LAST_DATA_ROW; $row++) {
                    $sheet->getCell('A' . $row)
                        ->setDataValidation($this->listValidation($productNameRange, false, 'Product name', 'Select an existing product name.'));

                    if ($this->templateType === self::TYPE_QUANTITY) {
                        $sheet->getCell('B' . $row)
                            ->setDataValidation($this->wholeNumberValidation(1, 999999, false, 'Quantity', 'Enter the opening quantity.'));

                        continue;
                    }

                    $sheet->getCell('F' . $row)
                        ->setDataValidation($this->wholeNumberValidation(0, 100, true, 'Battery health', 'Enter a whole number from 0 to 100.'));

                    $sheet->getCell('G' . $row)
                        ->setDataValidation($this->listValidation($screenConditionRange, true, 'Screen condition', 'Select a screen condition.'));

                    $sheet->getCell('H' . $row)
                        ->setDataValidation($this->listValidation($bodyConditionRange, true, 'Body condition', 'Select a body condition.'));

                    $sheet->getCell('I' . $row)
                        ->setDataValidation($this->listValidation($booleanRange, true, 'Fingerprint working', 'Select Yes or No.'));

                    $sheet->getCell('J' . $row)
                        ->setDataValidation($this->listValidation($booleanRange, true, 'Face ID working', 'Select Yes or No.'));
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
        $values = array_values(array_filter($values, fn ($value) => $value !== null && $value !== ''));

        if (empty($values)) {
            $values = [''];
        }

        foreach ($values as $index => $value) {
            $sheet->setCellValue($column . ($index + 1), $value);
        }

        $lastRow = count($values);

        return sprintf("'%s'!$%s$1:$%s$%d", self::LIST_SHEET_TITLE, $column, $column, $lastRow);
    }

    private function styleExampleRow(Worksheet $sheet): void
    {
        $lastColumn = $this->templateType === self::TYPE_QUANTITY ? 'D' : 'K';

        $sheet->getStyle("A2:{$lastColumn}2")->applyFromArray([
            'font' => [
                'italic' => true,
                'color' => ['rgb' => '666666'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F2F2F2'],
            ],
        ]);
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

    private function wholeNumberValidation(int $min, int $max, bool $allowBlank, string $title, string $prompt): DataValidation
    {
        $validation = new DataValidation();
        $validation->setType(DataValidation::TYPE_WHOLE);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setOperator(DataValidation::OPERATOR_BETWEEN);
        $validation->setAllowBlank($allowBlank);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setErrorTitle('Invalid value');
        $validation->setError("Please enter a whole number from {$min} to {$max}.");
        $validation->setPromptTitle($title);
        $validation->setPrompt($prompt);
        $validation->setFormula1((string) $min);
        $validation->setFormula2((string) $max);

        return $validation;
    }
}
