<?php

namespace App\Modules\Catalog\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Modules\Catalog\Application\UseCases\CreateProduct;
use App\Modules\Catalog\Application\UseCases\ListProducts;
use App\Modules\Catalog\Presentation\Requests\ListProductsRequest;
use App\Modules\Catalog\Presentation\Requests\StoreProductRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ProductController extends Controller
{
    public function index(ListProductsRequest $request, ListProducts $useCase)
    {
        $perPage = (int) ($request->validated()['per_page'] ?? 15);
        $companyId = (int) $request->attributes->get('active_company_id');
        Gate::forUser($request->user())->authorize(
            'viewAny',
            [Product::class, $companyId]
        );

        return response()->json($useCase->execute($companyId, $perPage));
    }

    public function store(StoreProductRequest $request, CreateProduct $useCase): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        Gate::forUser($request->user())->authorize(
            'create',
            [Product::class, $companyId]
        );

        $product = $useCase->execute($companyId, $request->validated());

        return response()->json(['data' => $product], 201);
    }
}
