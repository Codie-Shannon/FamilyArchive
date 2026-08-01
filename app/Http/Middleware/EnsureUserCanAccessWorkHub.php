<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureUserCanAccessWorkHub
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->user()?->canAccessWorkHub(),
            Response::HTTP_FORBIDDEN,
            'This account does not have an operational work queue.'
        );

        return $next($request);
    }
}
