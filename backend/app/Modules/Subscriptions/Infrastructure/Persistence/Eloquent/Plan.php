<?php

namespace App\Modules\Subscriptions\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
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
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'benefits' => 'array',
            'limits' => 'array',
            'entitlements' => 'array',
            'monthly_amount' => 'decimal:2',
            'annual_amount' => 'decimal:2',
            'trial_days' => 'integer',
            'grace_days' => 'integer',
            'terms_version' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
