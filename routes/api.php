<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
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



    // Admin-only routes
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::post("/create-user", [AuthController::class, 'createUser']);
    });
});
