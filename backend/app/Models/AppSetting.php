<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Generic key/value runtime setting, editable in the DB without a redeploy.
 * Mirrors AlphaDirect\Services\IntegrationSettings but for plain string
 * values instead of enabled/disabled flags.
 */
class AppSetting extends Model
{
    protected $table = 'app_settings';

    protected $fillable = ['key', 'value', 'updated_by', 'updated_by_name'];

    /**
     * Read a setting's value. Falls back to $default when there is no row
     * yet, and on any DB read error — a storage blip must never break a
     * caller that depends on this (e.g. routing an underwriting email).
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        try {
            return static::query()->where('key', $key)->value('value') ?? $default;
        } catch (\Throwable $e) {
            Log::warning('app_settings read failed', ['key' => $key, 'msg' => $e->getMessage()]);
            return $default;
        }
    }

    public static function set(string $key, ?string $value, $user = null): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'updated_by' => $user?->id, 'updated_by_name' => $user?->email ?? 'system']
        );
    }
}
