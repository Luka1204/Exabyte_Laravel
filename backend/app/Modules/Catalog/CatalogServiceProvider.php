<?php

namespace App\Modules\Catalog;

use Illuminate\Support\ServiceProvider;
use App\Modules\Catalog\Domain\Contracts\CategoryRepository;
use App\Modules\Catalog\Infrastructure\Persistence\EloquentCategoryRepository;

class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            CategoryRepository::class,
            EloquentCategoryRepository::class
        );
    }
}
