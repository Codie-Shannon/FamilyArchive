<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureUserCanManageTrustedIntake
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->user()?->canManageTrustedIntake(),
            Response::HTTP_FORBIDDEN,
            'Trusted intake access is required.',
        );

        return $next($request);
    }
}
