<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $r, Closure $next, ...$roles)
    {
        abort_unless($r->user() && in_array($r->user()->role?->name, $roles, true), 403, 'Anda tidak memiliki akses untuk melakukan tindakan ini.');

        return $next($r);
    }
}
