<?php

namespace App\Services\Audit;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditContext
{
    public function forRequest(Request $request): array
    {
        $userAgent = (string) $request->userAgent();

        return [
            'batch_uuid' => $request->attributes->get('audit_batch_uuid') ?? (string) Str::uuid(),
            'request_id' => $request->attributes->get('request_id') ?? $request->header('X-Request-Id') ?? (string) Str::uuid(),
            'ip_address' => $request->ip(),
            'user_agent' => $userAgent ?: null,
            'device' => $this->device($userAgent),
            'platform' => $this->platform($userAgent),
            'browser' => $this->browser($userAgent),
            'method' => $request->method(),
            'route' => optional($request->route())->getName() ?? optional($request->route())->uri(),
            'url' => $request->url(),
        ];
    }

    private function device(string $userAgent): ?string
    {
        if ($userAgent === '') {
            return null;
        }

        if (preg_match('/tablet|ipad/i', $userAgent)) {
            return 'tablet';
        }

        if (preg_match('/mobile|iphone|android/i', $userAgent)) {
            return 'mobile';
        }

        return 'desktop';
    }

    private function platform(string $userAgent): ?string
    {
        return match (true) {
            $userAgent === '' => null,
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Mac OS') => 'macOS',
            str_contains($userAgent, 'iPhone'), str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'Unknown',
        };
    }

    private function browser(string $userAgent): ?string
    {
        return match (true) {
            $userAgent === '' => null,
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'Chrome/') && ! str_contains($userAgent, 'Edg/') => 'Chrome',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Safari/') && ! str_contains($userAgent, 'Chrome/') => 'Safari',
            default => 'Unknown',
        };
    }
}
