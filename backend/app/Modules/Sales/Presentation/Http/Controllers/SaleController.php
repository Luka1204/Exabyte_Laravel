<?php

namespace App\Modules\Sales\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Modules\Sales\Application\UseCases\CheckoutSale;
use App\Modules\Sales\Application\UseCases\ReturnSale;
use App\Modules\Sales\Application\UseCases\VoidSale;
use App\Modules\Sales\Presentation\Requests\CheckoutSaleRequest;
use App\Modules\Sales\Presentation\Requests\ReturnSaleRequest;
use App\Modules\Sales\Presentation\Requests\VoidSaleRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class SaleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        Gate::forUser($request->user())->authorize('viewAny', [Sale::class, $companyId]);

        return response()->json(
            Sale::query()
                ->with(['items.returns', 'returns.items', 'user:id,name'])
                ->where('company_id', $companyId)
                ->latest('id')
                ->paginate(20)
        );
    }

    public function store(
        CheckoutSaleRequest $request,
        CheckoutSale $useCase
    ): JsonResponse {
        $companyId = (int) $request->attributes->get('active_company_id');
        Gate::forUser($request->user())->authorize('operate', [Sale::class, $companyId]);
        $result = $useCase->execute(
            $companyId,
            (int) $request->user()->id,
            $request->validated()
        );

        return response()->json([
            'data' => $result['sale'],
            'replayed' => $result['replayed'],
        ], $result['replayed'] ? Response::HTTP_OK : Response::HTTP_CREATED);
    }

    public function void(
        VoidSaleRequest $request,
        int $sale,
        VoidSale $useCase
    ): JsonResponse {
        $companyId = (int) $request->attributes->get('active_company_id');
        Gate::forUser($request->user())->authorize('void', [Sale::class, $companyId]);
        $voided = $useCase->execute(
            $companyId,
            (int) $request->user()->id,
            $sale,
            (string) $request->validated('reason')
        );

        return response()->json(['data' => $voided]);
    }

    public function storeReturn(
        ReturnSaleRequest $request,
        int $sale,
        ReturnSale $useCase
    ): JsonResponse {
        $companyId = (int) $request->attributes->get('active_company_id');
        Gate::forUser($request->user())->authorize('createReturn', [Sale::class, $companyId]);
        $result = $useCase->execute(
            $companyId,
            (int) $request->user()->id,
            $sale,
            $request->validated()
        );

        return response()->json([
            'data' => $result['return'],
            'replayed' => $result['replayed'],
        ], $result['replayed'] ? Response::HTTP_OK : Response::HTTP_CREATED);
    }
}
