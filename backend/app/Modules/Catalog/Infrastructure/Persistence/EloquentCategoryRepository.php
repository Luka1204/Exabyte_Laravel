<?php

namespace App\Modules\Catalog\Infrastructure\Persistence;

use App\Models\Category;
use App\Modules\Catalog\Domain\Contracts\CategoryRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentCategoryRepository implements CategoryRepository
{
    public function paginate(
        int $companyId,
        int $perPage = 15
    ): LengthAwarePaginator
    {
        return Category::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->paginate(min(max($perPage, 1), 100));
    }

    public function create(int $companyId, array $data): Category
    {
        return Category::query()->create([
            ...$data,
            'company_id' => $companyId,
        ]);
    }

    public function existsByName(int $companyId, string $name): bool
    {
        return Category::query()
            ->where('company_id', $companyId)
            ->where('name', $name)
            ->exists();
    }
}
