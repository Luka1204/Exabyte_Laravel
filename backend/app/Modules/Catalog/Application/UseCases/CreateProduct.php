<?php

namespace App\Modules\Catalog\Application\UseCases;

use App\Models\Product;
use App\Modules\Subscriptions\Application\Services\SubscriptionAccess;
use App\Modules\Catalog\Domain\Contracts\ProductRepository;
use App\Modules\Tenancy\Infrastructure\Persistence\Eloquent\Company;
use Illuminate\Support\Facades\DB;

class CreateProduct
{
    public function __construct(
        private ProductRepository $products,
        private SubscriptionAccess $subscriptionAccess
    ) {}

    public function execute(int $companyId, array $data): Product
    {
        $data['name'] = trim($data['name']);
        $data['sku'] = trim($data['sku']);
        $data['barcode'] = isset($data['barcode']) && trim($data['barcode']) !== ''
            ? trim($data['barcode'])
            : null;
        $data['tax_rate'] ??= 0;
        $data['is_active'] ??= true;

        return DB::transaction(function () use ($companyId, $data): Product {
            Company::query()->whereKey($companyId)->lockForUpdate()->firstOrFail();
            $this->subscriptionAccess->assertProductLimit($companyId);

            return $this->products->create($companyId, $data);
        });
    }
}
