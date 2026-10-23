<?php

namespace App\Modules\Catalog\Domain\Contracts;

use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CategoryRepository
{
    public function paginate(
        int $companyId,
        int $perPage = 15
    ): LengthAwarePaginator;

    public function create(int $companyId, array $data): Category;

    public function existsByName(int $companyId, string $name): bool;
}
