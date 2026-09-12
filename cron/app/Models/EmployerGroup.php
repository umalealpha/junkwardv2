<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployerGroup extends Model
{
    use HasFactory;
    protected $table = 'employer_groups';

    protected $fillable = [
        'employer_group_id',
        'name',
        'industry',
        'address',
        'town',
        'postal_code',
        'contact_name',
        'contact_phone',
        'contact_email',
        'broker',
        // 'primary_agent_name',
        'payment_method',
        'status',
        'no_of_employees',
        'bulk_emails',
        'other_industry',
        'account_name',
        'account_number',
        'bank_name_branch',
        'certificate_file',
        'certificate_filename',
        'tax_certificate_file',
        'tax_certificate_filename',
        'proof_address_file',
        'proof_address_filename',
        'terms_agreement',
        'initials',
        'notes',
        'metadata',
        'created_at',
        'updated_at'
    ];

    /**
     * Relationships
     */

    // One employer group has many employees (customers)
    public function employees()
    {
        return $this->hasMany(\AlphaDirect\Customer::class, 'employer_group_id', 'employer_group_id');
    }

    // One employer group has many dependants (through employees)
    public function dependants()
    {
        return $this->hasManyThrough(
            // \App\Models\Dependant::class,
            \AlphaDirect\Customer::class,
            'employer_group_id', // FK on customers
            'employee_id',       // FK on dependants
            'employer_group_id', // PK on employer_groups
            'id'                 // PK on customers
        );
    }
}
