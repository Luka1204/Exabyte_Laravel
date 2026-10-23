<?php

namespace App\Modules\Catalog\Application\UseCases;

use App\Models\Product;
use App\Modules\Catalog\Domain\Contracts\ProductRepository;

class CreateProduct
{
    public function __construct(private ProductRepository $products) {}

    public function execute(int $companyId, array $data): Product
    {
        $data['name'] = trim($data['name']);
        $data['sku'] = trim($data['sku']);
        $data['barcode'] = isset($data['barcode']) && trim($data['barcode']) !== ''
            ? trim($data['barcode'])
            : null;
        $data['tax_rate'] ??= 0;
        $data['is_active'] ??= true;

        return $this->products->create($companyId, $data);
    }
}
