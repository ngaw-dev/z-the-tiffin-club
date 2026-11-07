<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || $request->user()->role !== 'owner') {
            return response()->json([
                'message' => 'This action is unauthorized.',
            ], 403);
        }

        return $next($request);
    }
}
