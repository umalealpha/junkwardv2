<?php

namespace AlphaDirect\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * IntegrationSettings — runtime on/off switch for third-party integrations.
 *
 * Backed by the integration_settings table (mysql_system). An authorised
 * operator toggles a flag from the admin UI; the outbound service and inbound
 * webhook handler read it before doing any work. No redeploy needed.
 *
 * Every toggle is also written to the Spatie activity log (causer + old→new)
 * so there is a durable audit trail of who turned an integration on/off and
 * when — matching the audit pattern used by CronConfigController::runNow.
 */
class IntegrationSettings
{
    /**
     * Is the named integration currently enabled?
     *
     * Falls back to config("services.<integration>.enabled") (default false)
     * when there is no DB row yet, and on any DB read error — so a storage
     * blip can never accidentally flip an integration's effective state.
     */
    public static function isEnabled(string $integration, ?bool $default = null): bool
    {
        $default ??= (bool) config("services.$integration.enabled", false);

        try {
            $row = self::table()->where('integration', $integration)->first();
            return $row ? (bool) $row->enabled : $default;
        } catch (\Throwable $e) {
            Log::warning('integration_settings read failed', [
                'integration' => $integration,
                'msg'         => $e->getMessage(),
            ]);
            return $default;
        }
    }

    public static function get(string $integration): ?object
    {
        try {
            return self::table()->where('integration', $integration)->first();
        } catch (\Throwable $e) {
            Log::warning('integration_settings read failed', ['integration' => $integration, 'msg' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Toggle an integration and record the change to the audit log.
     *
     * @return array{integration:string, enabled:bool, previous:?bool, updated_by:?string, updated_at:string}
     */
    public static function setEnabled(string $integration, bool $enabled, $user = null, ?string $notes = null): array
    {
        $now      = now();
        $existing = self::get($integration);
        $previous = $existing ? (bool) $existing->enabled : null;

        $row = [
            'enabled'         => $enabled ? 1 : 0,
            'updated_by'      => $user?->id,
            'updated_by_name' => $user?->email ?? 'system',
            'notes'           => $notes !== null ? mb_substr($notes, 0, 500) : null,
            'updated_at'      => $now,
        ];

        if ($existing) {
            self::table()->where('integration', $integration)->update($row);
        } else {
            self::table()->insert(array_merge($row, [
                'integration' => $integration,
                'created_at'  => $now,
            ]));
        }

        // Audit trail — wrapped so a missing activity_log table never breaks
        // the toggle (mirrors CronConfigController).
        try {
            activity('integration')
                ->causedBy($user)
                ->withProperties([
                    'integration' => $integration,
                    'enabled'     => $enabled,
                    'previous'    => $previous,
                    'notes'       => $row['notes'],
                ])
                ->log('Integration ' . $integration . ' ' . ($enabled ? 'enabled' : 'disabled'));
        } catch (\Throwable $e) {
            Log::warning('integration audit log write failed: ' . $e->getMessage());
        }

        Log::info('Integration toggled', [
            'integration' => $integration,
            'enabled'     => $enabled,
            'previous'    => $previous,
            'by'          => $row['updated_by_name'],
        ]);

        return [
            'integration' => $integration,
            'enabled'     => $enabled,
            'previous'    => $previous,
            'updated_by'  => $row['updated_by_name'],
            'updated_at'  => $now->toIso8601String(),
        ];
    }

    /**
     * Read a single non-secret setting (e.g. program_id) from the JSON
     * `settings` column. Falls back to $default on any storage error.
     */
    public static function getSetting(string $integration, string $key, mixed $default = null): mixed
    {
        try {
            $row = self::get($integration);
            if (!$row || empty($row->settings)) {
                return $default;
            }
            $decoded = json_decode($row->settings, true);
            return is_array($decoded) ? ($decoded[$key] ?? $default) : $default;
        } catch (\Throwable $e) {
            Log::warning('integration_settings getSetting failed', ['integration' => $integration, 'key' => $key, 'msg' => $e->getMessage()]);
            return $default;
        }
    }

    /**
     * Upsert a single non-secret setting. NEVER store secrets here (api_key,
     * webhook_secret stay in env/SSM) — this is for identifiers like program_id.
     * Audit-logs the key that changed (not the value).
     */
    public static function putSetting(string $integration, string $key, ?string $value, $user = null): void
    {
        $now      = now();
        $existing = self::get($integration);

        $settings = [];
        if ($existing && !empty($existing->settings)) {
            $decoded = json_decode($existing->settings, true);
            if (is_array($decoded)) {
                $settings = $decoded;
            }
        }
        if ($value === null || $value === '') {
            unset($settings[$key]);
        } else {
            $settings[$key] = $value;
        }
        $payload = json_encode($settings);

        $row = [
            'settings'        => $payload,
            'updated_by'      => $user?->id,
            'updated_by_name' => $user?->email ?? 'system',
            'updated_at'      => $now,
        ];

        if ($existing) {
            self::table()->where('integration', $integration)->update($row);
        } else {
            self::table()->insert(array_merge($row, [
                'integration' => $integration,
                'enabled'     => 0,
                'created_at'  => $now,
            ]));
        }

        try {
            activity('integration')
                ->causedBy($user)
                ->withProperties(['integration' => $integration, 'setting' => $key])
                ->log('Integration ' . $integration . " setting '" . $key . "' updated");
        } catch (\Throwable $e) {
            Log::warning('integration setting audit log failed: ' . $e->getMessage());
        }

        Log::info('Integration setting updated', ['integration' => $integration, 'key' => $key, 'by' => $row['updated_by_name']]);
    }

    private static function table()
    {
        return DB::connection('mysql_system')->table('integration_settings');
    }
}
