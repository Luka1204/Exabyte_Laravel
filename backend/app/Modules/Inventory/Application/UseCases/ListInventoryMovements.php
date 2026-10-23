<?php

namespace App\Modules\Inventory\Application\UseCases;

use App\Models\InventoryMovement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListInventoryMovements
{
    public function execute(int $companyId, int $perPage): LengthAwarePaginator
    {
        return InventoryMovement::query()
            ->with([
                'product:id,name,sku',
                'user:id,name',
            ])
            ->where('company_id', $companyId)
            ->latest('id')
            ->paginate($perPage);
    }
}
