<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Role extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

  protected $table = 'roles';
  public function users(){

    return $this->belongsToMany('AlphaDirect\User','user_roles','role_id','user_id');
  }

  public function employees(){

    return $this->belongsToMany('AlphaDirect\User','user_roles','role_id','user_id');
  }


}
