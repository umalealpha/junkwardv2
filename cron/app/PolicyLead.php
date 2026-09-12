<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use AlphaDirect\PolicyLead as Leads;
use OwenIt\Auditing\Contracts\Auditable;


class PolicyLead extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table = 'policy_leads';


    public function saveLead($leadInfo){

        $query = new Leads();
        $query->agent_id = $leadInfo->agent_id;
        $query->fname = $leadInfo->first_name;
        $query->lname = $leadInfo->last_name;
        $query->email = $leadInfo->email;
        $query->cellphone = $leadInfo->cellphone;
        $query->has_purchased = $leadInfo->has_purchased;
        $query->product = $leadInfo->product_name;

        return $query->save();

    }
}
