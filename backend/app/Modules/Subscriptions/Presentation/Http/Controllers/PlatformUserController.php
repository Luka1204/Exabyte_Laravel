<?php

namespace App\Modules\Subscriptions\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Subscriptions\Infrastructure\Persistence\Eloquent\PlatformAuditEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlatformUserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::query()
            ->withCount('companies')
            ->withExists('platformAdmin')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'created_at']);

        return response()->json(['data' => $users->map(fn (User $user): array => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'companies_count' => $user->companies_count,
            'is_platform_admin' => (bool) $user->platform_admin_exists,
            'created_at' => $user->created_at,
        ])]);
    }

    public function setAdmin(Request $request, int $user): JsonResponse
    {
        $data = $request->validate(['is_platform_admin' => ['required', 'boolean']]);
        $target = User::query()->findOrFail($user);
        $grant = (bool) $data['is_platform_admin'];
        $exists = DB::table('platform_admins')->where('user_id', $target->id)->exists();

        if (! $grant && $exists && DB::table('platform_admins')->count() <= 1) {
            throw ValidationException::withMessages([
                'is_platform_admin' => ['No se puede revocar al último administrador de plataforma.'],
            ]);
        }

        DB::transaction(function () use ($target, $request, $grant): void {
            if ($grant) {
                DB::table('platform_admins')->insertOrIgnore([
                    'user_id' => $target->id,
                    'granted_by' => $request->user()->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('platform_admins')->where('user_id', $target->id)->delete();
            }

            PlatformAuditEvent::query()->create([
                'actor_user_id' => $request->user()->id,
                'action' => $grant ? 'platform_admin.granted' : 'platform_admin.revoked',
                'subject_type' => User::class,
                'subject_id' => (string) $target->id,
                'details' => ['email' => $target->email, 'source' => 'platform_ui'],
            ]);
        });

        return response()->json(['data' => [
            'id' => $target->id,
            'is_platform_admin' => $grant,
        ]]);
    }
}
