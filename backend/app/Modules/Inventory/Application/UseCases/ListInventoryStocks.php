<?php

namespace App\Modules\Inventory\Application\UseCases;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListInventoryStocks
{
    public function execute(int $companyId, int $perPage): LengthAwarePaginator
    {
        $stocks = Product::query()
            ->leftJoin('inventory_balances', function ($join) use ($companyId): void {
                $join->on('inventory_balances.product_id', '=', 'products.id')
                    ->where('inventory_balances.company_id', '=', $companyId);
            })
            ->where('products.company_id', $companyId)
            ->orderBy('products.name')
            ->paginate($perPage, [
                'products.id as product_id',
                'products.name',
                'products.sku',
                'products.barcode',
                'products.price',
                'products.tax_rate',
                'products.is_active',
                'inventory_balances.quantity',
            ]);

        return $stocks->through(function (Product $stock): Product {
            $quantity = (string) ($stock->quantity ?? '0');
            $negative = str_starts_with($quantity, '-');
            $unsigned = ltrim($quantity, '+-');
            [$whole, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');
            $stock->quantity = sprintf(
                '%s%s.%s',
                $negative ? '-' : '',
                $whole,
                substr(str_pad($fraction, 3, '0'), 0, 3)
            );
            $stock->is_active = (bool) $stock->is_active;

            return $stock;
        });
    }
}
