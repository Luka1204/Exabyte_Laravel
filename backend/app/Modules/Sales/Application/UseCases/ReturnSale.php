<?php

namespace App\Modules\Sales\Application\UseCases;

use App\Models\CashSession;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Modules\Tenancy\Infrastructure\Persistence\Eloquent\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class ReturnSale
{
    /**
     * @param  array{items: array<int, array{sale_item_id: int, quantity: string}>, reason: string, idempotency_key: string}  $data
     * @return array{return: SaleReturn, replayed: bool}
     */
    public function execute(int $companyId, int $userId, int $saleId, array $data): array
    {
        $quantities = [];
        foreach ($data['items'] as $item) {
            $quantities[(int) $item['sale_item_id']] = self::toMilliunits(
                (string) $item['quantity']
            );
        }
        ksort($quantities);

        $requestHash = hash('sha256', json_encode([
            'items' => $quantities,
            'reason' => trim($data['reason']),
        ], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use (
            $companyId,
            $userId,
            $saleId,
            $data,
            $quantities,
            $requestHash
        ): array {
            Company::query()->whereKey($companyId)->lockForUpdate()->firstOrFail();

            $existing = SaleReturn::query()
                ->where('company_id', $companyId)
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();

            if ($existing) {
                if (
                    $existing->sale_id !== $saleId
                    || ! hash_equals($existing->request_hash, $requestHash)
                ) {
                    throw new ConflictHttpException(
                        'La clave de idempotencia ya se utilizó para otra devolución.'
                    );
                }

                return [
                    'return' => $existing->load(['items.saleItem', 'refundedBy:id,name']),
                    'replayed' => true,
                ];
            }

            $sale = Sale::query()
                ->where('company_id', $companyId)
                ->whereKey($saleId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($sale->status, ['completed', 'partially_returned'], true)) {
                throw new ConflictHttpException(
                    'La venta ya fue anulada o devuelta completamente.'
                );
            }

            $cashSession = CashSession::query()
                ->where('company_id', $companyId)
                ->whereNull('closed_at')
                ->lockForUpdate()
                ->first();

            if (! $cashSession) {
                throw new ConflictHttpException('Abrí una caja para registrar el reintegro en efectivo.');
            }

            $items = $sale->items()
                ->whereIn('id', array_keys($quantities))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($items->count() !== count($quantities)) {
                throw new UnprocessableEntityHttpException(
                    'Uno o más artículos no pertenecen a esta venta.'
                );
            }

            $previousReturns = SaleReturnItem::query()
                ->selectRaw('sale_item_id, SUM(quantity) as quantity, SUM(refund_amount) as refund_amount')
                ->whereIn('sale_item_id', array_keys($quantities))
                ->groupBy('sale_item_id')
                ->get()
                ->keyBy('sale_item_id');

            $productIds = $items->pluck('product_id')->unique()->sort()->values()->all();
            $products = Product::query()
                ->where('company_id', $companyId)
                ->whereIn('id', $productIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($products->count() !== count($productIds)) {
                throw new ConflictHttpException(
                    'No se puede devolver la venta porque un producto ya no pertenece a la empresa.'
                );
            }

            $balances = InventoryBalance::query()
                ->where('company_id', $companyId)
                ->whereIn('product_id', $productIds)
                ->orderBy('product_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('product_id');

            $returnLines = [];
            $refundCents = 0;
            foreach ($quantities as $saleItemId => $quantityMilliunits) {
                $item = $items->get($saleItemId);
                $previous = $previousReturns->get($saleItemId);
                $returnedQuantity = self::toMilliunits((string) ($previous?->quantity ?? '0'));
                $remainingQuantity = self::toMilliunits((string) $item->quantity)
                    - $returnedQuantity;
                $remainingRefund = self::toCents((string) $item->line_total)
                    - self::toCents((string) ($previous?->refund_amount ?? '0'));

                if (
                    $quantityMilliunits <= 0
                    || $quantityMilliunits > $remainingQuantity
                    || $remainingRefund < 0
                ) {
                    throw new UnprocessableEntityHttpException(
                        "La cantidad a devolver supera el saldo del artículo {$item->sku}."
                    );
                }

                $lineRefundCents = $quantityMilliunits === $remainingQuantity
                    ? $remainingRefund
                    : self::roundDivide(
                        $remainingRefund * $quantityMilliunits,
                        $remainingQuantity
                    );

                $product = $products->get($item->product_id);
                $balance = $balances->get($item->product_id);
                $newBalanceMilliunits = self::toMilliunits(
                    (string) ($balance?->quantity ?? '0.000')
                ) + $quantityMilliunits;

                if ($newBalanceMilliunits > 99_999_999_999_999) {
                    throw new UnprocessableEntityHttpException(
                        'El stock resultante supera el máximo admitido.'
                    );
                }

                $returnLines[] = [
                    'item' => $item,
                    'product' => $product,
                    'balance' => $balance,
                    'quantity' => $quantityMilliunits,
                    'new_balance' => $newBalanceMilliunits,
                    'refund' => $lineRefundCents,
                ];
                $refundCents += $lineRefundCents;
            }

            $currentCashCents = self::toCents((string) $cashSession->expected_cash);
            if ($refundCents > $currentCashCents) {
                throw new ConflictHttpException(
                    'La caja no tiene efectivo suficiente para realizar este reintegro.'
                );
            }

            $saleReturn = SaleReturn::query()->create([
                'company_id' => $companyId,
                'sale_id' => $sale->id,
                'cash_session_id' => $cashSession->id,
                'refunded_by' => $userId,
                'idempotency_key' => $data['idempotency_key'],
                'request_hash' => $requestHash,
                'reason' => trim($data['reason']),
                'total' => self::fromCents($refundCents),
            ]);

            foreach ($returnLines as $line) {
                $balance = $line['balance'];
                if ($balance) {
                    $balance->quantity = self::fromMilliunits($line['new_balance']);
                    $balance->save();
                } else {
                    $balance = InventoryBalance::query()->create([
                        'company_id' => $companyId,
                        'product_id' => $line['product']->id,
                        'quantity' => self::fromMilliunits($line['new_balance']),
                    ]);
                }

                $saleReturn->items()->create([
                    'sale_item_id' => $line['item']->id,
                    'product_id' => $line['product']->id,
                    'quantity' => self::fromMilliunits($line['quantity']),
                    'refund_amount' => self::fromCents($line['refund']),
                ]);

                InventoryMovement::query()->create([
                    'company_id' => $companyId,
                    'product_id' => $line['product']->id,
                    'user_id' => $userId,
                    'sale_id' => $sale->id,
                    'type' => InventoryMovement::TYPE_SALE_RETURN,
                    'quantity_delta' => self::fromMilliunits($line['quantity']),
                    'balance_after' => $balance->quantity,
                    'reason' => 'Devolución '.$sale->ticket_number.': '.trim($data['reason']),
                    'idempotency_key' => (string) Str::uuid(),
                ]);
            }

            $cashSession->expected_cash = self::fromCents($currentCashCents - $refundCents);
            $cashSession->save();

            $totalReturnedItems = SaleReturnItem::query()
                ->whereIn('sale_item_id', $sale->items()->pluck('id'))
                ->selectRaw('sale_item_id, SUM(quantity) as quantity')
                ->groupBy('sale_item_id')
                ->get()
                ->keyBy('sale_item_id');
            $fullyReturned = $sale->items->every(
                fn (SaleItem $item): bool => self::toMilliunits(
                    (string) ($totalReturnedItems->get($item->id)?->quantity ?? '0')
                ) >= self::toMilliunits((string) $item->quantity)
            );
            $sale->status = $fullyReturned ? 'returned' : 'partially_returned';
            $sale->save();

            return [
                'return' => $saleReturn->load(['items.saleItem', 'refundedBy:id,name']),
                'replayed' => false,
            ];
        });
    }

    private static function toCents(string $amount): int
    {
        $negative = str_starts_with($amount, '-');
        $unsigned = ltrim($amount, '+-');
        [$whole, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');
        $cents = ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');

        return $negative ? -$cents : $cents;
    }

    private static function fromCents(int $amount): string
    {
        $sign = $amount < 0 ? '-' : '';
        $absolute = abs($amount);

        return sprintf('%s%d.%02d', $sign, intdiv($absolute, 100), $absolute % 100);
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
        return sprintf('%d.%03d', intdiv($quantity, 1000), $quantity % 1000);
    }

    private static function roundDivide(int $numerator, int $denominator): int
    {
        return intdiv($numerator + intdiv($denominator, 2), $denominator);
    }
}
