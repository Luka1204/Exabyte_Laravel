<?php

namespace App\Modules\Reporting\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Modules\Reporting\Application\UseCases\GetSalesSummary;
use App\Modules\Reporting\Presentation\Requests\SalesSummaryRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class SalesReportController extends Controller
{
    public function summary(
        SalesSummaryRequest $request,
        GetSalesSummary $useCase
    ): JsonResponse {
        $companyId = (int) $request->attributes->get('active_company_id');
        Gate::forUser($request->user())->authorize('viewAny', [Sale::class, $companyId]);
        $data = $request->validated();

        return response()->json([
            'data' => $useCase->execute($companyId, $data['from'], $data['to']),
        ]);
    }
}
