<?php

namespace App\Modules\Inventory\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Modules\Inventory\Application\UseCases\AdjustInventory;
use App\Modules\Inventory\Application\UseCases\ListInventoryMovements;
use App\Modules\Inventory\Application\UseCases\ListInventoryStocks;
use App\Modules\Inventory\Presentation\Requests\ListInventoryRequest;
use App\Modules\Inventory\Presentation\Requests\StoreInventoryAdjustmentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class InventoryController extends Controller
{
    public function stocks(
        ListInventoryRequest $request,
        ListInventoryStocks $useCase
    ): JsonResponse {
        $companyId = (int) $request->attributes->get('active_company_id');
        Gate::forUser($request->user())->authorize(
            'viewAny',
            [InventoryMovement::class, $companyId]
        );

        return response()->json($useCase->execute(
            $companyId,
            (int) ($request->validated()['per_page'] ?? 15)
        ));
    }

    public function movements(
        ListInventoryRequest $request,
        ListInventoryMovements $useCase
    ): JsonResponse {
        $companyId = (int) $request->attributes->get('active_company_id');
        Gate::forUser($request->user())->authorize(
            'viewAny',
            [InventoryMovement::class, $companyId]
        );

        return response()->json($useCase->execute(
            $companyId,
            (int) ($request->validated()['per_page'] ?? 15)
        ));
    }

    public function adjust(
        StoreInventoryAdjustmentRequest $request,
        AdjustInventory $useCase
    ): JsonResponse {
        $companyId = (int) $request->attributes->get('active_company_id');
        Gate::forUser($request->user())->authorize(
            'create',
            [InventoryMovement::class, $companyId]
        );

        $data = $request->validated();
        $result = $useCase->execute(
            $companyId,
            (int) $request->user()->id,
            (int) $data['product_id'],
            (string) $data['quantity_delta'],
            $data['reason'],
            $data['idempotency_key']
        );

        return response()->json([
            'data' => $result['movement']->load(['product:id,name,sku', 'user:id,name']),
            'replayed' => $result['replayed'],
        ], $result['replayed'] ? Response::HTTP_OK : Response::HTTP_CREATED);
    }
}
