<?php

namespace App\Modules\Catalog\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Modules\Catalog\Application\UseCases\CreateCategory;
use App\Modules\Catalog\Application\UseCases\ListCategories;
use App\Modules\Catalog\Presentation\Requests\ListCategoriesRequest;
use App\Modules\Catalog\Presentation\Requests\StoreCategoryRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CategoryController extends Controller
{
    public function index(
        ListCategoriesRequest $request,
        ListCategories $useCase
    ) {
        $perPage = (int) ($request->validated()['per_page'] ?? 15);
        $companyId = (int) $request->attributes->get('active_company_id');
        Gate::forUser($request->user())->authorize(
            'viewAny',
            [Category::class, $companyId]
        );

        return response()->json(
            $useCase->execute($companyId, $perPage)
        );
    }

    public function store(
        StoreCategoryRequest $request,
        CreateCategory $useCase
    ): JsonResponse {
        $companyId = (int) $request->attributes->get('active_company_id');
        Gate::forUser($request->user())->authorize(
            'create',
            [Category::class, $companyId]
        );
        $category = $useCase->execute(
            $companyId,
            $request->validated()
        );

        return response()->json([
            'data' => $category,
        ], 201);
    }

    public function update(
        StoreCategoryRequest $request,
        int $category
    ): JsonResponse {
        $companyId = (int) $request->attributes->get('active_company_id');
        Gate::forUser($request->user())->authorize(
            'update',
            [Category::class, $companyId]
        );
        $model = Category::query()
            ->where('company_id', $companyId)
            ->findOrFail($category);
        $model->fill($request->validated());
        $model->save();

        return response()->json(['data' => $model->refresh()]);
    }

    public function setStatus(Request $request, int $category): JsonResponse
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $companyId = (int) $request->attributes->get('active_company_id');
        Gate::forUser($request->user())->authorize(
            'update',
            [Category::class, $companyId]
        );
        $model = Category::query()
            ->where('company_id', $companyId)
            ->findOrFail($category);
        $model->is_active = $data['is_active'];
        $model->save();

        return response()->json(['data' => $model->refresh()]);
    }
}
