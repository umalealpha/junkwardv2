<?php

namespace AlphaDirect\Services\Backdate;

use AlphaDirect\Exceptions\BackdateForbiddenException;
use AlphaDirect\Models\AppSetting;
use AlphaDirect\Models\ClaimBackdateEvent;
use AlphaDirect\Models\ClaimBackdateGrant;
use AlphaDirect\Services\IntegrationSettings;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * BackdateGovernanceService — the ported Claims Tracker backdate rules.
 *
 * A change to a watched claim stage-date field to a value < today is a
 * "backdate". Rules (mirroring the tracker):
 *   - Hard floor: reject anything before the current Botswana FY start (1 July).
 *   - Cap: reject beyond `max_days_back` (default 30, runtime-overridable).
 *   - Permission: manage-role users may always backdate (override); request-role
 *     users may backdate only with an active, unexpired, un-revoked grant
 *     (per-user or ALL_CLAIMS_MANAGERS); everyone else is blocked.
 *
 * FLAG-GATED: enforce() and the alerts are a complete no-op unless the runtime
 * `claims_backdate_governance` flag is on. With the flag off, enforce() returns
 * the {enforced:false} sentinel WITHOUT inspecting, validating, recording or
 * alerting — so the claim date-edit path behaves exactly as before.
 */
class BackdateGovernanceService
{
    /** Runtime flag: is backdate enforcement + alerting on? */
    public function isEnabled(): bool
    {
        return IntegrationSettings::isEnabled(
            (string) config('claims_backdate.integration_key', 'claims_backdate_governance'),
            (bool) config('claims_backdate.enabled', false),
        );
    }

    /** Watched date-field columns. */
    public function dateFields(): array
    {
        return (array) config('claims_backdate.date_fields', []);
    }

    private function tz(): string
    {
        return (string) config('claims_backdate.timezone', 'Africa/Gaborone');
    }

    /** "Today" (date only) in the configured timezone. */
    public function today(): Carbon
    {
        return Carbon::now($this->tz())->startOfDay();
    }

    /** Current Botswana FY start (1 July) as a date. */
    public function fyStart(): Carbon
    {
        $now       = Carbon::now($this->tz());
        $fyMonth   = (int) config('claims_backdate.fy_start_month', 7);
        $startYear = $now->month >= $fyMonth ? $now->year : $now->year - 1;

        return Carbon::create($startYear, $fyMonth, 1, 0, 0, 0, $this->tz())->startOfDay();
    }

    /** Effective max-days-back (runtime AppSetting override, else config default). */
    public function maxDaysBack(): int
    {
        $key      = config('claims_backdate.settings_keys.max_days_back', 'backdate_max_days_back');
        $stored   = AppSetting::get($key);
        $fallback = (int) config('claims_backdate.max_days_back', 30);
        $n        = $stored !== null ? (int) $stored : $fallback;

        return $n >= 1 ? $n : $fallback;
    }

    /** The active grant that covers this user, or null. */
    public function activeGrantForUser($user): ?ClaimBackdateGrant
    {
        if (!$user) {
            return null;
        }
        $allToken = (string) config('claims_backdate.all_managers_token', 'ALL_CLAIMS_MANAGERS');

        return ClaimBackdateGrant::query()
            ->active()
            ->where(function ($q) use ($user, $allToken) {
                $q->where('target_user_id', (string) $user->id)
                  ->orWhere('target_user_id', $allToken);
            })
            ->orderByDesc('granted_at')
            ->first();
    }

    private function manageRoles(): array
    {
        return (array) config('claims_backdate.roles.manage', []);
    }

    private function requestRoles(): array
    {
        return (array) config('claims_backdate.roles.request', []);
    }

