<?php

namespace App\Modules\Identity\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Modules\Identity\Infrastructure\Jwt\JwtTokenService;

class AuthController extends Controller
{
    public function login(
        Request $request,
        JwtTokenService $tokens
    ) {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->where('email', $data['email'])
            ->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales son incorrectas.'],
            ]);
        }

        return response()->json([
            'access_token' => $tokens->issue($user),
            'token_type' => 'Bearer',
            'expires_in' => (int) config('jwt.ttl', 900),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
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

    public function logout(Request $request)
    {
        $user = $request->user();

        $user->token_version++;
        $user->save();

        return response()->json([
            'message' => 'Sesiones anteriores revocadas.',
        ]);
    }
}
