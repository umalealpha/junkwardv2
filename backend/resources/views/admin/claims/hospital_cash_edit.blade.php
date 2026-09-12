<!--begin::Portlet-->
<div class="kt-portlet kt-portlet--height-fluid">
    <div class="kt-portlet__head">
        <div class="kt-portlet__head-label">
            <h3 class="kt-portlet__head-title">
                Hospital CashBack Claim Information
            </h3>
        </div>
    </div>
    <div class="kt-portlet__body">
        <div class="kt-widget-4">
            <form id="storeClaim" action="{{ route('admin.claims.update', $claims->id) }}" method="POST" enctype="multipart/form-data">
                {{ method_field('PATCH') }}
                {{csrf_field()}}
                <input type="hidden" name="type" value="hospital_cash">
                

                <!-- Patient Section -->
                <h4 class="kt-heading kt-heading--md"><strong>Patient</strong></h4>
                <div class="form-group row">
                    <label for="patient_name" class="col-3 col-form-label">Name of Patient:</label>
                    <div class="col-9">
                        <input type="text" id="patient_name" name="patient_name" class="form-control" autocomplete="off" placeholder="Name of Patient" value="{{ isset($hospitalCash) ? $hospitalCash->patient_name : '' }}">
                    </div>
                </div>
                <div class="form-group row">
                    <label for="patient_dob" class="col-3 col-form-label">Date of Birth:</label>
                    <div class="col-9">
                        <input type="text" id="patient_dob" name="patient_dob" class="form-control kt_datepicker_1" autocomplete="off" placeholder="Select date of birth" @if(isset($hospitalCash) && $hospitalCash->patient_dob) value="{{ \Carbon\Carbon::parse($hospitalCash->patient_dob)->format('Y-m-d') }}" @endif>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-3 col-form-label">Are you a citizen of Botswana?</label>
                    <div class="col-9">
                        <div class="kt-radio-inline">
                            <label class="kt-radio">
                                <input type="radio" name="is_botswana_citizen" id="is_botswana_citizen_yes" value="yes" @if(isset($hospitalCash) && $hospitalCash->patient_identity_number && is_numeric($hospitalCash->patient_identity_number)) checked @endif> Yes
                                <span></span>
                            </label>
                            <label class="kt-radio">
                                <input type="radio" name="is_botswana_citizen" id="is_botswana_citizen_no" value="no" @if(isset($hospitalCash) && $hospitalCash->patient_identity_number && !is_numeric($hospitalCash->patient_identity_number)) checked @endif> No
                                <span></span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="form-group row" id="omang_field" style="display: {{ (isset($hospitalCash) && $hospitalCash->patient_identity_number && is_numeric($hospitalCash->patient_identity_number)) ? 'block' : 'none' }};">
                    <label for="patient_omang" class="col-3 col-form-label">Omang:</label>
                    <div class="col-9">
                        <input type="text" id="patient_omang" name="patient_omang" class="form-control" autocomplete="off" placeholder="Enter Omang number" value="{{ (isset($hospitalCash) && $hospitalCash->patient_identity_number && is_numeric($hospitalCash->patient_identity_number)) ? $hospitalCash->patient_identity_number : '' }}">
                    </div>
                </div>
                <div class="form-group row" id="passport_field" style="display: {{ (isset($hospitalCash) && $hospitalCash->patient_identity_number && !is_numeric($hospitalCash->patient_identity_number)) ? 'block' : 'none' }};">
                    <label for="patient_passport" class="col-3 col-form-label">Passport:</label>
                    <div class="col-9">
                        <input type="text" id="patient_passport" name="patient_passport" class="form-control" autocomplete="off" placeholder="Enter Passport number" value="{{ (isset($hospitalCash) && $hospitalCash->patient_identity_number && !is_numeric($hospitalCash->patient_identity_number)) ? $hospitalCash->patient_identity_number : '' }}">
                    </div>
                </div>
                <!-- Hidden field to store the selected value -->
                <input type="hidden" id="patient_identity_number" name="patient_identity_number" value="{{ isset($hospitalCash) ? $hospitalCash->patient_identity_number : '' }}">
                <div class="form-group row">
                    <label for="relationship" class="col-3 col-form-label">Relationship to Policyholder:</label>
                    <div class="col-9">
                        <select id="relationship" name="relationship" class="form-control kt_selectpicker" title="Please select relationship">
                            <option value="">Select Relationship</option>
                            <option value="Self" @if(isset($hospitalCash) && $hospitalCash->relationship == 'Self') selected @endif>Self</option>
                            <option value="Spouse" @if(isset($hospitalCash) && $hospitalCash->relationship == 'Spouse') selected @endif>Spouse</option>
                            <option value="Child" @if(isset($hospitalCash) && $hospitalCash->relationship == 'Child') selected @endif>Child</option>
                            <option value="Parent" @if(isset($hospitalCash) && $hospitalCash->relationship == 'Parent') selected @endif>Parent</option>
                            <option value="Other" @if(isset($hospitalCash) && $hospitalCash->relationship == 'Other') selected @endif>Other</option>
                        </select>
                    </div>
                </div>
                <div class="form-group row" id="relationship_other_field" style="display: {{ (isset($hospitalCash) && $hospitalCash->relationship == 'Other') ? 'block' : 'none' }};">
                    <label for="relationship_other" class="col-3 col-form-label">Specify Relationship:</label>
                    <div class="col-9">
                        <input type="text" id="relationship_other" name="relationship_other" class="form-control" autocomplete="off" placeholder="Please specify relationship" value="{{ isset($hospitalCash) ? $hospitalCash->relationship_other : '' }}">
                    </div>
                </div>
                <div class="form-group row">
                    <label for="occupation_date" class="col-3 col-form-label">Occupation Date:</label>
                    <div class="col-9">
                        <input type="text" id="occupation_date" name="occupation_date" class="form-control kt_datepicker_1" autocomplete="off" placeholder="Select occupation date" @if(isset($hospitalCash) && $hospitalCash->occupation_date) value="{{ \Carbon\Carbon::parse($hospitalCash->occupation_date)->format('Y-m-d') }}" @endif>
                    </div>
                </div>

                <div class="kt-separator kt-separator--space-md kt-separator--border-dashed"></div>

                <!-- General Practitioner Section -->
                <h4 class="kt-heading kt-heading--md"><strong>General Practitioner (usual family doctor)</strong></h4>
                <div class="form-group row">
                    <label for="gp_name" class="col-3 col-form-label">Name:</label>
                    <div class="col-9">
                        <input type="text" id="gp_name" name="gp_name" class="form-control" autocomplete="off" placeholder="General Practitioner Name" value="{{ isset($hospitalCash) ? $hospitalCash->gp_name : '' }}">
                    </div>
                </div>
                <div class="form-group row">
                    <label for="gp_postal_address" class="col-3 col-form-label">Postal Address:</label>
                    <div class="col-9">
                        <textarea id="gp_postal_address" name="gp_postal_address" class="form-control" rows="3" placeholder="General Practitioner Postal Address">{{ isset($hospitalCash) ? $hospitalCash->gp_postal_address : '' }}</textarea>
                    </div>
                </div>
                <div class="form-group row">
                    <label for="gp_cellular_no" class="col-3 col-form-label">Cellular No:</label>
                    <div class="col-9">
                        <input type="text" id="gp_cellular_no" name="gp_cellular_no" class="form-control" autocomplete="off" placeholder="GP Cellular Number" value="{{ isset($hospitalCash) ? $hospitalCash->gp_cellular_no : '' }}">
                    </div>
                </div>
                <div class="form-group row">
                    <label for="gp_telephone_no" class="col-3 col-form-label">Telephone No:</label>
                    <div class="col-9">
                        <input type="text" id="gp_telephone_no" name="gp_telephone_no" class="form-control" autocomplete="off" placeholder="GP Telephone Number" value="{{ isset($hospitalCash) ? $hospitalCash->gp_telephone_no : '' }}">
                    </div>
                </div>
                <div class="form-group row">
                    <label for="gp_fax_no" class="col-3 col-form-label">Fax No:</label>
                    <div class="col-9">
                        <input type="text" id="gp_fax_no" name="gp_fax_no" class="form-control" autocomplete="off" placeholder="GP Fax Number" value="{{ isset($hospitalCash) ? $hospitalCash->gp_fax_no : '' }}">
                    </div>
                </div>

                <div class="kt-separator kt-separator--space-md kt-separator--border-dashed"></div>

                <!-- Hospital Section -->
                <h4 class="kt-heading kt-heading--md"><strong>Hospital</strong></h4>
                <div class="form-group">
                    <p class="text-muted" style="font-size: 12px;"><strong>Please attach copies of the hospital account and day to day hospital records</strong></p>
                </div>
                <div class="form-group row">
                    <label for="hospital_name" class="col-3 col-form-label">Name of hospital:</label>
                    <div class="col-9">
                        <input type="text" id="hospital_name" name="hospital_name" class="form-control" autocomplete="off" placeholder="Name of hospital" value="{{ isset($hospitalCash) ? $hospitalCash->hospital_name : '' }}">
                    </div>
                </div>
                <div class="form-group row">
                    <label for="hospital_tel_fax" class="col-3 col-form-label">Tel and Fax No:</label>
                    <div class="col-9">
                        <input type="text" id="hospital_tel_fax" name="hospital_tel_fax" class="form-control" autocomplete="off" placeholder="Hospital Tel and Fax No" value="{{ isset($hospitalCash) ? $hospitalCash->hospital_tel_fax : '' }}">
                    </div>
                </div>
                <div class="form-group row">
                    <label for="admitting_doctor" class="col-3 col-form-label">Admitting Doctor:</label>
                    <div class="col-9">
                        <input type="text" id="admitting_doctor" name="admitting_doctor" class="form-control" autocomplete="off" placeholder="Admitting Doctor Name" value="{{ isset($hospitalCash) ? $hospitalCash->admitting_doctor : '' }}">
                    </div>
                </div>
                <div class="form-group row">
                    <label for="admitting_doctor_tel_fax" class="col-3 col-form-label">Tel and Fax No:</label>
                    <div class="col-9">
                        <input type="text" id="admitting_doctor_tel_fax" name="admitting_doctor_tel_fax" class="form-control" autocomplete="off" placeholder="Admitting Doctor Tel and Fax No" value="{{ isset($hospitalCash) ? $hospitalCash->admitting_doctor_tel_fax : '' }}">
                    </div>
                </div>
                <div class="form-group row">
                    <label for="admission_date" class="col-3 col-form-label">Admission Date:</label>
                    <div class="col-4">
                        <input type="text" id="admission_date" name="admission_date" class="form-control kt_datepicker_1" autocomplete="off" placeholder="Select admission date" @if(isset($hospitalCash) && $hospitalCash->admission_date) value="{{ \Carbon\Carbon::parse($hospitalCash->admission_date)->format('Y-m-d') }}" @endif>
                    </div>
                    <label for="admission_time" class="col-2 col-form-label">Time:</label>
                    <div class="col-3">
                        <input type="text" id="admission_time" name="admission_time" class="form-control kt_timepicker" autocomplete="off" placeholder="Admission time" value="{{ isset($hospitalCash) ? $hospitalCash->admission_time : '' }}">
                    </div>
                </div>
                <div class="form-group row">
                    <label for="discharge_date" class="col-3 col-form-label">Discharge Date:</label>
                    <div class="col-4">
                        <input type="text" id="discharge_date" name="discharge_date" class="form-control kt_datepicker_1" autocomplete="off" placeholder="Select discharge date" @if(isset($hospitalCash) && $hospitalCash->discharge_date) value="{{ \Carbon\Carbon::parse($hospitalCash->discharge_date)->format('Y-m-d') }}" @endif>
                    </div>
                    <label for="discharge_time" class="col-2 col-form-label">Time:</label>
                    <div class="col-3">
                        <input type="text" id="discharge_time" name="discharge_time" class="form-control kt_timepicker" autocomplete="off" placeholder="Discharge time" value="{{ isset($hospitalCash) ? $hospitalCash->discharge_time : '' }}">
                    </div>
                </div>

                <div class="kt-separator kt-separator--space-md kt-separator--border-dashed"></div>

                <!-- Claim Information Section -->
                <h4 class="kt-heading kt-heading--md"><strong>Claim Information</strong></h4>
                <div class="form-group row">
                    <label for="hospitalisation_type" class="col-12 col-form-label">Was hospitalisation due to an accident or sickness?</label>
                    <div class="col-12">
                        <select id="hospitalisation_type" name="hospitalisation_type" class="form-control kt_selectpicker" title="Please select">
                            <option value="">Select</option>
                            <option value="accident" @if(isset($hospitalCash) && $hospitalCash->hospitalisation_type == 'accident') selected @endif>Accident</option>
                            <option value="sickness" @if(isset($hospitalCash) && $hospitalCash->hospitalisation_type == 'sickness') selected @endif>Sickness</option>
                            <option value="pregnancy" @if(isset($hospitalCash) && $hospitalCash->hospitalisation_type == 'pregnancy') selected @endif>Pregnancy</option>
                            <option value="injury" @if(isset($hospitalCash) && $hospitalCash->hospitalisation_type == 'injury') selected @endif>Injury</option>
                        </select>
                    </div>
                </div>
                
                <!-- Accident Field -->
                <div class="form-group row" id="accident_field" style="display: {{ (isset($hospitalCash) && $hospitalCash->hospitalisation_type == 'accident') ? 'block' : 'none' }};">
                    <label for="accident_reported" class="col-12 col-form-label">Please provide the case number</label>
                    <div class="col-12">
                        <input type="text" id="accident_reported" name="accident_reported" class="form-control" autocomplete="off" placeholder="Case number" value="{{ isset($hospitalCash) ? $hospitalCash->accident_reported : '' }}">
                    </div>
                </div>
                
                <!-- Sickness Field -->
                <div class="form-group row" id="sickness_field" style="display: {{ (isset($hospitalCash) && $hospitalCash->hospitalisation_type == 'sickness') ? 'block' : 'none' }};">
                    <label for="symptoms_first_appeared" class="col-12 col-form-label">When did symptoms first appear?</label>
                    <div class="col-12">
                        <input type="text" id="symptoms_first_appeared" name="symptoms_first_appeared" class="form-control kt_datepicker_1" autocomplete="off" placeholder="Select date when symptoms first appeared" @if(isset($hospitalCash) && $hospitalCash->symptoms_first_appeared) value="{{ \Carbon\Carbon::parse($hospitalCash->symptoms_first_appeared)->format('Y-m-d') }}" @endif>
                    </div>
                </div>
                
                <!-- Pregnancy Fields -->
                <div id="pregnancy_field" style="display: {{ (isset($hospitalCash) && $hospitalCash->hospitalisation_type == 'pregnancy') ? 'block' : 'none' }};">
                    <div class="form-group row">
                        <label for="pregnancy_conception_date" class="col-12 col-form-label">Approximate date of conception</label>
                        <div class="col-12">
                            <input type="text" id="pregnancy_conception_date" name="pregnancy_conception_date" class="form-control kt_datepicker_1" autocomplete="off" placeholder="Select date of conception" @if(isset($hospitalCash) && $hospitalCash->pregnancy_conception_date) value="{{ \Carbon\Carbon::parse($hospitalCash->pregnancy_conception_date)->format('Y-m-d') }}" @endif>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="pregnancy_delivery_date" class="col-12 col-form-label">Date of delivery</label>
                        <div class="col-12">
                            <input type="text" id="pregnancy_delivery_date" name="pregnancy_delivery_date" class="form-control kt_datepicker_1" autocomplete="off" placeholder="Select date of delivery" @if(isset($hospitalCash) && $hospitalCash->pregnancy_delivery_date) value="{{ \Carbon\Carbon::parse($hospitalCash->pregnancy_delivery_date)->format('Y-m-d') }}" @endif>
                        </div>
                    </div>
                </div>
                
                <!-- Injury Field -->
                <div class="form-group row" id="injury_field" style="display: {{ (isset($hospitalCash) && $hospitalCash->hospitalisation_type == 'injury') ? 'block' : 'none' }};">
                    <label for="injury_date" class="col-12 col-form-label">When did the accident occur?</label>
                    <div class="col-12">
                        <input type="text" id="injury_date" name="injury_date" class="form-control kt_datepicker_1" autocomplete="off" placeholder="Select accident date" @if(isset($hospitalCash) && $hospitalCash->injury_date) value="{{ \Carbon\Carbon::parse($hospitalCash->injury_date)->format('Y-m-d') }}" @endif>
                    </div>
                </div>
                <div class="form-group row">
                    <label for="accident_circumstances" class="col-12 col-form-label">Describe the circumstances surrounding the accident?</label>
                    <div class="col-12">
                        <textarea id="accident_circumstances" name="accident_circumstances" class="form-control" rows="3" placeholder="Describe the circumstances">{{ isset($hospitalCash) ? $hospitalCash->accident_circumstances : '' }}</textarea>
                    </div>
                </div>
                <div class="form-group row">
                    <label for="first_consultation_date" class="col-12 col-form-label">When did you first consult a doctor for this condition?</label>
                    <div class="col-12">
                        <input type="text" id="first_consultation_date" name="first_consultation_date" class="form-control kt_datepicker_1" autocomplete="off" placeholder="Select first consultation date" @if(isset($hospitalCash) && $hospitalCash->first_consultation_date) value="{{ \Carbon\Carbon::parse($hospitalCash->first_consultation_date)->format('Y-m-d') }}" @endif>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-12 col-form-label">Is the patient on a medical scheme?</label>
                    <div class="col-12">
                        <div class="kt-radio-inline">
                            <label class="kt-radio">
                                <input type="radio" name="is_medical_scheme" id="is_medical_scheme_yes" value="yes" @if(isset($hospitalCash) && ($hospitalCash->medical_scheme_name || $hospitalCash->medical_aid_number)) checked @endif> Yes
                                <span></span>
                            </label>
                            <label class="kt-radio">
                                <input type="radio" name="is_medical_scheme" id="is_medical_scheme_no" value="no" @if(!isset($hospitalCash) || (!$hospitalCash->medical_scheme_name && !$hospitalCash->medical_aid_number)) checked @endif> No
                                <span></span>
                            </label>
                        </div>
                    </div>
                </div>
                <div id="medical_scheme_fields" style="display: {{ (isset($hospitalCash) && ($hospitalCash->medical_scheme_name || $hospitalCash->medical_aid_number)) ? 'block' : 'none' }};">
                    <div class="form-group row">
                        <label for="medical_scheme_name" class="col-12 col-form-label">Please supply name</label>
                        <div class="col-12">
                            <input type="text" id="medical_scheme_name" name="medical_scheme_name" class="form-control" autocomplete="off" placeholder="Medical scheme name" value="{{ isset($hospitalCash) ? $hospitalCash->medical_scheme_name : '' }}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="medical_aid_number" class="col-12 col-form-label">Please provide medical aid number</label>
                        <div class="col-12">
                            <input type="text" id="medical_aid_number" name="medical_aid_number" class="form-control" autocomplete="off" placeholder="Medical aid number" value="{{ isset($hospitalCash) ? $hospitalCash->medical_aid_number : '' }}">
                        </div>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-12 col-form-label">Does the patient have any other Hospital Insurance policies?</label>
                    <div class="col-12">
                        <div class="kt-radio-inline">
                            <label class="kt-radio">
                                <input type="radio" name="has_other_insurance" id="has_other_insurance_yes" value="yes" @if(isset($hospitalCash) && ($hospitalCash->other_insurance_company_name || $hospitalCash->other_insurance_policy_numbers)) checked @endif> Yes
                                <span></span>
                            </label>
                            <label class="kt-radio">
                                <input type="radio" name="has_other_insurance" id="has_other_insurance_no" value="no" @if(!isset($hospitalCash) || (!$hospitalCash->other_insurance_company_name && !$hospitalCash->other_insurance_policy_numbers)) checked @endif> No
                                <span></span>
                            </label>
                        </div>
                    </div>
                </div>
                <div id="other_insurance_fields" style="display: {{ (isset($hospitalCash) && ($hospitalCash->other_insurance_company_name || $hospitalCash->other_insurance_policy_numbers)) ? 'block' : 'none' }};">
                    <div class="form-group row">
                        <label for="other_insurance_company_name" class="col-12 col-form-label">Please supply Company name</label>
                        <div class="col-12">
                            <input type="text" id="other_insurance_company_name" name="other_insurance_company_name" class="form-control" autocomplete="off" placeholder="Company name" value="{{ isset($hospitalCash) ? $hospitalCash->other_insurance_company_name : '' }}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="other_insurance_policy_numbers" class="col-12 col-form-label">Please provide policy numbers</label>
                        <div class="col-12">
                            <input type="text" id="other_insurance_policy_numbers" name="other_insurance_policy_numbers" class="form-control" autocomplete="off" placeholder="Policy numbers" value="{{ isset($hospitalCash) ? $hospitalCash->other_insurance_policy_numbers : '' }}">
                        </div>
                    </div>
                </div>


                <div class="form-group row">
                    <div class="col-12">
                        <button type="submit" class="btn btn-success btn-lg">Update Claim</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<!--end::Portlet-->

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Relationship "Other" field handler
        const relationshipSelect = document.getElementById('relationship');
        const relationshipOtherField = document.getElementById('relationship_other_field');
        const relationshipOtherInput = document.getElementById('relationship_other');
        
        if (relationshipSelect && relationshipOtherField) {
            relationshipSelect.addEventListener('change', function() {
                if (this.value === 'Other') {
                    relationshipOtherField.style.display = 'block';
                } else {
                    relationshipOtherField.style.display = 'none';
                    if (this.value !== 'Other') {
                        relationshipOtherInput.value = '';
                    }
                }
            });
        }
        
        // Hospitalisation type field handler
        const hospitalisationTypeSelect = document.getElementById('hospitalisation_type');
        const accidentField = document.getElementById('accident_field');
        const sicknessField = document.getElementById('sickness_field');
        const pregnancyField = document.getElementById('pregnancy_field');
        const injuryField = document.getElementById('injury_field');
        
        if (hospitalisationTypeSelect) {
            hospitalisationTypeSelect.addEventListener('change', function() {
                const value = this.value;
                
                // Hide all fields first
                if (accidentField) accidentField.style.display = 'none';
                if (sicknessField) sicknessField.style.display = 'none';
                if (pregnancyField) pregnancyField.style.display = 'none';
                if (injuryField) injuryField.style.display = 'none';
                
                // Show relevant field based on selection
                if (value === 'accident' && accidentField) {
                    accidentField.style.display = 'block';
                } else if (value === 'sickness' && sicknessField) {
                    sicknessField.style.display = 'block';
                } else if (value === 'pregnancy' && pregnancyField) {
                    pregnancyField.style.display = 'block';
                } else if (value === 'injury' && injuryField) {
                    injuryField.style.display = 'block';
                } else {
                    // Clear values when hiding fields
                    if (accidentField) {
                        const accidentInput = document.getElementById('accident_reported');
                        if (accidentInput && value !== 'accident') accidentInput.value = '';
                    }
                    if (sicknessField) {
                        const symptomsInput = document.getElementById('symptoms_first_appeared');
                        if (symptomsInput && value !== 'sickness') symptomsInput.value = '';
                    }
                    if (pregnancyField) {
                        const conceptionInput = document.getElementById('pregnancy_conception_date');
                        const deliveryInput = document.getElementById('pregnancy_delivery_date');
                        if (conceptionInput && value !== 'pregnancy') conceptionInput.value = '';
                        if (deliveryInput && value !== 'pregnancy') deliveryInput.value = '';
                    }
                    if (injuryField) {
                        const injuryInput = document.getElementById('injury_date');
                        if (injuryInput && value !== 'injury') injuryInput.value = '';
                    }
                }
            });
            
            // Trigger on page load to show correct field if value is already set
            if (hospitalisationTypeSelect.value) {
                hospitalisationTypeSelect.dispatchEvent(new Event('change'));
            }
        }
        
        // Medical scheme radio button handler
        const medicalSchemeYes = document.getElementById('is_medical_scheme_yes');
        const medicalSchemeNo = document.getElementById('is_medical_scheme_no');
        const medicalSchemeFields = document.getElementById('medical_scheme_fields');
        
        if (medicalSchemeYes && medicalSchemeNo && medicalSchemeFields) {
            function toggleMedicalSchemeFields() {
                if (medicalSchemeYes.checked) {
                    medicalSchemeFields.style.display = 'block';
                } else {
                    medicalSchemeFields.style.display = 'none';
                    // Clear values when hiding
                    const nameInput = document.getElementById('medical_scheme_name');
                    const numberInput = document.getElementById('medical_aid_number');
                    if (nameInput) nameInput.value = '';
                    if (numberInput) numberInput.value = '';
                }
            }
            
            medicalSchemeYes.addEventListener('change', toggleMedicalSchemeFields);
            medicalSchemeNo.addEventListener('change', toggleMedicalSchemeFields);
        }
        
        // Other insurance radio button handler
        const otherInsuranceYes = document.getElementById('has_other_insurance_yes');
        const otherInsuranceNo = document.getElementById('has_other_insurance_no');
        const otherInsuranceFields = document.getElementById('other_insurance_fields');
        
        if (otherInsuranceYes && otherInsuranceNo && otherInsuranceFields) {
            function toggleOtherInsuranceFields() {
                if (otherInsuranceYes.checked) {
                    otherInsuranceFields.style.display = 'block';
                } else {
                    otherInsuranceFields.style.display = 'none';
                    // Clear values when hiding
                    const companyInput = document.getElementById('other_insurance_company_name');
                    const policyInput = document.getElementById('other_insurance_policy_numbers');
                    if (companyInput) companyInput.value = '';
                    if (policyInput) policyInput.value = '';
                }
            }
            
            otherInsuranceYes.addEventListener('change', toggleOtherInsuranceFields);
            otherInsuranceNo.addEventListener('change', toggleOtherInsuranceFields);
        }
        
        // Botswana citizen / Omang / Passport handler
        const citizenYes = document.getElementById('is_botswana_citizen_yes');
        const citizenNo = document.getElementById('is_botswana_citizen_no');
        const omangField = document.getElementById('omang_field');
        const passportField = document.getElementById('passport_field');
        const omangInput = document.getElementById('patient_omang');
        const passportInput = document.getElementById('patient_passport');
        const identityHidden = document.getElementById('patient_identity_number');
        const hospitalCashForm = document.getElementById('storeClaim');
        
        function toggleCitizenFields() {
            if (!citizenYes || !citizenNo || !omangField || !passportField) {
                return;
            }
            
            if (citizenYes.checked) {
                omangField.style.display = 'block';
                passportField.style.display = 'none';
                if (passportInput) {
                    passportInput.value = '';
                }
            } else if (citizenNo.checked) {
                omangField.style.display = 'none';
                passportField.style.display = 'block';
                if (omangInput) {
                    omangInput.value = '';
                }
            } else {
                omangField.style.display = 'none';
                passportField.style.display = 'none';
            }
        }
        
        if (citizenYes && citizenNo) {
            citizenYes.addEventListener('change', toggleCitizenFields);
            citizenNo.addEventListener('change', toggleCitizenFields);
            
            // Trigger on page load to show correct field if value is already set
            if (citizenYes.checked || citizenNo.checked) {
                toggleCitizenFields();
            }
        }
        
        if (hospitalCashForm && identityHidden) {
            hospitalCashForm.addEventListener('submit', function () {
                if (citizenYes && citizenYes.checked && omangInput) {
                    identityHidden.value = omangInput.value;
                } else if (citizenNo && citizenNo.checked && passportInput) {
                    identityHidden.value = passportInput.value;
                }
            });
        }
    });
</script>
