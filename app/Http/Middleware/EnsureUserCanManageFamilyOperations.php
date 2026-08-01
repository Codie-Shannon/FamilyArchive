<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureUserCanManageFamilyOperations
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->user()?->canManageFamilyOperations(),
            Response::HTTP_FORBIDDEN,
            'Archive administrator access is required.'
        );

        return $next($request);
    }
}
