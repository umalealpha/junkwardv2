<?php
namespace AlphaDirect;

use Illuminate\Database\Eloquent\Model;

class BeforeUpdatePolicy extends Model
{
    protected $connection = 'mysql2';
    protected $table = 'before_update_policy';
    protected $dates = ['policyActivatedDate'];

}
