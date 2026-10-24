<?php

namespace App\Modules\Subscriptions\Domain;

use App\Modules\Subscriptions\Infrastructure\Persistence\Eloquent\Subscription;
use DomainException;

class SubscriptionLifecycle
{
    /**
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        'pending' => ['active', 'canceled', 'expired'],
        'trialing' => ['active', 'past_due', 'canceled', 'expired'],
        'active' => ['past_due', 'grace', 'suspended', 'canceled', 'expired'],
        'past_due' => ['active', 'grace', 'suspended', 'canceled', 'expired'],
        'grace' => ['active', 'past_due', 'suspended', 'canceled', 'expired'],
        'suspended' => ['active', 'canceled', 'expired'],
        'canceled' => [],
        'expired' => [],
    ];

    public function transition(Subscription $subscription, string $nextStatus): void
    {
        if ($subscription->status === $nextStatus) {
            return;
        }

        if (! in_array($nextStatus, self::TRANSITIONS[$subscription->status] ?? [], true)) {
            throw new DomainException(
                "Subscription status transition from {$subscription->status} to {$nextStatus} is not allowed."
            );
        }

        $subscription->status = $nextStatus;
    }
}
