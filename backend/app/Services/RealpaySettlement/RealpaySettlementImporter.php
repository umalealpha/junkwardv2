<?php

namespace AlphaDirect\Services\RealpaySettlement;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use AlphaDirect\Policy;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\RealpayClientContracts;
use AlphaDirect\Http\Controllers\Admin\RealPayController;
use AlphaDirect\Http\Controllers\Admin\PolicyController;

/**
 * Shared engine for the RealPay settlement Excel import. Used by BOTH the CLI
 * command (realpay:import-settlement) and the queued job behind the UI screen,
 * so preview (dry-run) and commit run byte-identical logic.
 *
 * Per row: resolve policy -> fetch live contract(s) + embedded installments
 * from RealPay -> store contract if missing (idempotent) -> match the row to an
 * installment by date+amount -> insert payment_transactions only if that
 * InstalmentReferenceNumber isn't already present. All side-effects suppressed.
 * See ImportRealpaySettlementExcel command docblock for the full contract.
 */
class RealpaySettlementImporter
{
    /** Column header (normalised: lowercased, non-alnum stripped) => canonical key. */
    private array $headerMap = [
        'realpayclientnumber'  => 'client_number',
        'graphitepolicynumber' => 'policy_number',
        'contractnumber'       => 'contract_number',
        'merchantnumber'       => 'merchant_number',
        'productcode'          => 'product_code',
        'amount'               => 'amount',
        'numberofinstallments' => 'installments',
        'date'                 => 'date',
        'status'               => 'status',
    ];

    public array $stats = [
        'rows' => 0, 'policy_not_found' => 0, 'no_realpay_contract' => 0,
        'contract_stored' => 0, 'contract_existing' => 0,
        'no_matching_installment' => 0, 'tx_stored' => 0, 'tx_existing' => 0,
        'errors' => 0,
    ];

    private RealPayController $rp;
    private PolicyController $policyC;

    public function __construct()
    {
        $this->rp = new RealPayController();
        $this->policyC = new PolicyController();
    }

    /**
     * @param string        $filePath Absolute path to the .xlsx
     * @param bool          $commit   false = dry-run (no DB writes)
     * @param callable|null $onRow    fn(string $level, string $rowRef, string $msg): void
     * @return array{error?:string} the stats array (or ['error'=>...] on a fatal read failure)
     */
    public function run(string $filePath, bool $commit, int $limit = 0, int $offset = 0, ?callable $onRow = null): array
    {
        $log = function (string $level, string $rowRef, string $msg) use ($onRow) {
            Log::info('[realpay-settlement-import] ' . $rowRef . ' — ' . $msg);
            if ($onRow) $onRow($level, $rowRef, $msg);
        };

        $rows = $this->readRows($filePath);
        if (!is_array($rows)) {
            return ['error' => $rows]; // readRows returns an error string on failure
        }

        $i = 0;
        foreach ($rows as $r) {
            $i++;
            if ($offset > 0 && $i <= $offset) continue;
            if ($limit > 0 && $this->stats['rows'] >= $limit) break;
            $this->stats['rows']++;

            $policyNumber = trim((string) ($r['policy_number'] ?? ''));
            $contractNo   = trim((string) ($r['contract_number'] ?? ''));
            $rowRef       = "row {$i} [{$policyNumber} / {$contractNo}]";

            try {
                $this->processRow($r, $policyNumber, $contractNo, $rowRef, $commit, $log);
            } catch (\Throwable $e) {
                $this->stats['errors']++;
                $log('error', $rowRef, 'ERROR: ' . $e->getMessage());
            }
        }

        return $this->stats;
    }

    private function processRow(array $r, string $policyNumber, string $contractNo, string $rowRef, bool $commit, callable $log): void
    {
        $policy = Policy::where('policyNumber', $policyNumber)->first();
        if (!$policy) {
            $this->stats['policy_not_found']++;
            $log('warn', $rowRef, 'policy not found on Graphite — skipped');
            return;
        }

        $contracts = $this->rp->getExistingRealpayContract($policy->id, $policy->policyNumber);
        if (!is_array($contracts) || empty($contracts)) {
            $this->stats['no_realpay_contract']++;
            $log('warn', $rowRef, 'no ACTIVE contract on RealPay portal — skipped (cannot resolve installment reference)');
            return;
        }

        // (a) store contract if not already present locally
        $contractExists = RealpayClientContracts::where('policy_id', $policy->id)
            ->where('contract_number', $contractNo)->exists();
        if ($contractExists) {
            $this->stats['contract_existing']++;
            $log('line', $rowRef, 'contract already in DB — not re-storing');
        } else {
            if ($commit) {
                $synced = $this->rp->fetchAndStoreRealpayContract($policy, $contracts);
                $log('info', $rowRef, "stored contract + installments (synced {$synced})");
            } else {
                $log('line', $rowRef, 'WOULD store contract + installments');
            }
            $this->stats['contract_stored']++;
        }

        // (b) match this row to an installment by date + amount
        $rowDate   = $this->parseDate($r['date'] ?? null);
        $rowAmount = round((float) ($r['amount'] ?? 0), 2);
        $match = $this->findInstallment($contracts, $rowDate, $rowAmount);
        if (!$match) {
            $this->stats['no_matching_installment']++;
            $log('warn', $rowRef, "no installment matched date={$rowDate} amount={$rowAmount} — transaction skipped");
            return;
        }

        $ref = $match['InstalmentReferenceNumber'] ?? null;
        if (!$ref) {
            $this->stats['no_matching_installment']++;
            $log('warn', $rowRef, 'matched installment has no InstalmentReferenceNumber — skipped');
            return;
        }

        // (c) store transaction only if not already present (dedup on referenceNumber)
        if (PaymentTransaction::where('referenceNumber', $ref)->exists()) {
            $this->stats['tx_existing']++;
            $log('line', $rowRef, "transaction already present (ref {$ref}) — skipped");
            return;
        }

        $status = $this->mapStatus($match['InstalmentStatus'] ?? '');
        if ($commit) {
            $this->storeTransaction($match, $policy);
            $log('info', $rowRef, "stored transaction ref {$ref} status {$status}");
        } else {
            $log('line', $rowRef, "WOULD store transaction ref {$ref} status {$status}");
        }
        $this->stats['tx_stored']++;
    }

