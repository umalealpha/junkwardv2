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

class Policy extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;
    protected $auditTimestamps = true;

    protected $table = 'policies';
    protected $guarded = ['id'];
    protected $dates = ['policyActivatedDate'];

    /**
     * Prefixes that identify an Instant Insurance ("MIS") policy. The three
     * prefixes are an intentional product split (MIS = legacy retail / single
     * products, MIB = bundles, BUN = legacy bundle) — see
     * docs/instant-insurance-prefixes.md. They are grouped here only so the
     * date-defaulting guard below applies to the whole Instant Insurance family.
     */
    public const INSTANT_PREFIXES = ['MIS', 'MIB', 'BUN'];

    /**
     * Fill the policy term dates on creation when a lightweight Instant
     * Insurance flow forgot to set them. Historically several MIS creation
     * paths (FrontendController, MobileAppController, USSD, ChatBot, …) saved
     * the policy without term_start_date / term_end_date / expiry_date, leaving
     * the Start/End Date columns blank. This fills them in ONLY when null, so
     * any path that already sets proper dates is untouched. Fill-only: it never
     * cancels or blocks the save.
     *
     * Raw DB::table() inserts (e.g. LegalInsuranceController) bypass Eloquent
     * events — those paths already set the dates, so they are unaffected.
     */
    protected static function booted()
    {
        static::creating(function (Policy $policy) {
            $prefix = substr((string) $policy->policyNumber, 0, 3);
            if (!in_array($prefix, self::INSTANT_PREFIXES, true)) {
                return; // not an Instant Insurance policy — leave as-is
            }

            // created_at isn't populated until after this event fires, so use
            // the activation / billing date if present, otherwise "now".
            $start = $policy->policyActivatedDate
                ?? $policy->billingStartDate
                ?? Carbon::now()->format('Y-m-d');
            $start = Carbon::parse($start)->format('Y-m-d');

            if (empty($policy->term_start_date)) {
                $policy->term_start_date = $start;
            }
            if (empty($policy->term_end_date)) {
                $policy->term_end_date = Carbon::parse($policy->term_start_date ?: $start)->addYear()->format('Y-m-d');
            }
            if (empty($policy->expiry_date)) {
                $policy->expiry_date = Carbon::parse($policy->term_start_date ?: $start)->addYear()->format('Y-m-d');
            }
        });

        // DOM/COM only: when a policy is CANCELLED (status -> 2), remove the
        // already-issued FUTURE renewal batch and discard its invoices. The
        // monthly/quarterly auto-renew cron pre-issues the next cycle ahead of
        // time, so a mid-term cancellation otherwise leaves an orphaned ISSUED
        // RENEW action + Invoice ledger rows that keep billing the customer.
        // Every cancel path (V2 CANCEL action issue, legacy cancelPolicy /
        // cancelPolicyFromAll) writes status=2 through Eloquent, so this single
        // model hook covers them all. Tightly gated to the cancel transition on
        // products 7/8 so no other policy save is affected.
        static::updated(function (Policy $policy) {
            if ((int) $policy->status === 2
                && $policy->wasChanged('status')
                && in_array((int) $policy->product_id, [7, 8], true)) {
                self::purgeFutureRenewalBatch($policy);
            }
        });
    }

    /**
     * Soft-delete every FUTURE, already-ISSUED renewal action for the policy
     * (the next monthly/quarterly batch the auto-renew cron pre-issued) and
     * void its invoice ledger rows. Only strictly-future actions are touched
     * (effective_from > today), so the currently in-force term is preserved.
     *
     * Ledger has a deleted_at column but no SoftDeletes trait, so deleted_at is
     * set explicitly — mirrors CleanDuplicateInvoices. Once these rows are gone
     * the CANCEL action is the latest action, so DomComMonthly/QuaterlyAutoRenew
     * skip the policy (their latest-action CANCEL guard) and never recreate it.
     */
    protected static function purgeFutureRenewalBatch(Policy $policy): void
    {
        $futureRenewals = \AlphaDirect\Models\PolicyAction::where('policy_id', $policy->id)
            ->whereIn('transaction_type', ['RENEW', 'ANNIVERSARY-RENEW'])
            ->where('status', 'ISSUED')
            ->whereDate('effective_from', '>', Carbon::today())
            ->whereNull('deleted_at')
            ->get();

        foreach ($futureRenewals as $renewal) {
            // Discard the batch's invoices (Invoice / Invoice Premium / Invoice VAT).
            \AlphaDirect\Ledger::where('policy_id', $policy->id)
                ->where('action_id', $renewal->id)
                ->where('trans_type', 'like', 'Invoice%')
                ->whereNull('deleted_at')
                ->update(['deleted_at' => Carbon::now()]);

            // Soft-delete the pre-issued renewal action itself.
            $renewal->delete();
        }
    }

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

    /**
     * Duplicate guard for Instant Insurance (MIS / MIB / BUN) double-submits.
     *
     * Returns an existing near-identical policy created within the last
     * $withinSeconds for the same customer + product + premium, or null. Call
     * this at the TOP of a creation flow and short-circuit (return the existing
     * policy) when it is non-null, so a retried / double-clicked payment does
     * not mint a second identical active policy.
     *
     * NOTE: some flows (e.g. FrontendController::savePolicyYes) create a fresh
     * customer row per submit, so the customer_id differs between the two
     * duplicates. For those, resolve the customer by identity (omang / cellphone)
     * BEFORE creating the new customer and pass that id here — otherwise the
     * guard cannot see the earlier submission. A short window (default 2 min)
     * keeps deliberate repeat purchases hours apart from being blocked.
     */
    public static function recentInstantDuplicate(int $customerId, $productId, $premium, int $withinSeconds = 120)
    {
        if (!$customerId || !$productId) {
            return null;
        }

        return static::where('customer_id', $customerId)
            ->where('product_id', $productId)
            ->where('premium', $premium)
            ->where('created_at', '>=', Carbon::now()->subSeconds($withinSeconds))
            ->whereIn('status', [0, 1]) // pending or active — ignore cancelled
            ->latest('id')
            ->first();
    }

    /**
     * Identity-aware wrapper around recentInstantDuplicate() for the Instant
     * creation flows that mint a FRESH customer row on every submit (the V1
     * MIS / MIB creators — Mobile/Electronic, Legal, HCB, Third-Party Car,
     * Accidental Death and the bundle creator). Because the new customer_id
     * differs between two double-submits, recentInstantDuplicate() alone would
     * never see the earlier one. Here we first resolve the EXISTING customer by
     * identity (omang / passport via customer_profile / customer_kyc, then
     * cellphone) — exactly the dedupe order the bundle creator already uses —
     * and only then run the duplicate check.
     *
     * Returns the existing near-identical policy when a double-submit is
     * detected, or null (genuine first submit / no prior customer) so the
     * caller proceeds to create normally.
     */
    public static function recentInstantDuplicateByIdentity(?string $omang, ?string $passport, ?string $cellphone, $productId, $premium, int $withinSeconds = 120)
    {
        $customerId = static::resolveInstantCustomerId($omang, $passport, $cellphone);
        if (!$customerId) {
            return null;
        }

        return static::recentInstantDuplicate($customerId, $productId, $premium, $withinSeconds);
    }

    /**
     * Resolve an existing customer id from identity: omang → passport
     * (customer_profile, then customer_kyc), falling back to cellphone on the
     * customer table. Returns null when none match (first-time customer).
     */
    protected static function resolveInstantCustomerId(?string $omang, ?string $passport, ?string $cellphone): ?int
    {
        foreach ([['omang', $omang, 'omangNumber'], ['passport', $passport, 'passportNumber']] as [$profCol, $val, $kycCol]) {
            if (!$val) {
                continue;
            }
            $p = DB::table('customer_profile')->where($profCol, $val)->orderByDesc('customer_id')->first(['customer_id']);
            if ($p && $p->customer_id) {
                return (int) $p->customer_id;
            }
            if (\Schema::hasTable('customer_kyc')) {
                $k = DB::table('customer_kyc')->where($kycCol, $val)->orderByDesc('customer_id')->first(['customer_id']);
                if ($k && $k->customer_id) {
                    return (int) $k->customer_id;
                }
            }
        }

        if ($cellphone) {
            $c = DB::table('customer')->where('cellphone', $cellphone)->orderByDesc('id')->first(['id']);
            if ($c && $c->id) {
                return (int) $c->id;
            }
        }

        return null;
    }

    /**
     * Duplicate-prevention (parity with graphiteBWV8): is this vehicle plate
     * already held by a NON-CANCELLED policy? Blocks issuing a new Third Party /
     * Motor Comprehensive policy for a plate already on a draft (0) or active (1)
     * policy; a cancelled policy (status 2) frees the plate. Mirrors
     * FrontendPay\ClaimController::checkVehicleExist as a single indexed query.
     */
    public static function plateInUseByActivePolicy(?string $plate): bool
    {
        $plate = $plate !== null ? trim($plate) : null;
        if (!$plate) {
            return false;
        }

        return DB::table('vehicle as v')
            ->join('policies as p', 'p.id', '=', 'v.policy_id')
            ->where('v.vehiclePlate', $plate)
            ->whereNull('v.deleted_at')
            ->whereIn('p.status', [0, 1]) // draft or active — cancelled (2) is allowed
            ->exists();
    }

    /**
     * Duplicate-prevention: does the customer (resolved by identity) already
     * hold a NON-CANCELLED policy of this product? Used to stop a customer
     * buying the same single-cover product twice (Legal / Mobile / ADI).
     * Reuses the same identity resolution as the double-submit guard.
     */
    public static function customerHasActiveProductByIdentity(?string $omang, ?string $passport, ?string $cellphone, $productId): bool
    {
        if (!$productId) {
            return false;
        }
        $customerId = static::resolveInstantCustomerId($omang, $passport, $cellphone);
        if (!$customerId) {
            return false;
        }

        return static::where('customer_id', $customerId)
            ->where('product_id', $productId)
            ->whereIn('status', [0, 1]) // draft or active — cancelled (2) doesn't count
            ->exists();
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
        return $this->hasMany('AlphaDirect\Claim');
    }

    public function transactions()
    {
        return $this->hasMany('AlphaDirect\PaymentTransaction', 'policyNumber', 'policyNumber')->orderBy('id', 'DESC');
    }

    public function Policytransactions()
    {
        return $this->hasMany('AlphaDirect\Transaction', 'policyNumber', 'policyNumber')->orderBy('id', 'DESC');
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

    public function employerGroupPolicies()
    {
        return $this->hasMany('AlphaDirect\Models\EmployerGroupPolicy', 'policy_id', 'id');
    }

    public function scopeFindPolicy($query, $policyNumber)
    {
        return \AlphaDirect\Services\CacheService::rememberPolicyByNumber($policyNumber, function() use ($query, $policyNumber) {
            return $query->with(['profile', 'customer', 'product', 'policyBeneficiaries', 'vehicle', 'transactions', 'devices', 'hospitalCashbackCoapplicants'])
                ->where('policyNumber', $policyNumber)
                ->first();
        });
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
        return \AlphaDirect\Services\CacheService::rememberPolicyByNumber($policyNumber, function() use ($query, $policyNumber) {
            return $query->with(['profile', 'customer', 'product', 'policyBeneficiaries', 'vehicle', 'transactions', 'devices'])
                ->where('policyNumber', $policyNumber)
                ->first();
        });
    }

    public function getPolicyDataByPolicyNumber($policyNumber)
    {
        return \AlphaDirect\Services\CacheService::rememberPolicyByNumber($policyNumber, function() use ($policyNumber) {
            return $this->where('policyNumber', $policyNumber)->first();
        });
    }

    public function getPolicyDataById($id)
    {
        return \AlphaDirect\Services\CacheService::rememberPolicy($id, function() use ($id) {
            return $this->where('id', $id)->orWhere('policyNumber', $id)->first();
        });
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

    public function risk_address()
    {
        return $this->hasMany(RiskAddress::class,'policy_id', 'id');
    }

    public function customerKycDomCom()
    {
        return $this->hasOne(CustomerKycDomCom::class, 'customer_id', 'customer_id');
    }

    public function hospitalCashbackCoapplicants()
    {
        return $this->hasMany(HospitalCashbackCoapplicants::class, 'policy_id', 'id');
    }
    
}
