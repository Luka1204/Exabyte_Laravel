<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Subscriptions\Infrastructure\Persistence\Eloquent\PlatformAuditEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SetPlatformAdmin extends Command
{
    protected $signature = 'platform-admin:set {email} {--revoke : Remove platform administrator access}';

    protected $description = 'Grant or revoke platform administrator access for an existing account';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $this->error('No existe una cuenta con ese correo.');

            return self::FAILURE;
        }

        $revoke = (bool) $this->option('revoke');
        DB::transaction(function () use ($user, $revoke): void {
            if ($revoke) {
                DB::table('platform_admins')->where('user_id', $user->id)->delete();
            } else {
                DB::table('platform_admins')->insertOrIgnore([
                    'user_id' => $user->id,
                    'granted_by' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            PlatformAuditEvent::query()->create([
                'actor_user_id' => null,
                'action' => $revoke ? 'platform_admin.revoked' : 'platform_admin.granted',
                'subject_type' => User::class,
                'subject_id' => (string) $user->id,
                'details' => ['email' => $user->email, 'source' => 'console'],
            ]);
        });

        $this->info($revoke
            ? 'Se revocó el acceso de administración de plataforma.'
            : 'Se otorgó acceso de administración de plataforma.');

        return self::SUCCESS;
    }
}
