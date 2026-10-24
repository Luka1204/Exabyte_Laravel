<?php

namespace App\Modules\CashManagement\Application\UseCases;

use App\Models\CashSession;
use App\Modules\Tenancy\Infrastructure\Persistence\Eloquent\Company;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CloseCashSession
{
    public function execute(
        int $companyId,
        int $userId,
        int $sessionId,
        string $countedCash
    ): CashSession {
        return DB::transaction(function () use (
            $companyId,
            $userId,
            $sessionId,
            $countedCash
        ): CashSession {
            Company::query()->whereKey($companyId)->lockForUpdate()->firstOrFail();

            $session = CashSession::query()
                ->where('company_id', $companyId)
                ->whereNull('closed_at')
                ->whereKey($sessionId)
                ->lockForUpdate()
                ->first();

            if (! $session) {
                throw new ConflictHttpException('La caja no está abierta o no pertenece a esta empresa.');
            }

            $salesTotalCents = $session->sales()
                ->whereIn('status', ['completed', 'partially_returned', 'returned'])
                ->pluck('total')
                ->sum(fn (string $total): int => self::toCents($total));
            $returnsTotalCents = $session->returns()
                ->pluck('total')
                ->sum(fn (string $total): int => self::toCents($total));
            $expectedCents = self::toCents($session->opening_amount)
                + $salesTotalCents
                - $returnsTotalCents;
            $countedCents = self::toCents($countedCash);

            $session->expected_cash = self::fromCents($expectedCents);
            $session->counted_cash = self::fromCents($countedCents);
            $session->difference = self::fromCents($countedCents - $expectedCents);
            $session->closed_by = $userId;
            $session->closed_at = now();
            $session->save();

            return $session->load(['opener:id,name', 'closer:id,name']);
        });
    }

    private static function toCents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private static function fromCents(int $amount): string
    {
        $sign = $amount < 0 ? '-' : '';
        $absolute = abs($amount);

        return sprintf('%s%d.%02d', $sign, intdiv($absolute, 100), $absolute % 100);
    }
}
