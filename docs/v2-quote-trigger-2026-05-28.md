# V2 Quote generation — permanent scheduler-independent trigger (2026-05-28)

## Symptom

V2 Quote Sheet generation intermittently stalls. Jobs sit in `v2_pdf_jobs.status = 'queued_long'` and never progress. Running `php artisan cache:clear` restores it for a while, then it stalls again.

Live evidence at investigation time:
- Job `#2570` policy 213443 — sat in `queued` 9 min before user cancelled.
- Job `#2572` policy 213443 — still `queued_long` at time of report, several minutes after creation.
- Surrounding jobs `#2567`–`#2569`, `#2571` — all completed in 1–2 min on the same hour.

## Root cause

The scheduler at `backend/app/Console/Kernel.php:119` declared:

```php
$schedule->command('pdf:process-pending --limit=3')
         ->everyMinute()
         ->withoutOverlapping(5)
         ->name('pdf-process-pending')
         ->onOneServer();
```

Two latent bugs from `CACHE_DRIVER=file`:

1. **`withoutOverlapping(5)`** stores its lock in the cache store. With `file` cache, the lock is a file in `storage/framework/cache/data/...`. When a tick crashes mid-run (OOM on big PDF, ECS task replacement during deploy, `wkhtmltopdf` segfault), the lock file is left on disk. Every subsequent minute-tick sees the lock and skips. `cache:clear` removes the file → next tick proceeds → eventual crash → repeat.

2. **`onOneServer()`** requires a *shared* cache (Redis / DB / Memcached / DynamoDB). With `file` cache it is silently a no-op — each ECS task has its own cache directory.

`.env` is locked to ops, so flipping `CACHE_DRIVER` to `database` wasn't an option.

## Fix

Decouple V2 Quote generation from the scheduler entirely. The HTTP request that creates the `v2_pdf_jobs` row also spawns the worker directly — fire-and-forget, detached from the request lifecycle. The Laravel scheduler entry stays untouched as a once-a-minute failsafe.

Location: `backend/app/Http/Controllers/Api/V1/PolicyCreateController.php` — immediately after `Log::info("V2 Quote job {$job->id} queued ...")` inside `generateV2QuoteSheet()`.

```php
try {
    $php     = escapeshellarg(PHP_BINARY);
    $artisan = escapeshellarg(base_path('artisan'));
    if (DIRECTORY_SEPARATOR === '\\') {
        pclose(popen("start /B \"\" {$php} {$artisan} pdf:process-pending --limit=1", 'r'));
    } else {
        shell_exec("nohup {$php} {$artisan} pdf:process-pending --limit=1 > /dev/null 2>&1 &");
    }
} catch (\Throwable $e) {
    Log::warning("V2 Quote inline worker kick failed for job {$job->id}: " . $e->getMessage());
}
```

## Why it's permanent

- **No scheduler dependency.** A stuck scheduler lock cannot block V2 generation any more — every user click triggers its own worker process.
- **Concurrency-safe.** `ProcessPdfJobs::processOne()` already uses an optimistic UPDATE (`WHERE status = 'queued_long'`) at line 135–139. Two parallel workers racing for the same row → only one's UPDATE affects rows; the other gets 0 and skips.
- **Backstop preserved.** The hardcoded `$schedule->command('pdf:process-pending --limit=3')->everyMinute()` at Kernel.php:119 still runs, catching any case where the inline spawn fails (disabled `shell_exec`, permission denied, etc.). If the spawn works, the scheduler tick is a no-op (queue already drained).
- **No `.env` change required.**
- **No timeout regression.** Spawn is detached (`nohup ... &` on Linux, `start /B` on Windows), so the HTTP response returns immediately — no Cloudflare 60s issue that the original async refactor was designed to avoid.

## Behaviour change for users

- Small policies: V2 Quote PDF should now be ready in seconds instead of "wait for the next minute".
- Large policies: same flow as before (frontend polls `/quote-pdf-status/{jobId}`), but processing starts immediately instead of on the next minute-tick.
- A stuck scheduler is now invisible to users for V2 Quote — they will only notice if both the inline spawn AND the next scheduled tick fail.

## Verification

```sql
-- Should show no rows older than ~2 min in queued/queued_long
SELECT id, status, policy_id, created_at, updated_at, message
FROM v2_pdf_jobs
WHERE status IN ('queued','queued_long')
ORDER BY created_at;
```

Manually re-trigger from UI and confirm `created_at` → `updated_at` delta is seconds, not minutes.

## Files touched

- `backend/app/Http/Controllers/Api/V1/PolicyCreateController.php` — added inline worker kick after job creation (~18 lines, additive only).
- `docs/v2-quote-trigger-2026-05-28.md` — this note.
