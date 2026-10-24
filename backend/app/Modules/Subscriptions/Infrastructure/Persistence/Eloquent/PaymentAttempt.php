<?php

namespace App\Modules\Subscriptions\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAttempt extends Model
{
    protected $fillable = [
        'subscription_id',
        'attempt_number',
        'provider',
        'kind',
        'status',
        'amount',
        'currency',
        'provider_invoice_id',
        'provider_payment_id',
        'provider_status',
        'external_reference',
        'provider_data',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'provider_data' => 'array',
            'paid_at' => 'immutable_datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
