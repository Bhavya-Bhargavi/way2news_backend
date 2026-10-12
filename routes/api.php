<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminCategoryController;
use App\Http\Controllers\Admin\AdminNewsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\NewsController;

Route::prefix('auth')->group(function () {
    Route::post('/otp/request', [AuthController::class, 'requestOtp'])->middleware('throttle:otp');
    Route::post('/otp/verify', [AuthController::class, 'verifyOtp'])->middleware('throttle:otp');
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::get('/news', [NewsController::class, 'index']);
Route::get('/news/{news:slug}', [NewsController::class, 'show']);
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{category:slug}/news', [NewsController::class, 'byCategory']);

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::apiResource('news', AdminNewsController::class);
    Route::apiResource('categories', AdminCategoryController::class);
});
