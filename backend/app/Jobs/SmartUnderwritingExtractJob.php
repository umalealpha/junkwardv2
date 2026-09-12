<?php

namespace AlphaDirect\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use AlphaDirect\Http\Controllers\Admin\AiConfigController;
use AlphaDirect\Http\Controllers\Admin\VaultController;
use AlphaDirect\Services\SmartUw\PhpScheduleExtractor;

/**
 * Smart Underwriting extraction job.
 *
 * Pulls an uploaded broker schedule, hands it to the smart-uw-engine
 * (Python — normalize -> LLM -> validate), and writes one
 * smart_uw_extractions row per risk segment (sheet / subsidiary / site).
 *
 * The engine self-routes per segment: customer PII/ID content -> LOCAL model
 * (Ollama, nothing leaves the box, AD-POL-AI-GOV-001); non-PII commercial
 * schedule data -> the selected commercial provider (gemini | deepseek). The
 * choice + key are read here from the Credentials Vault and passed to the
 * engine per-request, so the CFO can switch the commercial "AI brain" from the
 * vault UI with no redeploy. We additionally force --local for KYC/ID file
 * uploads as a belt-and-braces guard.
 *
 * Infra dependency: normally NONE beyond the smartuw-engine sidecar running in
 * the same ECS task — with neither env var set the engine is called over HTTP
 * at self::DEFAULT_ENGINE_URL. Set SMARTUW_ENGINE_URL only to point somewhere
 * else, or SMARTUW_ENGINE_DIR to run the Python engine as a local subprocess
 * instead (that route DOES need python3 + the engine requirements in the image).
 * Configure via env:
 *   SMARTUW_ENGINE_URL  (default: the in-task sidecar, see DEFAULT_ENGINE_URL)
 *   SMARTUW_PYTHON      (default: python3)
 *   SMARTUW_ENGINE_DIR  (unset = use the sidecar)
 *   GEMINI_API_KEY / SMARTUW_GEMINI_MODEL  (commercial route; optional)
 *   OLLAMA_URL / SMARTUW_OLLAMA_MODEL      (local route)
 */
class SmartUnderwritingExtractJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1200;   // big workbooks + LLM are slow, but bounded
    public $tries   = 1;      // extraction isn't idempotent-cheap; surface failures

    /**
     * Where the engine sidecar listens when SMARTUW_ENGINE_URL is not set.
     * 8099, not 8088: the engine image sets ENV PORT=8099, EXPOSEs 8099 and
     * health-checks 8099 — the LISTEN port is what matters over the shared
     * task network namespace, not the portMappings entry.
     */
    private const DEFAULT_ENGINE_URL = 'http://localhost:8099';

    protected int $uploadId;

    /** ID/KYC document extensions/markers that must never leave the box. */
    // Matched as WHOLE WORDS. With str_contains, the two-character 'id' hid
    // inside ordinary filenames — Bridge, Midland, Providence, Davids — and
    // every one of those schedules was refused as a KYC document that only a
    // local model may read, of which this container has none. The upload was
    // unrecoverable and the message never mentioned the filename.
    private const PII_HINT = ['omang', 'passport', 'id', 'kyc', 'residence', 'licence', 'license'];

    /**
     * Formats that carry a machine-readable schedule, so the CONTENT can be
     * classified per segment before anything is sent anywhere. For these the
     * filename is not evidence of anything and must never force the local
     * model — "ABC (Pty) Ltd Motor Trade Licence 2026.xlsx" and "Passport
     * Motors Fleet.xlsx" are ordinary commercial schedules, and refusing them
     * breaks the standing rule that an upload always reaches the Underwriter.
     *
     * Scans (pdf, images) are the exception: their text is not available until
     * a vision provider has already read the file, so on those the filename is
     * the only signal we have before the decision must be made, and it stays
     * authoritative (AD-POL-AI-GOV-001).
     */
    private const SCHEDULE_EXTS = ['xlsx', 'xls', 'csv', 'txt', 'tsv', 'md', 'docx'];

    /**
     * Does this filename claim to be a KYC / ID document?
     *
     * Such an upload is forced to the local model, and this container has none
     * — so a false positive is not a warning, it is a dead end the operator
     * cannot get past. Whole words only: with str_contains the two-character
     * 'id' hid inside Bridge, Midland, Providence and Davids, and every one of
     * those schedules was refused.
     *
     * Pass $ext so a structured schedule is never judged by its name: the
     * per-segment content classifier does that job properly once the rows are
     * read, and it is the control that actually keeps PII off the cloud.
     *
     * Public and static so it can be tested without a database — the job's
     * handle() needs smart_uw_uploads before it reaches this rule.
     */
    public static function looksLikeKycFilename(string $filename, string $ext = ''): bool
    {
        if ($ext !== '' && in_array(strtolower($ext), self::SCHEDULE_EXTS, true)) {
            return false;
        }

        $words = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', ' ', $filename));
        foreach (self::PII_HINT as $hint) {
            if (preg_match('/\b' . preg_quote($hint, '/') . '\b/', $words)) {
                return true;
            }
        }

        return false;
    }

    public function __construct(int $uploadId)
    {
        $this->uploadId = $uploadId;
        // Dedicated queue — a 20-min extraction must NOT sit on the shared
        // workers that run SMS / email / transactional jobs. Run a worker with
        // `php artisan queue:work --queue=smartuw` (separate process/container).
        $this->onQueue('smartuw');
    }

    /**
     * START an extraction WITHOUT depending on a queue worker being alive.
     *
     * Why this exists: dispatch() only parks the job on the configured queue
     * CONNECTION. On the deployed envs the consuming worker has repeatedly not
     * been the one draining that connection — supervisord pins
     * `queue:work redis` while QUEUE_CONNECTION is `database`, so the job lands
     * in the `jobs` table on queue 'smartuw' while the redis worker sits idle
     * on an empty list. From the UI that is indistinguishable from having no
     * worker at all: the row stays 'queued' and the operator watches
     * "Waiting for an extraction worker…" until the watchdog fails it at 25min.
     *
     * So the PRIMARY trigger is now the same detached-CLI kick the V2 Quote PDF
     * and Refresh Endorsement paths already rely on (RefreshEndorseJob::start):
     * spawn `php artisan smartuw:run {id}` as its own background process. It
     * survives the php-fpm request ending, has no execution-time limit, needs
     * no worker and no scheduler, and reaches the engine sidecar over the same
     * in-task localhost link. Queue dispatch remains only as the fallback when
     * the shell functions are unavailable (disable_functions), and a spawn that
     * died before starting is re-kicked by self::reviveIfStalled(), driven by
     * the upload screen's own status poll.
     *
     * @return string 'cli' when a background worker was spawned, 'queue' when it fell back to dispatch()
     */
    public static function start(int $uploadId): string
    {
        self::stampSpawn($uploadId, 1);

        if (self::kickDetached($uploadId)) {
            return 'cli';
        }

        Log::warning("SmartUnderwritingExtractJob: CLI kick unavailable for upload {$uploadId} — falling back to queue dispatch.");
        self::dispatch($uploadId);

        return 'queue';
    }

    /**
     * Spawn `smartuw:run {id}` as a detached background process.
     *
     * Returns false when the platform's spawn function is unavailable, so the
     * caller can fall back to the queue. A true return only means the process
     * was launched — if it dies before flipping the row to 'processing',
     * self::reviveIfStalled() re-kicks it.
     */
    public static function kickDetached(int $uploadId): bool
    {
        // NEVER use PHP_BINARY here: under php-fpm it resolves to the FPM SAPI
        // binary, which cannot run artisan — and with output sent to /dev/null
        // the failure is invisible (root cause of the 2026-06-10 PDF pile-up).
        // Resolve a CLI binary: env override -> which-discovery -> default.
        $cliPhp = getenv('PHP_CLI_BINARY') ?: (DIRECTORY_SEPARATOR === '\\'
            ? (trim((string) strtok((string) @shell_exec('where php'), "\r\n")) ?: 'php')
            : (trim((string) @shell_exec('command -v php')) ?: '/usr/local/bin/php'));

        $cmd = implode(' ', [
            escapeshellarg($cliPhp),
            escapeshellarg(base_path('artisan')),
            'smartuw:run',
            escapeshellarg((string) $uploadId),
        ]);

        try {
            if (DIRECTORY_SEPARATOR === '\\') {
                if (!function_exists('popen') || !function_exists('pclose')) return false;
                // Windows (local dev) — start /B detaches from this process.
                pclose(popen("start /B \"\" {$cmd}", 'r'));
            } else {
                if (!function_exists('shell_exec')) return false;
                // Linux (deployed) — nohup survives the php-fpm request ending;
                // full output redirection releases the handle so this returns
                // instantly instead of blocking the HTTP response.
                shell_exec("nohup {$cmd} > /dev/null 2>&1 &");
            }
            Log::info("SmartUnderwritingExtractJob: spawned detached extractor for upload {$uploadId}.");
            return true;
        } catch (\Throwable $e) {
            Log::warning("SmartUnderwritingExtractJob: detached spawn failed for upload {$uploadId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * BACKSTOP: relaunch an extraction still sitting at 'queued' because its
     * worker never started.
     *
     * Deliberately NOT in the scheduler — the DB-driven scheduler depends on
     * `cron_kernels`, absent on the deployed envs, so that tick cannot be
     * relied on. The trigger is the upload screen's OWN status poll
     * (SmartUploadController::status), which runs every 2.5s for exactly as
     * long as the operator is waiting.
     *
     * Safe to call on every poll:
     *   - Only rows at 'queued' are touched. A live run flips to 'processing'
     *     as the first act of handle(), so a healthy extraction is skipped.
     *   - A row is only re-kicked once its last spawn is $staleSeconds old, so
     *     2.5s polling still yields at most one kick per window.
     *   - A cache lock makes the decision atomic, so two browser tabs polling
     *     in the same instant cannot both spawn.
     *   - After $maxAttempts the row is left to the caller's watchdog instead
     *     of being re-kicked forever.
     *
     * @return bool true when a spawn (or queue hand-off) was just triggered
     */
    public static function reviveIfStalled(int $uploadId, int $staleSeconds = 45, int $maxAttempts = 3): bool
    {
        try {
            $row = DB::table('smart_uw_uploads')->where('id', $uploadId)
                ->first(['id', 'status', 'spawn_attempts', 'last_spawn_at', 'created_at']);
        } catch (\Throwable $e) {
            // Columns not migrated yet — no revive tracking, but never break the poll.
            Log::warning('SmartUnderwritingExtractJob::reviveIfStalled: ' . $e->getMessage());
            return false;
        }
        if (!$row || $row->status !== 'queued') {
            return false;
        }

        // Only act once the previous spawn has had time to write 'processing'.
        // Plain timestamp compare — diffInSeconds()' sign convention varies
        // across Carbon versions and getting it backwards here would either
        // never re-kick or re-kick a healthy worker.
        $last = $row->last_spawn_at ?: $row->created_at;
        if ($last && strtotime((string) $last) > time() - $staleSeconds) {
            return false;
        }

        $attempts = (int) ($row->spawn_attempts ?? 0);
        if ($attempts >= $maxAttempts) {
            return false;
        }

        // Atomic claim so concurrent pollers can't each spawn an extractor. A
        // short TTL: this guards the decision only, never the run itself, so a
        // crashed poller cannot block recovery for more than one window.
        $lock = null;
        try {
            $lock = Cache::lock('smartuw-revive:' . $uploadId, max(10, $staleSeconds));
            if (!$lock->get()) {
                return false; // another poller is deciding right now
            }
        } catch (\Throwable $e) {
            $lock = null; // no lock driver — proceed unlocked rather than never recovering
        }

        try {
            // Re-read under the lock: the race winner may already have spawned,
            // and the row may have started processing meanwhile.
            $fresh = DB::table('smart_uw_uploads')->where('id', $uploadId)
                ->first(['status', 'spawn_attempts', 'last_spawn_at']);
            if (!$fresh || $fresh->status !== 'queued') {
                return false;
            }
            if ($fresh->last_spawn_at && strtotime((string) $fresh->last_spawn_at) > time() - $staleSeconds) {
                return false;
            }

            $attempt = (int) ($fresh->spawn_attempts ?? 0) + 1;
            if ($attempt > $maxAttempts) {
                return false;
            }
            self::stampSpawn($uploadId, $attempt);

            if (self::kickDetached($uploadId)) {
                Log::warning("SmartUnderwritingExtractJob::reviveIfStalled: re-kicked upload {$uploadId} (attempt {$attempt}).");
            } else {
                // Shell spawn unavailable on this host — the queue is the only
                // remaining route, so hand it over rather than looping.
                self::dispatch($uploadId);
                Log::warning("SmartUnderwritingExtractJob::reviveIfStalled: CLI spawn unavailable — dispatched upload {$uploadId} to the queue instead.");
            }

            return true;
        } finally {
            if ($lock) {
                try { $lock->release(); } catch (\Throwable $e) { /* TTL will clear it */ }
            }
        }
    }

    /**
     * Record that a spawn was just attempted. The two columns are nullable
     * additions — wrapped so an un-migrated database degrades to "no revive
     * tracking" instead of failing the upload request itself.
     *
     * updated_at is deliberately NOT touched: the poll-side watchdog measures
     * the wait from it, and refreshing it on every re-kick would push the
     * 1500s give-up deadline back forever.
     */
    private static function stampSpawn(int $uploadId, int $attempt): void
    {
        try {
            DB::table('smart_uw_uploads')->where('id', $uploadId)->update([
                'spawn_attempts' => $attempt,
                'last_spawn_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('SmartUnderwritingExtractJob: could not stamp spawn attempt: ' . $e->getMessage());
        }
    }

    /**
     * Use the in-process reader instead of the sidecar. Vault first so the CFO
     * can switch engines with no redeploy.
     *
     * Default ON when nobody has chosen. It used to default OFF "so the sidecar
     * stays primary wherever it is actually running", but that is the wrong
     * default in both directions: the AI settings card renders the box TICKED
     * on a never-configured install (prefer_php_set false -> shown as on), so
     * the screen said in-process while the job silently used the sidecar — and
     * the sidecar then failed the upload on the local-model route it cannot
     * serve (test env, 2026-09-04). Same value on both sides now; an operator
     * who wants the sidecar unticks the box and that choice is stored.
     */
    private function preferInProcess(): bool
    {
        $vault = (string) VaultController::get('smartuw_prefer_php', '');
        if ($vault !== '') {
            return in_array(strtolower($vault), ['1', 'true', 'yes', 'on'], true);
        }

        return (bool) env('SMARTUW_PREFER_PHP', true);
    }

    /**
     * Does this risk carry anything an underwriter could act on — a named
     * insured, a coverage section, or a vehicle? A segment whose extraction
     * threw comes back as nothing but {_segment, _error} and must not count.
     */
    private function riskHasData($risk): bool
    {
        if (!is_array($risk)) {
            return false;
        }

        return !empty($risk['coverages'])
            || !empty($risk['motor'])
            || trim((string) data_get($risk, 'customer.name')) !== '';
    }

    /**
     * How many of an engine result's risks carry data. Zero means the read ran
     * and failed on every segment, which is worth a second attempt with the
     * other reader before the upload is failed.
     */
    private function usableRiskCount($data): int
    {
        $risks = is_array($data) ? ($data['risks'] ?? []) : [];
        if (!is_array($risks)) {
            return 0;
        }

        $n = 0;
        foreach ($risks as $risk) {
            if ($this->riskHasData($risk)) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * Run the PHP extractor and translate its failures into one message that
     * names BOTH dead ends — the missing sidecar and why the fallback could not
     * cover for it. Without this the operator sees only the second half and
     * files it as a different bug.
     *
     * @param  \Throwable  $connectionError  the original sidecar failure
     */
    private function extractInProcess(
        string $tmpPath,
        string $ext,
        string $provider,
        string $commercialKey,
        bool $forceLocal,
        string $engineUrl,
        \Throwable $connectionError
    ): array {
        if (!PhpScheduleExtractor::supports($ext)) {
            throw new \RuntimeException(
                'Extraction engine unreachable at ' . $engineUrl . ' (the smartuw-engine '
                . 'sidecar is not running in this task), and .' . $ext . ' files can only be '
                . 'read by that engine. Convert the schedule to .xlsx, or start the sidecar. ('
                . $this->mask(substr($connectionError->getMessage(), 0, 160)) . ')'
            );
        }

        try {
            $data = (new PhpScheduleExtractor())->extract($tmpPath, $ext, $provider, $commercialKey, $forceLocal);
        } catch (\Throwable $inner) {
            throw new \RuntimeException(
                'Extraction engine unreachable at ' . $engineUrl . ', and the in-process '
                . 'fallback could not read this schedule: ' . $this->mask($inner->getMessage())
            );
        }

        Log::info('SmartUnderwritingExtractJob: in-process extraction complete', [
            'upload_id' => $this->uploadId,
            'segments'  => $data['segment_count'] ?? 0,
        ]);

        return $data;
    }

    /**
     * Redact anything credential-shaped before an engine error body / subprocess
     * stderr is persisted to smart_uw_uploads.message or the logs. Truncation is
     * NOT a secret filter (Smart UW PII audit 2026-06-18).
     */
    private function mask(string $s): string
    {
        // gsk_ is Groq's shape: the backend container carries GROQ_API_KEY, so
        // it is the key most likely to appear in an error here. x-goog-api-key
        // is Gemini's own header name, and a bare Authorization: header can
        // arrive without the word Bearer.
        return preg_replace(
            ['/sk-[A-Za-z0-9_\-]{6,}/', '/gsk_[A-Za-z0-9_\-]{6,}/',
             '/AIza[A-Za-z0-9_\-]{6,}/', '/AQ\.[A-Za-z0-9_\-]{6,}/',
             '/Bearer\s+\S+/i', '/x-goog-api-key\s*[=:]\s*\S+/i',
             '/Authorization\s*[=:]\s*\S+/i',
             '/((?:GEMINI|DEEPSEEK|ANTHROPIC|GROQ)_API_KEY|X-Commercial-Key)\s*[=:]\s*\S+/i'],
            ['sk-***', 'gsk_***', 'AIza***', 'AQ.***', 'Bearer ***',
             'x-goog-api-key: ***', 'Authorization: ***', '$1=***'],
            $s
        ) ?? $s;
    }

    public function handle(): void
    {
        $row = DB::table('smart_uw_uploads')->where('id', $this->uploadId)->first();
        if (!$row) {
            Log::warning('SmartUnderwritingExtractJob: upload row missing', ['id' => $this->uploadId]);
            return;
        }

        // ── Claim the row: exactly one extractor per upload ───────────────
        // There are now several ways this can be started — the detached CLI
        // kick, the queue fallback, a poll-driven re-kick, and a manual
        // `smartuw:run` — so without a claim two of them could extract the same
        // schedule and write two sets of smart_uw_extractions rows, which the
        // review screen renders as duplicated risk segments.
        //
        // The claim is a CONDITIONAL update, so it is atomic across processes
        // and nodes: only one racer flips 'queued' -> 'processing'. A racer that
        // loses stands down. There is no heartbeat while the engine works (a
        // single call can run 20 minutes), so a run is only treated as dead once
        // it has been silent for longer than the job's own timeout.
        // 'completed'/'failed' rows fall through and re-run: reaching here means
        // an operator asked for this one explicitly (smartuw:run / --stuck).
        if ($row->status === 'queued') {
            $claimed = DB::table('smart_uw_uploads')->where('id', $this->uploadId)
                ->where('status', 'queued')
                ->update(['status' => 'processing', 'updated_at' => now()]);
            if (!$claimed) {
                Log::info('SmartUnderwritingExtractJob: another extractor already claimed this upload — standing down', [
                    'upload_id' => $this->uploadId,
                ]);
                return;
            }
        } elseif ($row->status === 'processing') {
            $since = strtotime((string) ($row->updated_at ?? $row->created_at));
            if ($since && $since > time() - ($this->timeout + 60)) {
                Log::warning('SmartUnderwritingExtractJob: upload is already being extracted — standing down', [
                    'upload_id' => $this->uploadId, 'since' => $row->updated_at,
                ]);
                return;
            }
            // Silent past the timeout — the previous extractor is gone, take over.
            DB::table('smart_uw_uploads')->where('id', $this->uploadId)
                ->update(['status' => 'processing', 'updated_at' => now()]);
        } else {
            DB::table('smart_uw_uploads')->where('id', $this->uploadId)
                ->update(['status' => 'processing', 'updated_at' => now()]);
        }

        $tmp = null;
        try {
            // Materialise the stored file to a local temp path the engine can read.
            // 'local' disk (storage/app, EFS-shared) — must match the disk
            // SmartUploadController::upload wrote to. Not the default disk.
            if (!Storage::disk('local')->exists($row->uploaded_file)) {
                throw new \RuntimeException("stored file missing: {$row->uploaded_file}");
            }
            $tmp = tempnam(sys_get_temp_dir(), 'smartuw_') . '.' . $row->file_ext;
            file_put_contents($tmp, Storage::disk('local')->get($row->uploaded_file));

            // Force local for anything that smells like a KYC/ID document
            // (PII never leaves Alpha infra, AD-POL-AI-GOV-001).
            $name  = strtolower($row->original_name ?? '');
            $forceLocal = false;
            // Structured schedules are exempt — see looksLikeKycFilename(). A
            // spreadsheet's name is not evidence of its contents, and refusing
            // one is a dead end the Underwriter cannot get past.
            $forceLocal = self::looksLikeKycFilename($name, (string) $row->file_ext);

            // Engine invocation. PREFERRED: HTTP sidecar (SMARTUW_ENGINE_URL) —
            // the engine runs in its OWN container (see smart-uw-engine/Dockerfile),
            // so the PHP image needs neither Python nor the engine folder. This
            // is the deploy path that resolves PR-review blockers #1/#2.
            // FALLBACK: local python subprocess if SMARTUW_ENGINE_DIR is set.
            // If NEITHER is set we assume the in-task sidecar (see below) rather
            // than declaring the feature inert.
            $engineUrl = rtrim((string) env('SMARTUW_ENGINE_URL', ''), '/');
            $engineDir = (string) env('SMARTUW_ENGINE_DIR', '');

            // Neither configured: assume the in-task sidecar rather than giving
            // up. The engine container ships in the same ECS task and always
            // listens on 8099 (smart-uw-engine/Dockerfile sets ENV PORT=8099),
            // so its address is knowable without an env var — and the var has
            // in practice been set on the smartuw-worker container only, which
            // left extraction started from the web container dead with
            // "engine not configured" (staging, 2026-09-01). Containers in an
            // awsvpc task share a network namespace, so localhost is correct.
            // An unreachable default reports a connection error naming the URL,
            // which is a better dead end than an inert feature.
            if ($engineUrl === '' && $engineDir === '') {
                $engineUrl = self::DEFAULT_ENGINE_URL;
                Log::warning('SmartUnderwritingExtractJob: SMARTUW_ENGINE_URL unset — '
                    . 'falling back to the in-task sidecar at ' . $engineUrl, [
                        'upload_id' => $this->uploadId,
                    ]);
            }

            // Commercial provider for NON-PII segments (PII always stays local
            // inside the engine, regardless of this). The CFO sets it in the
            // Credentials Vault, so switching gemini<->deepseek needs no
            // redeploy — it takes effect on the next upload. Vault falls back
            // to .env, then to gemini; an unknown value is forced to gemini.
            // DEFAULT: deepseek. The mapping engine that reads a client's
            // schedule and converts it into our format is DeepSeek by
            // instruction (CFO, 2026-09-09); the vault switch still overrides
            // it per install, and a scan or an image is still read by
            // Gemini/Anthropic because DeepSeek's chat API takes no file part
            // (PhpScheduleExtractor::callVisionWithFile).
            $commercialProvider = strtolower((string) VaultController::get(
                'smartuw_commercial_provider', env('SMARTUW_COMMERCIAL_PROVIDER', 'deepseek')));
            if (!in_array($commercialProvider, ['gemini', 'deepseek'], true)) {
                $commercialProvider = 'deepseek';
            }
            // Per-request key from the vault (empty -> engine uses its own env
            // key, preserving today's behaviour). It travels only over the
            // in-task localhost link to the sidecar, never the internet.
            // Vault FIRST, env as the fallback. VaultController::get() is
            // env-first, which meant a wrong or stale GEMINI_API_KEY on the
            // container silently beat the key an operator had just saved in the
            // app — and on a deployed env only AWS can clear that var, so the
            // operator had no way out. The Smart UW key is app-editable, so the
            // app's value wins.
            $commercialKey = (string) VaultController::getVaultOnly(
                $commercialProvider . '_api_key',
                VaultController::get($commercialProvider . '_api_key', '')
            );
            // Still nothing? Borrow the key the KYC document reader runs on
            // (AiConfigController::getSettings — vault, then .env). Same Google
            // account, same generateContent endpoint: there was never a reason
            // for Smart UW to demand its own copy, and on the deployed task
            // GEMINI_API_KEY reaches the smartuw-engine container ONLY, so the
            // web/worker container had no key while KYC PDF reading worked.
            // PhpScheduleExtractor resolves this itself; doing it here too
            // means the sidecar gets an X-Commercial-Key as well.
            if (trim($commercialKey) === '') {
                $commercialKey = (string) (AiConfigController::getSettings()[$commercialProvider . '_api_key'] ?? '');
            }

            // Prefer the in-process reader outright when asked. This is not a
            // performance switch — it exists because the sidecar is WORSE than
            // this path for an ordinary schedule: it routes any segment with a
            // contact block to a local Ollama model that is not in the ECS task,
            // so a file that extracts here fails there. Without this flag,
            // adding the sidecar would silently un-fix the Diesel Heads case.
            // Which reader produced $data. Needed by the zero-segment retry
            // below: re-running the PHP reader over a file the PHP reader
            // already read would just waste a minute and repeat the answer.
            $usedInProcess = false;

            // The sidecar reads workbooks and PDFs only. CSV, Word and images
            // have a reader in PhpScheduleExtractor and nowhere else, so those
            // skip the sidecar whichever way the preference is set — otherwise
            // widening the upload gate would just move the failure one step
            // later, into the engine.
            $phpOnlyFormat = PhpScheduleExtractor::supports((string) $row->file_ext)
                && !in_array(strtolower((string) $row->file_ext), ['xlsx', 'xls', 'xlsb', 'pdf'], true);

            if ($engineUrl !== ''
                && ($this->preferInProcess() || $phpOnlyFormat)
                && PhpScheduleExtractor::supports((string) $row->file_ext)) {
                Log::info('SmartUnderwritingExtractJob: reading in-process, skipping the sidecar', [
                    'upload_id' => $this->uploadId,
                    'reason'    => $phpOnlyFormat ? 'format the sidecar cannot read' : 'SMARTUW_PREFER_PHP',
                ]);
                $engineUrl = '';
                $data = (new PhpScheduleExtractor())->extract(
                    $tmp, (string) $row->file_ext, $commercialProvider, $commercialKey, $forceLocal
                );
                $usedInProcess = true;
            }

            // Already read in-process above? Then there is no engine to invoke.
            // The in-process branch clears $engineUrl to mark the sidecar as
            // skipped, and this chain used to fall straight through to its
            // "mis-configured — both present but empty" else and throw away the
            // extraction that had just succeeded. It only ever surfaced once the
            // in-process reader started succeeding; before that it always failed
            // earlier, with the provider's error, and the dead end stayed hidden.
            if ($usedInProcess) {
                Log::info('SmartUnderwritingExtractJob: in-process extraction used, no engine call', [
                    'upload_id' => $this->uploadId,
                    'segments'  => $data['segment_count'] ?? null,
                ]);
            } elseif ($engineUrl !== '') {
                $req = Http::timeout($this->timeout - 60)
                    ->withBody(file_get_contents($tmp), 'application/octet-stream');
                if ($commercialKey !== '') {
                    $req = $req->withHeaders(['X-Commercial-Key' => $commercialKey]);
                }
                try {
                    $resp = $req->post($engineUrl . '/extract?ext=' . urlencode($row->file_ext)
                        . '&local=' . ($forceLocal ? '1' : '0')
                        . '&provider=' . urlencode($commercialProvider));
                } catch (\Illuminate\Http\Client\ConnectionException $e) {
                    // Sidecar absent (the staging case: no smartuw-engine
                    // container, and no AWS access to add one). Rather than
                    // dead-ending the feature on an infra change, read the
                    // schedule in-process — same prompt, same schema, same
                    // output shape, so everything downstream is unaffected.
                    // Only a CONNECTION failure falls back: an engine that
                    // answers with a 5xx has a real bug worth surfacing, and
                    // silently re-running it here would hide that.
                    Log::warning('SmartUnderwritingExtractJob: engine unreachable — using the in-process extractor', [
                        'upload_id' => $this->uploadId, 'url' => $engineUrl,
                    ]);
                    $data = $this->extractInProcess(
                        $tmp, (string) $row->file_ext, $commercialProvider, $commercialKey, $forceLocal,
                        $engineUrl, $e
                    );
                    $usedInProcess = true;
                    $resp = null;
                }

                // $resp is null when the in-process path already produced $data.
                if ($resp !== null) {
                    if ($resp->failed()) {
                        throw new \RuntimeException('engine sidecar ' . $resp->status() . ': '
                            . $this->mask(substr($resp->body(), 0, 400)));
                    }
                    $data = $resp->json();
                }
            } elseif ($engineDir !== '') {
                $cmd = [env('SMARTUW_PYTHON', 'python3'), 'extract.py', $tmp];
                if ($forceLocal) {
                    $cmd[] = '--local';
                }
                $process = new Process($cmd, $engineDir, [
                    'SMARTUW_COMMERCIAL_PROVIDER' => $commercialProvider,
                    'GEMINI_API_KEY'       => $commercialProvider === 'gemini' && $commercialKey !== ''
                        ? $commercialKey : env('GEMINI_API_KEY', ''),
                    'SMARTUW_GEMINI_MODEL' => env('SMARTUW_GEMINI_MODEL', 'gemini-2.5-flash'),
                    'DEEPSEEK_API_KEY'     => $commercialProvider === 'deepseek' && $commercialKey !== ''
                        ? $commercialKey : env('DEEPSEEK_API_KEY', ''),
                    'SMARTUW_DEEPSEEK_MODEL' => env('SMARTUW_DEEPSEEK_MODEL', 'deepseek-chat'),
                    'OLLAMA_URL'           => env('OLLAMA_URL', 'http://localhost:11434'),
                    'SMARTUW_OLLAMA_MODEL' => env('SMARTUW_OLLAMA_MODEL', 'llama3.1:8b'),
                ]);
                $process->setTimeout($this->timeout - 60);
                $process->run();
                if (!$process->isSuccessful()) {
                    throw new \RuntimeException('engine failed: '
                        . $this->mask(substr($process->getErrorOutput(), 0, 800)));
                }
                $data = json_decode($process->getOutput(), true);
            } else {
                // Only reachable when SMARTUW_ENGINE_URL is set to an empty-ish
                // value AND SMARTUW_ENGINE_DIR is set to one too — the unset
                // case now defaults to the sidecar above.
                throw new \RuntimeException(
                    'Smart-UW extraction engine mis-configured — SMARTUW_ENGINE_URL '
                    . 'and SMARTUW_ENGINE_DIR are both present but empty. Unset them '
                    . 'to use the in-task sidecar at ' . self::DEFAULT_ENGINE_URL . '.'
                );
            }

            if (!is_array($data) || !isset($data['risks'])) {
                throw new \RuntimeException('engine returned no risks: '
                    . substr(json_encode($data), 0, 400));
            }

            // The sidecar answered 200 with NOTHING in it — "0 sheet(s)/
            // segment(s)", no error, nothing for the operator to act on (test
            // env, 2026-09-03). That is a known sidecar behaviour, not a
            // mystery: normalize.py drops a sheet named Sheet1/2/3 outright, so
            // a broker workbook exported as a single unnamed sheet loses every
            // segment before a provider is ever called. PhpScheduleExtractor's
            // filler rule deliberately yields to substance and keeps the
            // largest sheet as a last resort, so read the file again here
            // rather than failing the upload. Only fires on an empty result, so
            // a normal extraction costs no extra provider call.
            //
            // The same retry covers the OTHER shape of an empty sidecar answer:
            // segments came back, but every one of them is {_segment, _error}
            // with nothing an underwriter could use. The usual cause is the PII
            // gate — providers.py routes any segment carrying a contact block to
            // the LOCAL model, and there is no Ollama in the ECS task, so the
            // segment dies on "localhost:11434 connection refused" (test env,
            // 2026-09-04). That is a sidecar-only dead end: PhpScheduleExtractor
            // redacts the PII lines and reads the rest through the commercial
            // provider, so re-reading here recovers the schedule instead of
            // failing the upload. Same policy either way — PII still never
            // reaches a commercial provider.
            $zeroSegmentReason = null;
            $hadNoSegments     = (int) ($data['segment_count'] ?? 0) === 0;
            if (!$usedInProcess
                && ($hadNoSegments || $this->usableRiskCount($data) === 0)
                && PhpScheduleExtractor::supports((string) $row->file_ext)) {
                Log::warning('SmartUnderwritingExtractJob: sidecar returned nothing usable — '
                    . 'retrying with the in-process reader', [
                        'upload_id' => $this->uploadId,
                        'reason'    => $hadNoSegments ? '0 segments' : 'every segment empty',
                    ]);
                try {
                    $retry = (new PhpScheduleExtractor())->extract(
                        $tmp, (string) $row->file_ext, $commercialProvider, $commercialKey, $forceLocal
                    );
                    // Take the retry when it is strictly better than what the
                    // sidecar gave us. On the 0-segment trigger any segment is
                    // an improvement (its per-sheet reasons are what the silent
                    // zero withheld); on the all-empty trigger only real data is.
                    if (is_array($retry) && isset($retry['risks'])
                        && (int) ($retry['segment_count'] ?? 0) > 0
                        && ($hadNoSegments || $this->usableRiskCount($retry) > 0)) {
                        $data = $retry;
                        $usedInProcess = true;
                        Log::info('SmartUnderwritingExtractJob: in-process retry found segments', [
                            'upload_id' => $this->uploadId,
                            'segments'  => $retry['segment_count'],
                        ]);
                    }
                } catch (\Throwable $e) {
                    // The PHP reader refuses too — but its refusal NAMES the
                    // sheets it saw and why each was dropped, which is exactly
                    // what the sidecar's silent zero withheld. Carry that into
                    // the failure message instead of sending the operator to a
                    // log they cannot open.
                    $zeroSegmentReason = $e->getMessage();
                    Log::warning('SmartUnderwritingExtractJob: in-process retry also found nothing', [
                        'upload_id' => $this->uploadId, 'error' => $zeroSegmentReason,
                    ]);
                }
            }

            $rowsWritten = 0;
            // Segments that actually carried something usable, and the first
            // hard error seen. A segment whose extraction THREW comes back as
            // nothing but {_segment, _error} — it still writes a row, so
            // counting rows alone reported "Extracted 1 risk(s)" over a screen
            // of em-dashes and zero coverage sections, hiding a dead provider
            // key behind a green success message (test env, 2026-09-02).
            $dataRows   = 0;
            $firstError = null;
            // How many "please check" / "needs classification" lines the whole
            // upload carries, so the completion message can say so.
            $exceptionRows = 0;
            foreach ($data['risks'] as $risk) {
                if (!is_array($risk)) {
                    continue;
                }
                $errors   = $risk['_errors'] ?? ($risk['_error'] ?? null);
                if ($this->riskHasData($risk)) {
                    $dataRows++;
                }
                if ($firstError === null && !empty($risk['_error'])) {
                    $firstError = (string) $risk['_error'];
                }
                $provider = $risk['_provider'] ?? null;
                $segment  = $risk['_segment'] ?? ($risk['_segment_name'] ?? 'segment');

                // Settle the classification shape HERE, for every reader.
                // PhpScheduleExtractor normalises its own output, but the
                // sidecar does not go through that class at all — so a bucket
                // it answered as a bare string reached the review screen as a
                // non-array, failed the panel's Array.isArray check and the
                // line vanished. Idempotent, so the in-process path is
                // unaffected by running it twice.
                $risk = PhpScheduleExtractor::normaliseClassification($risk);

                // Lines the reader placed but is unsure of, and lines it
                // refused to place. NOT errors — the underwriter classifies
                // them on the review screen — but they are what a confidence
                // figure should actually mean, and a segment full of them
                // reading 100% is what sent a schedule through unchecked.
                //
                // Counted here when the reader did not count them itself: only
                // PhpScheduleExtractor writes _exceptions, so a sidecar-read
                // schedule stored confidence 1.0 over unplaced lines and the
                // discrepancies list never mentioned them.
                $exceptions = is_array($risk['_exceptions'] ?? null)
                    ? $risk['_exceptions']
                    : PhpScheduleExtractor::classificationExceptions($risk);
                $risk['_exceptions'] = $exceptions;
                $errorList  = is_array($errors) ? $errors : ($errors ? [(string) $errors] : []);

                // A validation error is a broken read (0.5); an exception is a
                // shaky one the human is being asked about (0.75); neither is
                // a clean 1.0.
                $confidence = !empty($errorList) ? 0.5 : (!empty($exceptions) ? 0.75 : 1.0);

                $discrepancies = array_values(array_merge($errorList, $exceptions));

                DB::table('smart_uw_extractions')->insert([
                    'upload_id'      => $this->uploadId,
                    'segment_name'   => is_string($segment) ? mb_substr($segment, 0, 255) : 'segment',
                    'extracted_json' => json_encode($risk, JSON_UNESCAPED_UNICODE),
                    'confidence'     => $confidence,
                    'provider'       => $provider ? mb_substr((string) $provider, 0, 32) : null,
                    'discrepancies'  => $discrepancies ? json_encode($discrepancies) : null,
                    'human_verified' => false,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
                $rowsWritten++;
                $exceptionRows += count($exceptions);
            }

            // Nothing extracted. Reporting a bare "Extraction complete" over an
            // empty result sent the operator hunting for a review screen that
            // could never appear (staging 2026-09-01) — the workbook's sheets
            // had all been filtered out and nothing said so. The status is
            // 'completed' because a review row IS written below (the upload must
            // never dead-end); the MESSAGE carries the reason.
            if ($rowsWritten === 0) {
                $note = 'Needs classification — the reader could not map this schedule ('
                    . (int) ($data['segment_count'] ?? 0) . ' sheet(s)/segment(s) read). '
                    . ($zeroSegmentReason !== null
                        // The in-process reader's own words: it lists every
                        // sheet it saw and the reason each was dropped.
                        ? $this->mask($zeroSegmentReason)
                        : 'The schedule may be empty, image-only, or laid out in a way the '
                          . 'reader skipped — see the smartuw log for the per-sheet decisions.');

                // An upload NEVER dead-ends. The instruction is that the file
                // always reaches the underwriter and every exception is caught
                // by a person, so a schedule the reader could not map is
                // handed over as one reviewable segment carrying the reason —
                // "Review & Issue" still opens the wizard and the schedule is
                // built by hand. Previously this was status=failed with no
                // review row, which is the one outcome the operator cannot act
                // on: the file was accepted, then disappeared.
                $this->writeUnmappedSegment($note, (string) ($row->original_name ?? 'schedule'));

                DB::table('smart_uw_uploads')->where('id', $this->uploadId)->update([
                    'status'     => 'completed',
                    'message'    => mb_substr($note, 0, 950),
                    'updated_at' => now(),
                ]);
                Log::warning('SmartUnderwritingExtractJob: nothing mapped — handed to the underwriter', [
                    'upload_id' => $this->uploadId,
                    'segments'  => $data['segment_count'] ?? 0,
                ]);

                return;
            }

            // Rows written but not one of them carried data: the extraction ran
            // and failed on every segment (no provider key, provider 5xx, an
            // unreadable sheet). A cause worth showing, not a bare success —
            // the rows stay as the audit trail and open the review screen, and
            // the upload message says why nothing came back.
            if ($dataRows === 0) {
                $note = 'Needs classification — no data came back for any of the '
                    . (int) ($data['segment_count'] ?? count($data['risks'])) . ' segment(s). '
                    . ($firstError !== null
                        ? 'First error: ' . $this->mask($firstError)
                        : 'The provider returned an empty result for every segment.');
                // A raw "localhost:11434 connection refused" tells the operator
                // nothing. It always means the same thing: the sidecar's PII
                // gate sent the segment to the local model, and no local model
                // is deployed. Name the cause and the one-click fix.
                if ($firstError !== null && preg_match('/11434|ollama/i', $firstError)) {
                    $note .= ' The engine sidecar routed this schedule to the local (PII-safe) '
                        . 'model, which is not deployed. Tick "Read schedules in-process" in the '
                        . 'Smart Upload AI settings and re-upload — that reader removes the '
                        . 'identifier lines and reads the rest.';
                }
                // Completed, not failed: the segment rows exist, so the
                // review screen opens and shows the per-segment reason. The
                // underwriter classifies from the schedule in front of them
                // rather than being told the upload died.
                DB::table('smart_uw_uploads')->where('id', $this->uploadId)->update([
                    'status'     => 'completed',
                    'message'    => mb_substr($note, 0, 950),
                    'updated_at' => now(),
                ]);
                Log::warning('SmartUnderwritingExtractJob: every segment came back empty', [
                    'upload_id' => $this->uploadId,
                    'segments'  => $data['segment_count'] ?? null,
                    'error'     => $firstError,
                ]);

                return;
            }

            DB::table('smart_uw_uploads')->where('id', $this->uploadId)->update([
                'status'     => 'completed',
                'message'    => "{$rowsWritten} risk segment(s) extracted from {$data['segment_count']} sheet(s)."
                    . ($exceptionRows > 0
                        ? " {$exceptionRows} line(s) need your attention — see “please check” and "
                          . '“needs classification” on the review screen.'
                        : ''),
                'updated_at' => now(),
            ]);
            Log::info('SmartUnderwritingExtractJob completed', [
                'upload_id' => $this->uploadId, 'risks' => $rowsWritten,
            ]);
        } catch (\Throwable $e) {
            // The read itself broke — a missing file, a dead provider key, a
            // segment the DPA gate refused to send. The STATUS stays 'failed',
            // because the cause is real and an operator has to see it; but the
            // schedule still reaches a person: one reviewable segment carrying
            // the reason, so "Review & Issue" opens the wizard and the file can
            // be built by hand instead of vanishing after a successful upload.
            // Only when nothing was written already, so a partial extraction is
            // never overwritten by a hand-over row.
            $already = DB::table('smart_uw_extractions')
                ->where('upload_id', $this->uploadId)->exists();
            if (!$already) {
                try {
                    $this->writeUnmappedSegment(
                        'Needs classification — the reader stopped: ' . $this->mask($e->getMessage()),
                        (string) ($row->original_name ?? 'schedule')
                    );
                } catch (\Throwable $inner) {
                    // Never let the hand-over row hide the original failure.
                    Log::warning('SmartUnderwritingExtractJob: could not write the hand-over segment', [
                        'upload_id' => $this->uploadId, 'error' => $inner->getMessage(),
                    ]);
                }
            }

            DB::table('smart_uw_uploads')->where('id', $this->uploadId)->update([
                'status'     => 'failed',
                'message'    => mb_substr($this->mask($e->getMessage()), 0, 950),
                'updated_at' => now(),
            ]);
            Log::error('SmartUnderwritingExtractJob failed', [
                'upload_id' => $this->uploadId, 'error' => $e->getMessage(),
            ]);
        } finally {
            if ($tmp && is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }

    /**
     * Hand an unmapped schedule to the underwriter as a reviewable segment.
     *
     * Written when the reader produced nothing at all. The row carries no
     * invented data — empty buckets and the reason — so the review screen
     * renders "the reader could not map this schedule, classify it by hand"
     * and "Review & Issue" still opens the wizard. confidence 0 because
     * nothing was read, not because something was read badly.
     */
    private function writeUnmappedSegment(string $reason, string $originalName): void
    {
        $risk = [
            '_segment'     => 'Unmapped schedule',
            '_provider'    => null,
            '_errors'      => [$reason],
            '_exceptions'  => ['Needs classification — the whole schedule. Nothing was mapped.'],
            // Named so the review screen can say WHY it is empty rather than
            // rendering a screen of em-dashes.
            '_unmapped'    => $reason,
            '_source_file' => mb_substr($originalName, 0, 255),
            'policy'       => [],
            'customer'     => [],
            'coverages'    => [],
            'motor'        => [],
            'unclassified' => [],
        ];

        DB::table('smart_uw_extractions')->insert([
            'upload_id'      => $this->uploadId,
            'segment_name'   => 'Unmapped schedule',
            'extracted_json' => json_encode($risk, JSON_UNESCAPED_UNICODE),
            'confidence'     => 0,
            'provider'       => null,
            'discrepancies'  => json_encode([$reason]),
            'human_verified' => false,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    /**
     * Laravel calls this when the job dies WITHOUT handle()'s catch running:
     * worker --timeout kill, OOM, container replaced mid-deploy, Redis job
     * lost. Without it the row stayed 'processing' forever and the upload
     * screen span an "Extracting..." spinner with no error and no end — the
     * exact symptom reported 2026-08-31. Mark the row failed so the poller
     * stops and the operator sees a cause.
     */
    public function failed(\Throwable $e): void
    {
        // Same hand-over handle()'s catch does: the upload must never dead-end,
        // so a worker killed on timeout or OOM still leaves ONE reviewable
        // segment carrying the reason, and "Review & Issue" opens the wizard.
        // Only when nothing was written, so a partial extraction is kept.
        try {
            $already = DB::table('smart_uw_extractions')
                ->where('upload_id', $this->uploadId)->exists();
            if (!$already) {
                $name = (string) (DB::table('smart_uw_uploads')
                    ->where('id', $this->uploadId)->value('original_name') ?? 'schedule');
                $this->writeUnmappedSegment(
                    'Needs classification — the extraction worker stopped before finishing: '
                    . $this->mask($e->getMessage()),
                    $name
                );
            }
        } catch (\Throwable $inner) {
            // Never let the hand-over row hide the original failure.
            Log::warning('SmartUnderwritingExtractJob: could not write the hand-over segment', [
                'upload_id' => $this->uploadId, 'error' => $inner->getMessage(),
            ]);
        }

        DB::table('smart_uw_uploads')
            ->where('id', $this->uploadId)
            ->whereIn('status', ['queued', 'processing'])
            ->update([
                'status'     => 'failed',
                'message'    => mb_substr(
                    'Extraction worker stopped before finishing: ' . $this->mask($e->getMessage()),
                    0, 950),
                'updated_at' => now(),
            ]);
        Log::error('SmartUnderwritingExtractJob died', [
            'upload_id' => $this->uploadId, 'error' => $e->getMessage(),
        ]);
    }
}
