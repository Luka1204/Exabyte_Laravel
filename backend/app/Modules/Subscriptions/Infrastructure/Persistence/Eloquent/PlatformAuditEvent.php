<?php

namespace App\Modules\Subscriptions\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

class PlatformAuditEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_user_id',
        'action',
        'subject_type',
        'subject_id',
        'details',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
