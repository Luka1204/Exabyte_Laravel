<?php

namespace App\Modules\Catalog\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Modules\Catalog\Application\UseCases\CreateProduct;
use App\Modules\Catalog\Application\UseCases\ListProducts;
use App\Modules\Catalog\Presentation\Requests\ListProductsRequest;
use App\Modules\Catalog\Presentation\Requests\StoreProductRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    public function update(StoreProductRequest $request, int $product): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        Gate::forUser($request->user())->authorize(
            'update',
            [Product::class, $companyId]
        );
        $model = Product::query()
            ->where('company_id', $companyId)
            ->findOrFail($product);
        $model->fill($request->validated());
        $model->save();

        return response()->json(['data' => $model->refresh()]);
    }

    public function setStatus(Request $request, int $product): JsonResponse
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $companyId = (int) $request->attributes->get('active_company_id');
        Gate::forUser($request->user())->authorize(
            'update',
            [Product::class, $companyId]
        );
        $model = Product::query()
            ->where('company_id', $companyId)
            ->findOrFail($product);
        $model->is_active = $data['is_active'];
        $model->save();

        return response()->json(['data' => $model->refresh()]);
    }
}