    private function hasAnyRole($user, array $roles): bool
    {
        if (!$user || empty($roles)) {
            return false;
        }
        try {
            return $user->hasAnyRole($roles);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function canManage($user): bool
    {
        return $this->hasAnyRole($user, $this->manageRoles());
    }

    public function canRequest($user): bool
    {
        return $this->hasAnyRole($user, $this->requestRoles());
    }

    /**
     * Normalise a stored/incoming date value to a Y-m-d string (or '' if empty).
     */
    private function toDateString($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable $e) {
            return trim((string) $value);
        }
    }

    /**
     * Evaluate + enforce the backdate rules against a set of proposed date-field
     * changes. Called from the claim date-edit path BEFORE the write.
     *
     * @param array<int,array{field:string,old:mixed,new:mixed}> $dateChanges
     *        Only watched date fields, already diffed (old vs new).
     * @param mixed $user The authenticated user (nullable).
     *
     * @return array{enforced:bool,isBackdate:bool,grantId:?int}
     *
     * @throws BackdateForbiddenException when the flag is ON and a change violates
     *         the floor / cap / grant rules. NEVER throws when the flag is OFF.
     */
    public function enforce(array $dateChanges, $user): array
    {
        // FLAG OFF → complete no-op. Do not inspect, validate, record or alert.
        if (!$this->isEnabled()) {
            return ['enforced' => false, 'isBackdate' => false, 'grantId' => null];
        }

        $today = $this->today();

        // Which changes push a date into the past (vs today)?
        $backdated = [];
        foreach ($dateChanges as $c) {
            $newStr = $this->toDateString($c['new'] ?? null);
            if ($newStr === '') {
                continue;
            }
            if (Carbon::parse($newStr, $this->tz())->startOfDay()->lt($today)) {
                $backdated[] = $c + ['new_str' => $newStr];
            }
        }

        if (empty($backdated)) {
            return ['enforced' => true, 'isBackdate' => false, 'grantId' => null];
        }

        // Hard floor — nothing before current FY start.
        $fyStart = $this->fyStart();
        foreach ($backdated as $c) {
            if (Carbon::parse($c['new_str'], $this->tz())->startOfDay()->lt($fyStart)) {
                throw new BackdateForbiddenException(
                    "Date {$c['new_str']} is before the current financial year start ({$fyStart->toDateString()}).",
                    'BACKDATE_BEFORE_FY',
                );
            }
        }

        // Cap — not more than max-days-back.
        $maxDays    = $this->maxDaysBack();
        $minAllowed = $today->copy()->subDays($maxDays);
        foreach ($backdated as $c) {
            if (Carbon::parse($c['new_str'], $this->tz())->startOfDay()->lt($minAllowed)) {
                throw new BackdateForbiddenException(
                    "Date {$c['new_str']} exceeds the maximum allowed ({$maxDays} days back).",
                    'BACKDATE_EXCEEDS_CAP',
                );
            }
        }

        // Permission.
        if ($this->canManage($user)) {
            return ['enforced' => true, 'isBackdate' => true, 'grantId' => null]; // admin override
        }
        if ($this->canRequest($user)) {
            $grant = $this->activeGrantForUser($user);
            if ($grant) {
                return ['enforced' => true, 'isBackdate' => true, 'grantId' => (int) $grant->id];
            }
        }

        throw new BackdateForbiddenException(
            'Backdating is not permitted. Contact an admin to request a time-limited approval window.',
            'BACKDATE_NOT_ALLOWED',
        );
    }

    /**
     * Record a backdate event and fire the (send-gated) alerts. Call AFTER the
     * write commits and only when enforce() reported isBackdate=true.
     *
     * @param array<int,array{field:string,old:mixed,new:mixed}> $dateChanges
     */
    public function recordEvent(?int $grantId, int $claimId, ?string $claimNumber, $user, array $dateChanges): void
    {
        // Belt-and-braces: never record while the flag is off.
        if (!$this->isEnabled()) {
            return;
        }

        $today = $this->today();
        $backdated = array_values(array_filter(array_map(function ($c) use ($today) {
            $newStr = $this->toDateString($c['new'] ?? null);
            if ($newStr === '' || !Carbon::parse($newStr, $this->tz())->startOfDay()->lt($today)) {
                return null;
            }
            return [
                'field' => $c['field'] ?? '',
                'old'   => $this->toDateString($c['old'] ?? null),
                'new'   => $newStr,
            ];
        }, $dateChanges)));

        if (empty($backdated)) {
            return;
        }

        $username = $this->userLabel($user);
        $role     = $this->userRole($user);

        try {
            ClaimBackdateEvent::create([
                'grant_id'     => $grantId,
                'claim_id'     => $claimId,
                'claim_number' => (string) ($claimNumber ?? ''),
                'username'     => $username,
                'user_role'    => $role,
                'changes_json' => json_encode($backdated),
            ]);
        } catch (\Throwable $e) {
            Log::warning('claim backdate event log failed: ' . $e->getMessage());
        }

        $this->sendAlerts([
            'claim_id'     => $claimId,
            'claim_number' => (string) ($claimNumber ?? ''),
            'username'     => $username,
            'user_role'    => $role,
            'grant'        => $grantId ? "#{$grantId}" : 'admin override',
            'changes'      => $backdated,
            'timestamp'    => now()->toIso8601String(),
        ]);
    }

    /**
     * Send-gated Teams + email alert. No send happens unless the flag is ON
     * (guarded above/here) AND the channel is configured. Fully best-effort —
     * a failure here never affects the claim edit.
     */
    private function sendAlerts(array $payload): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $keys      = (array) config('claims_backdate.settings_keys', []);
        $webhook   = trim((string) AppSetting::get($keys['teams_webhook'] ?? 'backdate_teams_webhook', ''));
        $recipRaw  = trim((string) AppSetting::get($keys['alert_recipients'] ?? 'backdate_alert_recipients', ''));

        $summary = sprintf(
            'Backdate on claim %s by %s (%s) — %d field(s) [%s]',
            $payload['claim_number'] ?: $payload['claim_id'],
            $payload['username'],
            $payload['user_role'] ?: 'unknown role',
            count($payload['changes']),
            $payload['grant'],
        );

        // Teams — only if a webhook URL is configured.
        if ($webhook !== '') {
            try {
                Http::timeout(5)->post($webhook, [
                    '@type'    => 'MessageCard',
                    '@context' => 'http://schema.org/extensions',
                    'summary'  => 'Claim backdate alert',
                    'themeColor' => 'FE7F0C',
                    'title'    => 'Claim backdate alert',
                    'text'     => $summary,
                ]);
            } catch (\Throwable $e) {
                Log::warning('backdate Teams alert failed: ' . $e->getMessage());
            }
        }

        // Email — only if at least one recipient is configured.
        $recipients = array_values(array_filter(array_map('trim', preg_split('/[,;\s]+/', $recipRaw))));
        if (!empty($recipients)) {
            try {
                $lines = array_map(
                    fn ($c) => sprintf('• %s: %s → %s', $c['field'], $c['old'] ?: '(none)', $c['new']),
                    $payload['changes'],
                );
                $body = $summary . "\n\n" . implode("\n", $lines);
                Mail::raw($body, function ($m) use ($recipients, $payload) {
                    $m->to($recipients)
                      ->subject('[Backdate Alert] ' . ($payload['claim_number'] ?: $payload['claim_id']) . ' — by ' . $payload['username']);
                });
            } catch (\Throwable $e) {
                Log::warning('backdate email alert failed: ' . $e->getMessage());
            }
        }
    }

    public function userLabel($user): string
    {
        if (!$user) {
            return 'system';
        }
        $name = trim((string) ($user->firstName ?? '') . ' ' . (string) ($user->lastName ?? ''));
        if ($name !== '') {
            return $name;
        }
        return (string) ($user->email ?? $user->name ?? ('user#' . ($user->id ?? '?')));
    }

    public function userRole($user): string
    {
        if (!$user) {
            return '';
        }
        try {
            return (string) ($user->getRoleNames()->first() ?? '');
        } catch (\Throwable $e) {
            return '';
        }
    }
}
