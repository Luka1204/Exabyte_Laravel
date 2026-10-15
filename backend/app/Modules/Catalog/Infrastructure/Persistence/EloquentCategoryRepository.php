<?php

namespace App\Modules\Catalog\Infrastructure\Persistence;

use App\Models\Category;
use App\Modules\Catalog\Domain\Contracts\CategoryRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentCategoryRepository implements CategoryRepository
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Category::query()
            ->orderBy('name')
            ->paginate(min(max($perPage, 1), 100));
    }

    public function create(array $data): Category
    {
        return Category::query()->create($data);
    }

    public function existsByName(string $name): bool
    {
        return Category::query()
            ->where('name', $name)
            ->exists();
    }
}
