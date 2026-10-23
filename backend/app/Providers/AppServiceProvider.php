<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Policies\CategoryPolicy;
use App\Policies\InventoryPolicy;
use App\Policies\ProductPolicy;
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
        Gate::policy(InventoryMovement::class, InventoryPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
    }
}
