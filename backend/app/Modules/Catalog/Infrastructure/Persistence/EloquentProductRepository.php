<?php

namespace App\Modules\Catalog\Infrastructure\Persistence;

use App\Models\Product;
use App\Modules\Catalog\Domain\Contracts\ProductRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentProductRepository implements ProductRepository
{
    public function paginate(int $companyId, int $perPage = 15): LengthAwarePaginator
    {
        return Product::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->paginate(min(max($perPage, 1), 100));
    }

    public function create(int $companyId, array $data): Product
    {
        return Product::query()->create([
            ...$data,
            'company_id' => $companyId,
        ]);
    }
}
