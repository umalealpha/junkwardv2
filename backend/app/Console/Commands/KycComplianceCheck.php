<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use AlphaDirect\Mail\KycComplianceEmail;
use AlphaDirect\EmailSMSLogs;
use AlphaDirect\Models\CronStatus;
use Carbon\Carbon;

/**
 * Rewritten KYC compliance check — sends emails to customers with missing docs.
 *
 * Reads rules dynamically from kyc_compliance table (no hardcoded customer IDs).
 * For each product:
 *   1. Loads the compliance rule fields (check=1 mandatory, check=2 mandatory+alt)
 *   2. Finds active-policy customers whose required docs are NULL (not uploaded)
 *   3. Sends KYC compliance email with list of missing documents
 *
 * Scope: MIS products only (1-6, 9, 10). V8's equivalent
 * KycComplianceCheck.php cron iterates every product with a
 * kyc_compliance config, which incidentally covers MIS but also misc
 * legacy products. V2 ports the user's explicit MIS scope so DOM/COM
 * (7,8) and Specialist (16-19) are never touched by this cron — they
 * have their own KYC flows (RekycCampaigns, AdGroupKyc*).
 */
class KycComplianceCheck extends Command
{
    /** MIS product ids the V8 customer_kyc compliance cron applies to. */
    const MIS_PRODUCT_IDS = [1, 2, 3, 4, 5, 6, 9, 10];

    protected $signature   = 'kyccompliancecheck:cron';
    protected $description = 'Check KYC compliance and email customers with missing documents (MIS products only)';

    public function handle()
    {
        $cron = new CronStatus();
        $cron->name  = 'kyccompliancecheck:cron';
        $cron->start = Carbon::now();
        $cron->save();

        Log::info('KYC compliance check cron started');

        // Pre-load field definitions
        $fieldMap = DB::table('kyc_fields')->pluck('customer_kyc_column', 'name')->toArray();

        // Load products with compliance rules — MIS scope only (1-6, 9, 10).
        $products = DB::table('products')
            ->join('kyc_compliance', 'kyc_compliance.id', '=', 'products.kyc_compliance')
            ->whereNotNull('products.kyc_compliance')
            ->where('products.kyc_compliance', '>', 0)
            ->whereIn('products.id', self::MIS_PRODUCT_IDS)
            ->select('products.id as product_id', 'products.name as product_name', 'kyc_compliance.fields', 'kyc_compliance.flow_id')
            ->get();

        $totalEmails = 0;

        foreach ($products as $product) {
            $fieldsData = json_decode($product->fields, true);
            if (empty($fieldsData)) continue;

            // Build required column list (only check=1 and check=2)
            $requiredColumns = [];
            $missingDocNames = [];

            foreach ($fieldsData as $field) {
                $check = (int) ($field['check'] ?? 3);
                if ($check === 3) continue; // Optional

                $column = $fieldMap[$field['field'] ?? ''] ?? null;
                if (!$column) continue;

                $requiredColumns[] = $column;
                $missingDocNames[$column] = $field['field'];

                // For check=2, also track the alternative
                if ($check === 2 && !empty($field['other'])) {
                    $altColumn = $fieldMap[$field['other']] ?? null;
                    if ($altColumn) {
                        $requiredColumns[] = $altColumn;
                        $missingDocNames[$altColumn] = $field['other'];
                    }
                }
            }

            if (empty($requiredColumns)) continue;

            // Find customers with active policies who have NULL docs
            $query = DB::table('policies')
                ->join('customer', 'customer.id', '=', 'policies.customer_id')
                ->join('customer_kyc', 'customer_kyc.customer_id', '=', 'customer.id')
                ->where('policies.product_id', $product->product_id)
                ->where('policies.status', 1)
                ->where('customer.email', '!=', '')
                ->whereNotNull('customer.email')
                ->groupBy('policies.customer_id')
                ->select([
                    'policies.id',
                    'policies.customer_id',
                    'policies.policyNumber',
                    'customer.firstName',
                    'customer.lastName',
                    'customer.email',
                ]);

            // Dedup: skip customers who already received a KYC compliance email
            // within their next_send_date window. EmailSMSLogs records
            // last_sent_date + next_send_date when an email is sent below
            // (line ~135). If next_send_date is still in the future, do not send again.
            $query->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('email_sms_logs')
                  ->whereColumn('email_sms_logs.customer_id', 'policies.customer_id')
                  ->where('email_sms_logs.log_type', 'email')
                  ->where('email_sms_logs.content_type', 'kyc_compliance_email')
                  ->whereDate('email_sms_logs.next_send_date', '>', DB::raw('CURDATE()'));
            });

            // Add where conditions: any required doc is NULL
            $query->where(function ($q) use ($requiredColumns) {
                foreach ($requiredColumns as $i => $col) {
                    if ($i === 0) {
                        $q->whereNull("customer_kyc.{$col}");
                    } else {
                        $q->orWhereNull("customer_kyc.{$col}");
                    }
                }
            });

            $customers = $query->get();

            $this->info("Product: {$product->product_name} — {$customers->count()} customers with missing docs");

            foreach ($customers as $data) {
                $dataArr = (array) $data;
                $dataArr['flow_id'] = $product->flow_id;
                $dataArr['product_name'] = $product->product_name;

                try {
                    $markdown = new KycComplianceEmail($dataArr);
                    $html = $markdown->render('Mail.KycComplianceEmailView', ['data' => $dataArr]);

                    event(new \AlphaDirect\Events\SendMail(
                        $data->email,
                        'Alphadirect | Upload documents for KYC completion',
                        '',
                        $html,
                        null,
                        []
                    ));

                    EmailSMSLogs::addLog([
                        'customer_id'    => $data->customer_id,
                        'log_type'       => 'email',
                        'content_type'   => 'kyc_compliance_email',
                        'last_sent_date' => Carbon::now()->format('Y-m-d'),
                        'next_send_date' => Carbon::now()->addDays(1)->format('Y-m-d'),
                    ]);

                    $totalEmails++;
                } catch (\Exception $e) {
                    Log::error("KYC email failed for customer {$data->customer_id}: " . $e->getMessage());
                }
            }
        }

        $this->info("Done. Sent {$totalEmails} KYC compliance emails.");
        Log::info("KYC compliance check done. Sent {$totalEmails} emails.");

        $cron->end = Carbon::now();
        $cron->save();
    }
}
