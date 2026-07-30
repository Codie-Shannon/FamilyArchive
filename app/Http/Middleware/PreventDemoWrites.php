<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class PreventDemoWrites
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            (bool) config('portfolio_demo.enabled')
            && in_array($request->method(), config('portfolio_demo.write_methods'), true)
        ) {
            abort(Response::HTTP_FORBIDDEN, 'The portfolio demonstration is read-only.');
        }

        return $next($request);
    }
}
