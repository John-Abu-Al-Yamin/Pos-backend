<?php

namespace App\Http\Controllers;

use App\Exceptions\OpeningStockImportException;
use App\Exports\OpeningStockTemplateExport;
use App\Http\Requests\OpeningStock\ImportOpeningStockRequest;
use App\Http\Responses\ApiResponse;
use App\Services\OpeningStock\OpeningStockImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class OpeningStockController extends Controller
{
    public function __construct(private readonly OpeningStockImportService $openingStockImportService)
    {
    }

    public function import(ImportOpeningStockRequest $request)
    {
        try {
            $summary = $this->openingStockImportService->import(
                $request->file('file'),
                $request->validated('template_type')
            );
        } catch (OpeningStockImportException $exception) {
            return ApiResponse::error(
                message: $exception->getMessage(),
                statusCode: 422,
                errors: $exception->errors()
            );
        } catch (Throwable $exception) {
            Log::error('Opening stock import failed unexpectedly', [
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            return ApiResponse::error(
                message: 'Opening stock import failed. Please try again or contact support.',
                statusCode: 500
            );
        }

        return ApiResponse::success(
            message: 'Opening stock imported successfully',
            data: $summary
        );
    }

    public function template(Request $request)
    {
        $templateType = $request->query('template_type', OpeningStockTemplateExport::TYPE_MOBILE);

        if (! in_array($templateType, [OpeningStockTemplateExport::TYPE_MOBILE, OpeningStockTemplateExport::TYPE_QUANTITY], true)) {
            return ApiResponse::error(
                message: 'Invalid opening stock template type.',
                statusCode: 422,
                errors: [
                    [
                        'field' => 'template_type',
                        'message' => 'template_type must be mobile or quantity.',
                    ],
                ]
            );
        }

        $filename = $templateType === OpeningStockTemplateExport::TYPE_QUANTITY
            ? 'opening_stock_accessories_spare_parts_template.xlsx'
            : 'opening_stock_mobile_template.xlsx';

        return Excel::download(new OpeningStockTemplateExport($templateType), $filename);
    }
}
