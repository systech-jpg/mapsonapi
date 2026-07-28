<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\DolibarrUser;
use App\Traits\ApiResponse;

class CheckDolibarrApiKey
{
    use ApiResponse;

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get API Key from Bearer Token or Header (x-api-key)
        $apiKey = $request->bearerToken() ?? $request->header('x-api-key');

        if (!$apiKey) {
            return $this->errorResponse('API Key is missing. Please provide it in Authorization Bearer or x-api-key header.', 401);
        }

        // Find the user with this API Key
        // Since api_key is unique to user, we look for it in llxjp_user
        $user = DolibarrUser::with('groups')->where('api_key', $apiKey)->first();

        if (!$user) {
            return $this->errorResponse('Invalid API Key. Unauthorized.', 401);
        }

        // Attach the user to the request so controllers can access it
        $request->attributes->add(['dolibarr_user' => $user]);

        return $next($request);
    }
}
