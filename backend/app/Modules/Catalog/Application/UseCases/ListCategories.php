<?php

namespace App\Modules\Catalog\Application\UseCases;

use App\Modules\Catalog\Domain\Contracts\CategoryRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListCategories
{
    public function __construct(
        private CategoryRepository $categories
    ) {}

    public function execute(
        int $companyId,
        int $perPage = 15
    ): LengthAwarePaginator
    {
        return $this->categories->paginate($companyId, $perPage);
    }
}
