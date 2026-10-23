<?php

namespace App\Modules\Catalog\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Application\UseCases\CreateCategory;
use App\Modules\Catalog\Application\UseCases\ListCategories;
use App\Models\Category;
use App\Modules\Catalog\Presentation\Requests\StoreCategoryRequest;
use App\Modules\Catalog\Presentation\Requests\ListCategoriesRequest;
use Illuminate\Http\JsonResponse;
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
}
