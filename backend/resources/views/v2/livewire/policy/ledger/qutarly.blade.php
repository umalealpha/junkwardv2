@for ($i = 1; $i < 4; $i++)

<tr style="border-top: 1px solid #c0d4ec!important;border-bottom: 1px solid #c0d4ec!important;">
@if ($i == 1)
    <td class="yearly">{{  Carbon::now()->format('F d , Y') }}</td>
    @else
    <td class="yearly">{{  Carbon::now()->addMonths($i-1)->format('F d , Y') }}</td>
    @endif
    <td class="yearly">{{ $policyData->policyNumber }}-00{{ $i }}</td>
    <td class="yearly">P {{number_format(5000000 ?? "", 2, '.', ',')}}
    </td>
    <td class="yearly">#00000-0000-000{{ $i }}</td>
    <td class="yearly"></td>
    <td class="yearly">P -{{number_format(5000000 ?? "", 2, '.', ',')}}</td>
</tr>
<tr style="border-top: 1px solid #c0d4ec!important;border-bottom: 1px solid #c0d4ec!important;">
    @if ($i == 1)
    <td class="yearly">{{  Carbon::now()->format('F d , Y') }}</td>
    @else
    <td class="yearly">{{  Carbon::now()->addMonths($i-1)->format('F d , Y') }}</td>
    @endif
    
    <td class="yearly">{{ $policyData->policyNumber }}-00{{ $i }}</td>
    <td class="yearly">
    </td>
    <td class="yearly">Payment</td>
    <td class="yearly">P {{number_format(5000000 ?? "", 2, '.', ',')}}</td>
    <td class="yearly">P 0.00</td>    
</tr>
@endfor