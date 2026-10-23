<?php

namespace App\Modules\CashManagement\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CashSession;
use App\Modules\CashManagement\Application\UseCases\CloseCashSession;
use App\Modules\CashManagement\Application\UseCases\GetOpenCashSession;
use App\Modules\CashManagement\Application\UseCases\OpenCashSession;
use App\Modules\CashManagement\Presentation\Requests\CloseCashSessionRequest;
use App\Modules\CashManagement\Presentation\Requests\OpenCashSessionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class CashSessionController extends Controller
{
    public function current(Request $request, GetOpenCashSession $useCase): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        Gate::forUser($request->user())->authorize('viewAny', [CashSession::class, $companyId]);

        return response()->json(['data' => $useCase->execute($companyId)]);
    }

    public function open(
        OpenCashSessionRequest $request,
        OpenCashSession $useCase
    ): JsonResponse {
        $companyId = (int) $request->attributes->get('active_company_id');
        Gate::forUser($request->user())->authorize('operate', [CashSession::class, $companyId]);
        $session = $useCase->execute(
            $companyId,
            (int) $request->user()->id,
            (string) $request->validated('opening_amount')
        );

        return response()->json(['data' => $session], Response::HTTP_CREATED);
    }

    public function close(
        CloseCashSessionRequest $request,
        int $session,
        CloseCashSession $useCase
    ): JsonResponse {
        $companyId = (int) $request->attributes->get('active_company_id');
        Gate::forUser($request->user())->authorize('operate', [CashSession::class, $companyId]);
        $closed = $useCase->execute(
            $companyId,
            (int) $request->user()->id,
            $session,
            (string) $request->validated('counted_cash')
        );

        return response()->json(['data' => $closed]);
    }
}
