<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdempotencyKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (! is_string($key) || $key === '' || strlen($key) > 255) {
            return response()->json([
                'message' => 'The Idempotency-Key header is required.',
            ], Response::HTTP_BAD_REQUEST);
        }

        return $next($request);
    }
}
