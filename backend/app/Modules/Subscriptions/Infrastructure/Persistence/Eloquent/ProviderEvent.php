<?php

namespace App\Modules\Subscriptions\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

class ProviderEvent extends Model
{
    protected $fillable = [
        'provider',
        'provider_event_id',
        'topic',
        'resource_id',
        'request_id',
        'payload',
        'processing_status',
        'failure_code',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'immutable_datetime',
        ];
    }
}
