<?php

namespace App\Providers;

use App\Models\CashSession;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sale;
use App\Policies\CashManagementPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\InventoryPolicy;
use App\Policies\ProductPolicy;
use App\Policies\SalePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(CashSession::class, CashManagementPolicy::class);
        Gate::policy(InventoryMovement::class, InventoryPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Sale::class, SalePolicy::class);
    }
}
