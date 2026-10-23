<?php

namespace App\Modules\Identity\Infrastructure\Jwt;

use App\Models\User;

final readonly class AuthenticatedSession
{
    public function __construct(
        public User $user,
        public ?string $familyId,
    ) {}
}
