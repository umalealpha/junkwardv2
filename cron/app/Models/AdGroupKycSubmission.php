<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;

class AdGroupKycSubmission extends Model
{
    protected $table = 'ad_group_kyc_submissions';

    protected $fillable = [
        'customer_id',
        'policy_id',
        'employer_group_id',
        'form_last_completed',
        'company_name',
        'registration_no',
        'tin_number',
        'vat_number',
        'country_of_incorporation',
        'corporate_email',
        'postal_address',
        'corporate_physical_address',
        'website',
        'corporate_telephone',
        'type_of_business',
        'business_description',
        'contact_title',
        'contact_names',
        'contact_surname',
        'contact_date_of_birth',
        'contact_national_id',
        'contact_nationality',
        'contact_position',
        'contact_email',
        'contact_telephone',
        'contact_fax',
        'contact_physical_address',
        'contact_village',
        'contact_country',
        'additional_contact_name',
        'additional_contact_surname',
        'additional_contact_telephone',
        'additional_contact_mobile',
        'additional_contact_email',
        'account_name',
        'account_number',
        'bank_name',
        'bank_branch',
        'branch_code',
        'high_risk_country_involvement',
        'high_risk_country_details',
        'complex_ownership_structure',
        'complex_ownership_details',
        'other_high_risk_indicators',
        'consent',
        'declaration_full_name',
        'declaration_designation',
        'declaration_date',
        'declaration_place',
        'declaration_signature',
        // Directors 1..5
        'director1_full_name','director1_residential_address','director1_date_of_birth','director1_nationality','director1_pip_declaration','director1_source_of_wealth',
        'director2_full_name','director2_residential_address','director2_date_of_birth','director2_nationality','director2_pip_declaration','director2_source_of_wealth',
        'director3_full_name','director3_residential_address','director3_date_of_birth','director3_nationality','director3_pip_declaration','director3_source_of_wealth',
        'director4_full_name','director4_residential_address','director4_date_of_birth','director4_nationality','director4_pip_declaration','director4_source_of_wealth',
        'director5_full_name','director5_residential_address','director5_date_of_birth','director5_nationality','director5_pip_declaration','director5_source_of_wealth',
        // Shareholders 1..5
        'shareholder1_full_name','shareholder1_residential_address','shareholder1_date_of_birth','shareholder1_nationality','shareholder1_ownership_percentage','shareholder1_pip_declaration','shareholder1_source_of_wealth',
        'shareholder2_full_name','shareholder2_residential_address','shareholder2_date_of_birth','shareholder2_nationality','shareholder2_ownership_percentage','shareholder2_pip_declaration','shareholder2_source_of_wealth',
        'shareholder3_full_name','shareholder3_residential_address','shareholder3_date_of_birth','shareholder3_nationality','shareholder3_ownership_percentage','shareholder3_pip_declaration','shareholder3_source_of_wealth',
        'shareholder4_full_name','shareholder4_residential_address','shareholder4_date_of_birth','shareholder4_nationality','shareholder4_ownership_percentage','shareholder4_pip_declaration','shareholder4_source_of_wealth',
        'shareholder5_full_name','shareholder5_residential_address','shareholder5_date_of_birth','shareholder5_nationality','shareholder5_ownership_percentage','shareholder5_pip_declaration','shareholder5_source_of_wealth',
        // Documents
        'certificate_of_incorporation_path','certificate_of_incorporation_url','certificate_of_incorporation_original_name','certificate_of_incorporation_mime_type','certificate_of_incorporation_size',
        'proof_of_business_address_path','proof_of_business_address_url','proof_of_business_address_original_name','proof_of_business_address_mime_type','proof_of_business_address_size',
        'ownership_control_structure_path','ownership_control_structure_url','ownership_control_structure_original_name','ownership_control_structure_mime_type','ownership_control_structure_size',
        'proof_residential_address_directors_path','proof_residential_address_directors_url','proof_residential_address_directors_original_name','proof_residential_address_directors_mime_type','proof_residential_address_directors_size',
        'senior_managing_officer_id_path','senior_managing_officer_id_url','senior_managing_officer_id_original_name','senior_managing_officer_id_mime_type','senior_managing_officer_id_size',
        'certified_id_passport_directors_path','certified_id_passport_directors_url','certified_id_passport_directors_original_name','certified_id_passport_directors_mime_type','certified_id_passport_directors_size',
        'resolution_authorised_persons_path','resolution_authorised_persons_url','resolution_authorised_persons_original_name','resolution_authorised_persons_mime_type','resolution_authorised_persons_size',
        'id_authorised_persons_path','id_authorised_persons_url','id_authorised_persons_original_name','id_authorised_persons_mime_type','id_authorised_persons_size',
        'trading_license_path','trading_license_url','trading_license_original_name','trading_license_mime_type','trading_license_size',
        'tax_vat_registration_path','tax_vat_registration_url','tax_vat_registration_original_name','tax_vat_registration_mime_type','tax_vat_registration_size',
        'proof_source_funds_path','proof_source_funds_url','proof_source_funds_original_name','proof_source_funds_mime_type','proof_source_funds_size',
        'bank_confirmation_letter_path','bank_confirmation_letter_url','bank_confirmation_letter_original_name','bank_confirmation_letter_mime_type','bank_confirmation_letter_size',
        'tax_clearance_certificate_path','tax_clearance_certificate_url','tax_clearance_certificate_original_name','tax_clearance_certificate_mime_type','tax_clearance_certificate_size'
    ];

    protected $casts = [
        'consent' => 'boolean',
        'contact_date_of_birth' => 'date',
        'declaration_date' => 'date',
        // Director dates
        'director1_date_of_birth' => 'date','director2_date_of_birth' => 'date','director3_date_of_birth' => 'date','director4_date_of_birth' => 'date','director5_date_of_birth' => 'date',
        // Shareholder dates
        'shareholder1_date_of_birth' => 'date','shareholder2_date_of_birth' => 'date','shareholder3_date_of_birth' => 'date','shareholder4_date_of_birth' => 'date','shareholder5_date_of_birth' => 'date',
    ];

    /**
     * Get the directors for this submission
     */
    public function directors()
    {
        return $this->hasMany(AdGroupKycDirector::class, 'submission_id');
    }

    /**
     * Get the shareholders for this submission
     */
    public function shareholders()
    {
        return $this->hasMany(AdGroupKycShareholder::class, 'submission_id');
    }

    /**
     * Get the documents for this submission
     */
    public function documents()
    {
        return $this->hasMany(AdGroupKycDocument::class, 'submission_id');
    }
}


