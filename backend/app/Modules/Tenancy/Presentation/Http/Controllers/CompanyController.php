<?php

namespace App\Modules\Tenancy\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Application\UseCases\CreateCompany;
use App\Modules\Tenancy\Infrastructure\Persistence\Eloquent\Company;
use App\Modules\Tenancy\Presentation\Requests\StoreCompanyRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companies = $request->user()
            ->companies()
            ->orderBy('companies.name')
            ->get(['companies.id', 'companies.name']);

        return response()->json([
            'data' => $companies->map(fn (Company $company): array => [
                'id' => $company->id,
                'name' => $company->name,
                'role' => $company->pivot->role,
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
            ],
        ], 201);
    }
}
