<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Agency extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table = 'agencies';
    protected $fillable = ['name'];

    /**
     * Canonical broker/agency name: trim the ends and collapse any run of
     * inner whitespace to a single space. The `agencies.name` collation is
     * latin1_swedish_ci (case-insensitive), so case is already handled by the
     * DB; this only removes the whitespace variance that a plain equality /
     * `unique` rule would otherwise let through (e.g. "Broker" vs "Broker ").
     */
    public static function normalizeName($name): string
    {
        return preg_replace('/\s+/u', ' ', trim((string) $name));
    }

    /**
     * Normalise on every Eloquent write so all model-based paths converge on
     * the same stored form. (Raw query-builder paths must call normalizeName()
     * themselves — they bypass this mutator.)
     */
    public function setNameAttribute($value): void
    {
        $this->attributes['name'] = self::normalizeName($value);
    }

    public static function store($data){
        try{
            if(isset($data['status']) && $data['status'] == 1)
                $data['status'] = 1;
            else
                $data['status'] = 0;

            $name = self::normalizeName($data['name']);
            $exist = Agency::where('name', $name)->exists();

            if($exist == false)
                $agency = new Agency();
            else
                return ['Response' => 'error', 'Message' => 'Agency already exists'];

            $agency->name = $name;
            $agency->status = $data['status'];
            $agency->save();

            return ['Response'=>'success','Message'=>'Agency added successfully'];
        }catch(\Exception $e){
            return ['Response'=>'error','Message'=>$e->getMessage()];
        }
    }

    public static function getAgencies($column,$value){
        try{
            $agency = Agency::where($column,$value)->get(array('id','name'));
            return ['Response'=>'success','Agencies'=>$agency];
        }catch(\Exception $e){
            return ['Response'=>'error','Message'=>$e->getMessage()];
        }
    }
}
