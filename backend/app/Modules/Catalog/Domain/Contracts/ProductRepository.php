<?php

namespace App\Modules\Catalog\Domain\Contracts;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProductRepository
{
    public function paginate(int $companyId, int $perPage = 15): LengthAwarePaginator;

    public function create(int $companyId, array $data): Product;
}
