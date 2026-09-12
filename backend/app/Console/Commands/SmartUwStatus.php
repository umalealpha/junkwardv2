<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Show recent Smart Underwriting uploads and how long each has been waiting.
 *
 * Reading the raw table hides the one distinction that matters: a row whose
 * updated_at still equals created_at was NEVER picked up by a worker — the job
 * sets status='processing' as its first act. A row at 'processing' with a stale
 * updated_at means the worker took it and the engine is hanging or dead.
 * Those two have completely different fixes, so the table names which is which.
 */
class SmartUwStatus extends Command
{
    protected $signature = 'smartuw:status {--limit=15}';
    protected $description = 'List Smart UW uploads with wait time and a likely cause';

    public function handle(): int
    {
        $cols = ['id', 'status', 'original_name', 'message', 'created_at', 'updated_at'];
        // Spawn tracking is a later, nullable addition — select it only when the
        // database has actually been migrated, so this command still runs on an
        // older schema instead of dying with "unknown column".
        $hasSpawn = Schema::hasColumn('smart_uw_uploads', 'spawn_attempts');
        if ($hasSpawn) {
            $cols[] = 'spawn_attempts';
        }

        $rows = DB::table('smart_uw_uploads')
            ->orderByDesc('id')->limit((int) $this->option('limit'))
            ->get($cols);

        if ($rows->isEmpty()) {
            $this->info('No uploads.');
            return self::SUCCESS;
        }

        $this->table(
            ['id', 'status', 'file', 'waited', 'diagnosis', 'message'],
            $rows->map(function ($r) use ($hasSpawn) {
                $created = strtotime((string) $r->created_at);
                $updated = strtotime((string) $r->updated_at);
                $waited  = $created ? gmdate('H:i:s', max(0, time() - $created)) : '?';
                $never   = $updated && $created && ($updated - $created) < 2;

                // 'queued' + untouched updated_at = the extractor process was
                // never started. That is now retried automatically from the
                // upload screen's poll, so the attempt count is the useful
                // number: 3/3 and still queued means spawning itself is broken
                // (no CLI php / disable_functions), not merely a missing worker.
                $dx = '';
                if (in_array($r->status, ['queued', 'processing'], true)) {
                    if ($r->status === 'queued' && $never) {
                        $tries = $hasSpawn ? (int) ($r->spawn_attempts ?? 0) : null;
                        $dx = 'NEVER STARTED'
                            . ($tries !== null ? " — {$tries} spawn attempt(s)" : ' — no smartuw worker');
                    } else {
                        $dx = 'extractor took it; engine slow/dead';
                    }
                }

                return [
                    $r->id, $r->status,
                    mb_strimwidth((string) $r->original_name, 0, 32, '…'),
                    $waited, $dx,
                    mb_strimwidth((string) ($r->message ?? ''), 0, 48, '…'),
                ];
            })->all()
        );

        return self::SUCCESS;
    }
}
