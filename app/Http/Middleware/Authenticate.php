<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class Authenticate
{
    public function handle(Request $request, Closure $next)
    {
        $token = PersonalAccessToken::findToken($request->bearerToken());

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado'
            ], 401);
        }

        auth()->setUser($token->tokenable);

        return $next($request);
    }
}