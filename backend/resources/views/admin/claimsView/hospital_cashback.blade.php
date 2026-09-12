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
            @if(isset($hospitalCash) && $hospitalCash)
                <!-- Registered Claim Date -->
                <div class="form-group row">
                    <label class="col-3 col-form-label"><strong>Registered Claim Date:</strong></label>
                    <div class="col-9">
                        <p class="form-control-static">
                            @if($claims->registered_claim)
                                {{ \Carbon\Carbon::parse($claims->registered_claim)->format('d-m-Y') }}
                            @else
                                N/A
                            @endif
                        </p>
                    </div>
                </div>

                <div class="kt-separator kt-separator--space-md kt-separator--border-dashed"></div>

                <!-- Patient Section -->
                <h4 class="kt-heading kt-heading--md"><strong>Patient</strong></h4>
                <div class="form-group row">
                    <label class="col-3 col-form-label">Name of Patient:</label>
                    <div class="col-9">
                        <p class="form-control-static">{{ $hospitalCash->patient_name ?? 'N/A' }}</p>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-3 col-form-label">Date of Birth:</label>
                    <div class="col-9">
                        <p class="form-control-static">
                            @if($hospitalCash->patient_dob)
                                {{ \Carbon\Carbon::parse($hospitalCash->patient_dob)->format('d-m-Y') }}
                            @else
                                N/A
                            @endif
                        </p>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-3 col-form-label">Are you a citizen of Botswana?</label>
                    <div class="col-9">
                        <p class="form-control-static">
                            @if(isset($hospitalCash->patient_identity_number) && $hospitalCash->patient_identity_number)
                                @if(is_numeric($hospitalCash->patient_identity_number))
                                    Yes
                                @else
                                    No
                                @endif
                            @else
                                N/A
                            @endif
                        </p>
                    </div>
                </div>
                @if(isset($hospitalCash->patient_identity_number) && $hospitalCash->patient_identity_number)
                    @if(is_numeric($hospitalCash->patient_identity_number))
                        <div class="form-group row">
                            <label class="col-3 col-form-label">Omang:</label>
                            <div class="col-9">
                                <p class="form-control-static">{{ $hospitalCash->patient_identity_number }}</p>
                            </div>
                        </div>
                    @else
                        <div class="form-group row">
                            <label class="col-3 col-form-label">Passport:</label>
                            <div class="col-9">
                                <p class="form-control-static">{{ $hospitalCash->patient_identity_number }}</p>
                            </div>
                        </div>
                    @endif
                @endif
                <div class="form-group row">
                    <label class="col-3 col-form-label">Relationship to Policyholder:</label>
                    <div class="col-9">
                        <p class="form-control-static">{{ $hospitalCash->relationship ?? 'N/A' }}</p>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-3 col-form-label">Occupation Date:</label>
                    <div class="col-9">
                        <p class="form-control-static">
                            @if($hospitalCash->occupation_date)
                                {{ \Carbon\Carbon::parse($hospitalCash->occupation_date)->format('d-m-Y') }}
                            @else
                                N/A
                            @endif
                        </p>
                    </div>
                </div>

                <div class="kt-separator kt-separator--space-md kt-separator--border-dashed"></div>

                <!-- General Practitioner Section -->
                <h4 class="kt-heading kt-heading--md"><strong>General Practitioner (usual family doctor)</strong></h4>
                <div class="form-group row">
                    <label class="col-3 col-form-label">Name:</label>
                    <div class="col-9">
                        <p class="form-control-static">{{ $hospitalCash->gp_name ?? 'N/A' }}</p>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-3 col-form-label">Postal Address:</label>
                    <div class="col-9">
                        <p class="form-control-static">{{ $hospitalCash->gp_postal_address ?? 'N/A' }}</p>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-3 col-form-label">Cellular No:</label>
                    <div class="col-9">
                        <p class="form-control-static">{{ $hospitalCash->gp_cellular_no ?? 'N/A' }}</p>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-3 col-form-label">Telephone No:</label>
                    <div class="col-9">
                        <p class="form-control-static">{{ $hospitalCash->gp_telephone_no ?? 'N/A' }}</p>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-3 col-form-label">Fax No:</label>
                    <div class="col-9">
                        <p class="form-control-static">{{ $hospitalCash->gp_fax_no ?? 'N/A' }}</p>
                    </div>
                </div>

                <div class="kt-separator kt-separator--space-md kt-separator--border-dashed"></div>

                <!-- Hospital Section -->
                <h4 class="kt-heading kt-heading--md"><strong>Hospital</strong></h4>
                <div class="form-group row">
                    <label class="col-3 col-form-label">Name of hospital:</label>
                    <div class="col-9">
                        <p class="form-control-static">{{ $hospitalCash->hospital_name ?? 'N/A' }}</p>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-3 col-form-label">Tel and Fax No:</label>
                    <div class="col-9">
                        <p class="form-control-static">{{ $hospitalCash->hospital_tel_fax ?? 'N/A' }}</p>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-3 col-form-label">Admitting Doctor:</label>
                    <div class="col-9">
                        <p class="form-control-static">{{ $hospitalCash->admitting_doctor ?? 'N/A' }}</p>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-3 col-form-label">Tel and Fax No:</label>
                    <div class="col-9">
                        <p class="form-control-static">{{ $hospitalCash->admitting_doctor_tel_fax ?? 'N/A' }}</p>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-3 col-form-label">Admission Date:</label>
                    <div class="col-4">
                        <p class="form-control-static">
                            @if($hospitalCash->admission_date)
                                {{ \Carbon\Carbon::parse($hospitalCash->admission_date)->format('d-m-Y') }}
                            @else
                                N/A
                            @endif
                        </p>
                    </div>
                    <label class="col-2 col-form-label">Time:</label>
                    <div class="col-3">
                        <p class="form-control-static">{{ $hospitalCash->admission_time ?? 'N/A' }}</p>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-3 col-form-label">Discharge Date:</label>
                    <div class="col-4">
                        <p class="form-control-static">
                            @if($hospitalCash->discharge_date)
                                {{ \Carbon\Carbon::parse($hospitalCash->discharge_date)->format('d-m-Y') }}
                            @else
                                N/A
                            @endif
                        </p>
                    </div>
                    <label class="col-2 col-form-label">Time:</label>
                    <div class="col-3">
                        <p class="form-control-static">{{ $hospitalCash->discharge_time ?? 'N/A' }}</p>
                    </div>
                </div>

                <div class="kt-separator kt-separator--space-md kt-separator--border-dashed"></div>

                <!-- Claim Information Section -->
                <h4 class="kt-heading kt-heading--md"><strong>Claim Information</strong></h4>
                <div class="form-group row">
                    <label class="col-12 col-form-label">Was hospitalisation due to an accident or sickness?</label>
                    <div class="col-12">
                        <p class="form-control-static">{{ $hospitalCash->hospitalisation_reason ?? 'N/A' }}</p>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-12 col-form-label">Was the accident reported to the relevant authority, if so please provide the case number</label>
                    <div class="col-12">
                        <p class="form-control-static">{{ $hospitalCash->accident_reported ?? 'N/A' }}</p>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-12 col-form-label">If sickness, when did symptoms first appear?</label>
                    <div class="col-12">
                        <p class="form-control-static">
                            @if($hospitalCash->symptoms_first_appeared)
                                {{ \Carbon\Carbon::parse($hospitalCash->symptoms_first_appeared)->format('d-m-Y') }}
                            @else
                                N/A
                            @endif
                        </p>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-12 col-form-label">If pregnancy, approximate date of conception and date of delivery</label>
                    <div class="col-12">
                        <p class="form-control-static">{{ $hospitalCash->pregnancy_info ?? 'N/A' }}</p>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-12 col-form-label">If injury, when did the accident occur?</label>
                    <div class="col-12">
                        <p class="form-control-static">
                            @if($hospitalCash->injury_date)
                                {{ \Carbon\Carbon::parse($hospitalCash->injury_date)->format('d-m-Y') }}
                            @else
                                N/A
                            @endif
                        </p>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-12 col-form-label">Describe the circumstances surrounding the accident?</label>
                    <div class="col-12">
                        <p class="form-control-static">{{ $hospitalCash->accident_circumstances ?? 'N/A' }}</p>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-12 col-form-label">When did you first consult a doctor for this condition?</label>
                    <div class="col-12">
                        <p class="form-control-static">
                            @if($hospitalCash->first_consultation_date)
                                {{ \Carbon\Carbon::parse($hospitalCash->first_consultation_date)->format('d-m-Y') }}
                            @else
                                N/A
                            @endif
                        </p>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-12 col-form-label">Is the patient on a medical scheme? (If yes, please supply name and medical aid number)</label>
                    <div class="col-12">
                        <p class="form-control-static">{{ $hospitalCash->medical_scheme ?? 'N/A' }}</p>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-12 col-form-label">Does the patient have any other Hospital Insurance policies? If yes, please supply Company name and policy numbers.</label>
                    <div class="col-12">
                        <p class="form-control-static">{{ $hospitalCash->other_insurance ?? 'N/A' }}</p>
                    </div>
                </div>

                <div class="kt-separator kt-separator--space-md kt-separator--border-dashed"></div>

                <!-- Signature and Date -->
                <div class="form-group row">
                    <label class="col-3 col-form-label">Signed:</label>
                    <div class="col-4">
                        <p class="form-control-static">{{ $hospitalCash->signed_by ?? 'N/A' }}</p>
                    </div>
                    <label class="col-2 col-form-label">Date:</label>
                    <div class="col-3">
                        <p class="form-control-static">
                            @if($hospitalCash->claim_date)
                                {{ \Carbon\Carbon::parse($hospitalCash->claim_date)->format('d-m-Y') }}
                            @else
                                N/A
                            @endif
                        </p>
                    </div>
                </div>
            @else
                <div class="alert alert-info">
                    <p>No Hospital CashBack claim information available.</p>
                </div>
            @endif
        </div>
    </div>
</div>
<!--end::Portlet-->
