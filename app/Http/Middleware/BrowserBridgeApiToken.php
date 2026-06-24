<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BrowserBridgeApiToken
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $configuredToken = config('browserbridge.api_token');
        $providedToken = $request->bearerToken();

        if (! is_string($configuredToken) || $configuredToken === '') {
            return response()->json([
                'message' => 'BrowserBridge API token is not configured.',
            ], 503);
        }

        if (! is_string($providedToken) || ! hash_equals($configuredToken, $providedToken)) {
            return response()->json([
                'message' => 'Invalid BrowserBridge API token.',
            ], 401);
        }

        return $next($request);
    }
}
