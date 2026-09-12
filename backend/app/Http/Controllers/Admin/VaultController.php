<?php

namespace AlphaDirect\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Contracts\Encryption\DecryptException;

class VaultController extends Controller
{
    private const CACHE_KEY   = 'credential_vault_cache';
    private const CACHE_TTL   = 600; // 10 minutes
    private const SESSION_KEY = 'vault_unlocked_at';

    // ── Public page ──────────────────────────────────────────────────────────

    public function index()
    {
        $vaultAccess = DB::table('vault_access')->first();
        $isUnlocked  = $this->isUnlocked($vaultAccess);
        $hasPinSet   = !empty($vaultAccess?->pin_hash);

        $credentials    = collect();
        $groupedByCategory = [];
        $remainingSeconds  = 0;

        if ($isUnlocked) {
            $rows = DB::table('credential_vault')
                ->orderBy('category')
                ->orderBy('sort_order')
                ->get();

            foreach ($rows as $row) {
                $groupedByCategory[$row->category][] = $row;
            }

            // Calculate remaining seconds until auto-lock
            $unlockedAt      = session(self::SESSION_KEY);
            $timeoutMinutes  = $vaultAccess?->pin_timeout_minutes ?? 15;
            $expiresAt       = $unlockedAt->copy()->addMinutes($timeoutMinutes);
            $remainingSeconds = max(0, now()->diffInSeconds($expiresAt, false));
        }

        // Check lockout state
        $lockedUntil       = null;
        $lockedMinutesLeft = 0;
        if ($vaultAccess && $vaultAccess->locked_until) {
            $lockedUntil = \Carbon\Carbon::parse($vaultAccess->locked_until);
            if ($lockedUntil->isFuture()) {
                $lockedMinutesLeft = (int) ceil(now()->diffInSeconds($lockedUntil) / 60);
            } else {
                $lockedUntil = null;
            }
        }

        $categories = [
            'ai'          => 'AI Providers',
            'payment'     => 'Payment Gateways',
            'sms'         => 'SMS & Comms',
            'cloud'       => 'Cloud Storage',
            'compliance'  => 'Compliance',
            'tracking'    => 'Vehicle Tracking',
            'email'       => 'Email',
        ];

        return view('admin.vault.index', compact(
            'isUnlocked',
            'hasPinSet',
            'groupedByCategory',
            'remainingSeconds',
            'lockedUntil',
            'lockedMinutesLeft',
            'categories'
        ));
    }

    // ── Unlock ────────────────────────────────────────────────────────────────

    public function unlock(Request $request)
    {
        $request->validate(['pin' => 'required|digits:6']);

        $vaultAccess = DB::table('vault_access')->first();

        if (!$vaultAccess) {
            return response()->json(['success' => false, 'message' => 'Vault not configured.'], 422);
        }

        // Check lockout
        if ($vaultAccess->locked_until) {
            $lockedUntil = \Carbon\Carbon::parse($vaultAccess->locked_until);
            if ($lockedUntil->isFuture()) {
                $minutesLeft = (int) ceil(now()->diffInSeconds($lockedUntil) / 60);
                return response()->json([
                    'success' => false,
                    'message' => "Too many failed attempts. Vault locked for {$minutesLeft} more minute(s).",
                    'locked'  => true,
                ], 422);
            }
        }

        if (empty($vaultAccess->pin_hash)) {
            return response()->json(['success' => false, 'message' => 'No PIN has been set. Please set a PIN first.'], 422);
        }

        if (Hash::check($request->pin, $vaultAccess->pin_hash)) {
            // Correct PIN — unlock
            DB::table('vault_access')->update([
                'failed_attempts' => 0,
                'locked_until'    => null,
                'updated_at'      => now(),
            ]);

            session([self::SESSION_KEY => now()]);

            $remainingSeconds = ($vaultAccess->pin_timeout_minutes ?? 15) * 60;

            return response()->json([
                'success'          => true,
                'message'          => 'Vault unlocked.',
                'remaining_seconds' => $remainingSeconds,
            ]);
        }

        // Wrong PIN
        $newAttempts = ($vaultAccess->failed_attempts ?? 0) + 1;
        $update      = ['failed_attempts' => $newAttempts, 'updated_at' => now()];

        if ($newAttempts >= 3) {
            $update['locked_until'] = now()->addMinutes(30);
            DB::table('vault_access')->update($update);
            return response()->json([
                'success' => false,
                'message' => 'Too many failed attempts. Vault locked for 30 minutes.',
                'locked'  => true,
            ], 422);
        }

        DB::table('vault_access')->update($update);
        $remaining = 3 - $newAttempts;

        return response()->json([
            'success' => false,
            'message' => "Incorrect PIN. {$remaining} attempt(s) remaining.",
        ], 422);
    }

