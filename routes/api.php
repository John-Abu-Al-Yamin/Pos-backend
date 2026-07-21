<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\InventoryItemController;
use App\Http\Controllers\InventoryQuantityController;
use App\Http\Controllers\MarkupSettingController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseHeaderController;
use App\Http\Controllers\PurchaseItemController;
use App\Http\Controllers\PurchaseReturnableController;
use App\Http\Controllers\PurchaseReturnHeaderController;
use App\Http\Controllers\SalesHeaderController;
use App\Http\Controllers\SalesReturnHeaderController;
use App\Http\Controllers\SalesReturnableController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UsedDevicePurchaseHeaderController;
use App\Http\Controllers\UsedDevicePurchaseItemController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/hello', function () {
    return response()->json(['message' => 'Hello, World!']);
});

// AUTHENTICATION
Route::post("/login", [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Category routes
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::get('/categories/{id}', [CategoryController::class, 'show']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

    // Supplier routes
    Route::get('/suppliers', [SupplierController::class, 'index']);
    Route::post('/suppliers', [SupplierController::class, 'store']);
    Route::get('/suppliers/{id}', [SupplierController::class, 'show']);
    Route::put('/suppliers/{id}', [SupplierController::class, 'update']);
    Route::delete('/suppliers/{id}', [SupplierController::class, 'destroy']);

    // Customer routes
    Route::get('/customers', [CustomerController::class, 'index']);
    Route::post('/customers', [CustomerController::class, 'store']);
    Route::get('/customers/{id}', [CustomerController::class, 'show']);
    Route::put('/customers/{id}', [CustomerController::class, 'update']);
    Route::delete('/customers/{id}', [CustomerController::class, 'destroy']);

    // Brand routes
    Route::get('/brands', [BrandController::class, 'index']);
    Route::post('/brands', [BrandController::class, 'store']);
    Route::get('/brands/{id}', [BrandController::class, 'show']);
    Route::put('/brands/{id}', [BrandController::class, 'update']);
    Route::delete('/brands/{id}', [BrandController::class, 'destroy']);

    // Markup Setting routes
    Route::get('/markup-settings', [MarkupSettingController::class, 'index']);
    Route::post('/markup-settings', [MarkupSettingController::class, 'store']);
    Route::get('/markup-settings/{id}', [MarkupSettingController::class, 'show']);
    Route::put('/markup-settings/{id}', [MarkupSettingController::class, 'update']);
    Route::delete('/markup-settings/{id}', [MarkupSettingController::class, 'destroy']);

    // Product routes
    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);

    // Purchase Header routes
    Route::get('/purchase-headers', [PurchaseHeaderController::class, 'index']);
    Route::post('/purchase-headers', [PurchaseHeaderController::class, 'store']);
    Route::get('/purchase-headers/{id}', [PurchaseHeaderController::class, 'show']);
    Route::put('/purchase-headers/{id}', [PurchaseHeaderController::class, 'update']);
    Route::delete('/purchase-headers/{id}', [PurchaseHeaderController::class, 'destroy']);
    Route::post('/purchase-headers/{purchase}/complete', [PurchaseHeaderController::class, 'complete']);
    Route::post('/purchase-headers/{purchase}/cancel', [PurchaseHeaderController::class, 'cancel']);

    // Purchase Item routes
    Route::get('/purchase-items', [PurchaseItemController::class, 'index']);
    Route::post('/purchase-items', [PurchaseItemController::class, 'store']);
    Route::get('/purchase-items/{id}', [PurchaseItemController::class, 'show']);
    Route::put('/purchase-items/{id}', [PurchaseItemController::class, 'update']);
    Route::delete('/purchase-items/{id}', [PurchaseItemController::class, 'destroy']);

    // Inventory Quantities
    Route::get('/inventory-quantities', [InventoryQuantityController::class, 'index']);
    Route::get('/inventory-quantities/{id}', [InventoryQuantityController::class, 'show']);

    // Inventory Items
    Route::get('/inventory-items', [InventoryItemController::class, 'index']);
    Route::get('/inventory-items/{id}', [InventoryItemController::class, 'show']);

    // Stock Movements
    Route::get('/stock-movements', [StockMovementController::class, 'index']);
    Route::get('/stock-movements/{id}', [StockMovementController::class, 'show']);

    // Used Device Purchase Header routes
    Route::get('/used-purchase-headers', [UsedDevicePurchaseHeaderController::class, 'index']);
    Route::post('/used-purchase-headers', [UsedDevicePurchaseHeaderController::class, 'store']);
    Route::get('/used-purchase-headers/{id}', [UsedDevicePurchaseHeaderController::class, 'show']);
    Route::put('/used-purchase-headers/{id}', [UsedDevicePurchaseHeaderController::class, 'update']);
    Route::delete('/used-purchase-headers/{id}', [UsedDevicePurchaseHeaderController::class, 'destroy']);
    Route::post('/used-purchase-headers/{purchase}/complete', [UsedDevicePurchaseHeaderController::class, 'complete']);
    Route::post('/used-purchase-headers/{purchase}/cancel', [UsedDevicePurchaseHeaderController::class, 'cancel']);

    // Used Device Purchase Item routes (nested under purchase header)
    Route::get('/used-purchase-headers/{purchase}/items', [UsedDevicePurchaseItemController::class, 'index']);
    Route::post('/used-purchase-headers/{purchase}/items', [UsedDevicePurchaseItemController::class, 'store']);
    Route::get('/used-purchase-headers/{purchase}/items/{item}', [UsedDevicePurchaseItemController::class, 'show']);
    Route::put('/used-purchase-headers/{purchase}/items/{item}', [UsedDevicePurchaseItemController::class, 'update']);
    Route::delete('/used-purchase-headers/{purchase}/items/{item}', [UsedDevicePurchaseItemController::class, 'destroy']);

    // Users
    Route::get('/users', [AuthController::class, 'index']);

    // Sales routes
    Route::get('/pos/sales', [PosController::class, 'index']);
    Route::post('/pos/checkout', [PosController::class, 'checkout']);
    Route::get('/sales-headers', [SalesHeaderController::class, 'index']);
    Route::get('/sales-headers/{id}', [SalesHeaderController::class, 'show']);

    // Sales Returnable (eligible for return)
    Route::get('/sales-returnable', [SalesReturnableController::class, 'index']);
    Route::get('/sales-returnable/{id}', [SalesReturnableController::class, 'show']);

    // Sales Return routes
    Route::get('/sales-returns', [SalesReturnHeaderController::class, 'index']);
    Route::post('/sales-returns', [SalesReturnHeaderController::class, 'store']);
    Route::get('/sales-returns/{id}', [SalesReturnHeaderController::class, 'show']);


    // Purchase Returnable (eligible for return)
    Route::get('/purchase-returnable', [PurchaseReturnableController::class, 'index']);
    Route::get('/purchase-returnable/{id}', [PurchaseReturnableController::class, 'show']);

    // Purchase Return routes
    Route::get('/purchase-returns', [PurchaseReturnHeaderController::class, 'index']);
    Route::get('/purchase-returns/{id}', [PurchaseReturnHeaderController::class, 'show']);
    Route::post('/purchase-returns', [PurchaseReturnHeaderController::class, 'store']);


    // Admin-only routes
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::post("/create-user", [AuthController::class, 'createUser']);
    });
});
