<?php

namespace AlphaDirect\Models\Partner;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Partner company (courier / retailer) — backed by the existing
 * `atc_couriers` registry on mysql_system. company_code is the key the
 * Alpha Transit platform and atc_shipments use; agency_id links to the
 * legacy agencies row that commission and reporting scope under.
 */
class PartnerCompany extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $connection = 'mysql_system';
    protected $table      = 'atc_couriers';

    protected $fillable = [
        'company_code', 'name', 'contact_name', 'contact_email', 'contact_phone',
        'agency_id', 'products', 'notes', 'status',
    ];

    protected $casts = [
        'status'   => 'boolean',
        'products' => 'array',
    ];

    public function users()
    {
        return $this->hasMany(PartnerUser::class, 'company_id');
    }

    public function sellsProduct(int $productId): bool
    {
        return in_array($productId, array_map('intval', $this->products ?? []), true);
    }
}
