<?php

namespace AlphaDirect\Exports;

use AlphaDirect\PaymentTransaction;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class UpdatedAmountTxLogListExport implements WithHeadings, WithMapping,FromCollection
{
    /**
    * @return \Illuminate\Support\Collection
    */
    use Exportable;
    private $headings = [
        'ID',
        'Policy Number',
        'Reference Number',
        'Amount',
        'Payment Date',
        'Payment Method'
    ];

    protected $filter;

    function __construct($transactionList) {
        $this->transactionList = $transactionList;
    }

    public function map($id): array
    {
        $transaction = PaymentTransaction::where('id',$id->id)->first();
        if(isset($transaction->id)){
            $id = $transaction->id;
        }
        else{
            $id = 'N\A';
        }

        if(isset($transaction->policyNumber)){
            $policyNumber = $transaction->policyNumber;
        }
        else{
            $policyNumber = 'N\A';
        }

        if(isset($transaction->referenceNumber)){
            $referenceNumber = $transaction->referenceNumber;
        }
        else{
            $referenceNumber = 'N\A';
        }

        if(isset($transaction->amount)){
            $amount = $transaction->amount;
        }
        else{
            $amount = 'N\A';
        }

        if(isset($transaction->paymentDate)){
            $paymentDate = $transaction->paymentDate;
        }
        else{
            $paymentDate = 'N\A';
        }

        if(isset($transaction->paymentMethod)){
            $paymentMethod = $transaction->paymentMethod;
        }
        else{
            $paymentMethod = 'N\A';
        }
        return [
            $id,
            $policyNumber,
            $referenceNumber,
            $amount,
            $paymentDate,
            $paymentMethod
        ];

    }

    public function collection()
    {
        $query = PaymentTransaction::whereIn('id',$this->transactionList)->orderBy('created_at', 'DESC');
        $query = $query->get();
        return $query;
    }
    public function headings() : array
    {
        return $this->headings;
    }
}
