<?php

namespace App\Http\Controllers\api\LuisFigueroa;

use App\Http\Controllers\Controller;
use App\Models\AppUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Issues and revokes Sanctum bearer tokens. Neither the password nor the token is
 * ever written to the logs; the plain-text token is shown once, in the login response.
 */
class AuthTokenController extends Controller
{
    // POST /api/auth/token
    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ], [
            'required' => 'El campo :attribute es obligatorio.',
            'email' => 'El campo :attribute debe ser un correo válido.',
        ]);

        $usuario = AppUser::where('email', $datos['email'])->first();

        // Same answer for "unknown user", "wrong password" and "inactive user": no user enumeration.
        if (! $usuario || ! $usuario->is_active || ! Hash::check($datos['password'], $usuario->password_hash)) {
            return response()->json(['mensaje' => 'Credenciales inválidas.'], 401, [], JSON_UNESCAPED_UNICODE);
        }

        $expira = now()->addMinutes(config('luisfigueroa.token_ttl_minutes'));
        $token = $usuario->createToken('api', ['*'], $expira);

        return response()->json([
            'mensaje' => 'Token emitido',
            'token_type' => 'Bearer',
            'access_token' => $token->plainTextToken,
            'expira_en' => $expira->toIso8601String(),
            'usuario' => [
                'id' => $usuario->id,
                'rol' => $usuario->esAdmin() ? 'admin' : 'docente',
            ],
        ]);
    }

    // POST /api/auth/logout
    public function destroy(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['mensaje' => 'Sesión cerrada.']);
    }
}
