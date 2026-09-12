<div wire:loading.class="page-loading">
    <div class="page-loader flex-column bg-dark bg-opacity-25 ">
        <span class="spinner-border text-primary" role="status"></span>
        <span class="text-gray-800 fs-6 fw-semibold mt-5">Loading...</span>
    </div>
    <div class="row">
        <div class="col-sm-12">
            <div class='card card-custom gutter-b'>
                <div class="card-header">
                    <div class="card-title">
                        <h3 class="card-label">Participants Details</h3>
                    </div>
                </div>
                <div class="card-body">
                    <div class="col-sm-12">
                        <div class="table-responsive">
                            <table class="table align-middle table-row-dashed fs-6 gy-5 dataTable no-footer">
                                <thead>
                                <tr class="text-start fw-bold fs-7 text-uppercase gs-0">
                                    <th>Sr.No.</th>
                                    <th>Reinsurer</th>
                                    <th>Share</th>
                                    <th>Premium Share</th>
                                    <th>Commission Rate</th>
                                    <th>Commission Amount</th>
                                    <th>Tax Rate</th>
                                    <th>Tax Amount</th>
                                    <th>Net Premium</th>
                                    <th>Description</th>
                                    @if($editable)
                                    <th style="text-align:center;">Action</th>
                                    @endif
                                </tr>
                                </thead>
                                <tbody class="fw-semibold text-gray-600">
                                    @php $i=1; @endphp
                                    @forelse($this->getallParticipants() as $participants)
                                        <tr class="{!! ($loop->iteration % 2 == 0)?'even':'odd' !!}">
                                            <td>{{$i++}}</td>
                                            <td>{{$participants->n_PersonInfoId_FK  ?? ""}}</td>
                                            <td>{{$participants->n_SharePercent ?? ""}}</td>
                                            <td>{{$participants->n_PremiumShare ?? ""}}</td>
                                            <td>{{$participants->commission_rate ?? ""}}</td>
                                            <td>{{$participants->commission_amount ?? ""}}</td>
                                            <td>{{$participants->tax_rate ?? ""}}</td>
                                            <td>{{$participants->tax_amount ?? ""}}</td>
                                            <td>{{$participants->n_NetPremium ?? ""}}</td>
                                            <td>{{$participants->description ?? ""}}</td>
                                            @if($editable)
                                            <td>
                                                <div class="flex space-x-1 justify-around">
                                                    <button wire:click.prevent="participantsDetailsedit('{{\Crypt::encrypt($participants->id)}}')"  class="btn  btn-sm btn-outline btn-outline-dashed hover-scale btn-outline-primary btn-active-light-primary">
                                                        <span class="svg-icon svg-icon-2 m-0"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path></svg></span>
                                                    </button>
                                                    <button wire:click.prevent="triggerparticipantsDelete('{{\Crypt::encrypt($participants->id)}}')"  class="btn  btn-sm btn-outline btn-outline-dashed hover-scale btn-outline-danger btn-active-light-danger">
                                                        <span class="svg-icon svg-icon-2 m-0"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg></span>
                                                    </button>
                                                </div>
                                            </td>
                                            @endif
                                        </tr>
                                    @empty
                                        <tr><td colspan=7>Participants Details Not Present ..</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @if($editable)
    <hr>
    <div class="row">
        <div class="col-sm-12">
            <div class='card card-custom gutter-b'>
                <div class="card-header">
                    <div class="card-title">
                        <h3 class="card-label">
                            @if($R_isUpdate)
                                Update
                            @else
                                Add
                            @endif	Participants</h3>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-floating mb-3">
                                <x-form-input  type="number" name="n_SharePercent"  wire:keydown="calculateSharePercent"  wire:keyup="calculateSharePercent"  wire:model.defer="participants.n_SharePercent" placeholder="Share%"/>
                                <x-form-label for="n_SharePercent" required value="{{ __('Share%') }}"/>
                                <x-form-input-error name="participants.n_SharePercent"/>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-floating mb-3">
                                <x-form-input type="text" name="n_PremiumShare" wire:model.defer="participants.n_PremiumShare" placeholder="Premium Share" readonly/>
                                <x-form-label for="n_PremiumShare" required value="{{ __('Premium Share') }}"/>
                                <x-form-input-error name="participants.n_PremiumShare"/>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-floating mb-3">
                                <x-form-input type="number" name="commission_rate" wire:keydown="calculateCommissionRate"  wire:keyup="calculateCommissionRate"  wire:model.defer="participants.commission_rate"  placeholder="Commission Rate" />
                                <x-form-label for="commission_rate" required value="{{ __('Commission Rate') }}"/>
                                <x-form-input-error name="participants.commission_rate"/>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-floating mb-3">
                                <x-form-input type="text" name="commission_amount" wire:model.defer="participants.commission_amount"  id="participants.commission_amount" placeholder="Commission Amount" readonly/>
                                <x-form-label for="commission_amount" required value="{{ __('Commission Amount') }}"/>
                                <x-form-input-error name="participants.commission_amount"/>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-floating mb-3">
                                <x-form-input type="number" name="tax_rate" wire:keydown="calculateTaxRate"  wire:keyup="calculateTaxRate"  wire:model.defer="participants.tax_rate"  placeholder="Tax Rate" />
                                <x-form-label for="tax_rate" required value="{{ __('Tax Rate') }}"/>
                                <x-form-input-error name="participants.tax_rate"/>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-floating mb-3">
                                <x-form-input type="text" name="tax_amount" wire:model.defer="participants.tax_amount"   placeholder="Tax Amount" readonly/>
                                <x-form-label for="tax_amount" required value="{{ __('Tax Amount') }}"/>
                                <x-form-input-error name="participants.tax_amount"/>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-floating mb-3">
                                <x-form-input type="text" name="n_NetPremium" wire:model.defer="participants.n_NetPremium" placeholder="Net Premium" readonly/>
                                <x-form-label for="n_NetPremium" required value="{{ __('Net Premium') }}"/>
                                <x-form-input-error name="participants.n_NetPremium"/>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-floating mb-3">
                                <x-select name="reinsurer"  data-placeholder="Select an option"  aria-label="Select Reinsurer"  wire:model.lazy="participants.n_PersonInfoId_FK"
                                :options="array('1'=>'First Reinsurance','2'=>'AIG')" />
                                <x-form-label for="n_PersonInfoId_FK" required value="{{ __('Select Reinsurer') }}"/>
                                <x-form-input-error name="participants.n_PersonInfoId_FK"/>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-12">
                            <div class="form-floating mb-3">
                                <x-form-input type="text" name="description" wire:model.defer="participants.description"  placeholder="Description"/>
                                <x-form-label for="description" required value="{{ __('Description') }}"/>
                                <x-form-input-error name="participants.description"/>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-sm-6" style="text-align: right;">
                            @if($R_isUpdate)
                                <button type="button" wire:click.prevent="participantsDetailsEditCancel" class="btn btn-danger pull-right font-weight-bolder text-uppercase px-6 py-4" wire:loading.class="spinner spinner-right spinner-white pr-15"  wire:offline.attr="disabled" wire:loading.attr="disabled">
                                    Cancel
                                </button>
                            @endif
                        </div>
                        <div class="col-sm-6">
                            <button type="button" wire:click.prevent="addParticipants" class="btn btn-primary pull-right font-weight-bolder text-uppercase px-6 py-4" wire:loading.class="spinner spinner-right spinner-white pr-15"  wire:offline.attr="disabled" wire:loading.attr="disabled">
                                @if($R_isUpdate)
                                    Update
                                @else
                                    <i class="la la-plus"></i>Save
                                @endif
                            </button>
                        </div>
                    </div>
                </div>


            </div>
        </div>
    </div>
    @endif
</div>
