<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use AlphaDirect\KycFields;
use AlphaDirect\Models\CronStatus;
use Carbon\Carbon;

/**
 * Rewritten KYC compliance cron — reads rules dynamically from kyc_compliance table.
 *
 * For each product that has a kyc_compliance rule assigned:
 *   1. Loads the rule's required fields (check=1 mandatory, check=2 mandatory with alt)
 *   2. Resolves field names to customer_kyc columns via kyc_fields table
 *   3. For each customer with an active policy on that product:
 *      - Checks document STATUS columns ({column}Status or {column}FrontStatus)
 *      - If ALL required docs are approved (status=1) → compliance=1, status='Approve'
 *      - If any required doc is not approved → compliance=2, status='rejected'
 *   4. Also checks document expiry (omangExpiry, passportExpiry, licenseExpiry)
 *      - If expired → compliance=0, status='Renew'
 *
 * Example: Motor Comprehensive rule only requires "Driving License" →
 *          only driving_licenseStatus is checked, omang/passport are ignored.
 */
class UpdateCustomerKycCompliance extends Command
{
    /**
     * MIS product ids the V8 customer_kyc compliance cron applies to.
     * Mirrors the scope the user requested for KYC parity. DOM/COM
     * (7, 8) and Specialist (16-19) are intentionally excluded — they
     * use their own KYC flows (RekycCampaigns / AdGroupKyc*) and
     * customer_kyc.compliance is not the authoritative status for them.
     */
    const MIS_PRODUCT_IDS = [1, 2, 3, 4, 5, 6, 9, 10];

    protected $signature   = 'updateCustomerKycCompliance:cron
                              {--dry-run : Report what would change and write nothing}
                              {--product= : Restrict the run to a single product id}';
    protected $description = 'Update customer KYC compliance based on product-specific rules (MIS products only)';

    /**
     * Map a customer_kyc_column to its Status column in customer_kyc table.
     * The DB uses inconsistent naming, so we map explicitly.
     */
    private const STATUS_COLUMN_MAP = [
        'omang'           => 'omangFrontStatus',
        'omangBack'       => 'omangBackStatus',
        'driving_license' => 'driving_licenseStatus',
        'driversLicense'  => 'driving_licenseStatus',
        'proof_residence' => 'proof_residenceStatus',
        'proof_income'    => 'proof_incomeStatus',
        'passport'        => 'passportStatus',
        'passportBack'    => 'passportStatus',
    ];

    /**
     * Map a customer_kyc_column to its Remark column (for building failure reasons).
     */
    private const REMARK_COLUMN_MAP = [
        'omang'           => 'omangFrontRemark',
        'omangBack'       => 'omangBackRemark',
        'driving_license' => 'driving_licenseRemark',
        'driversLicense'  => 'driving_licenseRemark',
        'proof_residence' => 'proof_residenceRemark',
        'proof_income'    => 'proof_incomeRemark',
        'passport'        => 'passportRemark',
        'passportBack'    => 'passportRemark',
    ];

    /**
     * Friendly display names for the reason message.
     */
    private const DOC_DISPLAY_NAME = [
        'omang'           => 'Omang (Front)',
        'omangBack'       => 'Omang (Back)',
        'driving_license' => 'Driving License',
        'driversLicense'  => 'Driving License',
        'proof_residence' => 'Proof of Residence',
        'proof_income'    => 'Proof of Income',
        'passport'        => 'Passport',
        'passportBack'    => 'Passport (Back)',
    ];

