<style>
    .sorting {
        color: #646c9a !important;
        vertical-align: middle;
        padding: 10px 18px !important;
        font-weight: 700 !important;
        box-sizing: content-box;
    }
    .table-bordered th, .table-bordered td {
        border: 1px solid #ebedf2 !important;
    }
    .odd td{
        color: #646c9a !important;
        vertical-align: middle;
        padding: 10px 18px !important;
        font-weight: 500 !important;
        box-sizing: content-box;
    }
</style>
@can('policy_unissue')
<a class="btn btn-primary" id="add_invoice" style="color:#fff;margin-left: 710px;">Add Invoice</a>
@endcan
<br/><br/><br/>

<br/><br/><br/>
<table class="table table-striped table-bordered table-hover table-checkable dataTable no-footer" id="account_table" role="grid" aria-describedby="account_table_info" style="width: 1104px;">
   <thead>
      <tr role="row">
        <th class="sorting" >INVOICE DT.</th>
        <th class="sorting"  rowspan="1" colspan="1" >INVOICE NO.</th>
        <th class="sorting"  rowspan="1" colspan="1" >PREMIUM</th>
        <th class="sorting"  rowspan="1" colspan="1" >OTHER CHARGES</th>
        <th class="sorting"  rowspan="1" colspan="1" >DUE AMOUNT</th>
        <th class="sorting"  rowspan="1" colspan="1" >Credit Amount</th>
        <th class="sorting"  rowspan="1" colspan="1" >STATUS</th>
        <th class="sorting"  rowspan="1" colspan="1" >Action</th>

        <!-- <th class="sorting"  rowspan="1" colspan="1" >STATUS</th> -->
      </tr>
   </thead>
   <tbody>
        @php
        $finalTotal = 0;
        @endphp
        @foreach ($this->ledger as $ledger)
        @php
            $finalTotal = $finalTotal + $ledger->invoice_amount; 
            $invoiceRefNumber='';  $rFlag = 0;
            $credit_note = \AlphaDirect\Models\CreditNote::where('invoice_id',$ledger->id)->first();

           
            if($ledger->trans_type!='Invoice'){
                $credit_note_file = \AlphaDirect\Models\CreditNote::where('credit_note_no',$ledger->trans_ref)->first();
                if(isset($credit_note_file) && !empty($credit_note_file)){
                $invoiceRefNumber =  \AlphaDirect\Ledger::where('id', $credit_note_file->invoice_id)->value('invoice_no');
                }
            }
            if($ledger->trans_type=='Invoice' && isset($credit_note->invoice_id) && $ledger->id == $credit_note->invoice_id){
            $finalTotal = $finalTotal - $ledger->invoice_amount; 
            }
            $checkDate  = config('constants.policy.restrictionDate');
            $policyDate = Carbon::parse($ledger->invoice_date)->format('Y-m-d');
            if (strtotime($policyDate) > strtotime($checkDate)) {
                $rFlag = 1;
            }

        @endphp
        <tr role="row" class="odd">
            <td>@if ($ledger->trans_type == 'Invoice')
                            {{ Carbon::parse($ledger->invoice_date)->format('d-m-Y') }}
                            @elseif ($ledger->trans_type == 'Payment')
                            {{ Carbon::parse($ledger->accounting_date)->format('d-m-Y') }}
                            @else
                            {{ Carbon::parse($ledger->invoice_date)->format('d-m-Y') }}
            @endif</td>
            <td>@if($ledger->trans_type!='Invoice' && isset($credit_note_file) && !empty($credit_note_file))<a  href="{!! \AlphaDirect\Helper::getCloudFrontURL($credit_note_file->credit_note_file) !!}" " target="_blank" >
            {{ $invoiceRefNumber.'_'.$ledger->trans_ref ?? "" }}
            </a>@else
            <a href="{{ route('admin.policy.getInvoiceforDomCom', $ledger->id) }}"> {{ $ledger->invoice_no ?? "" }}</a>
            @endif</td>
            <td>P {{number_format((float)$ledger->invoice_amount ?? "", 2, '.', ',')}}</td>
            <td>P 0.00</td>
            <td>@if((isset($credit_note->invoice_id) && $ledger->id == $credit_note->invoice_id)) P 0.00 @else P {{number_format((float)$ledger->due_amount ?? "", 2, '.', ',')}}@endif</td>
            <td>@if($ledger->trans_type!='Invoice')P - {{number_format((float)$ledger->debit ?? "", 2, '.', ',')}}@endif</td>
            <td>  {{$ledger->status}}</td>
            <td> @can('policy_credit_note') @if($rFlag == 1) @if($ledger->trans_type=='Invoice')<a href="{{url('admin/policy/creditNoteView/'.$ledger->id) }} " target="_blank"  class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Creditnote">CR
            </a>@endif @endif @endcan</td>
            <!-- <td>PAID</td> -->
        </tr>        
        @endforeach
        <tr>
            <th class="sorting" colspan="4">Total </th>
            <th class="sorting" colspan="2">P {{number_format((float)$finalTotal ?? "", 2, '.', ',')}} </th>
        </tr>
   </tbody>
</table>
<a href="{{ route('admin.documents.accountStatementDomCom', ['id'=>$this->policy->id]) }}" target="_blank"  id="Statement" class="btn btn-sm btn-info align-self-center" style="margin-left:65%;margin-bottom: 0%">Account Statement</a>

<div class="modal" tabindex="-1" role="dialog" id="add_invoice_confirm">
        <div class="modal-dialog" role="document">
            <div class="modal-content" id="modal-add-invoice">
                <form action="{{ url('admin/policy/add_invoice_com_dom/'.$policyId) }}"
                    method="POST" autocomplete="off">
                    {{ csrf_field() }}
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">Add Invoice</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                      Please Select Invoice Date:
                      <input type="text" class="form-control kt_datepicker_1"
                        name="date"
                        value="" autocomplete="off"
                        placeholder="Select date" required />
                    </div>
                    <div class="modal-body">
                        Please Enter Amount:
                        <input type="text" x-mask:dynamic="$money($input)" class="form-control" name="invoice_amount" value="" required>
                    </div>
                    <div class="modal-body">
                        Please Enter Note:
                        <input type="text" class="form-control" name="description" value="" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary close" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" id="saveInvoice">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<script>
    jQuery(document).ready(function() {
        var restrictDate = "{{ config('constants.policy.restrictionDate') }}";
        $('#add_invoice').click(function() {
            $('#add_invoice_confirm').modal('show');
            return false;
        })
        $('.close').click(function() {
            $('#add_invoice_confirm').modal('hide');
            return false;
        })
        $('.kt_datepicker_1').datepicker('destroy'); // remove existing init
        $('.kt_datepicker_1').datepicker({
            todayHighlight: true,
            orientation: "bottom left",
            autoclose: true,
            format: 'yyyy-mm-dd',
           startDate: restrictDate,
        });
    });
</script>
    
