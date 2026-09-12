<?php
namespace AlphaDirect;

use AlphaDirect\Models\RiskAddress;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use  AlphaDirect\Country;
use Illuminate\Support\Arr;
use OwenIt\Auditing\Contracts\Auditable;
use DB;
use AlphaDirect\Models\CoverageMaster;

class PolicySonali extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table = 'policies_sonali';
    protected $dates = ['policyActivatedDate'];

    // protected $casts = [
    //     'created_at'  => 'datetime',
    // ];

    // public function getCreatedAtAttribute($value)
    // {
    //     return (new Carbon($value))->format('d/m/Y H:i');
    // }

    //adds in agent id to the data while saving audits
    public function transformAudit(array $data): array
    {

        if (Arr::has($data, 'new_values.agent_id')) {
            $data['agent_id'] = $data['new_values']['agent_id'];
        }
        if($this->auditEvent != 'created'){
		   if (Arr::has($data, 'new_values')) {
				$data['policy_id'] = $this->id;
				$data['policy_number'] = $this->policyNumber;
			}
		}

        //to store customer as a causer in case of APP/API call
        // if (Arr::has($data, 'new_values.lead_source')) {
        //     $data['customer_id'] = $data['new_values']['customer_id'];
        // }
        return $data;
    }
    //adds in activity tag to the data while saving audits
    public function generateTags(): array
    {

        if($this->status == '2'){
            return [
                'Policy'.' '.'Cancelled',
            ];
        }else{
            return [
                'Policy'.' '.$this->auditEvent,
            ];
        }
    }

    public function scopePolicy($query,$policy_id){
        return $query->where('id', $policy_id);
    }

    public function policyrenewal()
    {
        return $this->belongsTo('AlphaDirect\PolicyRenewal', 'policyNumber', 'policyNumber');
    }

    public function transaction(){
        return $this->belongsTo('AlphaDirect\PaymentTransaction','policyNumber','policyNumber');
    }

    public function trans()
    {
        return $this->belongsTo('AlphaDirect\Transaction', 'policyNumber', 'policyNumber');
    }

    public function feedback()
    {
        return $this->belongsTo('AlphaDirect\CustomerFeedback', 'id', 'policy_id');
    }

    public function customer()
    {
        return $this->belongsTo('AlphaDirect\Customer', 'customer_id', 'id');
    }

    public function profile()
    {
        return $this->belongsTo('AlphaDirect\CustomerProfile', 'customer_id', 'customer_id');
    }

    public function activation()
    {
        return $this->hasOne('AlphaDirect\Activation', 'activation_code', 'activation_code');
    }

    public function kyc()
    {
        return $this->hasOne('AlphaDirect\KYC', 'customer_id', 'customer_id')->select('id','status','customer_id', 'compliance', 'driving_license', 'omang', 'proof_residence', 'proof_income', 'passport');
    }

    public function product()
    {
        return $this->hasOne('AlphaDirect\Product', 'id', 'product_id');
    }

    public function store()
    {
        return $this->hasOne('AlphaDirect\Stores', 'id', 'storeID');
    }

    public function plan()
    {
        return $this->hasOne('AlphaDirect\Productplan', 'id', 'plan_id');
    }

    public function vehicle()
    {
        return $this->hasOne('AlphaDirect\Vehicle','policy_id', 'id');
    }

    public function PolicyVehicle()
    {
        return $this->hasMany('AlphaDirect\Vehicle','policy_id', 'id');
    }

    public function policyMembers()
    {
        return $this->hasMany('AlphaDirect\PolicyMember');
    }

    public function policyBeneficiaries()
    {
        return $this->hasMany('AlphaDirect\PolicyBeneficiary');
    }

    public function devices()
    {
        return $this->hasMany('AlphaDirect\PolicyCellPhone');
    }

    public function claim()
    {
        return $this->hasMany('AlphaDirect\Claim')->select();
    }

    public function transactions()
    {
        return $this->hasMany('AlphaDirect\PaymentTransaction', 'policyNumber', 'policyNumber')->select()->orderBy('id', 'DESC');
    }

    public function Policytransactions()
    {
        return $this->hasMany('AlphaDirect\Transaction', 'policyNumber', 'policyNumber')->select()->orderBy('id', 'DESC');
    }


	public function coverage()
    {
        return $this->hasMany('AlphaDirect\Models\PolicyCoverage', 'policy_id', 'id');
    }

    public function PolicyCoverage(){
        return $this->hasMany('AlphaDirect\Models\PolicyCoverage', 'policy_id', 'id');
    }

    public function agency()
    {
        return $this->belongsTo('AlphaDirect\Agency', 'agency_id', 'id');
    }

    public function policyTerm()
    {
        return $this->belongsTo('AlphaDirect\PolicyTerm', 'id', 'policy_id');
    }

    public function user()
    {
        return $this->belongsTo('AlphaDirect\User', 'agent_id', 'id');
    }

    public function scheduleTransactions()
    {
        return $this->hasMany('AlphaDirect\Policy', 'policy_id', 'id');
    }

    public function riskAddress()
    {
        return $this->hasMany(RiskAddress::class,'policy_id', 'id');
    }

    public function scopeFindPolicy($query, $policyNumber)
    {
        return $query->with(['profile', 'customer', 'product', 'policyBeneficiaries', 'vehicle', 'transactions','devices'])
            ->where('policyNumber', $policyNumber)
            ->first();
    }

    public function scopeFindClaim($query, $policyNumber)
    {
        return $query->with(['claim'])
        ->where('policyNumber', $policyNumber)
        ->where($this->claim->claim_type, 'Cellphone')
        ->get();
    }

    public function scopePolicyDetail($query, $policyNumber)
    {
        return $query->with(['profile', 'customer', 'product', 'policyBeneficiaries', 'vehicle', 'transactions', 'devices'])
            ->where('policyNumber', $policyNumber)
            ->first();
    }

    public function getPolicyDataByPolicyNumber($policyNumber)
    {
        return $this->where('policyNumber', $policyNumber)->first();
    }

    public function getPolicyDataById($id)
    {
        return $this->where('id', $id)->orWhere('policyNumber', $id)->first();
    }

    public function scopeFindPolicyWithCustomer($query, $policyNumber)
    {
        return $query->with(['profile', 'customer', ])
            ->where('policyNumber', $policyNumber)
            ->first();
    }

    public function scopeGetPolicyDataByPolicyNumberWithCustomerData($query, $policyNumber)
    {
        return $query->with(['customer'])
            ->where('policyNumber', $policyNumber)
            ->first();
    }

    public function quote()
    {
        return $this->hasOne('AlphaDirect\Quote','quoteCode', 'quoteNumber')->select();
    }

    public static function updateInfo($data){
//        Policy::disableAuditing();
        $policyData = Policy::where('id',$data['id'])->first();

        foreach($data as $key=>$d){
            $policyData->$key = $d;
        }
        $policyData->save();
    }

	public function getStatusDetailsAttribute($value)
    {
        switch ($this->status) {
            case 1:
                return '<span class="badge badge-light-success fw-bold px-4 py-3 me-6">Activated</span>';
                break;
            case 2:
                return '<span class="badge badge-light-danger fw-bold px-4 py-3 me-6">Cancelled</span>';
                break;
            default:
                return '<span class="badge badge-light-danger fw-bold px-4 py-3 me-6">Deactivated</span>';
        }
    }

    public function coverageMaster(){
        return $this->belongsToMany(CoverageMaster::class, 'policy_coverage', 'policy_id', 'coverage_id');
    }

    public function scopeProduct($query,$product_id){
        $query->where('product_id',$product_id);
    }

    public static function statusCount(Carbon $startDate,Carbon $endDate){
        $select = \DB::raw("(CASE
        WHEN status='0' THEN 'Deactivated'
        WHEN status='1' THEN 'Activated'
        WHEN status='2' THEN 'Cancelled'
        WHEN status='3' THEN 'Expired'
        ELSE 'Others' END
        ) as status_name,count(status) as count,created_at");
        return static::select($select)
            ->whereBetween('created_at', [
                $startDate->format('Y-m-d')." 00:00:00",
                $endDate->format('Y-m-d')." 23:59:59"
            ])
            ->groupBy('status_name')->get();
    }

    public function scopeStatus($query,$status){
        $query->where('status',$status);
    }

    public function scopebetweenCreatedDates($query, $startDate, $endDate){
        $query->whereBetween('created_at', [$startDate." 00:00:00",$endDate." 23:59:59"]);
    }

    public static function queryForPaymentTransCountwithDates($date,$productId){
        return Policy::rightJoin('payment_transactions','payment_transactions.policyNumber','=','policies.policyNumber')
                            ->whereDate( 'payment_transactions.new_payment_date', $date)
                            ->whereIn( 'payment_transactions.status', ['SUCCESS', 'Success', 'success'] )
                            ->where( 'policies.product_id', $productId)
                            ->where( 'policies.status', 1)
                            ->orderBy('payment_transactions.id','asc')
                            ->count();
    }

}
