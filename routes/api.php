<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductCategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\WarehouseController;
use App\Http\Middleware\EnsureUserHasTenant;
use App\Http\Middleware\SetPermissionsTeamId;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:6,1');

    Route::middleware(['auth:sanctum', EnsureUserHasTenant::class, SetPermissionsTeamId::class])
        ->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);

            Route::apiResource('product-categories', ProductCategoryController::class)
                ->middlewareFor(['index', 'show'], 'can:view-categories')
                ->middlewareFor(['store', 'update', 'destroy'], 'can:manage-categories');

            Route::apiResource('products', ProductController::class)
                ->middlewareFor(['index', 'show'], 'can:view-products')
                ->middlewareFor('store', 'can:create-products')
                ->middlewareFor('update', 'can:update-products')
                ->middlewareFor('destroy', 'can:delete-products');

            Route::apiResource('warehouses', WarehouseController::class)
                ->middlewareFor(['index', 'show'], 'can:view-warehouses')
                ->middlewareFor(['store', 'update', 'destroy'], 'can:manage-warehouses');

            Route::get('products/{product}/stock', [StockController::class, 'index'])
                ->middleware('can:view-stock');

            Route::post('products/{product}/stock/adjust', [StockController::class, 'adjust'])
                ->middleware('can:adjust-stock');

            Route::apiResource('customers', CustomerController::class)
                ->middlewareFor(['index', 'show'], 'can:view-customers')
                ->middlewareFor(['store', 'update', 'destroy'], 'can:manage-customers');

            Route::apiResource('orders', OrderController::class)
                ->only(['index', 'show', 'store'])
                ->middlewareFor(['index', 'show'], 'can:view-orders')
                ->middlewareFor('store', 'can:create-orders');

            Route::post('orders/{order}/confirm', [OrderController::class, 'confirm'])
                ->middleware('can:manage-orders');
            Route::post('orders/{order}/process', [OrderController::class, 'process'])
                ->middleware('can:manage-orders');
            Route::post('orders/{order}/ship', [OrderController::class, 'ship'])
                ->middleware('can:manage-orders');
            Route::post('orders/{order}/deliver', [OrderController::class, 'deliver'])
                ->middleware('can:manage-orders');
            Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])
                ->middleware('can:cancel-orders');
            Route::post('orders/{order}/refund', [OrderController::class, 'refund'])
                ->middleware('can:refund-orders');
        });
});
