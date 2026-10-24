<?php

namespace App\Modules\Reporting\Application\UseCases;

use App\Models\CashSession;
use App\Models\Sale;
use App\Models\SaleReturn;

class GetCashSummary
{
    /**
     * @return array{
     *     from: string,
     *     to: string,
     *     sessions_count: int,
     *     closed_sessions_count: int,
     *     opening_total: string,
     *     sales_total: string,
     *     returns_total: string,
     *     expected_cash_total: string,
     *     counted_cash_total: string,
     *     difference_total: string
     * }
     */
    public function execute(int $companyId, string $from, string $to): array
    {
        $sessions = CashSession::query()
            ->where('company_id', $companyId)
            ->whereDate('opened_at', '>=', $from)
            ->whereDate('opened_at', '<=', $to);
        $sessionIds = (clone $sessions)->select('id');

        $sessionTotals = (clone $sessions)
            ->selectRaw('COUNT(*) as sessions_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN closed_at IS NOT NULL THEN 1 ELSE 0 END), 0) as closed_sessions_count')
            ->selectRaw('COALESCE(SUM(ROUND(opening_amount * 100)), 0) as opening_cents')
            ->selectRaw('COALESCE(SUM(ROUND(expected_cash * 100)), 0) as expected_cash_cents')
            ->selectRaw('COALESCE(SUM(ROUND(COALESCE(counted_cash, 0) * 100)), 0) as counted_cash_cents')
            ->selectRaw('COALESCE(SUM(ROUND(COALESCE(difference, 0) * 100)), 0) as difference_cents')
            ->first();

        $salesCents = Sale::query()
            ->where('company_id', $companyId)
            ->whereIn('cash_session_id', $sessionIds)
            ->whereIn('status', ['completed', 'partially_returned', 'returned'])
            ->selectRaw('COALESCE(SUM(ROUND(total * 100)), 0) as total_cents')
            ->value('total_cents');

        $returnsCents = SaleReturn::query()
            ->where('company_id', $companyId)
            ->whereIn('cash_session_id', $sessionIds)
            ->selectRaw('COALESCE(SUM(ROUND(total * 100)), 0) as total_cents')
            ->value('total_cents');

        return [
            'from' => $from,
            'to' => $to,
            'sessions_count' => (int) $sessionTotals->sessions_count,
            'closed_sessions_count' => (int) $sessionTotals->closed_sessions_count,
            'opening_total' => self::fromCents((int) $sessionTotals->opening_cents),
            'sales_total' => self::fromCents((int) $salesCents),
            'returns_total' => self::fromCents((int) $returnsCents),
            'expected_cash_total' => self::fromCents((int) $sessionTotals->expected_cash_cents),
            'counted_cash_total' => self::fromCents((int) $sessionTotals->counted_cash_cents),
            'difference_total' => self::fromCents((int) $sessionTotals->difference_cents),
        ];
    }

    private static function fromCents(int $amount): string
    {
        $sign = $amount < 0 ? '-' : '';
        $absolute = abs($amount);

        return sprintf('%s%d.%02d', $sign, intdiv($absolute, 100), $absolute % 100);
    }
}
