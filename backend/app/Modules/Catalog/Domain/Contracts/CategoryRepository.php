<?php

namespace App\Modules\Catalog\Domain\Contracts;

use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CategoryRepository
{
    public function paginate(int $perPage = 15): LengthAwarePaginator;

    public function create(array $data): Category;

    public function existsByName(string $name): bool;
}
