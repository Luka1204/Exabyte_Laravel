<?php

namespace App\Modules\Catalog\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Application\UseCases\CreateCategory;
use App\Modules\Catalog\Application\UseCases\ListCategories;
use App\Modules\Catalog\Presentation\Requests\StoreCategoryRequest;
use App\Modules\Catalog\Presentation\Requests\ListCategoriesRequest;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function index(
        ListCategoriesRequest $request,
        ListCategories $useCase
    ) {
        $perPage = (int) $request->validated()['per_page'] ?? 15;

        return response()->json(
            $useCase->execute($perPage)
        );
    }

    public function store(
        StoreCategoryRequest $request,
        CreateCategory $useCase
    ): JsonResponse {
        $category = $useCase->execute($request->validated());

        return response()->json([
            'data' => $category,
        ], 201);
    }
}
