<?php

namespace App\Modules\CashManagement\Application\UseCases;

use App\Models\CashSession;
use App\Modules\Tenancy\Infrastructure\Persistence\Eloquent\Company;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class OpenCashSession
{
    public function execute(int $companyId, int $userId, string $openingAmount): CashSession
    {
        return DB::transaction(function () use ($companyId, $userId, $openingAmount): CashSession {
            Company::query()->whereKey($companyId)->lockForUpdate()->firstOrFail();

            $alreadyOpen = CashSession::query()
                ->where('company_id', $companyId)
                ->whereNull('closed_at')
                ->exists();

            if ($alreadyOpen) {
                throw new ConflictHttpException('Ya hay una caja abierta para esta empresa.');
            }

            return CashSession::query()->create([
                'company_id' => $companyId,
                'opened_by' => $userId,
                'opening_amount' => $openingAmount,
                'expected_cash' => $openingAmount,
                'opened_at' => now(),
            ]);
        });
    }
}
