<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Services\Invoice\InvoiceDataService;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceDataService $invoiceDataService)
    {
    }

    public function purchase(int $id)
    {
        try {
            $invoice = $this->invoiceDataService->forPurchase($id);
        } catch (ModelNotFoundException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        } catch (\DomainException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(
            message: 'Invoice generated successfully',
            data: $invoice
        );
    }

    public function sale(int $id)
    {
        try {
            $invoice = $this->invoiceDataService->forSale($id);
        } catch (ModelNotFoundException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        }

        return ApiResponse::success(
            message: 'Invoice generated successfully',
            data: $invoice
        );
    }
}
