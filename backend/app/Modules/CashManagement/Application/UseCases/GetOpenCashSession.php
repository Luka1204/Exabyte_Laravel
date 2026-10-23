<?php

namespace App\Modules\CashManagement\Application\UseCases;

use App\Models\CashSession;

class GetOpenCashSession
{
    public function execute(int $companyId): ?CashSession
    {
        return CashSession::query()
            ->with('opener:id,name')
            ->where('company_id', $companyId)
            ->whereNull('closed_at')
            ->latest('id')
            ->first();
    }
}
