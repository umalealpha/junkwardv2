<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Model;

class OrangeTransactions extends Model
{

    protected $table ='orange_transactions';

    protected $fillable = [
        'status','notif_token','txnid'
    ];
}
