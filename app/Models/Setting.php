<?php

namespace App\Models;

use App\Repositories\Contracts\SettingRepository;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Application-wide key-value setting.
 *
 * The static accessors below are a thin convenience shim that delegates to the
 * {@see SettingRepository}; the repository owns the storage and caching, and
 * application code should type-hint the repository rather than reach for these.
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
     * The raw stored value for the key, or the default when it is not set.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        return app(SettingRepository::class)->get($key, $default);
    }

    /**
     * The setting read as a boolean flag ("1" / "0").
     */
    public static function flag(string $key): bool
    {
        return app(SettingRepository::class)->flag($key);
    }

    /**
     * Store the value for the key, creating the row when it does not exist.
     */
    public static function set(string $key, ?string $value): void
    {
        app(SettingRepository::class)->set($key, $value);
    }

    /**
     * Store a boolean flag as "1" / "0".
     */
    public static function setFlag(string $key, bool $value): void
    {
        app(SettingRepository::class)->setFlag($key, $value);
    }
}
