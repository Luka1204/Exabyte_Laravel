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
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class CheckoutSale
{
    /**
     * @param  array{items: array<int, array{product_id: int, quantity: string}>, cash_received: string, idempotency_key: string}  $data
     * @return array{sale: Sale, replayed: bool}
     */
    public function execute(int $companyId, int $userId, array $data): array
    {
        $quantities = [];
        foreach ($data['items'] as $item) {
            $quantities[(int) $item['product_id']] = self::toMilliunits(
                (string) $item['quantity']
            );
        }
        ksort($quantities);

        $cashReceivedCents = self::toCents((string) $data['cash_received']);
        $requestHash = hash('sha256', json_encode([
            'items' => $quantities,
            'cash_received_cents' => $cashReceivedCents,
        ], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use (
            $companyId,
            $userId,
            $data,
            $quantities,
            $cashReceivedCents,
            $requestHash
        ): array {
            Company::query()->whereKey($companyId)->lockForUpdate()->firstOrFail();

            $existing = Sale::query()
                ->with('items')
                ->where('company_id', $companyId)
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();

            if ($existing) {
                if (! hash_equals($existing->request_hash, $requestHash)) {
                    throw new ConflictHttpException(
                        'La clave de idempotencia ya se utilizó para otra venta.'
                    );
                }

                return ['sale' => $existing, 'replayed' => true];
            }

            $cashSession = CashSession::query()
                ->where('company_id', $companyId)
                ->whereNull('closed_at')
                ->lockForUpdate()
                ->first();

            if (! $cashSession) {
                throw new ConflictHttpException('Abrí una caja antes de confirmar ventas.');
            }

            $products = Product::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->whereIn('id', array_keys($quantities))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($products->count() !== count($quantities)) {
                throw new UnprocessableEntityHttpException(
                    'Uno o más productos no están disponibles en esta empresa.'
                );
            }

            $balances = InventoryBalance::query()
                ->where('company_id', $companyId)
                ->whereIn('product_id', array_keys($quantities))
                ->orderBy('product_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('product_id');

            $subtotalCents = 0;
            $taxTotalCents = 0;
            $lines = [];
            foreach ($quantities as $productId => $quantityMilliunits) {
                if ($quantityMilliunits <= 0) {
                    throw new UnprocessableEntityHttpException(
                        'La cantidad de cada producto debe ser mayor a cero.'
                    );
                }

                $product = $products->get($productId);
                $balance = $balances->get($productId);
                if (! $balance || self::toMilliunits((string) $balance->quantity) < $quantityMilliunits) {
                    throw new UnprocessableEntityHttpException(
                        "No hay existencias suficientes para el producto {$product->sku}."
                    );
                }

                $unitPriceCents = self::toCents((string) $product->price);
                $lineSubtotalCents = self::roundDivide(
                    $unitPriceCents * $quantityMilliunits,
                    1000
                );
                if ($lineSubtotalCents > 999_999_999_999) {
                    throw new UnprocessableEntityHttpException(
                        'El importe de un artículo supera el máximo permitido.'
                    );
                }
                $taxRateBasisPoints = self::toHundredths((string) $product->tax_rate);
                $lineTaxCents = self::roundDivide(
                    $lineSubtotalCents * $taxRateBasisPoints,
                    10000
                );
                $subtotalCents += $lineSubtotalCents;
                $taxTotalCents += $lineTaxCents;
                $lines[] = [
                    'product' => $product,
                    'balance' => $balance,
                    'quantity_milliunits' => $quantityMilliunits,
                    'unit_price_cents' => $unitPriceCents,
                    'tax_rate_basis_points' => $taxRateBasisPoints,
                    'line_subtotal_cents' => $lineSubtotalCents,
                    'line_tax_cents' => $lineTaxCents,
                ];
            }

            $totalCents = $subtotalCents + $taxTotalCents;
            if ($totalCents > 999_999_999_999 || $cashReceivedCents < $totalCents) {
                throw new UnprocessableEntityHttpException(
                    $cashReceivedCents < $totalCents
                        ? 'El efectivo recibido no cubre el total de la venta.'
                        : 'El importe total supera el máximo permitido.'
                );
            }

            $sale = Sale::query()->create([
                'company_id' => $companyId,
                'cash_session_id' => $cashSession->id,
                'user_id' => $userId,
                'idempotency_key' => $data['idempotency_key'],
                'request_hash' => $requestHash,
                'status' => 'completed',
                'subtotal' => self::fromCents($subtotalCents),
                'tax_total' => self::fromCents($taxTotalCents),
                'total' => self::fromCents($totalCents),
                'cash_received' => self::fromCents($cashReceivedCents),
                'change_due' => self::fromCents($cashReceivedCents - $totalCents),
            ]);
            $sale->ticket_number = sprintf('EXA-%d-%08d', $companyId, $sale->id);
            $sale->save();

            foreach ($lines as $line) {
                $balance = $line['balance'];
                $newBalanceMilliunits = self::toMilliunits((string) $balance->quantity)
                    - $line['quantity_milliunits'];
                $balance->quantity = self::fromMilliunits($newBalanceMilliunits);
                $balance->save();

                $product = $line['product'];
                $lineTotalCents = $line['line_subtotal_cents'] + $line['line_tax_cents'];
                $sale->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'quantity' => self::fromMilliunits($line['quantity_milliunits']),
                    'unit_price' => self::fromCents($line['unit_price_cents']),
                    'tax_rate' => self::fromHundredths($line['tax_rate_basis_points']),
                    'line_subtotal' => self::fromCents($line['line_subtotal_cents']),
                    'line_tax' => self::fromCents($line['line_tax_cents']),
                    'line_total' => self::fromCents($lineTotalCents),
                ]);

                InventoryMovement::query()->create([
                    'company_id' => $companyId,
                    'product_id' => $product->id,
                    'user_id' => $userId,
                    'sale_id' => $sale->id,
                    'type' => InventoryMovement::TYPE_SALE,
                    'quantity_delta' => self::fromMilliunits(-$line['quantity_milliunits']),
                    'balance_after' => $balance->quantity,
                    'reason' => 'Venta '.$sale->ticket_number,
                    'idempotency_key' => (string) Str::uuid(),
                ]);
            }

            $cashSession->expected_cash = self::fromCents(
                self::toCents((string) $cashSession->expected_cash) + $totalCents
            );
            $cashSession->save();

            return ['sale' => $sale->load('items'), 'replayed' => false];
        });
    }

    private static function toCents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private static function toHundredths(string $amount): int
    {
        return self::toCents($amount);
    }

    private static function toMilliunits(string $quantity): int
    {
        [$whole, $fraction] = array_pad(explode('.', $quantity, 2), 2, '');

        return ((int) $whole * 1000) + (int) str_pad(substr($fraction, 0, 3), 3, '0');
    }

    private static function fromCents(int $amount): string
    {
        return sprintf('%d.%02d', intdiv($amount, 100), $amount % 100);
    }

    private static function fromHundredths(int $amount): string
    {
        return self::fromCents($amount);
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
