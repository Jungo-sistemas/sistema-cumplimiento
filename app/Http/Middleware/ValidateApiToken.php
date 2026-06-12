<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $raw = $request->bearerToken();

        if (! $raw) {
            return response()->json(['error' => 'Token requerido.'], 401);
        }

        $token = ApiToken::where('token', hash('sha256', $raw))->first();

        if (! $token) {
            return response()->json(['error' => 'Token inválido.'], 401);
        }

        $token->updateQuietly(['last_used_at' => now()]);

        $request->attributes->set('api_company_id', $token->company_id);

        return $next($request);
    }
}
