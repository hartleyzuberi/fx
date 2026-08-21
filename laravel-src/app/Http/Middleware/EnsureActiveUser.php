<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($request->user() && $request->user()->is_active === false, 403, 'This account is inactive. Contact an administrator.');

        return $next($request);
    }
}
