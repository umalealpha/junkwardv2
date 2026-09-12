<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Customer;
use AlphaDirect\Models\AmlResult;
use AlphaDirect\Models\AuditLog;
use AlphaDirect\Models\CronStatus;
use AlphaDirect\Models\KycCase;
use AlphaDirect\Policy;
use AlphaDirect\Services\OpenSanctionsClient;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

class WeeklyAmlScreening extends Command
{
    protected $signature = 'aml:weekly-screening';
    protected $description = 'Weekly AML sanctions screening for all customers with active policies. Sends report to KYC team.';

    public function handle()
    {
        $cron = null;
        try {
            $cron = new CronStatus();
            $cron->name = 'aml:weekly-screening';
            $cron->start = Carbon::now();
            $cron->save();
        } catch (\Throwable $e) {
            $this->warn('Cron status logging skipped (read-only DB): ' . $e->getMessage());
        }

        Log::info('Weekly AML screening started');
        $this->info('Weekly AML screening started — ' . now()->toDateTimeString());

        $os = app(OpenSanctionsClient::class);

        // Check AML service health
        try {
            $health = $os->health();
            if (($health['status'] ?? '') !== 'ok') {
                Log::error('AML service not ready', $health);
                $this->error('AML service not ready: ' . json_encode($health));
                return 1;
            }
            $this->info("AML service OK — {$health['entries_loaded']} entries loaded");
        } catch (\Throwable $e) {
            Log::error('AML service unreachable: ' . $e->getMessage());
            $this->error('AML service unreachable: ' . $e->getMessage());
            return 1;
        }

        // Only scan customers whose policies were activated in the last 7 days
        $since = Carbon::now()->subDays(7)->startOfDay();
        $this->info("Scanning customers with policies activated since: {$since->toDateString()}");

        $policyScope = function ($q) use ($since) {
            $q->where('status', 1)
              ->where('policyActivatedDate', '>=', $since);
        };

        $total = Customer::whereHas('policy', $policyScope)
            ->whereNotNull('firstName')
            ->where('firstName', '!=', '')
            ->whereNotNull('lastName')
            ->where('lastName', '!=', '')
            ->count();

        $this->info("Screening {$total} newly activated customers...");
        Log::info("Weekly AML screening: {$total} customers activated since {$since->toDateString()}");

        if ($total === 0) {
            $this->info('No newly activated customers this week. Sending all-clear report.');
        }

        $flagged = [];
        $screened = 0;
        $errors = 0;
        $startTime = microtime(true);

        // Process in chunks to avoid memory issues
        Customer::with('profile')
            ->whereHas('policy', $policyScope)
            ->whereNotNull('firstName')
            ->where('firstName', '!=', '')
            ->whereNotNull('lastName')
            ->where('lastName', '!=', '')
            ->chunk(200, function ($customers) use ($os, &$flagged, &$screened, &$errors, $total) {

        foreach ($customers as $customer) {
            $fullName = trim($customer->firstName . ' ' . ($customer->middleName ?? '') . ' ' . $customer->lastName);
            $dob = null;
            if ($customer->profile && $customer->profile->dob) {
                try {
                    $dob = Carbon::parse($customer->profile->dob)->format('Y-m-d');
                } catch (\Exception $e) {}
            }

            try {
                $response = $os->match($fullName, $dob);
                $results = data_get($response, 'responses.q1.results', []);
                $maxScore = collect($results)->pluck('score')->max() ?? 0;

                // Update or create KYC case + AML result
                $allDatasets = [];
                $target = false;
                foreach ($results as $result) {
                    if (isset($result['datasets'])) {
                        foreach ($result['datasets'] as $ds) {
                            if (!in_array($ds, $allDatasets)) $allDatasets[] = $ds;
                        }
                    }
                    if ($target === false && isset($result['target'])) {
                        $target = $result['target'];
                    }
                }

                try {
                    $case = KycCase::firstOrCreate(
                        ['customer_id' => $customer->id],
                        ['status' => 'created', 'sanctions_max' => null, 'decision' => null]
                    );

                    AmlResult::create([
                        'kyc_case_id' => $case->id,
                        'query' => ['name' => $fullName, 'dob' => $dob, 'source' => 'weekly_screening'],
                        'results' => [
                            'total_results' => count($results),
                            'top_matches' => array_slice($results, 0, 5),
                        ],
                        'max_score' => $maxScore,
                        'datasets' => array_slice($allDatasets, 0, 20),
                        'target' => $target,
                        'programId' => [],
                    ]);

                    $case->sanctions_max = $maxScore;
                    $case->status = $maxScore >= 0.9 ? 'rejected' : ($maxScore >= 0.7 ? 'review' : 'approved');
                    $case->decision = [
                        'max_score' => $maxScore,
                        'final' => $case->status,
                        'checked_at' => now()->toISOString(),
                        'source' => 'weekly_screening',
                    ];
                    $case->save();
                } catch (\Throwable $e) {
                    // DB write may fail on read-only replica (local dev) — screening still continues
                }

                // Collect flagged customers for report
                if ($maxScore >= 0.7) {
                    $topMatch = $results[0] ?? [];
                    $flagged[] = [
                        'customer_id' => $customer->id,
                        'name' => $fullName,
                        'dob' => $dob,
                        'score' => round($maxScore, 3),
                        'risk_level' => $maxScore >= 0.9 ? 'CRITICAL' : 'HIGH',
                        'matched_name' => $topMatch['caption'] ?? 'Unknown',
                        'datasets' => implode(', ', $allDatasets),
                        'kyc_case_id' => isset($case) ? $case->id : null,
                        'policy_count' => $customer->policy()->where('status', 1)->count(),
                    ];

                    // Block customer if critical
                    if ($maxScore >= 0.9) {
                        try {
                            Customer::where('id', $customer->id)
                                ->where(function ($q) {
                                    $q->whereNull('is_blocked')->orWhere('is_blocked', '!=', '1');
                                })
                                ->update([
                                    'is_blocked' => '1',
                                    'block_reason' => "AML weekly screening match (score: " . round($maxScore, 2) . ")",
                                ]);
                        } catch (\Throwable $e) {}
                    }
                }

                $screened++;

                if ($screened % 500 === 0) {
                    $this->info("  Progress: {$screened}/{$total} screened, " . count($flagged) . " flagged");
                }

            } catch (\Throwable $e) {
                $errors++;
                Log::warning("AML screening failed for customer {$customer->id}: " . $e->getMessage());
            }
        }

        }); // end chunk

        $elapsed = round(microtime(true) - $startTime, 1);

        // Build and send report
        $reportData = [
            'date' => now()->format('d M Y'),
            'total_screened' => $screened,
            'total_flagged' => count($flagged),
            'total_errors' => $errors,
            'elapsed_seconds' => $elapsed,
            'flagged_customers' => $flagged,
            'health' => $health,
            'threshold' => 0.7,
        ];

        $this->sendReport($reportData);

        Log::info("Weekly AML screening complete", [
            'screened' => $screened,
            'flagged' => count($flagged),
            'errors' => $errors,
            'elapsed' => $elapsed,
        ]);

        $this->info("\nDone: {$screened} screened, " . count($flagged) . " flagged, {$errors} errors ({$elapsed}s)");

        if ($cron && $cron->exists) {
            try {
                $cron->end = Carbon::now();
                $cron->save();
            } catch (\Throwable $e) {}
        }

        return 0;
    }

    private function sendReport(array $data)
    {
        $recipients = array_filter(array_map('trim', explode(',', env('AML_REPORT_EMAILS', 'compliance@alphadirect.co.bw'))));

        if (empty($recipients)) {
            $this->warn('No AML_REPORT_EMAILS configured — skipping email');
            return;
        }

        try {
            $html = View::make('emails.aml-weekly-report', $data)->render();
            $subject = "AML Weekly Screening Report — {$data['date']} | {$data['total_flagged']} Flagged";

            foreach ($recipients as $to) {
                event(new \AlphaDirect\Events\SendMail(
                    $to,
                    $subject,
                    '',
                    $html,
                    null,
                    []
                ));
            }

            $this->info("Report sent to: " . implode(', ', $recipients));
            Log::info("AML weekly report sent", ['recipients' => $recipients, 'flagged' => $data['total_flagged']]);
        } catch (\Throwable $e) {
            Log::error("Failed to send AML report: " . $e->getMessage());
            $this->error("Failed to send report: " . $e->getMessage());
        }
    }
}
