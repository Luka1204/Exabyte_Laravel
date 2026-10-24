<?php

namespace App\Modules\Identity\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Application\UseCases\RefreshSession;
use App\Modules\Identity\Application\UseCases\RegisterAccount;
use App\Modules\Identity\Presentation\Requests\RegisterAccountRequest;
use App\Modules\Identity\Presentation\Requests\RefreshSessionRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(
        RegisterAccountRequest $request,
        RegisterAccount $register,
        RefreshSession $sessions
    ) {
        $result = $register->execute($request->validated());

        return response()->json([
            ...$sessions->create($result['user']),
            'user' => [
                'id' => $result['user']->id,
                'name' => $result['user']->name,
                'email' => $result['user']->email,
            ],
            'company' => [
                'id' => $result['company']->id,
                'name' => $result['company']->name,
                'role' => 'owner',
            ],
        ], 201);
    }

    public function login(
        Request $request,
        RefreshSession $sessions
    ) {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->where('email', $data['email'])
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales son incorrectas.'],
            ]);
        }

        return response()->json([
            ...$sessions->create($user),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function refresh(RefreshSessionRequest $request, RefreshSession $sessions)
    {
        return response()->json(
            $sessions->rotate($request->validated('refresh_token'))
        );
    }

    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);
    }

    public function logout(Request $request, RefreshSession $sessions)
    {
        $user = $request->user();
        $familyId = $request->attributes->get('auth_session_family_id');

        if (is_string($familyId)) {
            $sessions->revokeFamily($familyId);
        } else {
            $user->increment('token_version');
        }

        return response()->json([
            'message' => 'Sesión revocada.',
        ]);
    }
}
