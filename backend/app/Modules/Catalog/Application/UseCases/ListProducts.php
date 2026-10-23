<?php

namespace App\Modules\Catalog\Application\UseCases;

use App\Modules\Catalog\Domain\Contracts\ProductRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListProducts
{
    public function __construct(private ProductRepository $products) {}

    public function execute(int $companyId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->products->paginate($companyId, $perPage);
    }
}
