<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-shot command to add an email address to every report_stakeholders
 * report_type. Used to bulk-onboard a recipient (e.g. K. Katolkar to
 * every Finance Anomaly / SLA / claims report) without click-through on
 * each report card.
 *
 *   php artisan reports:add-recipient kkatolkar@alphadirect.co.bw "K Katolkar"
 *
 * Idempotent — skips report_types where the email is already present.
 * If the table is empty it seeds the canonical report_type list so the
 * recipient still gets attached to runs that fire later.
 */
class AddReportRecipient extends Command
{
    protected $signature   = 'reports:add-recipient {email} {name=} {--report-type= : restrict to a single report_type}';
    protected $description = 'Add an email to report_stakeholders for every report_type (or one if --report-type given).';

    /** Canonical fallback list when the table is empty. */
    private const KNOWN_TYPES = [
        'anomaly', 'daily_compliance', 'sla', 'collections',
        'endorsement', 'renewal', 'claims', 'reinsurance', 'finance',
    ];

    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));
        $name  = trim((string) $this->argument('name')) ?: $email;
        $only  = $this->option('report-type');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("Invalid email: {$email}");
            return self::FAILURE;
        }
        if (!Schema::hasTable('report_stakeholders')) {
            $this->error('Table report_stakeholders does not exist on this DB.');
            return self::FAILURE;
        }

        if ($only) {
            $types = [$only];
        } else {
            $types = DB::table('report_stakeholders')
                ->select('report_type')->groupBy('report_type')
                ->pluck('report_type')->all();
            if (empty($types)) {
                $this->warn('report_stakeholders is empty — seeding canonical report_type list: ' . implode(', ', self::KNOWN_TYPES));
                $types = self::KNOWN_TYPES;
            }
        }

        $inserted = 0; $skipped = 0;
        foreach ($types as $rt) {
            $exists = DB::table('report_stakeholders')
                ->where('report_type', $rt)->where('email', $email)->exists();
            if ($exists) {
                $skipped++;
                $this->line("  · {$rt} — skip (already present)");
                continue;
            }
            DB::table('report_stakeholders')->insert([
                'report_type' => $rt,
                'name'        => $name,
                'email'       => $email,
                'active'      => 1,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
            $inserted++;
            $this->line("  + {$rt} — added");
        }

        $this->info("DONE — inserted={$inserted}, skipped={$skipped}, types_processed=" . count($types));
        return self::SUCCESS;
    }
}
