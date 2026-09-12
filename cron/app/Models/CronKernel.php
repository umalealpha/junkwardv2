<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CronKernel extends Model
{
    use HasFactory;
    protected $table = 'cron_kernel';
    protected $fillable = [ 'cron_name','run_type','run_time','status','run_on_server' ]; 
}