    public function handle()
    {
        $dryRun = (bool) $this->option('dry-run');

        // A first live run rewrites compliance/status/reason across the whole
        // active book, so the dry run must be able to report without writing.
        if (!$dryRun) {
            $cron = new CronStatus();
            $cron->name  = 'updateCustomerKycCompliance:cron';
            $cron->start = Carbon::now();
            $cron->save();
        } else {
            $cron = null;
            $this->warn('DRY RUN — nothing will be written.');
        }

        Log::info('KYC compliance cron started' . ($dryRun ? ' (dry run)' : ''));

        // Pre-load all KYC field definitions (name → customer_kyc_column)
        $fieldMap = DB::table('kyc_fields')->pluck('customer_kyc_column', 'name')->toArray();

        // Load MIS products that have a kyc_compliance rule. DOM/COM
        // (7,8) and Specialist (16-19) are intentionally excluded.
        $products = DB::table('products')
            ->join('kyc_compliance', 'kyc_compliance.id', '=', 'products.kyc_compliance')
            ->whereNotNull('products.kyc_compliance')
            ->where('products.kyc_compliance', '>', 0)
            ->whereIn('products.id', self::MIS_PRODUCT_IDS)
            ->when($this->option('product'), fn($q) => $q->where('products.id', (int) $this->option('product')))
            ->select('products.id as product_id', 'products.name as product_name', 'kyc_compliance.fields')
            ->get();

        $totalUpdated = 0;
        $totalApproved = 0;
        $totalUnapproved = 0;
        // Dry-run only: how many records each outcome would touch.
        $wouldChange = ['to_approve' => 0, 'to_unapprove' => 0, 'to_renew' => 0, 'unchanged' => 0, 'errors' => 0];

        foreach ($products as $product) {
            $fieldsData = json_decode($product->fields, true);
            if (empty($fieldsData)) continue;

            // Build list of required columns and their check type
            $requiredDocs = $this->resolveRequiredDocs($fieldsData, $fieldMap);
            if (empty($requiredDocs)) continue;

            // Get all status columns we need to check
            $statusCols = collect($requiredDocs)->flatMap(function ($doc) {
                $cols = [];
                $statusCol = self::STATUS_COLUMN_MAP[$doc['column']] ?? null;
                if ($statusCol) $cols[] = $statusCol;
                if ($doc['alt_column']) {
                    $altStatusCol = self::STATUS_COLUMN_MAP[$doc['alt_column']] ?? null;
                    if ($altStatusCol) $cols[] = $altStatusCol;
                }
                return $cols;
            })->unique()->values()->toArray();

            // The document path columns themselves. Needed so the failure reason
            // can tell "the customer never sent it" apart from "it is sitting
            // here unreviewed" — those read identically on the status column
            // alone, and reporting the second as "Not Uploaded" sends staff off
            // to chase a customer who has already complied.
            $docCols = collect($requiredDocs)
                ->flatMap(fn($doc) => array_filter([$doc['column'], $doc['alt_column']]))
                ->unique()
                ->filter(fn($c) => Schema::hasColumn('customer_kyc', $c))
                ->values()
                ->toArray();

            // Get customers with active policies on this product
            $selectCols = array_merge(
                ['customer_kyc.id', 'customer_kyc.customer_id'],
                array_map(fn($c) => "customer_kyc.{$c}", $statusCols),
                array_map(fn($c) => "customer_kyc.{$c}", $docCols),
                // Expiry columns for later check
                ['customer_kyc.omangExpiry', 'customer_kyc.passportExpiry', 'customer_kyc.licenseExpiry', 'customer_kyc.approved_date']
            );

            $customers = DB::table('policies')
                ->join('customer_kyc', 'customer_kyc.customer_id', '=', 'policies.customer_id')
                ->where('policies.product_id', $product->product_id)
                ->where('policies.status', 1) // active
                ->whereNotNull('customer_kyc.status')
                ->where('customer_kyc.status', '!=', 'Unchecked')
                ->groupBy('policies.customer_id')
                ->select($selectCols)
                ->get();

            $this->info("Product: {$product->product_name} (ID:{$product->product_id}) — {$customers->count()} customers");

            foreach ($customers as $customer) {
                try {
                $result = $this->evaluateCompliance($customer, $requiredDocs);

                if ($result['compliant']) {
                    // Also check expiry dates
                    $expiryResult = $this->checkExpiry($customer);
                    if ($expiryResult['expired']) {
                        if (!$dryRun) {
                            DB::table('customer_kyc')->where('id', $customer->id)->update([
                                'compliance'    => 0,
                                'status'        => 'Renew',
                                'reason'        => $expiryResult['reason'],
                                'approved_date' => null,
                            ]);
                        }
                        $wouldChange['to_renew']++;
                        $totalUnapproved++;
                    } else {
                        if (!$dryRun) {
                            DB::table('customer_kyc')->where('id', $customer->id)->update([
                                'compliance' => 1,
                                'status'     => 'Approve',
                                'reason'     => 'Approved',
                            ]);
                        }
                        $wouldChange['to_approve']++;
                        $totalApproved++;
                    }
                } else {
                    $reason = 'Your KYC document(s) is unapproved due to the following reason : <br>' . $result['reason'];
                    if (!$dryRun) {
                        DB::table('customer_kyc')->where('id', $customer->id)->update([
                            'compliance' => 2,
                            'status'     => 'rejected',
                            'reason'     => $reason,
                        ]);
                    }
                    $wouldChange['to_unapprove']++;
                    $totalUnapproved++;
                }
                $totalUpdated++;
                } catch (\Throwable $e) {
                    // One malformed row (e.g. a bad stored date) must never abort
                    // the whole sweep. Log it, count it, and carry on.
                    $wouldChange['errors']++;
                    Log::warning('KYC compliance: skipped customer_kyc id ' . ($customer->id ?? 'unknown') . ' — ' . $e->getMessage());
                }
            }
        }

        if ($dryRun) {
            $this->warn('DRY RUN — no rows were written. Would have changed:');
            $this->info("  to Approve   : {$wouldChange['to_approve']}");
            $this->info("  to Unapprove : {$wouldChange['to_unapprove']}");
            $this->info("  to Renew     : {$wouldChange['to_renew']}");
            $this->info("  errors(skip) : {$wouldChange['errors']}");
            $this->info("  total        : {$totalUpdated}");
            Log::info('KYC compliance dry run: ' . json_encode($wouldChange));
            return 0;
        }

        $this->info("Done. Updated: {$totalUpdated} (Approved: {$totalApproved}, Unapproved: {$totalUnapproved})");
        Log::info("KYC compliance cron done. Updated: {$totalUpdated} (Approved: {$totalApproved}, Unapproved: {$totalUnapproved})");

        $cron->end = Carbon::now();
        $cron->save();
    }

