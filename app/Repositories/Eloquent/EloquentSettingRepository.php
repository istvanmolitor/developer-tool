<?php

namespace App\Repositories\Eloquent;

use App\Models\Setting;
use App\Repositories\Contracts\SettingRepository;
use Illuminate\Support\Facades\Cache;

class EloquentSettingRepository implements SettingRepository
{
    public function get(string $key, ?string $default = null): ?string
    {
        return Cache::rememberForever(
            $this->cacheKey($key),
            fn (): ?string => Setting::query()->where('key', $key)->value('value'),
        ) ?? $default;
    }

    public function flag(string $key): bool
    {
        return $this->get($key) === '1';
    }

    public function set(string $key, ?string $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);

        Cache::forget($this->cacheKey($key));
    }

    public function setFlag(string $key, bool $value): void
    {
        $this->set($key, $value ? '1' : '0');
    }

    /**
     * Cache key for a single setting value.
     */
    private function cacheKey(string $key): string
    {
        return "setting:{$key}";
    }
}
