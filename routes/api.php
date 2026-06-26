<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseHeaderController;
use App\Http\Controllers\PurchaseItemController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\StockItemController;
use App\Http\Controllers\SupplierController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;



Route::get('/hello', function () {
    return response()->json(['message' => 'Hello, World!']);
});




// AUTHENTICATION
Route::post("/login", [AuthController::class, 'login']);


Route::middleware('auth:sanctum')->group(function () {

    // Authenticated routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);


    // Category routes
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::get('/categories/{id}', [CategoryController::class, 'show']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

    // Product routes
    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);

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

    // Purchase Header routes
    Route::get('/purchase-headers', [PurchaseHeaderController::class, 'index']);
    Route::post('/purchase-headers', [PurchaseHeaderController::class, 'store']);
    Route::get('/purchase-headers/{id}', [PurchaseHeaderController::class, 'show']);
    Route::put('/purchase-headers/{id}', [PurchaseHeaderController::class, 'update']);
    Route::delete('/purchase-headers/{id}', [PurchaseHeaderController::class, 'destroy']);

    // Purchase Item routes
    Route::get('/purchase-items', [PurchaseItemController::class, 'index']);
    Route::post('/purchase-items', [PurchaseItemController::class, 'store']);
    Route::get('/purchase-items/{id}', [PurchaseItemController::class, 'show']);
    Route::put('/purchase-items/{id}', [PurchaseItemController::class, 'update']);
    Route::delete('/purchase-items/{id}', [PurchaseItemController::class, 'destroy']);

    // Sale routes
    Route::get('/sales', [SaleController::class, 'index']);
    Route::post('/sales', [SaleController::class, 'store']);
    Route::get('/sales/{id}', [SaleController::class, 'show']);
    Route::delete('/sales/{id}', [SaleController::class, 'destroy']);

    // Return routes
    Route::get('/sales/{id}/returnable', [SaleController::class, 'returnable']);
    Route::get('/returns', [ReturnController::class, 'index']);
    Route::post('/returns', [ReturnController::class, 'store']);
    Route::get('/returns/{id}', [ReturnController::class, 'show']);

    // stock_items
    Route::get('/stock-items/available', [StockItemController::class, 'available']);
    Route::get('/stock-items', [StockItemController::class, 'index']);
    Route::get('/stock-items/{id}', [StockItemController::class, 'show']);






    // Admin-only routes
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::post("/create-user", [AuthController::class, 'createUser']);
    });
});
