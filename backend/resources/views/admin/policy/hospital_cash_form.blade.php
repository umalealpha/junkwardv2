<form id="storeHospitalCashClaim" action="{{ (isset($claims) && $claims->id) ? route('admin.claims.update', $claims->id) : route('admin.policy.storeClaim') }}" method="POST" enctype="multipart/form-data" class="kt-form">
    @if(isset($claims) && $claims->id)
        {{ method_field('PATCH') }}
    @endif
    {{csrf_field()}}
    <input type="hidden" name="policy_id" value="{{ $policy->id }}">
    <input type="hidden" name="type" value="hospital_cash">
    
    <!-- Patient Section -->
    <h4 class="kt-heading kt-heading--md"><strong>Patient</strong></h4>
    <div class="form-group row">
        <label for="patient_name" class="col-3 col-form-label">Name of Patient:</label>
        <div class="col-9">
            <input type="text" id="patient_name" name="patient_name" class="form-control required" title="Please enter patient name" autocomplete="off" placeholder="Name of Patient">
        </div>
    </div>
    <div class="form-group row">
        <label for="patient_dob" class="col-3 col-form-label">Date of Birth:</label>
        <div class="col-9">
            <input type="text" id="patient_dob" name="patient_dob" class="form-control kt_datepicker_1" autocomplete="off" placeholder="Select date of birth">
        </div>
    </div>
    <div class="form-group row">
        <label for="patient_identity_number" class="col-3 col-form-label">Identity Number:</label>
        <div class="col-9">
            <input type="text" id="patient_identity_number" name="patient_identity_number" class="form-control" autocomplete="off" placeholder="Identity Number">
        </div>
    </div>
    <div class="form-group row">
        <label for="relationship" class="col-3 col-form-label">Relationship to Policyholder:</label>
        <div class="col-9">
            <select id="relationship" name="relationship" class="form-control kt_selectpicker required" title="Please select relationship">
                <option value="">Select Relationship</option>
                <option value="Self">Self</option>
                <option value="Spouse">Spouse</option>
                <option value="Child">Child</option>
                <option value="Parent">Parent</option>
                <option value="Other">Other</option>
            </select>
        </div>
    </div>
    <div class="form-group row" id="relationship_other_field" style="display: none;">
        <label for="relationship_other" class="col-3 col-form-label">Specify Relationship:</label>
        <div class="col-9">
            <input type="text" id="relationship_other" name="relationship_other" class="form-control" autocomplete="off" placeholder="Please specify relationship">
        </div>
    </div>
    <div class="form-group row">
        <label for="occupation_date" class="col-3 col-form-label">Occupation Date:</label>
        <div class="col-9">
            <input type="text" id="occupation_date" name="occupation_date" class="form-control kt_datepicker_1" autocomplete="off" placeholder="Select occupation date">
        </div>
    </div>

    <div class="kt-separator kt-separator--space-md kt-separator--border-dashed"></div>

    <!-- General Practitioner Section -->
    <h4 class="kt-heading kt-heading--md"><strong>General Practitioner (usual family doctor)</strong></h4>
    <div class="form-group row">
        <label for="gp_name" class="col-3 col-form-label">Name:</label>
        <div class="col-9">
            <input type="text" id="gp_name" name="gp_name" class="form-control" autocomplete="off" placeholder="General Practitioner Name">
        </div>
    </div>
    <div class="form-group row">
        <label for="gp_postal_address" class="col-3 col-form-label">Postal Address:</label>
        <div class="col-9">
            <textarea id="gp_postal_address" name="gp_postal_address" class="form-control" rows="3" placeholder="General Practitioner Postal Address"></textarea>
        </div>
    </div>
    <div class="form-group row">
        <label for="gp_cellular_no" class="col-3 col-form-label">Cellular No:</label>
        <div class="col-9">
            <input type="text" id="gp_cellular_no" name="gp_cellular_no" class="form-control" autocomplete="off" placeholder="GP Cellular Number">
        </div>
    </div>
    <div class="form-group row">
        <label for="gp_telephone_no" class="col-3 col-form-label">Telephone No:</label>
        <div class="col-9">
            <input type="text" id="gp_telephone_no" name="gp_telephone_no" class="form-control" autocomplete="off" placeholder="GP Telephone Number">
        </div>
    </div>
    <div class="form-group row">
        <label for="gp_fax_no" class="col-3 col-form-label">Fax No:</label>
        <div class="col-9">
            <input type="text" id="gp_fax_no" name="gp_fax_no" class="form-control" autocomplete="off" placeholder="GP Fax Number">
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
            <input type="text" id="hospital_name" name="hospital_name" class="form-control required" title="Please enter hospital name" autocomplete="off" placeholder="Name of hospital">
        </div>
    </div>
    <div class="form-group row">
        <label for="hospital_tel_fax" class="col-3 col-form-label">Tel and Fax No:</label>
        <div class="col-9">
            <input type="text" id="hospital_tel_fax" name="hospital_tel_fax" class="form-control" autocomplete="off" placeholder="Hospital Tel and Fax No">
        </div>
    </div>
    <div class="form-group row">
        <label for="admitting_doctor" class="col-3 col-form-label">Admitting Doctor:</label>
        <div class="col-9">
            <input type="text" id="admitting_doctor" name="admitting_doctor" class="form-control" autocomplete="off" placeholder="Admitting Doctor Name">
        </div>
    </div>
    <div class="form-group row">
        <label for="admitting_doctor_tel_fax" class="col-3 col-form-label">Tel and Fax No:</label>
        <div class="col-9">
            <input type="text" id="admitting_doctor_tel_fax" name="admitting_doctor_tel_fax" class="form-control" autocomplete="off" placeholder="Admitting Doctor Tel and Fax No">
        </div>
    </div>
    <div class="form-group row">
        <label for="admission_date" class="col-3 col-form-label">Admission Date:</label>
        <div class="col-4">
            <input type="text" id="admission_date" name="admission_date" class="form-control kt_datepicker_1 required" title="Please enter admission date" autocomplete="off" placeholder="Select admission date">
        </div>
        <label for="admission_time" class="col-2 col-form-label">Time:</label>
        <div class="col-3">
            <input type="text" id="admission_time" name="admission_time" class="form-control kt_timepicker" autocomplete="off" placeholder="Admission time">
        </div>
    </div>
    <div class="form-group row">
        <label for="discharge_date" class="col-3 col-form-label">Discharge Date:</label>
        <div class="col-4">
            <input type="text" id="discharge_date" name="discharge_date" class="form-control kt_datepicker_1" autocomplete="off" placeholder="Select discharge date">
        </div>
        <label for="discharge_time" class="col-2 col-form-label">Time:</label>
        <div class="col-3">
            <input type="text" id="discharge_time" name="discharge_time" class="form-control kt_timepicker" autocomplete="off" placeholder="Discharge time">
        </div>
    </div>

    <div class="kt-separator kt-separator--space-md kt-separator--border-dashed"></div>

    <!-- Questions Section -->
    <h4 class="kt-heading kt-heading--md"><strong>Claim Information</strong></h4>
    <div class="form-group row">
        <label for="hospitalisation_type" class="col-12 col-form-label">Was hospitalisation due to an accident or sickness?</label>
        <div class="col-12">
            <select id="hospitalisation_type" name="hospitalisation_type" class="form-control kt_selectpicker required" title="Please select">
                <option value="">Select</option>
                <option value="accident">Accident</option>
                <option value="sickness">Sickness</option>
                <option value="pregnancy">Pregnancy</option>
                <option value="injury">Injury</option>
            </select>
        </div>
    </div>
    
    <!-- Accident Field -->
    <div class="form-group row" id="accident_field" style="display: none;">
        <label for="accident_reported" class="col-12 col-form-label">Please provide the case number</label>
        <div class="col-12">
            <input type="text" id="accident_reported" name="accident_reported" class="form-control" autocomplete="off" placeholder="Case number">
        </div>
    </div>
    
    <!-- Sickness Field -->
    <div class="form-group row" id="sickness_field" style="display: none;">
        <label for="symptoms_first_appeared" class="col-12 col-form-label">When did symptoms first appear?</label>
        <div class="col-12">
            <input type="text" id="symptoms_first_appeared" name="symptoms_first_appeared" class="form-control kt_datepicker_1" autocomplete="off" placeholder="Select date when symptoms first appeared">
        </div>
    </div>
    
    <!-- Pregnancy Fields -->
    <div id="pregnancy_field" style="display: none;">
        <div class="form-group row">
            <label for="pregnancy_conception_date" class="col-12 col-form-label">Approximate date of conception</label>
            <div class="col-12">
                <input type="text" id="pregnancy_conception_date" name="pregnancy_conception_date" class="form-control kt_datepicker_1" autocomplete="off" placeholder="Select date of conception">
            </div>
        </div>
        <div class="form-group row">
            <label for="pregnancy_delivery_date" class="col-12 col-form-label">Date of delivery</label>
            <div class="col-12">
                <input type="text" id="pregnancy_delivery_date" name="pregnancy_delivery_date" class="form-control kt_datepicker_1" autocomplete="off" placeholder="Select date of delivery">
            </div>
        </div>
    </div>
    
    <!-- Injury Field -->
    <div class="form-group row" id="injury_field" style="display: none;">
        <label for="injury_date" class="col-12 col-form-label">When did the accident occur?</label>
        <div class="col-12">
            <input type="text" id="injury_date" name="injury_date" class="form-control kt_datepicker_1" autocomplete="off" placeholder="Select accident date">
        </div>
    </div>
    <div class="form-group row">
        <label for="accident_circumstances" class="col-12 col-form-label">Describe the circumstances surrounding the accident?</label>
        <div class="col-12">
            <textarea id="accident_circumstances" name="accident_circumstances" class="form-control" rows="3" placeholder="Describe the circumstances"></textarea>
        </div>
    </div>
    <div class="form-group row">
        <label for="first_consultation_date" class="col-12 col-form-label">When did you first consult a doctor for this condition?</label>
        <div class="col-12">
            <input type="text" id="first_consultation_date" name="first_consultation_date" class="form-control kt_datepicker_1" autocomplete="off" placeholder="Select first consultation date">
        </div>
    </div>
    <div class="form-group row">
        <label class="col-12 col-form-label">Is the patient on a medical scheme?</label>
        <div class="col-12">
            <div class="kt-radio-inline">
                <label class="kt-radio">
                    <input type="radio" name="is_medical_scheme" id="is_medical_scheme_yes" value="yes"> Yes
                    <span></span>
                </label>
                <label class="kt-radio">
                    <input type="radio" name="is_medical_scheme" id="is_medical_scheme_no" value="no" checked> No
                    <span></span>
                </label>
            </div>
        </div>
    </div>
    <div id="medical_scheme_fields" style="display: none;">
        <div class="form-group row">
            <label for="medical_scheme_name" class="col-12 col-form-label">Please supply name</label>
            <div class="col-12">
                <input type="text" id="medical_scheme_name" name="medical_scheme_name" class="form-control" autocomplete="off" placeholder="Medical scheme name">
            </div>
        </div>
        <div class="form-group row">
            <label for="medical_aid_number" class="col-12 col-form-label">Please provide medical aid number</label>
            <div class="col-12">
                <input type="text" id="medical_aid_number" name="medical_aid_number" class="form-control" autocomplete="off" placeholder="Medical aid number">
            </div>
        </div>
    </div>
    <div class="form-group row">
        <label class="col-12 col-form-label">Does the patient have any other Hospital Insurance policies?</label>
        <div class="col-12">
            <div class="kt-radio-inline">
                <label class="kt-radio">
                    <input type="radio" name="has_other_insurance" id="has_other_insurance_yes" value="yes"> Yes
                    <span></span>
                </label>
                <label class="kt-radio">
                    <input type="radio" name="has_other_insurance" id="has_other_insurance_no" value="no" checked> No
                    <span></span>
                </label>
            </div>
        </div>
    </div>
    <div id="other_insurance_fields" style="display: none;">
        <div class="form-group row">
            <label for="other_insurance_company_name" class="col-12 col-form-label">Please supply Company name</label>
            <div class="col-12">
                <input type="text" id="other_insurance_company_name" name="other_insurance_company_name" class="form-control" autocomplete="off" placeholder="Company name">
            </div>
        </div>
        <div class="form-group row">
            <label for="other_insurance_policy_numbers" class="col-12 col-form-label">Please provide policy numbers</label>
            <div class="col-12">
                <input type="text" id="other_insurance_policy_numbers" name="other_insurance_policy_numbers" class="form-control" autocomplete="off" placeholder="Policy numbers">
            </div>
        </div>
    </div>

    <div class="kt-separator kt-separator--space-md kt-separator--border-dashed"></div>

    <div class="form-group row">
        <div class="col-12">
            <button type="submit" class="btn btn-success btn-lg">Submit Claim</button>
            <a href="{{ route('admin.policy.index') }}" class="btn btn-secondary btn-lg">Cancel</a>
        </div>
    </div>
</form>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const relationshipSelect = document.getElementById('relationship');
        const relationshipOtherField = document.getElementById('relationship_other_field');
        const relationshipOtherInput = document.getElementById('relationship_other');
        
        if (relationshipSelect && relationshipOtherField) {
            // Show/hide "Other" field based on selection
            relationshipSelect.addEventListener('change', function() {
                if (this.value === 'Other') {
                    relationshipOtherField.style.display = 'block';
                    relationshipOtherInput.setAttribute('required', 'required');
                } else {
                    relationshipOtherField.style.display = 'none';
                    relationshipOtherInput.removeAttribute('required');
                    relationshipOtherInput.value = '';
                }
            });
            
            // Check initial value on page load
            if (relationshipSelect.value === 'Other') {
                relationshipOtherField.style.display = 'block';
                relationshipOtherInput.setAttribute('required', 'required');
            }
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
    });
</script>
