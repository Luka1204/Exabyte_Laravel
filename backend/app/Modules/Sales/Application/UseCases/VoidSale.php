<?php

namespace App\Modules\Sales\Application\UseCases;

use App\Models\CashSession;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sale;
use App\Modules\Tenancy\Infrastructure\Persistence\Eloquent\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class VoidSale
{
    public function execute(
        int $companyId,
        int $userId,
        int $saleId,
        string $reason
    ): Sale {
        return DB::transaction(function () use (
            $companyId,
            $userId,
            $saleId,
            $reason
        ): Sale {
            Company::query()->whereKey($companyId)->lockForUpdate()->firstOrFail();

            $sale = Sale::query()
                ->where('company_id', $companyId)
                ->whereKey($saleId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($sale->status !== 'completed') {
                throw new ConflictHttpException('La venta ya fue anulada o no puede anularse.');
            }

            $cashSession = CashSession::query()
                ->where('company_id', $companyId)
                ->whereKey($sale->cash_session_id)
                ->whereNull('closed_at')
                ->lockForUpdate()
                ->first();

            if (! $cashSession) {
                throw new ConflictHttpException(
                    'Solo se pueden anular ventas de la caja actualmente abierta.'
                );
            }

            $items = $sale->items()->orderBy('product_id')->get();
            $productIds = $items->pluck('product_id')->all();
            $products = Product::query()
                ->where('company_id', $companyId)
                ->whereIn('id', $productIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($products->count() !== count($productIds)) {
                throw new ConflictHttpException(
                    'No se puede anular la venta porque uno o más productos no pertenecen a la empresa.'
                );
            }

            $balances = InventoryBalance::query()
                ->where('company_id', $companyId)
                ->whereIn('product_id', $productIds)
                ->orderBy('product_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('product_id');

            foreach ($items as $item) {
                $quantityMilliunits = self::toMilliunits((string) $item->quantity);
                $balance = $balances->get($item->product_id);
                $currentMilliunits = self::toMilliunits(
                    (string) ($balance?->quantity ?? '0.000')
                );
                $newMilliunits = $currentMilliunits + $quantityMilliunits;

                if ($newMilliunits > 99_999_999_999_999) {
                    throw new ConflictHttpException(
                        'No se puede anular la venta porque el saldo excedería el máximo permitido.'
                    );
                }

                $balanceQuantity = self::fromMilliunits($newMilliunits);
                if ($balance) {
                    $balance->quantity = $balanceQuantity;
                    $balance->save();
                } else {
                    $balance = InventoryBalance::query()->create([
                        'company_id' => $companyId,
                        'product_id' => $item->product_id,
                        'quantity' => $balanceQuantity,
                    ]);
                }

                InventoryMovement::query()->create([
                    'company_id' => $companyId,
                    'product_id' => $item->product_id,
                    'user_id' => $userId,
                    'sale_id' => $sale->id,
                    'type' => InventoryMovement::TYPE_SALE_VOID,
                    'quantity_delta' => self::fromMilliunits($quantityMilliunits),
                    'balance_after' => $balance->quantity,
                    'reason' => trim($reason),
                    'idempotency_key' => (string) Str::uuid(),
                ]);
            }

            $cashExpectedCents = self::toCents((string) $cashSession->expected_cash)
                - self::toCents((string) $sale->total);
            if ($cashExpectedCents < 0) {
                throw new ConflictHttpException(
                    'No se puede anular la venta porque el efectivo esperado no sería válido.'
                );
            }

            $cashSession->expected_cash = self::fromCents($cashExpectedCents);
            $cashSession->save();

            $sale->status = 'voided';
            $sale->voided_by = $userId;
            $sale->voided_at = now();
            $sale->void_reason = trim($reason);
            $sale->save();

            return $sale->load('items');
        });
    }

    private static function toCents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private static function fromCents(int $amount): string
    {
        return sprintf('%d.%02d', intdiv($amount, 100), $amount % 100);
    }

    private static function toMilliunits(string $quantity): int
    {
        [$whole, $fraction] = array_pad(explode('.', $quantity, 2), 2, '');

        return ((int) $whole * 1000) + (int) str_pad(substr($fraction, 0, 3), 3, '0');
    }

    private static function fromMilliunits(int $quantity): string
    {
        return sprintf('%d.%03d', intdiv($quantity, 1000), $quantity % 1000);
    }
}
