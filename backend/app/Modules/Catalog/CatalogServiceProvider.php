<?php

namespace App\Modules\Catalog;

use App\Modules\Catalog\Domain\Contracts\CategoryRepository;
use App\Modules\Catalog\Domain\Contracts\ProductRepository;
use App\Modules\Catalog\Infrastructure\Persistence\EloquentCategoryRepository;
use App\Modules\Catalog\Infrastructure\Persistence\EloquentProductRepository;
use Illuminate\Support\ServiceProvider;

class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            CategoryRepository::class,
            EloquentCategoryRepository::class
        );
        $this->app->bind(
            ProductRepository::class,
            EloquentProductRepository::class
        );
    }
}
