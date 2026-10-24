<?php

namespace App\Modules\Subscriptions\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Tenancy\Infrastructure\Persistence\Eloquent\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    protected $fillable = [
        'company_id',
        'billing_user_id',
        'plan_id',
        'terms_snapshot',
        'billing_period',
        'status',
        'provider',
        'provider_status',
        'provider_subscription_id',
        'checkout_url',
        'external_reference',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'grace_ends_at',
        'cancel_at_period_end',
        'canceled_at',
        'last_provider_event_at',
    ];

    protected function casts(): array
    {
        return [
            'terms_snapshot' => 'array',
            'trial_ends_at' => 'immutable_datetime',
            'current_period_start' => 'immutable_datetime',
            'current_period_end' => 'immutable_datetime',
            'grace_ends_at' => 'immutable_datetime',
            'cancel_at_period_end' => 'boolean',
            'canceled_at' => 'immutable_datetime',
            'last_provider_event_at' => 'immutable_datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function billingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'billing_user_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function paymentAttempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }
}
