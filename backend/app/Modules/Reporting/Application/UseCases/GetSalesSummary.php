<?php

namespace App\Modules\Reporting\Application\UseCases;

use App\Models\Sale;
use App\Models\SaleReturn;

class GetSalesSummary
{
    /**
     * @return array{
     *     from: string,
     *     to: string,
     *     sales_count: int,
     *     sales_total: string,
     *     returns_count: int,
     *     returns_total: string,
     *     voided_count: int,
     *     voided_total: string,
     *     net_sales: string,
     *     average_ticket: string
     * }
     */
    public function execute(int $companyId, string $from, string $to): array
    {
        $sales = Sale::query()
            ->where('company_id', $companyId)
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to);

        $completed = (clone $sales)
            ->whereIn('status', ['completed', 'partially_returned', 'returned'])
            ->selectRaw(
                'COUNT(*) as transaction_count, COALESCE(SUM(ROUND(total * 100)), 0) as total_cents'
            )
            ->first();

        $voided = (clone $sales)
            ->where('status', 'voided')
            ->selectRaw(
                'COUNT(*) as transaction_count, COALESCE(SUM(ROUND(total * 100)), 0) as total_cents'
            )
            ->first();

        $returns = SaleReturn::query()
            ->where('company_id', $companyId)
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->selectRaw(
                'COUNT(*) as transaction_count, COALESCE(SUM(ROUND(total * 100)), 0) as total_cents'
            )
            ->first();

        $salesCount = (int) $completed->transaction_count;
        $salesCents = (int) $completed->total_cents;
        $returnsCount = (int) $returns->transaction_count;
        $returnsCents = (int) $returns->total_cents;
        $voidedCount = (int) $voided->transaction_count;
        $voidedCents = (int) $voided->total_cents;
        $netSalesCents = $salesCents - $returnsCents;

        return [
            'from' => $from,
            'to' => $to,
            'sales_count' => $salesCount,
            'sales_total' => self::fromCents($salesCents),
            'returns_count' => $returnsCount,
            'returns_total' => self::fromCents($returnsCents),
            'voided_count' => $voidedCount,
            'voided_total' => self::fromCents($voidedCents),
            'net_sales' => self::fromCents($netSalesCents),
            'average_ticket' => self::fromCents(
                $salesCount > 0
                    ? intdiv($salesCents + intdiv($salesCount, 2), $salesCount)
                    : 0
            ),
        ];
    }

    private static function fromCents(int $amount): string
    {
        $sign = $amount < 0 ? '-' : '';
        $absolute = abs($amount);

        return sprintf('%s%d.%02d', $sign, intdiv($absolute, 100), $absolute % 100);
    }
}
