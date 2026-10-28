<?php

use App\Modules\Activity\Presentation\Http\Controllers\CompanyActivityController;
use App\Modules\Activity\Presentation\Http\Middleware\RecordCompanyActivity;
use App\Modules\Activity\Presentation\Http\Middleware\RequireCompanyAdmin;
use App\Modules\CashManagement\Presentation\Http\Controllers\CashSessionController;
use App\Modules\Catalog\Presentation\Http\Controllers\CategoryController;
use App\Modules\Catalog\Presentation\Http\Controllers\ProductController;
use App\Modules\Identity\Presentation\Http\Controllers\AuthController;
use App\Modules\Identity\Presentation\Http\Middleware\AuthenticateJwt;
use App\Modules\Inventory\Presentation\Http\Controllers\InventoryController;
use App\Modules\Reporting\Presentation\Http\Controllers\SalesReportController;
use App\Modules\Sales\Presentation\Http\Controllers\SaleController;
use App\Modules\Subscriptions\Presentation\Http\Controllers\CompanySubscriptionController;
use App\Modules\Subscriptions\Presentation\Http\Controllers\MercadoPagoWebhookController;
use App\Modules\Subscriptions\Presentation\Http\Controllers\PlanController;
use App\Modules\Subscriptions\Presentation\Http\Controllers\PlatformBillingController;
use App\Modules\Subscriptions\Presentation\Http\Middleware\RequirePlatformAdmin;
use App\Modules\Subscriptions\Presentation\Http\Middleware\RequireSubscriptionEntitlement;
use App\Modules\Tenancy\Presentation\Http\Controllers\CompanyController;
use App\Modules\Tenancy\Presentation\Http\Controllers\CompanyMemberController;
use App\Modules\Tenancy\Presentation\Http\Middleware\RequireCompanyMembership;
use App\Modules\Tenancy\Presentation\Http\Middleware\RequireCompanyOwner;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register'])
        ->middleware('throttle:3,1');
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1');
    Route::post('/auth/refresh', [AuthController::class, 'refresh'])
        ->middleware('throttle:10,1');
    Route::get('/plans', [PlanController::class, 'index']);
    Route::post('/payments/mercadopago/webhook', MercadoPagoWebhookController::class)
        ->middleware('throttle:120,1');

    Route::middleware([AuthenticateJwt::class, RecordCompanyActivity::class])->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::prefix('/platform')
            ->middleware(RequirePlatformAdmin::class)
            ->group(function () {
                Route::get('/plans', [PlanController::class, 'adminIndex']);
                Route::post('/plans', [PlanController::class, 'store']);
                Route::put('/plans/{plan}', [PlanController::class, 'update']);
                Route::get('/subscriptions', [PlatformBillingController::class, 'subscriptions']);
                Route::get('/payments', [PlatformBillingController::class, 'payments']);
                Route::get('/metrics', [PlatformBillingController::class, 'metrics']);
            });
        Route::prefix('/companies/{company}/subscription')
            ->middleware(RequireCompanyOwner::class)
            ->group(function () {
                Route::get('/', [CompanySubscriptionController::class, 'show']);
                Route::post('/', [CompanySubscriptionController::class, 'start'])
                    ->middleware('throttle:10,1');
                Route::put('/', [CompanySubscriptionController::class, 'change'])
                    ->middleware('throttle:10,1');
                Route::delete('/', [CompanySubscriptionController::class, 'cancel']);
            });
        Route::get('/companies', [CompanyController::class, 'index']);
        Route::post('/companies', [CompanyController::class, 'store']);
        Route::patch('/companies/{company}', [CompanyController::class, 'update'])
            ->middleware(RequireCompanyOwner::class);
        Route::prefix('/companies/{company}/members')
            ->middleware(RequireCompanyOwner::class)
            ->group(function () {
                Route::get('/', [CompanyMemberController::class, 'index']);
                Route::patch('/{member}', [CompanyMemberController::class, 'update']);
                Route::delete('/{member}', [CompanyMemberController::class, 'destroy']);
            });

        Route::middleware(RequireCompanyMembership::class)->group(function () {
            Route::get('/activity', [CompanyActivityController::class, 'index'])
                ->middleware(RequireCompanyAdmin::class);
            Route::middleware(RequireSubscriptionEntitlement::class.':catalog')->group(function () {
                Route::get('/categories', [CategoryController::class, 'index']);
                Route::post('/categories', [CategoryController::class, 'store']);
                Route::put('/categories/{category}', [CategoryController::class, 'update']);
                Route::patch('/categories/{category}/status', [CategoryController::class, 'setStatus']);
                Route::get('/products', [ProductController::class, 'index']);
                Route::post('/products', [ProductController::class, 'store']);
                Route::put('/products/{product}', [ProductController::class, 'update']);
                Route::patch('/products/{product}/status', [ProductController::class, 'setStatus']);
            });
            Route::middleware(RequireSubscriptionEntitlement::class.':inventory')->group(function () {
                Route::get('/inventory/stocks', [InventoryController::class, 'stocks']);
                Route::get('/inventory/movements', [InventoryController::class, 'movements']);
                Route::post('/inventory/adjustments', [InventoryController::class, 'adjust']);
            });
            Route::middleware(RequireSubscriptionEntitlement::class.':pos')->group(function () {
                Route::get('/cash/session', [CashSessionController::class, 'current']);
                Route::post('/cash/session', [CashSessionController::class, 'open']);
                Route::post('/cash/session/{session}/close', [CashSessionController::class, 'close']);
                Route::get('/sales', [SaleController::class, 'index']);
                Route::post('/sales', [SaleController::class, 'store']);
                Route::post('/sales/{sale}/void', [SaleController::class, 'void']);
                Route::post('/sales/{sale}/returns', [SaleController::class, 'storeReturn']);
            });
            Route::get('/reports/sales', [SalesReportController::class, 'summary'])
                ->middleware(RequireSubscriptionEntitlement::class.':sales_reports');
            Route::get('/reports/inventory', [SalesReportController::class, 'inventory'])
                ->middleware(RequireSubscriptionEntitlement::class.':inventory_reports');
            Route::get('/reports/cash', [SalesReportController::class, 'cash'])
                ->middleware(RequireSubscriptionEntitlement::class.':cash_reports');
        });
    });
});
