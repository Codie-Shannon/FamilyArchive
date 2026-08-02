<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAccountIsApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->account_state !== 'approved') {
            if (! $request->expectsJson()) {
                return redirect()->route('account.waiting');
            }

            abort(Response::HTTP_FORBIDDEN, 'Account approval is required before this account can access the archive.');
        }

        return $next($request);
    }
}
