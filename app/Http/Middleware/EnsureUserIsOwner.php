<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless(
            $user !== null && $user->role === 'owner',
            Response::HTTP_FORBIDDEN,
            'Owner access is required.'
        );

        return $next($request);
    }
}
