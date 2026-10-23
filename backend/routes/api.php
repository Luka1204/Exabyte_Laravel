<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Identity\Presentation\Http\Controllers\AuthController;
use App\Modules\Identity\Presentation\Http\Middleware\AuthenticateJwt;
use App\Modules\Catalog\Presentation\Http\Controllers\CategoryController;
use App\Modules\Tenancy\Presentation\Http\Controllers\CompanyController;
use App\Modules\Tenancy\Presentation\Http\Controllers\CompanyMemberController;
use App\Modules\Tenancy\Presentation\Http\Middleware\RequireCompanyMembership;
use App\Modules\Tenancy\Presentation\Http\Middleware\RequireCompanyOwner;

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
        });
    });
});