    /**
     * Resolve the fields JSON array into a clean list of required documents.
     * Returns array of ['column' => 'driving_license', 'alt_column' => null|'passport', 'check' => 1|2]
     */
    private function resolveRequiredDocs(array $fieldsData, array $fieldMap): array
    {
        $docs = [];
        foreach ($fieldsData as $field) {
            $check = (int) ($field['check'] ?? 3);
            if ($check === 3) continue; // Optional — skip

            $column    = $fieldMap[$field['field'] ?? ''] ?? null;
            $altColumn = null;

            if ($check === 2 && !empty($field['other'])) {
                $altColumn = $fieldMap[$field['other']] ?? null;
            }

            if (!$column) continue; // Unknown field — skip

            $docs[] = [
                'field_name' => $field['field'] ?? '',
                'column'     => $column,
                'alt_column' => $altColumn,
                'check'      => $check,
            ];
        }
        return $docs;
    }

    /**
     * Human label for a required document. Must never come back empty — a
     * blank label produces reasons that read " : Not Uploaded" and tell the
     * reviewer nothing about which document is actually holding the policy.
     */
    private function displayName(array $doc): string
    {
        $name = self::DOC_DISPLAY_NAME[$doc['column']] ?? null;
        if (!empty($name)) return $name;

        if (!empty($doc['field_name'])) return $doc['field_name'];

        if (!empty($doc['column'])) {
            return ucwords(str_replace('_', ' ', $doc['column']));
        }

        return 'Required document';
    }

