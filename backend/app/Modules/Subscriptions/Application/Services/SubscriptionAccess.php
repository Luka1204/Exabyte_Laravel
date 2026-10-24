<?php

namespace App\Modules\Subscriptions\Application\Services;

use App\Models\Product;
use App\Modules\Subscriptions\Infrastructure\Persistence\Eloquent\Subscription;
use App\Modules\Subscriptions\Infrastructure\Persistence\Eloquent\SubscriptionAccessExemption;
use Illuminate\Validation\ValidationException;

class SubscriptionAccess
{
    /**
     * @return array{status: int, message: string, code: string}|null
     */
    public function featureDenial(int $companyId, string $entitlement): ?array
    {
        if (! config('subscriptions.access_enforcement', true) || $this->isExempt($companyId)) {
            return null;
        }

        $subscription = $this->usableSubscription($companyId);
        if (! $subscription) {
            return [
                'status' => 402,
                'code' => 'subscription_required',
                'message' => 'Se necesita una suscripción vigente para usar esta función.',
            ];
        }

        if (($subscription->terms_snapshot['entitlements'][$entitlement] ?? false) !== true) {
            return [
                'status' => 403,
                'code' => 'entitlement_not_included',
                'message' => 'La función no está incluida en el plan contratado.',
            ];
        }

        return null;
    }

    public function assertProductLimit(int $companyId): void
    {
        if (! config('subscriptions.access_enforcement', true) || $this->isExempt($companyId)) {
            return;
        }

        $subscription = $this->usableSubscription($companyId);
        if (! $subscription) {
            throw ValidationException::withMessages([
                'subscription' => 'Se necesita una suscripción vigente para crear productos.',
            ]);
        }

        $limit = $subscription->terms_snapshot['limits']['products'] ?? null;
        if ($limit === null) {
            return;
        }

        $created = Product::query()->where('company_id', $companyId)->count();
        if ($created >= (int) $limit) {
            throw ValidationException::withMessages([
                'products' => "El plan permite hasta {$limit} productos. Cambiá de plan para ampliar el límite.",
            ]);
        }
    }

    private function isExempt(int $companyId): bool
    {
        return SubscriptionAccessExemption::query()
            ->where('company_id', $companyId)
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now())
            )
            ->exists();
    }

    private function usableSubscription(int $companyId): ?Subscription
    {
        $subscription = Subscription::query()
            ->where('company_id', $companyId)
            ->latest('id')
            ->first();

        if (! $subscription) {
            return null;
        }

        return match ($subscription->status) {
            'active' => $subscription->current_period_end?->isFuture() ? $subscription : null,
            'trialing' => $subscription->trial_ends_at?->isFuture() ? $subscription : null,
            'grace' => $subscription->grace_ends_at?->isFuture() ? $subscription : null,
            default => null,
        };
    }
}
