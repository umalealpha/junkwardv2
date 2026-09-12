<?php

namespace AlphaDirect\Exports;
use AlphaDirect\Claim;
use AlphaDirect\ClaimAccident;
use AlphaDirect\ClaimKeyLoss;
use AlphaDirect\Customer;
use AlphaDirect\Policy;
use AlphaDirect\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ClaimAsOnDateDataExport implements WithHeadings,WithMapping,FromCollection
{
    /**
     * @return \Illuminate\Support\Collection
     */

    use Exportable;
    private $headings = [
                                'Claim No',
                                'Policy #',
                                'Product Name',
                                'Insured Name',
                                'CountyCode',
                                'Date Of loss',
                                'Claim Status',
                                'Claim Status',
                                'Status Date',
                                'Att. Involved',
                                'PA Involved',
                                'Short Desc.',
                                'Reported Date',
                                'MaxTrans',
                                'Claim_SubStatus_na',
                                'Total Reserve',
                                'Total Payment',
                                'Balance',
                                'Loss Rpt Attach Y/N',
                                'Salvage/SubrogationReserve',
                                'Salvage/SubrogationPayment',
                                'Claims Allocated To',
    ];

    protected $filter;

    function __construct($filter) {

        $this->filter = $filter;
    }

    public function map($id): array
    {

        $claim = Claim::where('id',$id)->first();
        if($claim->id !=null){
            $claim_no = $claim->claim_number;
        }
        else{
            $claim_no = 'N\A';
        }
        if($claim->id !=null){
            $policy = Policy::where('id',$claim->policy_id)->first();
            if($policy)
            {
                $policy_number = $policy->policyNumber;
            }
            else{
                $policy_number = 'NA';
            }
        }
        else
        {
            $policy_number = 'NA';
        }
        if($claim->id !=null){
            $policy = Policy::where('id',$claim->policy_id)->first();
            if($policy)
            {
                $product = Product::where('id',$policy->product_id)->first();
                if($product)
                {
                    $product_name = $product->name;
                }
                else
                {
                    $product_name = 'NA';
                }
            }
            else
            {
                $product_name = 'NA';
            }
        }
        else
        {
            $product_name = 'NA';
        }
        if($claim->id !=null){
            $customer = Customer::where('id',$claim->customer_id)->first();
            if($customer)
            {
                $insured_name = $customer->firstName;
            }
            else
            {
                $insured_name = 'NA';
            }
        }
        else
        {
            $insured_name = 'NA';
        }
        if($claim->id !=null){
            $county_code = '-';
        }
        else
        {
            $county_code = 'NA';
        }
        if($claim->id !=null){
            $claim_key_loss = ClaimKeyLoss::where('claim_id',$claim->id)->first();
            if($claim_key_loss)
            {
                $date_of_loss = $claim_key_loss->date_of_loss;
            }
            else
            {
                $date_of_loss = 'NA';
            }
        }
        else
        {
            $date_of_loss = 'NA';
        }
        if($claim->id !=null){
            $claim_accident = ClaimAccident::where('claim_id',$claim->id)->first();
            if($claim_accident)
            {
                $claim_status = $claim_accident->claim_status;
            }
            else{
                $claim_status = 'NA';
            }
        }
        else{
            $claim_status = 'NA';
        }
        if($claim->id !=null){
            $claim_accident = ClaimAccident::where('claim_id',$claim->id)->first();
            if($claim_accident)
            {
                $claim_status1 = $claim_accident->claim_status;
            }
            else{
                $claim_status1 = 'NA';
            }
        }
        else{
            $claim_status1 = 'NA';
        }
        if($claim->id !=null){
            $status_date = $claim->created_at;
        }
        else
        {
            $status_date = 'NA';
        }
        if($claim->id !=null){
            $att_involved = '-';
        }
        else
        {
            $att_involved = 'NA';
        }
        if($claim->id !=null){
            $pa_involved = '-';
        }
        else
        {
            $pa_involved = 'NA';
        }
        if($claim->id !=null){
            $short_desc = '-';
        }
        else
        {
            $short_desc = 'NA';
        }
        if($claim->id !=null){
            $reported_date = '-';
        }
        else
        {
            $reported_date = 'NA';
        }
        if($claim->id !=null){
            $max_trans = '-';
        }
        else
        {
            $max_trans = 'NA';
        }
        if($claim->id !=null){
            $claim_accident = ClaimAccident::where('claim_id',$claim->id)->first();
            if($claim_accident)
            {
                $claim_sub_status_na = $claim_accident->claim_sub_status;
            }
            else
            {
                $claim_sub_status_na = 'NA';
            }
        }
        else
        {
            $claim_sub_status_na = 'NA';
        }
        if($claim->id !=null){
            $total_reserve = '-';
        }
        else
        {
            $total_reserve = 'NA';
        }
        if($claim->id !=null){
            $total_payment = '-';
        }
        else
        {
            $total_payment = 'NA';
        }
        if($claim->id !=null){
            $balance = '-';
        }
        else
        {
            $balance = 'NA';
        }
        if($claim->id !=null){
            $loss_rpt_attach_y_or_n = '-';
        }
        else
        {
            $loss_rpt_attach_y_or_n = 'NA';
        }
        if($claim->id !=null){
            $salvage_subrogation_reserve = '-';
        }
        else
        {
            $salvage_subrogation_reserve = 'NA';
        }
        if($claim->id !=null){
            $salvage_subrogation_payment = '-';
        }
        else
        {
            $salvage_subrogation_payment = 'NA';
        }
        if($claim->id !=null){
            $claim_accident = ClaimAccident::where('claim_id',$claim->id)->first();
            if($claim_accident)
            {
                $claims_allocated_to = $claim_accident->claim_allocated_to;
            }
            else
            {
                $claims_allocated_to = 'NA';
            }
        }
        else
        {
            $claims_allocated_to = 'NA';
        }
        return [
            $claim_no,
            $policy_number,
            $product_name,
            $insured_name,
            $county_code,
            $date_of_loss,
            $claim_status,
            $claim_status1,
            $status_date,
            $att_involved,
            $pa_involved,
            $short_desc,
            $reported_date,
            $max_trans,
            $claim_sub_status_na,
            $total_reserve,
            $total_payment,
            $balance,
            $loss_rpt_attach_y_or_n,
            $salvage_subrogation_reserve,
            $salvage_subrogation_payment,
            $claims_allocated_to,

        ];

    }

    public function collection()
    {
        $query = Claim::orderBy('created_at', 'DESC');
        if ($this->filter['filterDateFrom'] != '-1' && $this->filter['filterDateto'] != '-1')
        {
            $query->whereBetween(DB::raw('date(created_at)') , [Carbon::parse($this->filter['filterDateFrom'])
                ->format('Y-m-d') , Carbon::parse($this->filter['filterDateto'])
                ->format('Y-m-d') ]);
        }elseif ($this->filter['filterDateFrom'] != '-1' && $this->filter['filterDateto'] == '-1'){
            //Carbon::parse('today')
            $query->whereBetween(DB::raw('date(created_at)') , [Carbon::parse($this->filter['filterDateFrom'])
                ->format('Y-m-d') , Carbon::parse('today')
                ->format('Y-m-d') ]);
        }
        $query = $query->get();
        return $query->pluck('id');
    }
    public function headings() : array
    {
        return $this->headings;
    }
}
