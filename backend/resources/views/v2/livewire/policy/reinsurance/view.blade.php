<style>
    .modal-body
    {
        padding: 10px;
    }
    .form-control
    {
        margin-top: 10px;
    }
</style>
<div>
    <div class="card mb-5 mb-xl-10">
        <div class="card-header cursor-pointer">
            <div class="card-title m-0">
                <h3 class="fw-bold m-0">Policy Reinsurance</h3>
                <button class="btn btn-primary btn-sm m-1" wire:click="policyReinsuranceCalculations" >Reinsurance Calculations</button>
                <!-- <a class="btn btn-primary" id="add_reinsurance_calculations_btn" style="color:#fff;margin-left: 710px;">Reinsurance Calculations</a> -->
            </div>
        </div>        
        
    </div>
    </div>
    <div class="card-body p-9">
        @livewire('policy.reinsurance.tmp-table',['policy_id' => $this->policy->id,'action_id' => $this->actionId])
    </div>
</div>
<script>
    
</script>