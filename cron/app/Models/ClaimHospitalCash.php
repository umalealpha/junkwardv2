<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClaimHospitalCash extends Model
{
    use HasFactory;
    protected $table = 'claim_hospital_cash';
    protected $fillable = [
        'claim_id',
        'patient_name',
        'patient_dob',
        'patient_identity_number',
        'patient_dob_id',
        'relationship',
        'relationship_other',
        'occupation_date',
        'gp_name',
        'gp_postal_address',
        'gp_cellular_no',
        'gp_telephone_no',
        'gp_fax_no',
        'hospital_name',
        'hospital_tel_fax',
        'admitting_doctor',
        'admitting_doctor_tel_fax',
        'admission_date',
        'admission_time',
        'discharge_date',
        'discharge_time',
        'hospitalisation_type',
        'hospitalisation_reason',
        'accident_reported',
        'symptoms_first_appeared',
        'pregnancy_conception_date',
        'pregnancy_delivery_date',
        'pregnancy_info',
        'injury_date',
        'accident_circumstances',
        'first_consultation_date',
        'medical_scheme_name',
        'medical_aid_number',
        'medical_scheme',
        'other_insurance_company_name',
        'other_insurance_policy_numbers',
        'other_insurance',
        'signed_by',
        'claim_date'
    ];
    protected $guarded = ['id'];
}