    // ── Lock ──────────────────────────────────────────────────────────────────

    public function lock()
    {
        session()->forget(self::SESSION_KEY);
        return redirect()->route('admin.vault.index')->with('info', 'Vault locked.');
    }

    // ── Set PIN (first time) ──────────────────────────────────────────────────

    public function setPin(Request $request)
    {
        $request->validate([
            'new_pin'                  => 'required|digits:6|confirmed',
            'new_pin_confirmation'     => 'required|digits:6',
        ]);

        $vaultAccess = DB::table('vault_access')->first();

        // Allow set if no PIN yet, or vault is unlocked
        if (!empty($vaultAccess?->pin_hash) && !$this->isUnlocked($vaultAccess)) {
            return response()->json(['success' => false, 'message' => 'Unlock the vault first.'], 403);
        }

        DB::table('vault_access')->update([
            'pin_hash'   => Hash::make($request->new_pin),
            'updated_at' => now(),
        ]);

        // Auto-unlock after setting PIN
        session([self::SESSION_KEY => now()]);

        return response()->json(['success' => true, 'message' => 'PIN set successfully. Vault is now unlocked.']);
    }

    // ── Change PIN ────────────────────────────────────────────────────────────

    public function changePin(Request $request)
    {
        $request->validate([
            'current_pin'              => 'required|digits:6',
            'new_pin'                  => 'required|digits:6|confirmed',
            'new_pin_confirmation'     => 'required|digits:6',
        ]);

        $vaultAccess = DB::table('vault_access')->first();

        if (!$this->isUnlocked($vaultAccess)) {
            return response()->json(['success' => false, 'message' => 'Vault is not unlocked.'], 403);
        }

        if (!Hash::check($request->current_pin, $vaultAccess->pin_hash)) {
            return response()->json(['success' => false, 'message' => 'Current PIN is incorrect.'], 422);
        }

        DB::table('vault_access')->update([
            'pin_hash'   => Hash::make($request->new_pin),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'PIN changed successfully.']);
    }

    // ── Save credentials ──────────────────────────────────────────────────────

    public function save(Request $request)
    {
        $vaultAccess = DB::table('vault_access')->first();

        if (!$this->isUnlocked($vaultAccess)) {
            return response()->json(['success' => false, 'message' => 'Vault is locked. Please unlock first.'], 403);
        }

        $credentials = $request->input('credentials', []);

        if (empty($credentials) || !is_array($credentials)) {
            return response()->json(['success' => false, 'message' => 'No credentials provided.'], 422);
        }

        $saved = 0;
        foreach ($credentials as $key => $value) {
            $key = trim($key);
            if (empty($key)) continue;

            // Skip if value is empty or all masked dots (not changed)
            if ($value === null || $value === '' || preg_match('/^[•\s]+$/', $value)) {
                continue;
            }

            $encrypted = Crypt::encryptString($value);

            DB::table('credential_vault')->updateOrInsert(
                ['setting_key' => $key],
                [
                    'setting_value' => $encrypted,
                    'updated_at'    => now(),
                    'created_at'    => now(),
                ]
            );
            $saved++;
        }

        // Bust vault cache so new values take effect immediately
        Cache::forget(self::CACHE_KEY);

        return response()->json(['success' => true, 'message' => "{$saved} credential(s) saved."]);
    }

