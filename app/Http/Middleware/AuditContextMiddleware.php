<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuditContextMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set(
            'request_id',
            $request->header('X-Request-Id') ?: (string) Str::uuid()
        );

        $request->attributes->set('audit_batch_uuid', (string) Str::uuid());

        return $next($request);
    }
}