    /**
     * Evaluate whether a customer meets all required doc statuses.
     * check=1: the document's status column must be 1 (approved).
     * check=2: EITHER the primary OR the alternative must be approved.
     */
    private function evaluateCompliance(object $customer, array $requiredDocs): array
    {
        $failReasons = [];

        foreach ($requiredDocs as $doc) {
            $statusCol = self::STATUS_COLUMN_MAP[$doc['column']] ?? null;
            if (!$statusCol) {
                // Fail CLOSED. A *required* document with no status-column mapping
                // must never be silently treated as satisfied — that would mark a
                // policy compliant without the document ever being verified (e.g.
                // if the KYC/Data-Protection forms are switched from optional to
                // required in the rule before their columns are mapped here).
                // Record it as unverifiable and log so the missing
                // STATUS_COLUMN_MAP entry is added deliberately.
                $failReasons[] = $this->displayName($doc) . ' : cannot be verified (no compliance mapping)';
                Log::warning("KYC compliance: required document '{$doc['column']}' has no STATUS_COLUMN_MAP entry — failing closed for customer_kyc id " . ($customer->id ?? 'unknown'));
                continue;
            }

            $status = $customer->$statusCol ?? null;

            if ($doc['check'] === 1) {
                // Mandatory — must be approved
                if ((int) $status !== 1) {
                    $displayName = $this->displayName($doc);
                    $remarkCol   = self::REMARK_COLUMN_MAP[$doc['column']] ?? null;
                    $remark      = $remarkCol ? ($customer->$remarkCol ?? null) : null;
                    $hasFile     = !empty($customer->{$doc['column']} ?? null);

                    if ($status === null || $status === '') {
                        // An empty status means nobody has reviewed it — that is
                        // NOT the same as the document being absent. Saying
                        // "Not Uploaded" over a document that is on file makes
                        // staff wait for a customer who has already sent it.
                        $failReasons[] = $hasFile
                            ? "{$displayName} : Uploaded — awaiting verification"
                            : "{$displayName} : Not Uploaded";
                    } elseif ($remark) {
                        $failReasons[] = "{$displayName} : {$remark}";
                    } else {
                        $failReasons[] = "{$displayName} : Not Approved";
                    }
                }
            } elseif ($doc['check'] === 2) {
                // Mandatory with alternative — one of them must be approved
                $altStatusCol = $doc['alt_column'] ? (self::STATUS_COLUMN_MAP[$doc['alt_column']] ?? null) : null;
                $altStatus    = $altStatusCol ? ($customer->$altStatusCol ?? null) : null;

                if ((int) $status !== 1 && (int) $altStatus !== 1) {
                    $displayName    = $this->displayName($doc);
                    $altDisplayName = $doc['alt_column']
                        ? (self::DOC_DISPLAY_NAME[$doc['alt_column']] ?? $doc['alt_column'])
                        : '';
                    $primaryOnFile  = !empty($customer->{$doc['column']} ?? null);
                    $altOnFile      = $doc['alt_column'] ? !empty($customer->{$doc['alt_column']} ?? null) : false;
                    $label          = $altDisplayName ? "{$displayName} or {$altDisplayName}" : $displayName;

                    // Mirror the check=1 precedence so a REJECTED alternative is
                    // not mislabelled "awaiting verification":
                    //   1. on file but not yet reviewed (blank status) → awaiting
                    //   2. on file and reviewed-not-approved (rejected) → the remark, else Not Approved
                    //   3. nothing on file at all                       → Not Uploaded
                    $primaryUnreviewed = $primaryOnFile && ($status === null || $status === '');
                    $altUnreviewed     = $altOnFile && ($altStatus === null || $altStatus === '');

                    if ($primaryUnreviewed || $altUnreviewed) {
                        $failReasons[] = "{$label} : Uploaded — awaiting verification";
                    } elseif ($primaryOnFile || $altOnFile) {
                        $primaryRemarkCol = self::REMARK_COLUMN_MAP[$doc['column']] ?? null;
                        $altRemarkCol     = $doc['alt_column'] ? (self::REMARK_COLUMN_MAP[$doc['alt_column']] ?? null) : null;
                        $remark = ($primaryRemarkCol ? ($customer->$primaryRemarkCol ?? null) : null)
                            ?: ($altRemarkCol ? ($customer->$altRemarkCol ?? null) : null);
                        $failReasons[] = $remark ? "{$label} : {$remark}" : "{$label} : Not Approved";
                    } else {
                        $failReasons[] = "{$label} : Not Uploaded";
                    }
                }
            }
        }

        return [
            'compliant' => empty($failReasons),
            'reason'    => implode('<br>', $failReasons),
        ];
    }

    /**
     * Check document expiry — returns expired flag if any ID doc has expired.
     */
    private function checkExpiry(object $customer): array
    {
        $today   = Carbon::today();
        $reasons = [];

        $expiryFields = [
            'omangExpiry'    => 'Omang',
            'passportExpiry' => 'Passport',
            'licenseExpiry'  => 'Driving License',
        ];

        foreach ($expiryFields as $col => $name) {
            $raw  = $customer->$col ?? null;
            $date = $this->parseDate($raw);
            if ($date && $date->lte($today)) {
                $reasons[] = "{$name} expired on {$raw}";
            }
        }

        // Also check if approved > 365 days ago
        $approvedDate = $this->parseDate($customer->approved_date ?? null);
        if ($approvedDate && $approvedDate->diffInDays($today) > 365) {
            $reasons[] = 'KYC approval older than 365 days — re-verification needed';
        }

        return [
            'expired' => !empty($reasons),
            'reason'  => implode('<br>', $reasons),
        ];
    }

    /**
     * Parse a stored KYC date defensively. Botswana data stores some expiry
     * dates as DD/MM/YYYY (e.g. "30/04/2021"), which Carbon::parse() misreads
     * as US M/D/Y and THROWS on (day > 12) — aborting the whole sweep. Returns
     * null on anything unparseable so the caller skips it instead of crashing.
     */
    private function parseDate($value): ?Carbon
    {
        if ($value === null || $value === '' || $value === '0000-00-00') {
            return null;
        }

        $value = trim((string) $value);

        // DD/MM/YYYY (day-first) — parse explicitly; "!" zeroes the time part.
        if (preg_match('#^\d{1,2}/\d{1,2}/\d{4}$#', $value)) {
            try {
                $d = Carbon::createFromFormat('!d/m/Y', $value);
                return $d ?: null;
            } catch (\Throwable $e) {
                return null;
            }
        }

        // Everything else (ISO Y-m-d, Y-m-d H:i:s, …) — never let it throw.
        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
