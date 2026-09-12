<?php

namespace Modules\Inventory\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Arr;
class Partners extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    public function generateTags(): array
    {
         return [
             'Partner'.' '.$this->auditEvent,
         ];
    }

    public function transformAudit(array $data): array
    {
         if (Arr::has($data, 'new_values.agent_id')) {
             $data['user_id'] = $data['new_values']['agent_id'];
         }
         return $data;
    }

	use SoftDeletes;
    protected $fillable = ['name','status'];
    protected $table='store_partners';

    protected static function newFactory()
    {
        return \Modules\Inventory\Database\factories\PartnersFactory::new();
    }
}
