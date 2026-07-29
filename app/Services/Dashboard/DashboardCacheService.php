<?php

namespace App\Services\Dashboard;

use Closure;
use Illuminate\Support\Facades\Cache;

class DashboardCacheService
{
    private const VERSION_KEY = 'dashboard:cache_version';

    public function remember(string $key, int $seconds, Closure $callback): mixed
    {
        return Cache::remember($this->versionedKey($key), now()->addSeconds($seconds), $callback);
    }

    public function clear(): void
    {
        Cache::forever(self::VERSION_KEY, $this->version() + 1);
    }

    private function versionedKey(string $key): string
    {
        return 'dashboard:v' . $this->version() . ':' . $key;
    }

    private function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }
}
