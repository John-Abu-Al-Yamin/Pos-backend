<?php

namespace App\Http\Controllers;

use App\Exports\ProductImportTemplateExport;
use App\Http\Requests\Products\ImportProductsRequest;
use App\Http\Requests\Products\StoreProductRequest;
use App\Http\Requests\Products\UpdateProductRequest;
use App\Http\Responses\ApiResponse;
use App\Imports\ProductsImport;
use App\Models\Product;
use App\Services\Product\ProductImportService;
use App\Services\Product\ProductService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class ProductController extends Controller
{
    public function __construct(private readonly ProductService $productService)
    {
    }

    public function store(StoreProductRequest $request)
    {
        $product = $this->productService->create($request->validated());

        return ApiResponse::success(
            message: 'Product created successfully',
            data: $product
        );
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);

        $products = Product::with(['category', 'brand'])
            ->when($request->filled('search'), fn($q) =>
                $q->where('name', 'like', '%' . $request->search . '%')
            )
            ->when($request->filled('category_id'), fn($q) =>
                $q->where('category_id', $request->category_id)
            )
            ->when($request->filled('brand_id'), fn($q) =>
                $q->where('brand_id', $request->brand_id)
            )
            ->when($request->filled('type'), fn($q) =>
                $q->where('type', $request->type)
            )
            ->paginate($perPage);

        return ApiResponse::success(
            message: 'Products fetched successfully',
            data: $products
        );
    }

    public function show(int $id)
    {
        $product = Product::with(['category', 'brand'])->find($id);

        if (!$product) {
            return ApiResponse::error(
                message: 'Product not found',
                statusCode: 404
            );
        }

        return ApiResponse::success(
            data: $product,
            message: 'Product fetched successfully'
        );
    }

    public function update(UpdateProductRequest $request, int $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return ApiResponse::error(
                message: 'Product not found',
                statusCode: 404
            );
        }

        $product->update($request->validated());

        return ApiResponse::success(
            data: $product,
            message: 'Product updated successfully'
        );
    }

    public function destroy(int $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return ApiResponse::error(
                message: 'Product not found',
                statusCode: 404
            );
        }

        $product->delete();

        return ApiResponse::success(
            message: 'Product deleted successfully'
        );
    }

    public function import(ImportProductsRequest $request, ProductImportService $service)
    {
        $startedAt = microtime(true);
        Log::info('Product import started');

        Excel::import(new ProductsImport($service), $request->file('file'));

        $summary = $service->summary();
        $duration = round(microtime(true) - $startedAt, 2);

        Log::info('Product import finished', [
            'duration' => $duration,
            'total_rows' => $summary['total_rows'],
            'created' => $summary['created'],
            'skipped' => $summary['skipped'],
            'failed' => $summary['failed'],
        ]);

        return ApiResponse::success(
            message: 'Product import completed',
            data: array_merge($summary, ['duration_seconds' => $duration])
        );
    }

    public function importTemplate()
    {
        return Excel::download(new ProductImportTemplateExport(), 'product-import-template.xlsx');
    }
}
