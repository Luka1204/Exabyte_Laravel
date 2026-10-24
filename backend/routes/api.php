<?php

use App\Modules\CashManagement\Presentation\Http\Controllers\CashSessionController;
use App\Modules\Catalog\Presentation\Http\Controllers\CategoryController;
use App\Modules\Catalog\Presentation\Http\Controllers\ProductController;
use App\Modules\Identity\Presentation\Http\Controllers\AuthController;
use App\Modules\Identity\Presentation\Http\Middleware\AuthenticateJwt;
use App\Modules\Inventory\Presentation\Http\Controllers\InventoryController;
use App\Modules\Sales\Presentation\Http\Controllers\SaleController;
use App\Modules\Reporting\Presentation\Http\Controllers\SalesReportController;
use App\Modules\Tenancy\Presentation\Http\Controllers\CompanyController;
use App\Modules\Tenancy\Presentation\Http\Controllers\CompanyMemberController;
use App\Modules\Tenancy\Presentation\Http\Middleware\RequireCompanyMembership;
use App\Modules\Tenancy\Presentation\Http\Middleware\RequireCompanyOwner;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1');
    Route::post('/auth/refresh', [AuthController::class, 'refresh'])
        ->middleware('throttle:10,1');

    Route::middleware(AuthenticateJwt::class)->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/companies', [CompanyController::class, 'index']);
        Route::post('/companies', [CompanyController::class, 'store']);
        Route::prefix('/companies/{company}/members')
            ->middleware(RequireCompanyOwner::class)
            ->group(function () {
                Route::get('/', [CompanyMemberController::class, 'index']);
                Route::patch('/{member}', [CompanyMemberController::class, 'update']);
                Route::delete('/{member}', [CompanyMemberController::class, 'destroy']);
            });

        Route::middleware(RequireCompanyMembership::class)->group(function () {
            Route::get('/categories', [CategoryController::class, 'index']);
            Route::post('/categories', [CategoryController::class, 'store']);
            Route::get('/products', [ProductController::class, 'index']);
            Route::post('/products', [ProductController::class, 'store']);
            Route::get('/inventory/stocks', [InventoryController::class, 'stocks']);
            Route::get('/inventory/movements', [InventoryController::class, 'movements']);
            Route::post('/inventory/adjustments', [InventoryController::class, 'adjust']);
            Route::get('/cash/session', [CashSessionController::class, 'current']);
            Route::post('/cash/session', [CashSessionController::class, 'open']);
            Route::post('/cash/session/{session}/close', [CashSessionController::class, 'close']);
            Route::get('/sales', [SaleController::class, 'index']);
            Route::post('/sales', [SaleController::class, 'store']);
            Route::post('/sales/{sale}/void', [SaleController::class, 'void']);
            Route::post('/sales/{sale}/returns', [SaleController::class, 'storeReturn']);
            Route::get('/reports/sales', [SalesReportController::class, 'summary']);
            Route::get('/reports/inventory', [SalesReportController::class, 'inventory']);
            Route::get('/reports/cash', [SalesReportController::class, 'cash']);
        });
    });
});
