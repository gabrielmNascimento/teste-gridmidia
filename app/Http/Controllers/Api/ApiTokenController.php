<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApiTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        if (! Auth::guard('web')->validate([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
        ])) {
            return response()->json([
                'message' => 'As credenciais informadas são inválidas.',
            ], 401);
        }

        $user = User::query()->where('email', $credentials['email'])->firstOrFail();
        $token = $user->createToken($credentials['device_name']);

        return response()->json([
            'data' => [
                'token_type' => 'Bearer',
                'token' => $token->plainTextToken,
                'user' => $user->only(['id', 'name', 'email']),
            ],
        ], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Token revogado com sucesso.']);
    }
}
