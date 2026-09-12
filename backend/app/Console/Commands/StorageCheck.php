<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Http\Controllers\Api\V1\SystemDiagnosticsController;
use Illuminate\Console\Command;

/**
 * storage:check
 *
 * CLI mirror of GET /api/v1/system/storage-status. Renders the same
 * data as a human-readable table — handy for ECS exec-command sessions
 * where the FE Storage Status page isn't reachable.
 *
 * Usage:
 *   php artisan storage:check
 *   php artisan storage:check --json
 */
class StorageCheck extends Command
{
    protected $signature = 'storage:check
        {--json : Output raw JSON (same shape as the HTTP endpoint)}';

    protected $description = 'Diagnose storage paths used by V2 Quote Sheet, Policy Doc, and other generators.';

    public function handle(): int
    {
        // Reuse the controller's logic — single source of truth for
        // "what does a healthy storage tree look like". Bypass the
        // admin auth gate via reflection: anyone with shell access
        // already exceeds admin privilege.
        $controller = new SystemDiagnosticsController();
        $rc = new \ReflectionClass($controller);
        $inspect = $rc->getMethod('inspect');
        $inspect->setAccessible(true);
        $expected = $rc->getReflectionConstant('EXPECTED_PATHS')->getValue();

        $rows = [];
        foreach ($expected as $entry) {
            $full = storage_path('app/' . $entry['path']);
            $rows[] = $inspect->invoke($controller, $full, $entry);
        }

        if ($this->option('json')) {
            $this->line(json_encode([
                'host'      => gethostname(),
                'app_env'   => config('app.env'),
                'base_path' => storage_path('app'),
                'paths'     => $rows,
                'checked_at'=> now()->toIso8601String(),
            ], JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $this->line('');
        $this->info('═══ Storage diagnostic ═══');
        $this->line("Host        : " . gethostname());
        $this->line("APP_ENV     : " . config('app.env'));
        $this->line("Base        : " . storage_path('app'));
        $this->line("Checked at  : " . now()->toIso8601String());
        $this->line('');

        $byCategory = [];
        foreach ($rows as $r) {
            $byCategory[$r['category']][] = $r;
        }

        $criticalProblems = 0;
        foreach ($byCategory as $category => $items) {
            $this->info("── {$category} ──");
            foreach ($items as $r) {
                $icon = $this->iconFor($r);
                if ($r['critical'] && (!$r['exists'] || ($r['files'] === 0 && $r['subdirs'] === 0))) {
                    $criticalProblems++;
                }
                $size = $this->humanSize($r['size']);
                $line = sprintf(
                    '%s %s   %d files (%d subdirs), %s, last mod: %s',
                    $icon,
                    str_pad($r['path'], 56),
                    $r['files'],
                    $r['subdirs'],
                    $size,
                    $r['last_modified'] ?: '—'
                );
                $this->line($line);
                if ($r['note']) {
                    $this->line('     ' . ($r['critical'] ? '<fg=red>' : '<fg=yellow>') . '↳ ' . $r['note'] . '</>');
                }
            }
            $this->line('');
        }

        if ($criticalProblems > 0) {
            $this->error("⚠ {$criticalProblems} critical path(s) empty or missing. ");
            $this->line('  Try: php artisan storage:sync-static-pdfs down');
            return self::FAILURE;
        }

        $this->info('All critical paths populated.');
        return self::SUCCESS;
    }

    private function iconFor(array $r): string
    {
        if (! $r['exists'])                       return '❌';
        if ($r['critical'] && $r['files'] === 0 && $r['subdirs'] === 0) return '⚠ ';
        return '✅';
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes < 1024)         return $bytes . ' B';
        if ($bytes < 1024 * 1024)  return number_format($bytes / 1024, 1) . ' KB';
        return number_format($bytes / 1024 / 1024, 2) . ' MB';
    }
}
