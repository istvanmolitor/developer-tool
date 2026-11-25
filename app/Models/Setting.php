<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Application-wide key-value setting.
 *
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Cache key for a single setting value.
     */
    private static function cacheKey(string $key): string
    {
        return "setting:{$key}";
    }

    /**
     * The raw stored value for the key, or the default when it is not set.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        return Cache::rememberForever(
            self::cacheKey($key),
            fn (): ?string => self::query()->where('key', $key)->value('value'),
        ) ?? $default;
    }

    /**
     * The setting read as a boolean flag ("1" / "0").
     */
    public static function flag(string $key): bool
    {
        return self::get($key) === '1';
    }

    /**
     * Store the value for the key, creating the row when it does not exist.
     */
    public static function set(string $key, ?string $value): void
    {
        self::query()->updateOrCreate(['key' => $key], ['value' => $value]);

        Cache::forget(self::cacheKey($key));
    }

    /**
     * Store a boolean flag as "1" / "0".
     */
    public static function setFlag(string $key, bool $value): void
    {
        self::set($key, $value ? '1' : '0');
    }
}
