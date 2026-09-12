
<tr style="border-top: 1px solid #c0d4ec!important;border-bottom: 1px solid #c0d4ec!important;">
    <td class="yearly">{{  Carbon::now()->format('F d , Y') }}</td>
    <td class="yearly">{{ $policyData->policyNumber }}-001</td>
    <td class="yearly">P {{number_format(60000000 ?? "", 2, '.', ',')}}
    </td>
    <td class="yearly">#00000-0000-0000</td>
    <td class="yearly"></td>
    <td class="yearly">P -{{number_format(60000000 ?? "", 2, '.', ',')}}</td>
</tr>
<tr style="border-top: 1px solid #c0d4ec!important;border-bottom: 1px solid #c0d4ec!important;">
    <td class="yearly">{{  Carbon::now()->format('F d , Y') }}</td>
    <td class="yearly">{{ $policyData->policyNumber }}-001</td>
    <td class="yearly">
    </td>
    <td class="yearly">Payment</td>
    <td class="yearly">P {{number_format(60000000 ?? "", 2, '.', ',')}}</td>
    <td class="yearly">P 0.00</td>    
</tr>