    // ── Reveal a single credential ────────────────────────────────────────────

    public function getCredential(Request $request)
    {
        $request->validate(['setting_key' => 'required|string']);

        $vaultAccess = DB::table('vault_access')->first();

        if (!$this->isUnlocked($vaultAccess)) {
            return response()->json(['success' => false, 'message' => 'Vault is locked.'], 403);
        }

        $row = DB::table('credential_vault')
            ->where('setting_key', $request->setting_key)
            ->first();

        if (!$row || empty($row->setting_value)) {
            return response()->json(['success' => true, 'value' => '']);
        }

        try {
            $value = Crypt::decryptString($row->setting_value);
        } catch (DecryptException $e) {
            // Value might be stored as plain text (legacy)
            $value = $row->setting_value;
        }

        return response()->json(['success' => true, 'value' => $value]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function isUnlocked($vaultAccess = null): bool
    {
        $unlockedAt = session(self::SESSION_KEY);

        if (!$unlockedAt) {
            return false;
        }

        if (!$vaultAccess) {
            $vaultAccess = DB::table('vault_access')->first();
        }

        $timeoutMinutes = $vaultAccess?->pin_timeout_minutes ?? 15;
        $expiresAt      = $unlockedAt->copy()->addMinutes($timeoutMinutes);

        return now()->lessThan($expiresAt);
    }

    // ── Static helpers (called by other services) ─────────────────────────────

    /**
     * Maps vault setting_key names to their corresponding .env variable names.
     * .env takes priority — vault DB is the fallback.
     */
    private static array $envMap = [
        // AI
        'ai_provider'            => 'AI_PROVIDER',
        'ai_enabled'             => 'AI_ENABLED',
        'groq_api_key'           => 'GROQ_API_KEY',
        'groq_model'             => 'AI_MODEL',
        'anthropic_api_key'      => 'ANTHROPIC_API_KEY',
        'anthropic_model'        => 'ANTHROPIC_MODEL',
        // Smart Underwriting — commercial extraction provider (PII always local).
        // smartuw_commercial_provider = gemini | deepseek. Read per-upload by
        // SmartUnderwritingExtractJob and passed to the engine, so the CFO can
        // switch the commercial "AI brain" from the vault with no redeploy.
        'smartuw_commercial_provider' => 'SMARTUW_COMMERCIAL_PROVIDER',
        'smartuw_deepseek_model'      => 'SMARTUW_DEEPSEEK_MODEL',
        'gemini_api_key'              => 'GEMINI_API_KEY',
        'deepseek_api_key'            => 'DEEPSEEK_API_KEY',
        // Payment gateways
        'dpo_company_token'      => 'DPO_COMPANY_TOKEN',
        'dpo_api_url'            => 'DPO_API_URL',
        'realpay_api_key'        => 'REALPAY_API_KEY',
        'realpay_api_url'        => 'REALPAY_API_URL',
        'flutterwave_public_key' => 'FLW_PUBLIC_KEY',
        'flutterwave_secret_key' => 'FLW_SECRET_KEY',
        'orange_money_api_key'   => 'ORANGE_MONEY_API_KEY',
        'ngenius_api_key'        => 'NGENIUS_API_KEY',
        'ngenius_outlet_ref'     => 'NGENIUS_OUTLET_REF',
        // SMS / Comms
        'infobip_api_key'        => 'INFOBIP_API_KEY',
        'infobip_base_url'       => 'INFOBIP_BASE_URL',
        'whatsapp_token'         => 'WHATSAPP_TOKEN',
        'whatsapp_phone_id'      => 'WHATSAPP_PHONE_ID',
        // Cloud / Storage
        'aws_access_key_id'      => 'AWS_ACCESS_KEY_ID',
        'aws_secret_access_key'  => 'AWS_SECRET_ACCESS_KEY',
        'aws_default_region'     => 'AWS_DEFAULT_REGION',
        'aws_bucket'             => 'AWS_BUCKET',
        'cloudfront_url'         => 'AWS_CLOUDFRONT_URL',
        // Compliance
        'open_sanctions_api_key' => 'OPEN_SANCTIONS_API_KEY',
        // Vehicle Tracking
        'webfleet_api_key'       => 'WEBFLEET_API_KEY',
        'webfleet_account'       => 'WEBFLEET_ACCOUNT',
        // Email
        'mail_username'          => 'MAIL_USERNAME',
        'mail_password'          => 'MAIL_PASSWORD',
        'mail_from_address'      => 'MAIL_FROM_ADDRESS',
    ];

    /**
     * Read a single credential.
     * Priority: .env value (if non-empty) → vault DB → $default
     */
    public static function get(string $key, $default = null)
    {
        // 1. .env takes priority
        if (isset(static::$envMap[$key])) {
            $envValue = env(static::$envMap[$key]);
            if ($envValue !== null && $envValue !== '') {
                return $envValue;
            }
        }

        // 2. Vault DB (decrypted, cached)
        $all = static::loadCache();
        return $all[$key] ?? $default;
    }

    /**
     * Vault value ONLY — the env map is deliberately skipped.
     *
     * get() checks .env FIRST, which is right for most settings: an env var is
     * the deployment's own override. It is wrong for a credential the operator
     * can set in the app, because a stale or wrong env value then silently
     * wins and the saved key is ignored with no way to clear it (a deployed
     * env where only AWS can edit the task definition — Smart UW, 2026-09-02).
     *
     * Callers that offer in-app editing should try this first and fall back to
     * get(), so the vault wins and env still works as a fallback.
     */
    public static function getVaultOnly(string $key, $default = null)
    {
        $all = static::loadCache();
        $value = $all[$key] ?? null;

        return ($value === null || $value === '') ? $default : $value;
    }

    /**
     * Return all decrypted key-value pairs for a given category.
     */
    public static function getCategory(string $category): array
    {
        $rows = DB::table('credential_vault')
            ->where('category', $category)
            ->get();

        $result = [];
        foreach ($rows as $row) {
            if (empty($row->setting_value)) {
                $result[$row->setting_key] = null;
                continue;
            }
            try {
                $result[$row->setting_key] = Crypt::decryptString($row->setting_value);
            } catch (DecryptException $e) {
                $result[$row->setting_key] = $row->setting_value;
            }
        }

        return $result;
    }

    /**
     * Load (and cache) the full decrypted vault as a flat key=>value array.
     */
    private static function loadCache(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            // The vault is optional. On environments where the credential_vault
            // table hasn't been provisioned (fresh / dev DBs), fall back to
            // env-only config instead of throwing. A hard QueryException here
            // used to bubble all the way up and turn every vault-backed feature
            // — AI/OCR scanning, payment gateways — into a 502 (the OCR scan
            // endpoint mapped it to ocr_failed → 502). get() already supplies
            // env() defaults, so an empty cache is the correct degraded state.
            try {
                if (!Schema::hasTable('credential_vault')) {
                    return [];
                }
                $rows = DB::table('credential_vault')->get();
            } catch (\Throwable $e) {
                Log::warning('VaultController::loadCache failed; using env defaults', ['msg' => $e->getMessage()]);
                return [];
            }
            $result = [];
            foreach ($rows as $row) {
                if (empty($row->setting_value)) {
                    $result[$row->setting_key] = null;
                    continue;
                }
                try {
                    $result[$row->setting_key] = Crypt::decryptString($row->setting_value);
                } catch (DecryptException $e) {
                    // Stored as plain-text (legacy / non-secret)
                    $result[$row->setting_key] = $row->setting_value;
                }
            }
            return $result;
        });
    }
}
