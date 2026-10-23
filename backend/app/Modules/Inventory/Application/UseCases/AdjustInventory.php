<?php

namespace App\Modules\Inventory\Application\UseCases;

use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Modules\Tenancy\Infrastructure\Persistence\Eloquent\Company;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class AdjustInventory
{
    /**
     * @return array{movement: InventoryMovement, replayed: bool}
     */
    public function execute(
        int $companyId,
        int $userId,
        int $productId,
        string $quantityDelta,
        string $reason,
        string $idempotencyKey
    ): array {
        $deltaMilliunits = self::toMilliunits($quantityDelta);
        $quantityDelta = self::fromMilliunits($deltaMilliunits);

        return DB::transaction(function () use (
            $companyId,
            $userId,
            $productId,
            $quantityDelta,
            $deltaMilliunits,
            $reason,
            $idempotencyKey
        ): array {
            Company::query()->whereKey($companyId)->lockForUpdate()->firstOrFail();

            $existing = InventoryMovement::query()
                ->where('company_id', $companyId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                if (
                    $existing->product_id !== $productId
                    || $existing->quantity_delta !== $quantityDelta
                    || $existing->reason !== $reason
                ) {
                    throw new ConflictHttpException(
                        'La clave de idempotencia ya se utilizó para otra operación.'
                    );
                }

                return ['movement' => $existing, 'replayed' => true];
            }

            $balance = InventoryBalance::query()
                ->where('company_id', $companyId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            $currentMilliunits = self::toMilliunits($balance?->quantity ?? '0.000');
            $newMilliunits = $currentMilliunits + $deltaMilliunits;

            if ($newMilliunits < 0) {
                throw new UnprocessableEntityHttpException(
                    'El ajuste no puede dejar existencias negativas.'
                );
            }

            if ($newMilliunits > 99_999_999_999_999) {
                throw new UnprocessableEntityHttpException(
                    'El stock resultante supera el máximo admitido.'
                );
            }

            $balanceQuantity = self::fromMilliunits($newMilliunits);
            $deltaQuantity = self::fromMilliunits($deltaMilliunits);

            if ($balance) {
                $balance->quantity = $balanceQuantity;
                $balance->save();
            } else {
                $balance = InventoryBalance::query()->create([
                    'company_id' => $companyId,
                    'product_id' => $productId,
                    'quantity' => $balanceQuantity,
                ]);
            }

            $movement = InventoryMovement::query()->create([
                'company_id' => $companyId,
                'product_id' => $productId,
                'user_id' => $userId,
                'type' => InventoryMovement::TYPE_ADJUSTMENT,
                'quantity_delta' => $deltaQuantity,
                'balance_after' => $balance->quantity,
                'reason' => $reason,
                'idempotency_key' => $idempotencyKey,
            ]);

            return ['movement' => $movement, 'replayed' => false];
        });
    }

    private static function toMilliunits(string $quantity): int
    {
        $negative = str_starts_with($quantity, '-');
        $unsigned = ltrim($quantity, '+-');
        [$whole, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');
        $milliunits = ((int) $whole * 1000) + (int) str_pad($fraction, 3, '0');

        return $negative ? -$milliunits : $milliunits;
    }

    private static function fromMilliunits(int $quantity): string
    {
        $sign = $quantity < 0 ? '-' : '';
        $absolute = abs($quantity);

        return sprintf('%s%d.%03d', $sign, intdiv($absolute, 1000), $absolute % 1000);
    }
}
