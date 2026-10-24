<?php

namespace App\Modules\Subscriptions\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Subscriptions\Infrastructure\Persistence\Eloquent\Plan;
use App\Modules\Subscriptions\Infrastructure\Persistence\Eloquent\PlatformAuditEvent;
use App\Modules\Subscriptions\Presentation\Requests\StorePlanRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PlanController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Plan::query()
                ->where('is_active', true)
                ->orderBy('monthly_amount')
                ->get($this->publicFields()),
        ]);
    }

    public function adminIndex(): JsonResponse
    {
        return response()->json([
            'data' => Plan::query()->orderBy('id')->get(),
        ]);
    }

    public function store(StorePlanRequest $request): JsonResponse
    {
        $data = $request->validated();
        $plan = DB::transaction(function () use ($request, $data): Plan {
            $plan = Plan::query()->create($data);
            $this->audit($request->user()->getKey(), 'plan.created', $plan);

            return $plan->refresh();
        });

        return response()->json(['data' => $plan], 201);
    }

    public function update(StorePlanRequest $request, int $plan): JsonResponse
    {
        $data = $request->validated();
        $plan = DB::transaction(function () use ($request, $data, $plan): Plan {
            $model = Plan::query()->lockForUpdate()->findOrFail($plan);
            $contractFields = [
                'code',
                'name',
                'description',
                'benefits',
                'currency',
                'monthly_amount',
                'annual_amount',
                'limits',
                'entitlements',
                'trial_days',
                'grace_days',
            ];
            $model->fill($data);
            $termsChanged = collect($contractFields)->contains(
                fn (string $field): bool => $model->isDirty($field)
            );

            if ($termsChanged) {
                $model->terms_version++;
            }

            $model->save();
            $this->audit(
                $request->user()->getKey(),
                'plan.updated',
                $model,
                ['terms_version' => $model->terms_version]
            );

            return $model->refresh();
        });

        return response()->json(['data' => $plan]);
    }

    /**
     * @return list<string>
     */
    private function publicFields(): array
    {
        return [
            'id',
            'code',
            'name',
            'description',
            'benefits',
            'currency',
            'monthly_amount',
            'annual_amount',
            'limits',
            'entitlements',
            'trial_days',
            'grace_days',
            'terms_version',
        ];
    }

    /**
     * @param array<string, int|string> $details
     */
    private function audit(
        int $actorId,
        string $action,
        Plan $plan,
        array $details = []
    ): void {
        PlatformAuditEvent::query()->create([
            'actor_user_id' => $actorId,
            'action' => $action,
            'subject_type' => Plan::class,
            'subject_id' => (string) $plan->getKey(),
            'details' => [
                'code' => $plan->code,
                ...$details,
            ],
        ]);
    }
}