    /** Insert one payment_transactions row with all model side-effects suppressed. */
    private function storeTransaction(array $inst, Policy $policy): void
    {
        $status  = $this->mapStatus($inst['InstalmentStatus'] ?? '');
        $payDate = $this->parseDate($inst['InstalmentActionDate'] ?? null);
        $payload = [
            'policyNumber'            => $policy->policyNumber,
            'policy_id'               => $policy->id,
            'referenceNumber'         => $inst['InstalmentReferenceNumber'] ?? null,
            'amount'                  => $inst['InstalmentAmount'] ?? null,
            'status'                  => $status,
            'paymentDate'             => $payDate,
            'new_payment_date'        => $payDate,
            'paymentMethod'           => 'RealPay',
            'numberOfInstalmentsPaid' => 1,
            'note'                    => 'Settlement Excel import ' . $status,
            'send_sms_email'          => 0,
        ];

        PaymentTransaction::withoutEvents(function () use ($payload) {
            $this->policyC->updatePaymentTransactions($payload);
        });
    }

    private function findInstallment(array $contracts, ?string $date, float $amount): ?array
    {
        foreach ($contracts as $c) {
            foreach (($c['ContractInstalments'] ?? []) as $inst) {
                $instDate   = $this->parseDate($inst['InstalmentActionDate'] ?? null);
                $instAmount = round((float) ($inst['InstalmentAmount'] ?? 0), 2);
                if ($date !== null && $instDate === $date && abs($instAmount - $amount) < 0.005) {
                    return $inst;
                }
            }
        }
        return null;
    }

    public function mapStatus(string $s): string
    {
        return match (strtoupper(trim($s))) {
            'S' => 'SUCCESS',
            'F' => 'FAILED',
            'E' => 'ERROR',
            'I' => 'CANCELLED',
            default => strtoupper(trim($s)) ?: 'UNKNOWN',
        };
    }

    public function parseDate($value): ?string
    {
        if ($value === null || $value === '') return null;
        if (is_numeric($value)) {
            try { return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->format('Y-m-d'); }
            catch (\Throwable $e) { /* fall through */ }
        }
        foreach (['d-m-Y H:i', 'd-m-Y', 'Y-m-d H:i:s', 'Y-m-d', 'd/m/Y H:i', 'd/m/Y'] as $fmt) {
            $d = \DateTime::createFromFormat($fmt, trim((string) $value));
            if ($d !== false) return Carbon::instance($d)->format('Y-m-d');
        }
        try { return Carbon::parse($value)->format('Y-m-d'); } catch (\Throwable $e) { return null; }
    }

    /** @return array<int,array<string,mixed>>|string rows, or an error message string */
    public function readRows(string $path)
    {
        if (!is_file($path) || !is_readable($path)) {
            return "File not found or not readable: {$path}";
        }
        try {
            $sheet = IOFactory::load($path)->getActiveSheet();
        } catch (\Throwable $e) {
            return 'Could not read spreadsheet: ' . $e->getMessage();
        }
        $raw = $sheet->toArray(null, true, true, false);
        if (empty($raw)) return 'Spreadsheet is empty.';

        $header = array_shift($raw);
        $colKey = [];
        foreach ($header as $idx => $label) {
            $norm = preg_replace('/[^a-z0-9]/', '', strtolower((string) $label));
            if (isset($this->headerMap[$norm])) $colKey[$idx] = $this->headerMap[$norm];
        }
        $missing = array_diff(['policy_number', 'contract_number', 'amount', 'date'], array_values($colKey));
        if ($missing) {
            return 'Missing required columns: ' . implode(', ', $missing)
                . '. Found headers: ' . implode(' | ', array_filter($header));
        }

        $rows = [];
        foreach ($raw as $line) {
            $rec = [];
            foreach ($colKey as $idx => $key) $rec[$key] = $line[$idx] ?? null;
            if (trim((string) ($rec['policy_number'] ?? '')) === '') continue;
            $rows[] = $rec;
        }
        return $rows;
    }
}
