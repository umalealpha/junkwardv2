<?php

namespace AlphaDirect\Exports;

use AlphaDirect\PolicyPaymentStatusDump;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PolicyPaymentStatusDumpExport implements WithHeadings,WithMapping,FromCollection
{
    /**
    * @return \Illuminate\Support\Collection
    */

    use Exportable;

    private $headings = [
        'PolicyNumber',
        'Premium',
        'Premium_freq',
        'Total prem due on customer',
        'Total payment paid by customer',
        'Balance',
        'Failed tx count',
        'Policy status',
    ];

    protected $filter;

    function __construct($filter) {
        $this->filter = $filter;
    }


    public function map($data): array
    {
        $premium_frequency = '-';
        if($data->premium_freq == 1 || $data->premium_freq == null){
            $premium_frequency = 'Monthly';
        }
        if($data->premium_freq == 2){
            $premium_frequency = '3 Installment';
        }
        if($data->premium_freq == 3){
            $premium_frequency = 'Annual';
        }

        $status = '-';
        if ($data->policy_status == 1) {
            $status = 'Activated';
        } elseif ($data->policy_status == 2) {
            $status = 'Cancel';
        } else {
            $status = 'Deactivated';
        }
        return [
            $data->policyNumber,
            $data->premium,
            $premium_frequency,
            $data->total_prem_due_customer,
            $data->total_payment_paid_by_cust,
            $data->balance,
            $data->failed_tx_count,
            $status,
        ];
    }
//    public function query()
//    {
//        return PolicyPaymentStatusDump::query();
//    }

    public function collection()
    {

        $query = PolicyPaymentStatusDump::latest();
        if($this->filter['policyStatus_filter'] != '-1'){
            $query->where('policy_status',$this->filter['policyStatus_filter']);

        }
        if($this->filter['balance_filter'] != '-1'){
            if($this->filter['balance_filter'] == 0){
                $query->where('balance','>',0);
            }
            if($this->filter['balance_filter'] == 1){
                $query->where('balance','<',0);
            }
            if($this->filter['balance_filter'] == 2){
                $query->where('balance','=',0);
            }
        }
        return $query->get();
    }
    public function headings() : array
    {
        return $this->headings;
    }
}
