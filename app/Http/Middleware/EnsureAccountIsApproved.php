<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAccountIsApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->user()?->account_state === 'approved',
            Response::HTTP_FORBIDDEN,
            'Owner approval is required before this account can access the archive.'
        );

        return $next($request);
    }
}
