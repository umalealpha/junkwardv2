<?php namespace AlphaDirect\Repositories\CustomerKyc;


use AlphaDirect\KYC;

class CustomerKycRepository implements CustomerKycInterface
{
    // policyCellPhone property on class instances
    protected $kyc;

    // Constructor to bind policyCellPhone to repo
    public function __construct(KYC $kyc)
    {
        $this->kyc = $kyc;
    }


    public function add_new_customer_kyc($attributes)
    {
        $kyc = $this->get_customer_kyc_by_id($attributes['customer_id']);
        if($kyc == NULL)
        {
            $kyc = new $this->kyc;
            foreach($attributes as $key => $value) {
                $kyc->$key = $value;
            }
            $kyc->save();
        } else {
            foreach($attributes as $key => $value) {
                $kyc->$key = $value;
            }
            $kyc->save();
        }
        return $kyc->id;
    }

    public function get_customer_kyc_by_id($id) {
        return $this->kyc->where('customer_id',$id)->first();
    }

    public function update_customer_kyc_by_id($id, $attributes) {
        $kyc = $this->get_customer_kyc_by_id($id);
        if($kyc == NULL)
        {
            $attributes['customer_id'] = $id;
            $kyc = $this->add_new_customer_kyc($attributes);
            return $kyc;
        } else {
            foreach($attributes as $key => $value) {
                $kyc->$key = $value;
            }
            $kyc->save();
        }
        return $kyc;
    }

    public function get_customer_kyc_by_token($id) {
        return $this->kyc->where('id',$id)->first();
    }

    public function update_customer_kyc_by_token($id, $attributes) {
        $kyc = $this->get_customer_kyc_by_token($id);
        if($kyc == NULL)
        {
            $attributes['customer_id'] = $id;
            $kyc = $this->add_new_customer_kyc($attributes);
            return $kyc;
        } else {
            foreach($attributes as $key => $value) {
                $kyc->$key = $value;
            }
            $kyc->save();
        }
        return $kyc;
    }

}