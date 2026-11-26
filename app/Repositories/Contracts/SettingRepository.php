<?php

namespace App\Repositories\Contracts;

use App\Models\Setting;

/**
 * Data access for the application-wide key-value {@see Setting}
 * store.
 *
 * This is a key-value store rather than entity CRUD, so unlike the other
 * contracts it does not extend {@see Repository}.
 */
interface SettingRepository
{
    /**
     * The raw stored value for the key, or the default when it is not set.
     */
    public function get(string $key, ?string $default = null): ?string;

    /**
     * The setting read as a boolean flag ("1" / "0").
     */
    public function flag(string $key): bool;

    /**
     * Store the value for the key, creating the row when it does not exist.
     */
    public function set(string $key, ?string $value): void;

    /**
     * Store a boolean flag as "1" / "0".
     */
    public function setFlag(string $key, bool $value): void;
}
