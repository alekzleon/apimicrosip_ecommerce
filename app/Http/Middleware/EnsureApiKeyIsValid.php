<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiKeyIsValid
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedKey = config('services.internal_api.key');
        $providedKey = (string) $request->header('X-API-KEY');

        if (! $expectedKey || ! hash_equals($expectedKey, $providedKey)) {
            return response()->json([
                'ok' => false,
                'message' => 'No autorizado',
            ], 401);
        }

        return $next($request);
    }
}