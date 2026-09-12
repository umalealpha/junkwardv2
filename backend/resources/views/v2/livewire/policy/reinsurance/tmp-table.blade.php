<div>           
    <table class="table table-striped table-responsive" style="overflow-y: auto;display:block;">
        <thead>
            <tr>
                <th>Risk Id</th>
                <th>Group Name</th>
                <th>Total Sum Insured</th>      
                <td>Total Premium</td>
                <td>NETRETENTION</td>
                <td>NETRETENTION SI</td>
                <!-- <td>QUOTASHARING</td>
                <td>QUOTASHARING SI</td> -->
                <td>QUOTESHARE</td>
                <td>QUOTESHARE SI</td>
                <td>SURPLUS</td>
                <td>SURPLUS SI</td>
                <td>AUTO FACULTATIVE</td>
                <td>AUTO FACULTATIVE SI</td>
                <td>FACULTATIVE PLACEMENT</td>
                <td>FACULTATIVE PLACEMENT SI</td>
                 {{-- <td>EXCESS OF LOSS</td>
                <td>EXCESS OF LOSS SI</td>
                 <td>EXCESS OF LOSS QUOTA</td>
                <td>EXCESS OF LOSS QUOTA SI</td> --}}
                <td>Action</td>
            </tr>
        </thead>            
        <tbody>                                                      
            
            @if(isset($this->Reinsurance))
            
            @foreach($this->Reinsurance as $index => $Reinsurance)
            <tr>
                <td>{!! $Reinsurance->address_name !!}</td>
                <td>{!! $Reinsurance->group_code !!}</td>
                <td>P {!! number_format((float)$Reinsurance->totalSumInsured, 2, '.', ',') !!}</td>
                <td>P {!! number_format((float)$Reinsurance->totalPremium, 2, '.', ',') !!}</td>
                <td>{!! isset($Reinsurance->NETRETENTION) ? ($Reinsurance->NETRETENTION < 0 ? "P 0.00" : "P ".number_format((float)$Reinsurance->NETRETENTION, 2, '.', ',')) : "P 0.00";
                    !!}</td>
                <td>{!! isset($Reinsurance->NETRETENTION_SI) ? ($Reinsurance->NETRETENTION_SI < 0 ? "P 0.00" : "P ".number_format((float)$Reinsurance->NETRETENTION_SI, 2, '.', ',')) : "P 0.00";
                    !!}</td>
                <td>{!! isset($Reinsurance->QUOTASHARING) ? ($Reinsurance->QUOTASHARING < 0 ? "P 0.00" : "P ".number_format(floor($Reinsurance->QUOTASHARING * 100) / 100, 2, '.', ',')) : "P 0.00";
                    !!}</td>
                <td>{!! isset($Reinsurance->QUOTASHARING_SI) ? ($Reinsurance->QUOTASHARING_SI < 0 ? "P 0.00" : "P ".number_format((float)$Reinsurance->QUOTASHARING_SI, 2, '.', ',')) : "P 0.00";
                    !!}</td>
                <td>{!! isset($Reinsurance->SURPLUS) ? ($Reinsurance->SURPLUS < 0 ? "P 0.00" : "P ".number_format((float)$Reinsurance->SURPLUS, 2, '.', ',')) : "P 0.00";
                    !!}</td>
                 <td>{!! isset($Reinsurance->SURPLUS_SI) ? ($Reinsurance->SURPLUS_SI < 0 ? "P 0.00" : "P ".number_format((float)$Reinsurance->SURPLUS_SI, 2, '.', ',')) : "P 0.00";
                    !!}</td>
                <td>{!! isset($Reinsurance->FACULTATIVE) ? ($Reinsurance->FACULTATIVE < 0 ? "P 0.00" : "P ".number_format((float)$Reinsurance->FACULTATIVE, 2, '.', ',')) : "P 0.00";
                        !!}</td>
                <td>{!! isset($Reinsurance->FACULTATIVE_SI) ? ($Reinsurance->FACULTATIVE_SI < 0 ? "P 0.00" : "P ".number_format((float)$Reinsurance->FACULTATIVE_SI, 2, '.', ',')) : "P 0.00";
                        !!}</td>
                <td>{!! isset($Reinsurance->FACULATIVEPLACEMENT) ? ($Reinsurance->FACULATIVEPLACEMENT < 0 ? "P 0.00" : "P ".number_format((float)$Reinsurance->FACULATIVEPLACEMENT, 2, '.', ',')) : "P 0.00";
                    !!}</td>
                <td>{!! isset($Reinsurance->FACULATIVEPLACEMENT_SI) ? ($Reinsurance->FACULATIVEPLACEMENT_SI < 0 ? "P 0.00" : "P ".number_format((float)$Reinsurance->FACULATIVEPLACEMENT_SI, 2, '.', ',')) : "P 0.00";
                    !!}</td>
               {{-- <td>{!! isset($Reinsurance->EXCESSOFLOSS) ? ($Reinsurance->EXCESSOFLOSS < 0 ? "P 0.00" : "P ".number_format((float)$Reinsurance->EXCESSOFLOSS, 2, '.', ',')) : "P 0.00";
                    !!}</td>
                <td>{!! isset($Reinsurance->EXCESSOFLOSS_SI) ? ($Reinsurance->EXCESSOFLOSS_SI < 0 ? "P 0.00" : "P ".number_format((float)$Reinsurance->EXCESSOFLOSS_SI, 2, '.', ',')) : "P 0.00";
                    !!}</td> 
                <td>{!! isset($Reinsurance->EXCESSOFLOSS_QUOTA) ? ($Reinsurance->EXCESSOFLOSS_QUOTA < 0 ? "P 0.00" : "P ".number_format((float)$Reinsurance->EXCESSOFLOSS_QUOTA, 2, '.', ',')) : "P 0.00";
                    !!}</td>
                <td>{!! isset($Reinsurance->EXCESSOFLOSS_QUOTA_SI) ? ($Reinsurance->EXCESSOFLOSS_QUOTA_SI < 0 ? "P 0.00" : "P ".number_format((float)$Reinsurance->EXCESSOFLOSS_QUOTA_SI, 2, '.', ',')) : "P 0.00";
                    !!}</td> 
                 --}}
                 <!-- <td>
                     <a href="?id={{ $Reinsurance->id }}&tab=10">
                        <input type="button" class="btn btn-info" value="Edit">
                    </a>                                                
                </td>  -->
                <td>
                <!-- <a href="{{ route('admin.policy.reinsuranceSlip', ['policyId'=>$this->policy_id, 'actionId'=>$this->action_id, 'groupId'=>$Reinsurance->group_id]) }}" 
                target="_blank"  id="reinsuranceSlip" class="btn btn-sm btn-info align-self-center" 
                style="margin-left:65%;margin-bottom: 0%">Reinsurance Slip </a>  -->

                </td>
            </tr>
            @endforeach
            @endif
        </tbody>
    </table>
</div>