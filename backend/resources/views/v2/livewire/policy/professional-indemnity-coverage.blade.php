{{-- Professional Indemnity Coverage --}}
<div class="professional-indemnity-coverage-section">
    {{-- Professional Indemnity Form --}}
    <div class="row mb-4">
        <div class="col-sm-12">
            <div class="card bg-light shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Professional Indemnity</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <tbody>
                                <tr>
                                    <td style="width: 30%; vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Insured</strong>
                                    </td>
                                    <td style="width: 70%;">
                                        <div class="form-floating mb-3">
                                            <input type="text" 
                                                class="form-control" 
                                                wire:model.defer="professionalIndemnityCoverage.{{ $policyCoverage->id }}.insured" 
                                                placeholder="Insured">
                                            <label>Insured</label>
                                            <x-form-input-error name="professionalIndemnityCoverage.{{ $policyCoverage->id }}.insured"/>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="width: 30%; vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Profession/Business</strong>
                                    </td>
                                    <td style="width: 70%;">
                                        <div class="form-floating mb-3">
                                            <textarea class="form-control" 
                                                wire:model.defer="professionalIndemnityCoverage.{{ $policyCoverage->id }}.profession_business" 
                                                rows="3" 
                                                placeholder="Free text area"
                                                style="height: 100px;"></textarea>
                                            <label>Free text area</label>
                                            <x-form-input-error name="professionalIndemnityCoverage.{{ $policyCoverage->id }}.profession_business"/>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="width: 30%; vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Basis of cover</strong>
                                    </td>
                                    <td style="width: 70%;">
                                        <div class="form-floating mb-3">
                                            <input type="text" 
                                                class="form-control" 
                                                wire:model.defer="professionalIndemnityCoverage.{{ $policyCoverage->id }}.basis_of_cover" 
                                                placeholder="Basis of cover">
                                            <label>Basis of cover</label>
                                            <x-form-input-error name="professionalIndemnityCoverage.{{ $policyCoverage->id }}.basis_of_cover"/>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="width: 30%; vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Period of insurance</strong>
                                    </td>
                                    <td style="width: 70%;">
                                        <div class="form-floating mb-3">
                                            <input type="text" 
                                                class="form-control" 
                                                wire:model.defer="professionalIndemnityCoverage.{{ $policyCoverage->id }}.period_of_insurance" 
                                                placeholder="Period of insurance">
                                            <label>Period of insurance</label>
                                            <x-form-input-error name="professionalIndemnityCoverage.{{ $policyCoverage->id }}.period_of_insurance"/>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="width: 30%; vertical-align: middle; background-color: #f8f9fa;">
                                        <strong>Retroactive date</strong>
                                    </td>
                                    <td style="width: 70%;">
                                        <div class="form-floating mb-3">
                                            <x-form-date type="text" 
                                                class="kt_datepicker_2 form-control" 
                                                wire:model.defer="professionalIndemnityCoverage.{{ $policyCoverage->id }}.retroactive_date" 
                                                placeholder="Retroactive date"/>
                                            <label>Retroactive date</label>
                                            <x-form-input-error name="professionalIndemnityCoverage.{{ $policyCoverage->id }}.retroactive_date"/>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-primary" wire:click="$emit('saveProfessionalIndemnityCoverage', {{ $policyCoverage->id }})">
                            <i class="fa fa-save"></i> Save Professional Indemnity
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

