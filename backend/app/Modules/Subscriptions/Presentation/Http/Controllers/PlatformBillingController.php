<?php

namespace App\Modules\Subscriptions\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Subscriptions\Infrastructure\Persistence\Eloquent\PaymentAttempt;
use App\Modules\Subscriptions\Infrastructure\Persistence\Eloquent\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformBillingController extends Controller
{
    public function subscriptions(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['sometimes', 'string', 'in:pending,trialing,active,past_due,grace,suspended,canceled,expired'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ]);

        $subscriptions = Subscription::query()
            ->with([
                'company:id,name',
                'billingUser:id,name,email',
                'plan:id,code,name',
                'paymentAttempts' => fn ($query) => $query
                    ->select([
                        'id',
                        'subscription_id',
                        'attempt_number',
                        'kind',
                        'status',
                        'amount',
                        'currency',
                        'provider_status',
                        'paid_at',
                    ])
                    ->latest('attempt_number'),
            ])
            ->when(
                $filters['status'] ?? null,
                fn ($query, string $status) => $query->where('status', $status)
            )
            ->latest('id')
            ->paginate($filters['per_page'] ?? 25);

        return response()->json($subscriptions);
    }

    public function payments(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['sometimes', 'string', 'in:pending,paid,failed,canceled'],
            'kind' => ['sometimes', 'string', 'in:initial,renewal,change'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ]);

        $payments = PaymentAttempt::query()
            ->select([
                'id',
                'subscription_id',
                'attempt_number',
                'provider',
                'kind',
                'status',
                'amount',
                'currency',
                'provider_payment_id',
                'provider_status',
                'paid_at',
                'created_at',
            ])
            ->with([
                'subscription:id,company_id,plan_id,billing_period,status',
                'subscription.company:id,name',
                'subscription.plan:id,code,name',
            ])
            ->when(
                $filters['status'] ?? null,
                fn ($query, string $status) => $query->where('status', $status)
            )
            ->when(
                $filters['kind'] ?? null,
                fn ($query, string $kind) => $query->where('kind', $kind)
            )
            ->latest('id')
            ->paginate($filters['per_page'] ?? 25);

        return response()->json($payments);
    }

    public function metrics(): JsonResponse
    {
        $activeSubscriptions = Subscription::query()
            ->where('status', 'active')
            ->get(['company_id', 'terms_snapshot', 'billing_period']);

        $monthlyRecurringEstimate = $activeSubscriptions->sum(
            fn (Subscription $subscription): float => (float) (
                $subscription->terms_snapshot['amount'] ?? 0
            ) / ($subscription->billing_period === 'annual' ? 12 : 1)
        );

        $planDistribution = Subscription::query()
            ->selectRaw('plan_id, status, COUNT(*) as subscriptions_count')
            ->with('plan:id,code,name')
            ->groupBy('plan_id', 'status')
            ->get()
            ->map(fn (Subscription $subscription): array => [
                'plan_id' => $subscription->plan_id,
                'plan_code' => $subscription->plan?->code,
                'plan_name' => $subscription->plan?->name,
                'status' => $subscription->status,
                'subscriptions' => (int) $subscription->subscriptions_count,
            ]);

        return response()->json([
            'data' => [
                'registered_companies' => \App\Modules\Tenancy\Infrastructure\Persistence\Eloquent\Company::query()->count(),
                'companies_with_active_subscriptions' => $activeSubscriptions
                    ->pluck('company_id')
                    ->unique()
                    ->count(),
                'active_subscriptions' => $activeSubscriptions->count(),
                'pending_subscriptions' => Subscription::query()->where('status', 'pending')->count(),
                'canceled_subscriptions' => Subscription::query()->where('status', 'canceled')->count(),
                'failed_renewals' => PaymentAttempt::query()
                    ->where('kind', 'renewal')
                    ->where('status', 'failed')
                    ->count(),
                'collected_revenue_ars' => number_format(
                    (float) PaymentAttempt::query()
                        ->where('status', 'paid')
                        ->whereNotNull('paid_at')
                        ->sum('amount'),
                    2,
                    '.',
                    ''
                ),
                'estimated_monthly_recurring_revenue_ars' => number_format(
                    $monthlyRecurringEstimate,
                    2,
                    '.',
                    ''
                ),
                'plan_distribution' => $planDistribution,
                'as_of' => now()->toIso8601String(),
            ],
        ]);
    }
}
