<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;



Route::get('/hello', function () {
    return response()->json(['message' => 'Hello, World!']);
});




// AUTHENTICATION
Route::post("/login", [AuthController::class, 'login']);


Route::middleware('auth:sanctum')->group(function () {



    // Admin-only routes
    Route::middleware('admin')->prefix('admin')->group(function () {

        Route::get('/dashboard', function () {
            return response()->json(['message' => 'Welcome, Admin!']);
        });

        Route::post("/create-user", [AuthController::class, 'createUser']);

    });
});
