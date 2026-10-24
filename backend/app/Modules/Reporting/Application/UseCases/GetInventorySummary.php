<?php

namespace App\Modules\Reporting\Application\UseCases;

use App\Models\InventoryMovement;
use App\Models\Product;

class GetInventorySummary
{
    /**
     * @return array{
     *     products_count: int,
     *     active_products_count: int,
     *     products_with_stock: int,
     *     out_of_stock_products: int,
     *     movements: array<string, int>
     * }
     */
    public function execute(int $companyId, string $from, string $to): array
    {
        $products = Product::query()
            ->leftJoin('inventory_balances', function ($join) use ($companyId): void {
                $join->on('inventory_balances.product_id', '=', 'products.id')
                    ->where('inventory_balances.company_id', '=', $companyId);
            })
            ->where('products.company_id', $companyId)
            ->selectRaw('COUNT(*) as products_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN products.is_active = 1 THEN 1 ELSE 0 END), 0) as active_products_count')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN products.is_active = 1 AND COALESCE(inventory_balances.quantity, 0) > 0 THEN 1 ELSE 0 END), 0) as products_with_stock'
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN products.is_active = 1 AND COALESCE(inventory_balances.quantity, 0) <= 0 THEN 1 ELSE 0 END), 0) as out_of_stock_products'
            )
            ->first();

        $movementCounts = InventoryMovement::query()
            ->where('company_id', $companyId)
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->selectRaw('type, COUNT(*) as movement_count')
            ->groupBy('type')
            ->pluck('movement_count', 'type');

        return [
            'products_count' => (int) $products->products_count,
            'active_products_count' => (int) $products->active_products_count,
            'products_with_stock' => (int) $products->products_with_stock,
            'out_of_stock_products' => (int) $products->out_of_stock_products,
            'movements' => [
                InventoryMovement::TYPE_ADJUSTMENT => (int) ($movementCounts[InventoryMovement::TYPE_ADJUSTMENT] ?? 0),
                InventoryMovement::TYPE_SALE => (int) ($movementCounts[InventoryMovement::TYPE_SALE] ?? 0),
                InventoryMovement::TYPE_SALE_VOID => (int) ($movementCounts[InventoryMovement::TYPE_SALE_VOID] ?? 0),
                InventoryMovement::TYPE_SALE_RETURN => (int) ($movementCounts[InventoryMovement::TYPE_SALE_RETURN] ?? 0),
            ],
        ];
    }
}
