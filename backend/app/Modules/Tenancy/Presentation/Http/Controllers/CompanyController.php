<?php

namespace App\Modules\Tenancy\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Application\UseCases\CreateCompany;
use App\Modules\Tenancy\Infrastructure\Persistence\Eloquent\Company;
use App\Modules\Tenancy\Presentation\Requests\StoreCompanyRequest;
use App\Modules\Tenancy\Presentation\Requests\UpdateCompanyRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companies = $request->user()
            ->companies()
            ->wherePivot('is_active', true)
            ->orderBy('companies.name')
            ->get(['companies.id', 'companies.name', 'companies.is_active']);

        return response()->json([
            'data' => $companies->map(fn (Company $company): array => [
                'id' => $company->id,
                'name' => $company->name,
                'role' => $company->pivot->role,
                'is_active' => (bool) ($company->is_active && $company->pivot->is_active),
            ]),
        ]);
    }

    public function store(
        StoreCompanyRequest $request,
        CreateCompany $createCompany
    ): JsonResponse {
        $company = $createCompany->execute(
            $request->user(),
            $request->validated()['name']
        );

        return response()->json([
            'data' => [
                'id' => $company->id,
                'name' => $company->name,
                'role' => 'owner',
                'is_active' => $company->is_active,
            ],
        ], 201);
    }

    public function update(
        UpdateCompanyRequest $request,
        int $company
    ): JsonResponse {
        $model = Company::query()->findOrFail($company);
        $model->fill($request->validated());
        $model->save();

        return response()->json([
            'data' => [
                'id' => $model->id,
                'name' => $model->name,
                'is_active' => $model->is_active,
            ],
        ]);
    }
}
