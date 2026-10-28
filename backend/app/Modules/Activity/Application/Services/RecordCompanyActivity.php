<?php

namespace App\Modules\Activity\Application\Services;

use App\Modules\Activity\Infrastructure\Persistence\Eloquent\CompanyActivityEvent;

class RecordCompanyActivity
{
    /**
     * @param  array<string, int|string|bool|null>  $details
     */
    public function execute(
        int $companyId,
        ?int $actorUserId,
        string $action,
        string $subjectType,
        ?string $subjectId,
        array $details = []
    ): CompanyActivityEvent {
        return CompanyActivityEvent::query()->create([
            'company_id' => $companyId,
            'actor_user_id' => $actorUserId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'details' => $details,
        ]);
    }
}
