<?php

namespace App\Http\Middleware;

use App\Models\ClientSystem;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateClientSystem
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $client = $token ? ClientSystem::where('key_hash', hash('sha256', $token))->where('active', true)->first() : null;

        if (! $client) {
            return response()->json(['message' => '无效的接入密钥'], 401);
        }

        $request->attributes->set('client_system', $client);

        return $next($request);
    }
}
