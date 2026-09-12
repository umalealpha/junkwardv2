<div>
    <div class="row">
        <div class="col-sm-12 mb-3">
            <button class="btn btn-primary btn-sm m-1" wire:click="policyEarnedPremiumCalculations" >Earned Premium Calculations</button>
        </div>

        {{-- <div class="col-sm-12">
        <span style="color:black;">Policy Number : {{ $this->policy_details->policyNumber }}</span><br><hr><br>
        </div> --}}

        <div class="col-sm-5">
            <table style="width:100%">
                <tr>
                    <th>Policy #/Holder Name : </th>
                    <td>{{ $this->policy_details->policyNumber }} / {{ $this->policy_details->customer?->firstName ?? '-' }} {{ $this->policy_details->customer?->lastName ?? '-' }}</td>
                </tr>
                <tr>
                    <th>Term Start Date : </th>
                    <td>
                        @if(isset($this->policy_details?->term_start_date))
                        {{ Carbon\Carbon::parse($this->policy_details?->term_start_date)->format('d/m/Y') }}
                        @endif
                    </td>
                </tr>
                <tr>
                    <th>Transaction Eff. Dt. : </th>
                    <td>
                        @if(isset($this->earned_Premium?->end_date))
                        {{ Carbon\Carbon::parse($this->earned_Premium?->end_date)->format('d/m/Y') }}
                        @endif
                        {{-- {{ $this->earned_Premium?->end_date ?? '-' }} --}}
                    </td>
                </tr>
                <tr>
                    <th>Transaction Type : </th>
                    <td>{{ $this->action?->transaction_type ?? '-' }}</td>
                </tr>
                <tr>
                    <th>Last Updated Date : </th>
                    <td>
                        @if(isset($this->action?->updated_at))
                        {{ Carbon\Carbon::parse($this->action?->updated_at )->format('d/m/Y') }}
                        @endif

                        {{-- {{ $this->action?->updated_at ?? '-' }} --}}
                    </td>
                </tr>
                <tr>
                    <th>Premium : </th>
                    <td>{{ $this->action?->premium ?? '-' }}</td>
                </tr>
                <tr>
                    <th>Total Premium : </th>
                    <!-- <td>{{ $this->policy_details?->annual_premium ?? '-'  }}</td> -->
                    <td>{{ $this->action?->premium ?? '-'  }}</td>
                </tr>
                <tr>
                    <th>Total Claim : </th>
                    <td>{{ $this->claim_count ?? '-'  }}</td>
                </tr>
                <tr>
                    <th>Serv Rep : </th>
                    <td>{{ $this->policy_details->user?->firstName ?? '-'  }} {{ $this->policy_details->user?->lastName ?? '-'  }}</td>
                </tr>
                <tr>
                    <th>Transaction Note : </th>
                    <td>{{ $this->action?->transaction_reason ?? '-'}}</td>
                </tr>
            </table>
        </div>
        <div class="col-sm-2">
            <table style="width:100%">
            </table>
        </div>
        <div class="col-sm-5">
            <table style="width:100%">
                <tr>
                    <th>Renewal Plan :</th>
                    <td> @if ( $this->policy_details->premium_freq == 3)
                        {{ 'Yearly' }}
                        @elseif ( $this->policy_details->premium_freq == 2)
                        {{ '3 Installment' }}
                        @else
                        {{ 'Monthly' }}
                        @endif
                    </td>
                </tr>
                <tr>
                    <th>Term End Date :</th>
                    <td>
                        @if(isset($this->policy_details?->term_end_date))
                        {{ Carbon\Carbon::parse($this->policy_details?->term_end_date)->format('d/m/Y') }}
                        @endif
                    </td>
                </tr>
                <tr>
                    <th>Transaction Exp. Dt. :</th>
                    <td>
                        @if(isset($this->earned_Premium?->end_date))
                        {{ Carbon\Carbon::parse($this->earned_Premium?->end_date)->format('d/m/Y') }}
                        @endif
                    </td>
                </tr>
                <tr>
                    <th>Bill To : </th>
                    <td>{{ $this->policy_details->customer?->firstName ?? '-' }} {{ $this->policy_details->customer?->lastName ?? '-' }}</td>
                </tr>
                <tr>
                    <th>Last Updated By : </th>
                    <td>
                        @if(isset($this->policy_details?->term_end_date))
                        {{ Carbon\Carbon::parse($this->policy_details?->term_end_date)->format('d/m/Y') }}
                        @endif
                    </td>
                </tr>
                <tr>
                    <th>Premium Change : </th>
                    <td>-</td>
                </tr>
                <tr>
                    <th>Inception Date :</th>
                    <td>
                        @if(isset($this->policy_details?->term_end_date))
                        {{ Carbon\Carbon::parse($this->policy_details?->term_end_date)->format('d/m/Y') }}
                        @endif
                    </td>
                </tr>
                {{-- <tr>
                    <th>U/writer :</th>
                    <td>{{ $this->policy_details->user->firstName }}  {{ $this->policy_details->user->lastName }}</td>
                </tr> --}}
            </table>
        </div>
    </div>

<br>
<hr>
<br>
    <div>
        @livewire('policy.earned-premium.table', ['policy' => $this->policy])
    </div>
</div>
