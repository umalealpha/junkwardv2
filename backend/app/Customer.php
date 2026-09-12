<?php

namespace AlphaDirect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Arr;
use AlphaDirect\CustomerProfile;
use Illuminate\Support\Facades\DB;
use AlphaDirect\Policy;


class Customer extends Authenticatable implements Auditable
{
    use Notifiable;
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table = 'customer';
    // protected $fillable = ['email'];
    protected $guarded = ['id'];

    protected $guard = 'customer';

    protected $hidden = [
        'password', 'api_token',
    ];

 public function transformAudit(array $data): array
    {

        if (Arr::has($data, 'new_values')) {

            if($this->auditEvent != 'created'){
             $policy = Policy::where('customer_id', $this->id)->first();
             if($policy != null){
             $data['policy_id'] = $policy->id;
             $data['policy_number'] = $policy->policyNumber;
             }
            }
           }
        //to store customer as a causer in case of APP/API call
        // if (Arr::has($data, 'new_values.lead_source')) {
        //     $data['customer_id'] = $data['new_values']['customer_id'];
        // }

        return $data;
    }
    public function getFirstNameAttribute($value){
        return ucwords(strtolower($value));
    }
	public function getFullNameAttribute(){
        return ucwords(strtolower($this->firstName)) . " ".ucwords(strtolower($this->middleName)) . " ".ucwords(strtolower($this->lastName));
    }
    public function getMiddleNameAttribute($value){
        return ucwords(strtolower($value));
    }

    public function getLastNameAttribute($value)
    {
        return ucwords(strtolower($value));
    }

    public function generateTags(): array
    {
        return [
            'Customer'.' '.$this->auditEvent,
        ];
    }

    public function customer_cashback()
    {
        return $this->hasMany('AlphaDirect\Models\CustomerCashback', 'customer_id', 'id');
    }

    public function profile()
    {
        return $this->hasOne('AlphaDirect\CustomerProfile', 'customer_id', 'id');
    }

    public function policy()
    {
        return $this->hasMany('AlphaDirect\Policy', 'customer_id');
    }

    protected function claims()
    {
        return $this->hasMany('AlphaDirect\Claim', 'customer_id');
    }

    public function banking()
    {
        return $this->hasOne('AlphaDirect\CustomerBanking', 'customer_id', 'id');
    }

    public function lead()
    {
        return $this->hasOne('AlphaDirect\Lead', 'customerId', 'id');
    }

    public function qoute()
    {
        return $this->hasOne('AlphaDirect\Quote', 'customerId', 'id');
    }

    public function KYC()
    {
        return $this->hasOne('AlphaDirect\KYC', 'customer_id', 'id');
    }

    public function transaction()
    {
        return $this->belongsTo('AlphaDirect\Transaction');
    }

    public function scopeCustomerPolicies($query, $customerInfo, $category)
    {

        switch ($category) {

            case "cellPhone":

                return $query->with(['profile', 'policy'])
                    ->where('cellphone', $customerInfo)
                     ->get();

            break;
            case "email":

                return $query->with(['profile', 'policy'])
                    ->where('email',$customerInfo)
                     ->get();

            break;

            case "customerName":
                $searchValues = preg_split('/\s+/', $customerInfo, -1, PREG_SPLIT_NO_EMPTY);
                $query->with(['profile']);
                foreach ($searchValues as $value) {
                    $query->where(function ($q) use ($value) {
                        $q->orWhere('firstName', 'like', '%' . $value . '%')
                        ->orWhere('lastName', 'like', '%' . $value . '%');
                    });
                }
                return $query->get();

            break;

            default:

                return response()->json('Something went wrong with search type');

            break;
        }
    }

    public function updateInfo($customerInfo)
    {

        $query = Customer::where('id', $customerInfo->customer_id)->first();
        if(isset($customerInfo->firstName)){$query->firstName = $customerInfo->firstName;}
        if(isset($customerInfo->middleName)){$query->middleName = $customerInfo->middleName;}
        if(isset($customerInfo->lastName)){$query->lastName = $customerInfo->lastName;}
        if(isset($customerInfo->cellphone)){ $query->cellphone = $customerInfo->cellphone;}
        if(isset( $customerInfo->email)){ $query->email = $customerInfo->email;}
       return $query->save();
    }

    public function updateInfoPay($customer_id,$customerInfo)
    {

        $query = Customer::where('id', $customer_id)->first();

        $query->firstName = $customerInfo->firstName;
        $query->lastName = $customerInfo->lastName;
        $query->cellphone = $customerInfo->cellphone;
        $query->email = $customerInfo->email;
        return $query->save();
    }

    public function scopecheckIfCustomerPhoneNumberExists($query, $cellphone)
    {
        return $query->where('cellphone', $cellphone)->exists();
    }

    public function scopegetCustomerDataUsingCellphone($query, $cellphone)
    {
        return $query->where('cellphone', $cellphone)->get();
    }

    public function scopeCustomerClaim($query, $customerInfo, $category)
    {
        switch ($category) {

            case "customerName":

                // return
                // $query->with(['profile', 'policy'])
                // ->orWhereRaw("concat(firstName, ' ', lastName) like '%' .  $customerInfo . '%' ")
                // ->orWhere(DB::raw('CONCAT_WS(" ", firstName, lastName)'), 'like', '%' .  $customerInfo . '%')
                // ->orWhere('firstName', 'like', '%' . $customerInfo . '%')
                // ->orWhere('middleName', 'like', '%' . $customerInfo . '%')
                // ->orWhere('lastName', 'like', '%' . $customerInfo . '%')
                // ->get();

                $searchValues = preg_split('/\s+/', $customerInfo, -1, PREG_SPLIT_NO_EMPTY);
                $query->with(['profile', 'policy']);
                foreach ($searchValues as $value) {
                    $query
                        ->orWhere('firstName', 'like', '%' . $value . '%')
                        ->orWhere('lastName', 'like', '%' . $value . '%');
                }
                return $query->get();

            break;
            case "cellPhone":

                return
                $query->with(['profile', 'policy'])
                ->orWhere('cellphone', 'like', '%' . $customerInfo . '%')
                    ->get();
            break;

            default:

                return response()->json('Something went wrong with search type');

            break;
        }
    }

    public static function queryForListedCustomerCountWithStatusBetweenDates($startDate,$endDate,$customerCategory=null){
        $startDate = $startDate->format('Y-m-d');
        $endDate = $endDate->format('Y-m-d');
        return Customer::whereBetween( 'created_at', [$startDate." 00:00:00",$endDate." 23:59:59"])
                        ->where('customer_category',$customerCategory)
                        ->orderBy('id','desc')
                        ->count();
    }

    public function rewardTier()
    {
        return $this->belongsTo(\AlphaDirect\Models\RewardTier::class, 'tier_id');
    }

    public function customerRewards()
    {
        return $this->hasMany(\AlphaDirect\Models\CustomerBenefit::class, 'customer_id');
    }

    public function customerPoints()
    {
        return $this->hasMany(CustomerPoint::class, 'customer_id');
    }

    /**
     * Get total active points from customer_point table
     */
    public function getTotalActivePoints()
    {
        return $this->customerPoints()
            ->active()
            ->sum('point');
    }

    /**
     * Get all active points with details
     */
    public function getActivePoints()
    {
        return $this->customerPoints()
            ->active()
            ->orderBy('created_at', 'desc')
            ->get();
    }

}
