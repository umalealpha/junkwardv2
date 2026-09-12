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
     * HR emails, parsed from bulk_emails. Legacy rows hold either a JSON
     * array or a comma/newline-separated string — every V8 reader
     * re-implemented this parse; keep the single canonical copy here.
     */
    public function getHrEmailsAttribute(): array
    {
        $raw = $this->attributes['bulk_emails'] ?? null;
        if (empty($raw)) return [];
        if (is_array($raw)) return array_values(array_filter($raw));

        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return array_values(array_filter(array_map('trim', $decoded)));
        }
        return array_values(array_filter(array_map('trim', preg_split('/[,\n;]+/', $raw))));
    }

    /**
     * Normalize writes to a canonical JSON array (accepts an array or a
     * comma/newline-delimited string). Reads stay backward compatible via
     * the accessor above.
     */
    public function setBulkEmailsAttribute($value): void
    {
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            $value = (json_last_error() === JSON_ERROR_NONE && is_array($decoded))
                ? $decoded
                : preg_split('/[,\n;]+/', $value);
        }
        $emails = is_array($value) ? array_values(array_filter(array_map('trim', $value))) : [];
        $this->attributes['bulk_emails'] = $emails ? json_encode($emails) : null;
    }

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
