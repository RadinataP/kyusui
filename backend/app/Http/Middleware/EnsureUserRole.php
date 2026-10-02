<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        if ($request->user() === null || ! in_array($request->user()->role?->name, $roles, true)) {
            return new JsonResponse([
                'data' => null,
                'message' => 'Anda tidak memiliki akses untuk melakukan tindakan ini.',
            ], 403);
        }

        return $next($request);
    }
}
