<?php

namespace AlphaDirect\Exports;

use AlphaDirect\Helpers\PiiMask;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithBatchInserts;

class CustomerBankingReportExport implements FromQuery, WithHeadings, WithMapping, WithChunkReading, WithBatchInserts
{
    use Exportable;

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    public function query()
    {
        return DB::table('customer_banking as cb')
            ->select([
                'cb.accountNumber',
                'banks.bank_name',
                'bankBranches.name AS branch_name',
                'c.id AS customer_id',
                DB::raw("CONCAT(c.firstName, ' ', c.lastName) AS customer_name"),
                'c.cellphone',
                'c.email',
                'p.policyNumber',
                'p.status AS policy_status_raw',
                DB::raw('NULL AS last_transaction_amount'),
                DB::raw('NULL AS last_transaction_status'),
                DB::raw('NULL AS last_transaction_date')
            ])
            ->leftJoin('policies as p', 'p.id', '=', 'cb.policy_id')
            ->leftJoin('customer as c', 'p.customer_id', '=', 'c.id')
            ->leftJoin('bankBranches', 'cb.branchCode', '=', 'bankBranches.branch_id')
            ->leftJoin('banks', 'banks.bank_number', '=', 'cb.bankName')
            ->where('cb.billing', 'realpay')
            ->whereNotNull('cb.accountNumber')
            ->where('cb.accountNumber', '!=', '')
            ->whereIn('p.status', [0, 1])
            ->orderBy('cb.accountNumber')
            ->orderBy('p.policyNumber');
    }

    /**
     * @return int
     */
    public function chunkSize(): int
    {
        return 500; // Process 500 records at a time
    }

    /**
     * @return int
     */
    public function batchSize(): int
    {
        return 500; // Insert 500 records at a time
    }

    /**
     * Get policy status text
     */
    private function getPolicyStatus($status)
    {
        switch ($status) {
            case 1:
                return 'Active';
            case 0:
                return 'Deactive';
            default:
                return 'Unknown';
        }
    }

    /**
     * Get transaction status text
     */
    private function getTransactionStatus($status)
    {
        if ($status == 1 || $status == 'success'||$status == 'SUCCESS'||$status == 'Success') {
            return 'Success';
        }
        return 'Failed';
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Account Number',
            'Bank Name',
            'Branch Name',
            'Customer ID',
            'Customer Name',
            'Cellphone',
            'Email',
            'Policy Number',
            'Policy Status',
            'Last Transaction Amount',
            'Last Transaction Status',
            'Last Transaction Date'
        ];
    }

    /**
     * @param mixed $row
     * @return array
     */
    public function map($row): array
    {
        // Get last transaction data for this policy
        $lastTransaction = $this->getLastTransactionForPolicy($row->policyNumber);
        
        return [
            PiiMask::ifBankAccount($row->accountNumber),
            PiiMask::ifBankName($row->bank_name ?? 'N/A'),
            PiiMask::ifBranchCode($row->branch_name ?? 'N/A'),
            $row->customer_id ?? 'N/A',
            $row->customer_name ?? 'N/A',
            $row->cellphone ?? 'N/A',
            PiiMask::ifEmail($row->email ?? 'N/A'),
            $row->policyNumber ?? 'N/A',
            $this->getPolicyStatus($row->policy_status_raw),
            $lastTransaction ? $lastTransaction->amount : 'N/A',
            $lastTransaction ? $this->getTransactionStatus($lastTransaction->status) : 'N/A',
            $lastTransaction ? \Carbon\Carbon::parse($lastTransaction->created_at)->format('Y-m-d H:i:s') : 'N/A'
        ];
    }

    /**
     * Cache for last transactions to avoid repeated queries
     */
    private $lastTransactionCache = [];

    /**
     * Get last transaction for a policy number
     */
    private function getLastTransactionForPolicy($policyNumber)
    {
        if (!$policyNumber) {
            return null;
        }

        // Check cache first
        if (isset($this->lastTransactionCache[$policyNumber])) {
            return $this->lastTransactionCache[$policyNumber];
        }

        // Query database for last transaction
        $lastTransaction = DB::table('payment_transactions')
            ->select(['amount', 'status', 'created_at'])
            ->where('policyNumber', $policyNumber)
            ->orderBy('id', 'desc')
            ->first();

        // Cache the result
        $this->lastTransactionCache[$policyNumber] = $lastTransaction;

        return $lastTransaction;
    }
}