<?php

namespace AlphaDirect\Http\Livewire\Policy;

use Illuminate\Support\Facades\DB;

use AlphaDirect\Customer;
use AlphaDirect\Http\Traits\Policy\AddCoverageTrait;
use AlphaDirect\Models\Company;
use AlphaDirect\Models\PolicyAction;
use AlphaDirect\PolicyTerm;
use Livewire\Component;
use AlphaDirect\Policy;
use AlphaDirect\Models\newPolicyCoverages;
use AlphaDirect\Models\RiskAddress;
use AlphaDirect\Models\PolicyCoverage;
use AlphaDirect\Models\PolicyCoverageNote;
use AlphaDirect\Models\ReinsuranceTreaty;
use AlphaDirect\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redirect;
use AlphaDirect\Models\PolicyCoverageDetail;
use AlphaDirect\Vehicle;
use AlphaDirect\Models\Motor;

use function PHPUnit\Framework\isNull;

class EditWizard extends Component
{

    use AddCoverageTrait;
	public $tab=1;
    public $legaltab=1;
	public $step=1;
	public $editmode=true;
	public $policies;
	public $selectedAgency;
	public $customer_profile,$customer;
    public $Application_Update = true;
	public $isPrevious = false;
	public $transaction;
	public $agents=[];
	public $allPlan=[];
	public $allCities=[];
	public $employment=[];
	public $sourceOfIncome;
	public $selectedTermId;
    public $actionId;
    public $previousActionId;
    public $action;
    public $policyAction;
    public $PolicyTerm;
    public $customer_profile_date;
	public $customer_profile_dob;
    Public $term_start_date;
    public $policies_expiry_date;
	public $newActionDates;
	public $riskAddressId;
	public $restricted_editable = 0;
        // public $expandedYears = [];
  protected $listeners = [
        'refreshParent','refreshlapse' => 'refreshParent',
    ];
	public $policyCoverageRiskAddress = [
        'riskAddressId' => null,
    ];
    protected $queryString = ['tab','actionId','selectedTermId','previousActionId'];
	
 public function refreshLapseData($actionId)
{
    // reload only that action data (example)
    $this->actionId = $actionId; // if your component uses this
    $this->policies = PolicyAction::find($actionId); // or whatever you use to display it

    // Livewire will re-render after the method ends
    $this->dispatchBrowserEvent('alert', [
        'type' => 'success',
        'message' => 'Lapsed Successfully'
    ]);
}
    public function refreshParent($actionId=null,$selectedTermId=null)
    {
        $this->mount($this->policies->id);
        if($actionId != null){
            $this->actionId = $actionId;
        }
        if($selectedTermId != null){
            $this->selectedTermId = $selectedTermId;
        }
        $this->render();
    }

    public function rules(){
		return [
			'policyCoverageRiskAddress.riskAddressId' => 'required|integer',
			'selectedAgency'=>'', #step1
			'policies.premium_freq'=>'', #step1
			// 'policies.term_start_date'=>'', #step1
            'term_start_date'=>'', #step1
			'policies_expiry_date'=>'', #step1
			'customer_profile.binder_date'=>'', #step1
			'customer_profile.select_product'=>'', #step1
			'policies.policyNumber'=>'', #step1
			// 'customer_profile.estm'=>'', #step1
			'policies.agency_id'=>'', #step1
			'policies.agent_id'=>'', #step1
			'policies.status'=>'', #step1
            'policies.uw_app_status'=>'', #step1
			'policies.product_id'=>'', #step1
			'policies.plan_id'=>'', #step1
            'policies.gfs_policy_no'=>'', #step1
			'customer_profile.entity_type'=>'', #step1
			'customer_profile.company_id'=>'', #step1
			'customer.firstName'=>'', #step1
			'customer.middleName'=>'', #step1
			'customer.lastName'=>'', #step1
			'customer_profile.gender'=>'', #step1
			'customer_profile.maritalstatus'=>'', #step1
			'customer_profile_dob'=>'', #step1
			'customer_profile.omang'=>'', #step1
			'customer_profile.passport'=>'', #step1
			// 'customer_profile.know_name'=>'', #step1
			'customer_profile.post_address'=>'', #step1
			'customer_profile.state'=>'', #step1
			'customer_profile.city'=>'', #step1
			'customer.email'=>'', #step1
			'customer.cellphone'=>'', #step1
			'customer_profile_date'=>'', #step1
			'customer_profile.business_note'=>'', #step1
			'customer_profile.decline_proposal'=>'', #step1
			'customer_profile.refused_policy'=>'', #step1
            'customer_profile.cancel_policy'=>'', #step1
			'customer_profile.insure'=>'', #step1
			'customer_profile.firm_member'=>'', #step1
			'customer_profile.books'=>'', #step1
			'customer_profile.about_alpha'=>'', #step1
			'sourceOfIncome'=>'',
			'employment.*'=>''
		];
	}
	protected $messages = [];


	public function mount($policyId = null){
		DB::update("UPDATE ip_add SET ip_address = ? WHERE id = ?", [$_SERVER['REMOTE_ADDR'], 1]);
		$selectIP = DB::select("SELECT ip_address FROM ip_add WHERE id = ?", [1]);
		$ip_address = $selectIP[0]->ip_address;

	    if (is_null($policyId)){
            $this->policies= Policy::findorfail(\Crypt::decrypt(request()->route('id')));
        }else{
            $this->policies= Policy::findorfail($policyId);
        }

		$this->customer = $this->policies->customer;
		$this->transaction = $this->policies->trans;
		$this->kyc=$this->policies->kyc;
		$this->feedback = $this->policies->feedback;
		$this->customer_profile = $this->policies->profile;
		$this->customer_profile->decline_proposal = ($this->customer_profile->decline_proposal==1)?true:false;
		$this->customer_profile->refused_policy = ($this->customer_profile->refused_policy==1)?true:false;
		$this->customer_profile->cancel_policy = ($this->customer_profile->cancel_policy==1)?true:false;
		if(($this->customer_profile->select_product)=='Commercial All Risk'){
			$this->customer_profile->firm_member = ($this->customer_profile->firm_member==1)?true:false;
			$this->customer_profile->books = ($this->customer_profile->books==1)?true:false;
		}
		// Company-only products (20 / 23 / 24) are Annual-term; 23 / 24 are
		// also Organisation-held (Commercial Liabilities 20 takes either
		// holder per UW 2026-08-26, so its entity_type is left as stored).
		// Set it on LOAD as well as on save, so a policy created before this
		// rule opens with the right shape — otherwise the Entity Type select
		// (Organisation only for 23 / 24) would render with a stale 'Person'
		// value and the Alpine x-show blocks would keep the individual
		// customer fields on screen until the operator touched it.
		if (\AlphaDirect\Support\CompanyOnlyProducts::includes($this->policies->product_id)) {
			$this->policies->premium_freq = \AlphaDirect\Support\CompanyOnlyProducts::ANNUAL_FREQ;
		}
		if (\AlphaDirect\Support\CompanyOnlyProducts::entityLocked($this->policies->product_id)) {
			$this->customer_profile->entity_type = \AlphaDirect\Support\CompanyOnlyProducts::ENTITY_TYPE;
		}
		$this->agency= new \AlphaDirect\Agency();

		// if($this->tab===1){
		// 	$this->agents= \AlphaDirect\User::where('agency_id', $this->policies->agency_id)
		// 	 ->get()->keyBy('id')->map(function($d){
		// 		return [
		// 			'id'=>$d->id,
		// 			'name'=>$d->full_name
		// 		];
		// 	});
		// }#Need to discuss with Team for this issue SO I have commented this code
		$this->agents= \AlphaDirect\User::where('agency_id', $this->policies->agency_id)
		->get()->keyBy('id')->map(function($d){
		   return [
			   'id'=>$d->id,
			   'name'=>$d->full_name
		   ];
	   });
        $this->updateHasStatus();
        $this->PolicyTerm = PolicyTerm::firstOrCreate(['policy_id'=>$this->policies->id]);
        if(!isset($this->selectedTermId)){
            $this->selectedTermId = $this->policyTerms[0]->id ?? null;
        }
		$this->newActionDates=PolicyAction::where('id',$this->actionId)->first();
       // $this->policyAction = PolicyAction::firstOrCreate(['policy_id'=>$this->policies->id,'term_id'=>$this->selectedTermId]);
        $action = PolicyAction::Term($this->selectedTermId)->Policy($this->policies->id)->orderBy('id', 'desc')->first();
        if(isset($action->id)){
            $this->policyAction = $action;
        }else{
            $this->policyAction = PolicyAction::Create(['policy_id'=>$this->policies->id,'term_id'=>$this->selectedTermId]);
        }
        $this->actionId = $this->actionId ?? $this->policyAction->id;
        // Work on the SELECTED action (actionId) rather than latest-in-term, so header cards,
        // dates and the frequency dropdown reflect the action actually being edited. This mirrors
        // what updatedActionId() already does when the action dropdown is changed.
        $selectedAction = PolicyAction::find($this->actionId);
        if ($selectedAction) {
            $this->policyAction = $selectedAction;
        }
        $this->previousActionId = $this->previousActionId ?? $this->policyAction->previous_action_id;
		$this->action = $this->policyAction;

		if($_SERVER['REMOTE_ADDR']== $ip_address) {
			//dd($this->policies->id,$this->selectedTermId,$this->policyAction->id);
			$this->risk_address_id = RiskAddress::where('policy_id', $this->policies->id)
			->where('term_id', $this->selectedTermId)
			->where('action_id', $this->policyAction->id)
			->first();
			//dd($this->risk_address_id);
			if(isset($this->risk_address_id))
			{
				$this->riskAddressId = $this->risk_address_id->id ?? $this->nextRecord->id;
				$this->riskAddressId = $this->risk_address_id->id ?? '';
				$this->previousRecord = RiskAddress::where('id', '<', $this->riskAddressId)
					->where('term_id', $this->selectedTermId)
					->where('action_id', $this->policyAction->id)
					->where('policy_id', $this->policies->id)
					->orderBy('id', 'desc')
					->first();

				$this->lastRecord = RiskAddress::where('term_id', $this->selectedTermId)
				->where('action_id', $this->policyAction->id)
				->where('policy_id', $this->policies->id)
				->orderBy('id', 'desc')
				->first();

				$this->nextRecord = RiskAddress::where('id', '>', $this->riskAddressId)
					->where('term_id', $this->selectedTermId)
					->where('action_id', $this->policyAction->id)
					->where('policy_id', $this->policies->id)
					->orderBy('id', 'asc')
					->first();

				$this->nextRecord = $this->nextRecord->id ?? '';
				//$this->previousRecord = $this->previousRecord->id ?? '';


			}

		}

		$this->ip_address = $ip_address;
        $this->term_start_date = (new Carbon($this->PolicyTerm->term_start_date))->format(config('constants.date.format'));
        $this->policies_expiry_date = (new Carbon($this->PolicyTerm->term_end_date))->format(config('constants.date.format'));
        $this->customer_profile_date = (new Carbon($this->customer_profile->date))->format(config('constants.date.format'));
		$this->customer_profile_dob = (new Carbon($this->customer_profile->dob))->format(config('constants.date.format'));
		$this->restricted_editable = 0;
		$checkDate  = config('constants.policy.restrictionDate');
		$policyDate = $this->policyAction->effective_from;

		// if (strtotime($policyDate) < strtotime($checkDate)) {
		// 	$this->editable = null;
		// 	$this->restricted_editable = 1;
		// }
	}

	public function submitToUnissued(){

		// Revert via the Eloquent model (NOT the query builder) so the
		// ISSUED -> QUOTE transition is written to the audits table — the same
		// policy audit screen UWs check — attributed to the acting user.
		// A bare PolicyAction::where()->update() fires no model events, so the
		// un-issue would otherwise leave NO audit trail (the "nobody unissued
		// it" symptom). Mirrors PolicyCreateController::unissuePolicy().
		$action    = PolicyAction::findOrFail($this->actionId);
		$oldStatus = $action->status;
		$action->update(['status' => 'QUOTE']);

        // Discard this action's invoice on un-issue so that re-issuing
        // regenerates ONE correct invoice. generateInvoiceDomComIssued is
        // gated on `whereNull('deleted_at')`, so without this the stale
        // invoice survives the un-issue -> re-issue cycle (wrong invoice /
        // wrong payplan) or a second invoice gets created. Mirrors the RENEW
        // regen path in PolicyCreateController::issuePolicy — soft-delete only
        // (original invoice numbers stay in audit history via deleted_at).
        $policyId     = $this->policies->id;
        $actionId     = $this->actionId;
        $invoiceTypes = ['Invoice', 'Invoice VAT', 'Invoice Premium'];

        // Safety: never discard an invoice that has had payment / credit
        // activity — that must go through Reverse Invoice instead, otherwise
        // the allocated payment would be orphaned.
        $hasPaymentActivity = \AlphaDirect\Ledger::where('policy_id', $policyId)
                ->where('action_id', $actionId)
                ->whereIn('trans_type', ['Payment', 'Refund', 'Credit Note'])
                ->whereNull('deleted_at')
                ->exists()
            || \AlphaDirect\Ledger::where('policy_id', $policyId)
                ->where('action_id', $actionId)
                ->where('trans_type', 'Invoice')
                ->whereNull('deleted_at')
                ->where('status', '!=', 'Pending')
                ->exists();

        if ($hasPaymentActivity) {
            $this->dispatchBrowserEvent('alert', ['type' => 'warning', 'message' => 'Unissued — invoice kept because a payment/credit exists on it. Use Reverse Invoice to undo the invoice.']);
        } else {
            // Sub-ledger legs must go with the invoice. generateInvoiceDomComIssued
            // writes SIX rows per invoice — three in policy_ledger and three GL legs
            // in policy_subledger (Insurance Sales / VAT Control / Accounts
            // Receivable), tied to the invoice by trans_ref = invoice_no. Discarding
            // only the policy_ledger half left the old GL legs behind, so the
            // re-issue's fresh set stacked on top and the Sub Ledger tab showed the
            // invoice twice. Read the numbers BEFORE the soft-delete.
            $invoiceNos = \AlphaDirect\Ledger::where('policy_id', $policyId)
                ->where('action_id', $actionId)
                ->where('trans_type', 'Invoice')
                ->whereNull('deleted_at')
                ->whereNotNull('invoice_no')
                ->where('invoice_no', '!=', '')
                ->pluck('invoice_no')
                ->unique()
                ->values()
                ->all();

            \AlphaDirect\Ledger::where('policy_id', $policyId)
                ->where('action_id', $actionId)
                ->whereIn('trans_type', $invoiceTypes)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => now()]);

            if (!empty($invoiceNos)) {
                $sub = \Illuminate\Support\Facades\DB::table('policy_subledger')
                    ->where('policy_id', $policyId)
                    ->whereIn('trans_ref', $invoiceNos)
                    ->where(function ($q) use ($actionId) {
                        $q->where('action_id', $actionId)->orWhereNull('action_id');
                    });

                // policy_subledger is not guaranteed to have a deleted_at column
                // (no migration adds one) — same capability check and hard-delete
                // fallback DeleteNullActionInvoices uses.
                if (\Illuminate\Support\Facades\Schema::hasColumn('policy_subledger', 'deleted_at')) {
                    (clone $sub)->whereNull('deleted_at')->update(['deleted_at' => now()]);
                } else {
                    $sub->delete();
                }
            }

            $this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Unissued Successfully']);
        }

        activity('Policy Unissued')
        ->performedOn($this->policies)
        ->causedBy(auth()->user())
        ->log("Unissued — {$action->transaction_type} reverted from {$oldStatus} to QUOTE (action #{$action->id})");
    }

	// added by snehal

	public function submitToLapse()
	{
    PolicyAction::where('id', $this->actionId)->update(['status' => 'LAPSED']);

	// Lapse = cover ended -> NOT active. Write 2 (Cancelled/Lapsed), the same
	// code the V2 API writes (Api\V1\PolicyCreateController::lapsePolicy) and
	// the same code an issued CANCEL writes. This used to write 0
	// (Deactivated), which meant the two screens disagreed about the same
	// event AND blocked the follow-up REISSUE / REINSTATE — that guard passes
	// on policy status 2/3, never on 0.
	// Model save (not the query builder) so the 1 -> 2 flip lands in `audits`
	// with the acting user — a bare ->update() fires no model events, which is
	// the "nobody lapsed it" symptom already fixed in submitToUnissued above.
	$lapsedPolicy = Policy::find($this->policies->id);
	if ($lapsedPolicy) {
		$lapsedPolicy->status = 2;
		$lapsedPolicy->save();
	}

    activity('Policy Lapsed')
        ->performedOn($this->policies)
        ->causedBy(auth()->user())
        ->log('Status : Lapse');
		// Dispatch reload event
		//$this->emit('refreshlapse', $this->actionId);
	
		 // Ask browser to reload page
    $this->dispatchBrowserEvent('reload-page', [
        'message' => 'Lapsed Successfully',
    ]);
    }

		/**
		 * Super Admin only: discard a wrongly created RENEW transaction
		 * (e.g. an annual policy the monthly auto-renew cron batch-renewed)
		 * TOGETHER with its invoice. Same soft-delete cascade as the
		 * endorsement delete, plus the invoice discard from the un-issue path.
		 */
		public function deleteRenewTransaction()
	{
		// Server-side gate — the blade hides the button, but the action must
		// also be protected against direct Livewire calls.
		if (!auth()->user()->hasRole('Super Admin')) {
			$this->dispatchBrowserEvent('alert', ['type' => 'warning', 'message' => 'Only a Super Admin can delete a RENEW transaction.']);
			return;
		}

		$policyId = $this->policies->id;
		$actionId = $this->actionId;

		$action = PolicyAction::where('id', $actionId)
			->where('policy_id', $policyId)
			->whereNull('deleted_at')
			->first();

		if (!$action || $action->transaction_type !== 'RENEW') {
			$this->dispatchBrowserEvent('alert', ['type' => 'warning', 'message' => 'Only RENEW transactions can be deleted here.']);
			return;
		}

		// Safety: never discard an invoice that has had payment / credit
		// activity — that must go through Reverse Invoice instead, otherwise
		// the allocated payment would be orphaned. Same guard as submitToUnissued.
		$hasPaymentActivity = \AlphaDirect\Ledger::where('policy_id', $policyId)
				->where('action_id', $actionId)
				->whereIn('trans_type', ['Payment', 'Refund', 'Credit Note'])
				->whereNull('deleted_at')
				->exists()
			|| \AlphaDirect\Ledger::where('policy_id', $policyId)
				->where('action_id', $actionId)
				->where('trans_type', 'Invoice')
				->whereNull('deleted_at')
				->where('status', '!=', 'Pending')
				->exists();

		if ($hasPaymentActivity) {
			$this->dispatchBrowserEvent('alert', ['type' => 'warning', 'message' => 'RENEW not deleted — a payment/credit exists on its invoice. Use Reverse Invoice to undo the invoice first.']);
			return;
		}

		// Discard this RENEW's invoice rows. Soft-delete only — the original
		// invoice numbers stay in audit history via deleted_at.
		\AlphaDirect\Ledger::where('policy_id', $policyId)
			->where('action_id', $actionId)
			->whereIn('trans_type', ['Invoice', 'Invoice VAT', 'Invoice Premium'])
			->whereNull('deleted_at')
			->update(['deleted_at' => now()]);

		activity('Renew Transaction Deleted')
			->performedOn($this->policies)
			->causedBy(auth()->user())
			->log('Deleted RENEW transaction and its invoice for Action ID: ' . $actionId);

		// Soft-delete the action and all its action-versioned children
		// (coverages, details, vehicles, risk addresses, motor) — same
		// cascade the endorsement delete uses. Calls the protected worker
		// directly: this path has already cleared its own Super Admin +
		// RENEW + no-payment guards, which the public entry point's
		// QUOTE-only rule would (correctly) reject.
		$this->performActionDataDelete();
	}

	/**
	 * Super Admin only: re-rate the selected RENEW-ISSUED action and update
	 * its invoice amount to the rated premium. Nothing else changes. Shares
	 * the exact logic the React policy page uses via RenewInvoiceRateService.
	 */
	public function rateRenewInvoice()
	{
		if (!auth()->user()->hasRole('Super Admin')) {
			$this->dispatchBrowserEvent('alert', ['type' => 'warning', 'message' => 'Only a Super Admin can rate a RENEW invoice.']);
			return;
		}

		$policyId = $this->policies->id;
		$action = PolicyAction::where('id', $this->actionId)
			->where('policy_id', $policyId)
			->whereNull('deleted_at')
			->first();

		if (!$action) {
			$this->dispatchBrowserEvent('alert', ['type' => 'warning', 'message' => 'Action not found.']);
			return;
		}

		try {
			$result = \AlphaDirect\Services\RenewInvoiceRateService::rate($policyId, $action);

			activity('Renew Invoice Rated')
				->performedOn($this->policies)
				->causedBy(auth()->user())
				->log("Rated RENEW action id={$action->id}: premium {$result['old_premium']} -> {$result['new_premium']}");

			$this->dispatchBrowserEvent('alert', ['type' => 'success', 'message' => "Invoice updated to rated premium: {$result['new_premium']} (was {$result['old_premium']})."]);
		} catch (\RuntimeException $e) {
			$this->dispatchBrowserEvent('alert', ['type' => 'warning', 'message' => $e->getMessage()]);
		} catch (\Throwable $e) {
			\Log::error('rateRenewInvoice (livewire) failed', [
				'policy_id' => $policyId, 'action_id' => $action->id, 'error' => $e->getMessage(),
			]);
			$this->dispatchBrowserEvent('alert', ['type' => 'error', 'message' => 'Rate failed: ' . $e->getMessage()]);
		}
	}

	/**
	 * DELETE ENDORSEMENT / Delete CANCEL-QUOTE button.
	 *
	 * The blade only RENDERS these buttons for a QUOTE ENDORSE / CANCEL with the
	 * right permission (edit-wizard.blade.php) — but a Livewire public method is
	 * directly callable, so a view-only guard is no guard at all. This method
	 * therefore re-checks the same rules server-side before wiping the action's
	 * whole coverage tree. Issued cover is never bulk-deleted from here; the one
	 * legitimate issued case (a wrongly batch-created RENEW) goes through
	 * deleteRenewTransaction(), which has its own Super Admin + payment guards.
	 */
	public function deleteActionRelatedData()
	{
		$action = PolicyAction::where('id', $this->actionId)
			->where('policy_id', $this->policies->id)
			->first();

		if (!$action) {
			$this->dispatchBrowserEvent('alert', ['type' => 'warning', 'message' => 'Action not found.']);
			return;
		}

		if ($action->status !== 'QUOTE') {
			$this->dispatchBrowserEvent('alert', [
				'type' => 'warning',
				'message' => "This action is {$action->status}, not a QUOTE. Issued transaction data cannot be deleted here — unissue it first, or use Delete RENEW for a wrongly created renewal.",
			]);
			return;
		}

		if (!in_array($action->transaction_type, ['ENDORSE', 'CANCEL'], true)) {
			$this->dispatchBrowserEvent('alert', [
				'type' => 'warning',
				'message' => "Only an ENDORSE or CANCEL quote can be deleted here (this is a {$action->transaction_type}).",
			]);
			return;
		}

		$permission = $action->transaction_type === 'CANCEL' ? 'policy_delete_cancel' : 'policy_delete_endorse';
		if (!auth()->user()->can($permission)) {
			$this->dispatchBrowserEvent('alert', ['type' => 'warning', 'message' => 'You do not have permission to delete this transaction.']);
			return;
		}

		$this->performActionDataDelete();
	}

	/**
	 * The actual cascade. Deliberately NOT public: every caller must clear its
	 * own guards first (see deleteActionRelatedData / deleteRenewTransaction).
	 */
	protected function performActionDataDelete()
	{
		$userId = auth()->id();
		$now = now();
		$actionId = $this->actionId;
		$policyId = $this->policies->id;

		// 1️⃣ Soft delete from policy_actions
		PolicyAction::where('id', $actionId)->where('policy_id', $policyId)
			->update([
				'deleted_at' => $now
			]);

		// 2️⃣ Soft delete policy_coverages
		PolicyCoverage::where('action_id', $actionId)
		->where('policy_id', $policyId)
			->update([
				'deleted_at' => $now
			]);

		// 3️⃣ Soft delete policy_coverage_details
		PolicyCoverageDetail::join('policy_coverages as pc', 'pc.id', '=', 'policy_coverage_detail.policy_coverage_id')
						->where('pc.action_id', $actionId)
						->where('pc.policy_id', $policyId)
						->update([
							'policy_coverage_detail.deleted_at' => $now
						]);

		// 4️⃣ Soft delete vehicles
		Vehicle::where('action_id', $actionId)
		->where('policy_id', $policyId)
			->update([
				'deleted_at' => $now
			]);

		// 5️⃣ Soft delete risk addresses
		RiskAddress::where('action_id', $actionId)
		->where('policy_id', $policyId)
			->update([
				'deleted_at' => $now
			]);

		// 6️⃣ Soft delete motor records using related coverage
		Motor::join('policy_coverages as pc', 'pc.id', '=', 'motor.policy_coverage_id')
		->where('pc.policy_id', $policyId)
			->where('pc.action_id', $actionId)
			->update([
				'motor.deleted_at' => $now
			]);

		// Log Activity
		activity('Policy Action Deleteed')
			->performedOn($this->policies)
			->causedBy(auth()->user())
			->log('Deleted data related to Action ID: ' . $actionId);

		// Set latest action
		$this->setLatestAction();
		$this->dispatchBrowserEvent('reload-page', [
        'message' => 'Action data deleted successfully!',
    	]);
	}
	public function setLatestAction()
	{
		$policyId = $this->policies->id;
		$latest = PolicyAction::where('policy_id', $policyId)
			->whereNull('deleted_at')
			->orderByDesc('id')
			->first();

		if ($latest) {
			$this->actionId = $latest->id;
		}
	}

	public function updatedTab($value){
		if($value===2){
			$this->agents= \AlphaDirect\User::where('agency_id', $this->policies->agency_id)
			 ->get()->keyBy('id')->map(function($d){
				return [
					'id'=>$d->id,
					'name'=>$d->full_name
				];
			});

			$this->allPlan= \AlphaDirect\Productplan::where('product_id', $this->policies->product_id)
			 ->get()->keyBy('id')->map(function($d){
				return [
					'id'=>$d->id,
					'name'=>$d->name
				];
			});
			$this->allCities= \AlphaDirect\City::where('state_id', $this->customer_profile->state)
			 ->get()->keyBy('id')->map(function($d){
				return [
					'id'=>$d->id,
					'name'=>$d->name
				];
			});
			$sou = json_decode(json_encode($this->customer_profile->sourceOfIncome), true);
			if(is_array($sou)){
				$this->sourceOfIncome=array_key_first($sou);
				$this->employment=$sou[$this->sourceOfIncome];
			}else{
				$this->sourceOfIncome=$this->customer_profile->sourceOfIncome;
			}
		}
	}

    /* Save Step 1*/
	public function saveStep1(){
		// dd($this);
		$sourceOfIncome=[];
		if(is_array($this->employment)&& count($this->employment)>0){
			$sourceOfIncome[$this->sourceOfIncome]=$this->employment;
		}else{
			$sourceOfIncome=$this->sourceOfIncome;
		}
		$this->policies->has_member = 1;
		// NB: do NOT write policies.status here. This is the Policy Details
		// (step 1) form — an ordinary demographic/term edit. It previously did
		// `$this->policies->status = 0;` unconditionally, so every Update on a
		// live, issued, fully-paid policy silently flipped it to Deactivated
		// (and, because status 0 stops monthly invoicing, silently stopped
		// billing it). ~43 DOMG/COMG policies were switched off this way with
		// their policyActivatedDate still populated — the tell-tale signature.
		// Policy status is owned by the transaction lifecycle ONLY:
		//   issue (Submit::submitToIssue / Api\V1\PolicyCreateController::issuePolicy) -> 1
		//   CANCEL issue / lapse                                                       -> 2
		//   draft creation (AddWizard)                                                 -> 0
		// Nothing on this screen may move it.
		// Company-only products (20 Commercial Liabilities / 23 Guarantee /
		// 24 Miscellaneous): Annual term on all three, Organisation holder on
		// 23 / 24 only — Commercial Liabilities allows an Individual holder
		// (UW 2026-08-26), so never coerce its entity_type. COERCE rather
		// than add a validation rule — a failing rule in saveStep1 aborts the
		// whole save with no error surfaced on this screen, which reads to the
		// operator as "Update does nothing". Also repairs a policy that was
		// created on one of these products before the rule existed.
		if (\AlphaDirect\Support\CompanyOnlyProducts::includes($this->policies->product_id)) {
			$this->policies->premium_freq = \AlphaDirect\Support\CompanyOnlyProducts::ANNUAL_FREQ;
		}
		if (\AlphaDirect\Support\CompanyOnlyProducts::entityLocked($this->policies->product_id)
			&& $this->customer_profile) {
			$this->customer_profile->entity_type = \AlphaDirect\Support\CompanyOnlyProducts::ENTITY_TYPE;
		}
        $valid=[];
		$valid['policies.product_id']='required';
		$valid['policies.plan_id']='required';
		$valid['customer_profile.entity_type']='required';
		$valid['policies.premium_freq']='required';
        $valid['term_start_date']='required';
		$valid['policies_expiry_date']='required';
		$valid['customer_profile.insure']='required';
		$valid['customer_profile.about_alpha']='required';
        $valid['policies.gfs_policy_no']='';
		// if(($this->customer_profile->select_product)=='Commercial All Risk'){
		// 	$valid['customer_profile.date']='required';
		// }
        if(($this->policies->product_id)==7){
			$valid['customer_profile_date']='required';
		}
		if(($this->customer_profile->entity_type)=='Organisation'){
            $this->customer = Customer::where('company_id',$this->customer_profile->company->id)->first(); // @todo need to refactor the code it's only temporary solution
			$valid['customer_profile.company_id']='required';
		}else{
            $valid['customer.email']='required';
            $valid['customer.cellphone']='required|regex:/^([0-9\s\-\+\(\)]*)$/|min:8|max:8';
            $valid['customer_profile.state']='required';
            $valid['customer_profile.city']='required';
			$valid['customer.firstName']='required';
			$valid['customer.lastName']='required';
			$valid['customer_profile.gender']='required';
			$valid['customer_profile.maritalstatus']='required';
			$valid['customer_profile_dob']='required';
			$valid['customer_profile.sourceOfIncome']='required';
			$valid['sourceOfIncome']='required';
			if(($this->sourceOfIncome)=='employment'){
				$valid['employment.where_are_you_employed_?']='required';
				$valid['employment.what_is_your_monthly_salary_?']='required';
			}elseif(($this->sourceOfIncome)=='pensioner_retired'){
				$valid['employment.how_much_is_your_monthly_pension_']='required';
			}elseif(($this->sourceOfIncome)=='bussiness'){
				$valid['employment.name_of_your_bussiness']='required';
				$valid['employment.bussiness_address']='required';
				$valid['employment.what_is_your_monthly_income_?']='required';
			}elseif(($this->sourceOfIncome)=='inheritance'){
				$valid['employment.who_did_you_inherit_these_funds_from_?']='required';
			}elseif(($this->sourceOfIncome)=='gifts'){
				$valid['employment.who_gifted_you_these_funds_?']='required';
				$valid['employment.how_much_were_you_gifted_?']='required';
				$valid['employment.gift_frequency']='required';
			}elseif(($this->sourceOfIncome)=='investments'){
				$valid['employment.what_is_the_amount_of_funds_invested_?']='required';
				$valid['employment.how_much_do_you_earn_from_these_investments_per_month_?']='required';
			}
			if (empty($this->customer_profile->omang) && empty($this->customer_profile->passport)){
				$valid['customer_profile.omang']='required|regex:/^[a-zA-Z0-9]+$/|min:5|max:25';
			    $valid['customer_profile.passport']='required|regex:/^[0-9]{4}[1-2][0-9]{4}$/|max:9';
			}
			if (empty($this->customer_profile->omang)){
			    $valid['customer_profile.passport']='required|regex:/^[a-zA-Z0-9]+$/|min:5|max:25';
			}
			if (empty($this->customer_profile->passport)){
				$valid['customer_profile.omang']='required|regex:/^[0-9]{4}[1-2][0-9]{4}$/|max:9';
			}
		}

		$this->customer_profile->sourceOfIncome=json_encode($sourceOfIncome);
		$this->validate($valid,$this->messages);
        $this->customer->save();


        activity('Applicant Information')
        ->performedOn($this->policies)
        ->causedBy(User::where('id', auth()->user()->id)->first())
        ->log('Applicant Information Updated');

		$this->customer_profile->decline_proposal = ($this->customer_profile->decline_proposal==true)?1:0;
		$this->customer_profile->refused_policy = ($this->customer_profile->refused_policy==true)?1:0;
		$this->customer_profile->cancel_policy = ($this->customer_profile->cancel_policy==true)?1:0;

		if(($this->customer_profile->select_product)=='Commercial All Risk'){
			$this->customer_profile->firm_member = ($this->customer_profile->firm_member==true)?1:0;
			$this->customer_profile->books = ($this->customer_profile->books==true)?1:0;
		}
		$this->customer_profile->customer_id = $this->policies->customer_id;
       // $this->customer_profile->customer_id= $this->customer->id;
        if(!empty($this->customer_profile_date)){
			$this->customer_profile->date = Carbon::createFromFormat(config('constants.date.format'),$this->customer_profile_date);
		}
		if(!empty($this->customer_profile_dob)){
			$this->customer_profile->dob = Carbon::createFromFormat(config('constants.date.format'),$this->customer_profile_dob);
		}
		$this->customer_profile->save();

		activity('Applicant Information')
        ->performedOn($this->policies)
        ->causedBy(User::where('id', auth()->user()->id)->first())
        ->log('Applicant Profile Information Updated');
		//dd( $this);
		// ── Frequency is per-TRANSACTION ────────────────────────────────────
		//
		// A frequency change made here writes exactly two things: the policy
		// column (further down, via $this->policies->save()) and the SELECTED
		// action's own stamp (further down too, gated on $frequencyChanged).
		// No other action row is touched.
		//
		// $storedFrequency is what is still on disk at this point, so a
		// difference means THIS save changed the frequency. Two defects used to
		// live here and they inverted the stamps on real policies - on 129521
		// the Annual 01/01/2025-31/12/2025 NEWBUSINESS ended up marked Quarterly
		// and the Quarterly 01/01/2026-31/03/2026 ANNIVERSARY-RENEW marked
		// Annual, the exact opposite of what the action dates say:
		//
		//   1. a loop here overwrote current_frequency_id on EVERY action
		//      on/before the edited one with the single outgoing value,
		//      destroying the per-action history it was meant to preserve; and
		//   2. the stamp further down ran on every save, so merely re-saving
		//      Policy Details while sitting on an old NEWBUSINESS pushed the
		//      policy's present frequency back onto that historical action.
		$storedFrequency  = Policy::where('id', $this->policies->id)->value('premium_freq');
		$frequencyChanged = (int) $storedFrequency !== (int) $this->policies->premium_freq;
		//added  by snehal
		$this->policies->agent_id=$this->policies->agent_id ?? NULL;
		$this->policies->agency_id=$this->policies->agency_id ?? NULL;
		//--------------
        $this->policies->term_start_date = Carbon::createFromFormat(config('constants.date.format'),$this->term_start_date);
        $this->policies->expiry_date = Carbon::createFromFormat(config('constants.date.format'),$this->policies_expiry_date);
        $this->policies->save();
	  	// 1️⃣ Fetch the record
		$actionRecord = PolicyAction::find($this->actionId);

		if ($actionRecord) {
			// Record the frequency ON THIS TRANSACTION - but only when this save
			// actually changed it. Writing it unconditionally meant any edit to an
			// older action (dates, agent, applicant details) silently re-stamped
			// that action with the policy's PRESENT frequency, which is how a
			// full-year NEWBUSINESS came to be labelled Quarterly.
			if ($frequencyChanged) {
				$actionRecord->current_frequency_id = $this->policies->premium_freq;
			}

			// Stamp the edited Effective From/To onto the SELECTED action row itself.
			// The date fields are editable for QUOTE NEWBUSINESS/ANNIVERSARY-RENEW/REINSTATE/REISSUE
			// (see applicant-information.blade.php), but the block below only writes the latest-in-term
			// NEWBUSINESS/ANNIVERSARY-RENEW action ($this->policyAction) — so the action actually being
			// edited (actionId) never received the new dates when it differed from that / was REINSTATE/REISSUE.
			if ($actionRecord->status == "QUOTE"
				&& in_array($actionRecord->transaction_type, ["NEWBUSINESS", "ANNIVERSARY-RENEW", "REINSTATE", "REISSUE"])) {
				$actionRecord->effective_from = Carbon::createFromFormat(config('constants.date.format'), $this->term_start_date);
				$actionRecord->effective_to   = Carbon::createFromFormat(config('constants.date.format'), $this->policies_expiry_date);
			}
			$actionRecord->save();
		}
         if(($this->policyAction->transaction_type == "NEWBUSINESS" || $this->policyAction->transaction_type == "ANNIVERSARY-RENEW" ) && $this->policyAction->status== "QUOTE" ){     // Updated Term
            $this->PolicyTerm->term_start_date = Carbon::createFromFormat(config('constants.date.format'),$this->term_start_date);
            $this->PolicyTerm->term_end_date = Carbon::createFromFormat(config('constants.date.format'),$this->policies_expiry_date);
           $this->PolicyTerm->save();

             // Updated Action
            $this->policyAction->effective_from = Carbon::createFromFormat(config('constants.date.format'),$this->term_start_date);
            $this->policyAction->effective_to = Carbon::createFromFormat(config('constants.date.format'),$this->policies_expiry_date);
           $this->policyAction->save();
        }

        // Refresh the in-memory action so the header cards, the "Current Premium Frequency" badge
        // and the date fields reflect the just-saved values on this same render (no full reload
        // needed). All of these read $this->policyAction / $this->newActionDates.
        if ($actionRecord) {
            $this->policyAction   = $actionRecord;
            $this->newActionDates = $actionRecord;
            $this->action         = $actionRecord;
        }

        activity('Policy')
        ->performedOn($this->policies)
        ->causedBy(User::where('id', auth()->user()->id)->first())
        ->log('Policy Details Updated');

        $this->updateHasStatus();
        $this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Policy Details Updated Successfully!']);
     }

    public function updatedCustomerProfileDate($value){
        $this->updatedCustomerProfile($value, "customer_profile_date");
    }

	public function updatedCustomerProfile($value, $key){
		if ($key=="state") {
			$data= \AlphaDirect\City::where('state_id', $value)
			 ->get()->keyBy('id')->map(function($d){
				return [
					'id'=>$d->id,
					'name'=>$d->name
				];
			});
			$this->allCities = $data;
			$this->dispatchBrowserEvent('dropdown-changed',[
				'key'=>'state_id',
				'data'=>$data
			]);
        }

		if ($key=="customer_profile_date") {
            $d = Carbon::createFromFormat(config('constants.date.format'),$value ?? "");
			$years = \Carbon\Carbon::parse($d)->age;
			$this->customer_profile->business_note= $years.' years old';
		}
	}

	/* step 1*/

	public function getPremiumFreqPropert(){
		//return ["1"=>"MONTHLY","2"=>"3 INSTALLMENTS","3"=>"ANNUAL","4"=>"SEMIANNUAL","5"=>"QUARTERLY"];
		return ["1"=>"MONTHLY","3"=>"ANNUAL","5"=>"QUARTERLY","6"=>"MANUAL INPUT"];
	}
	/* step 1*/
	public function getSourceOfIncome(){
		return array('unemployed'=>'Unemployed','employment'=>'Employment','pensioner_retired'=>'Pensioner/Retired','bussiness'=>'Self-Employment/Business','inheritance'=>'Inheritance','gifts'=>'Gifts','investments'=>'Investments');
	}
	/*
		Get All Active Agency step 1
	*/
	public function getAgency(){
		return \AlphaDirect\Agency::whereStatus(1)->get()->keyBy('id')->map(function($d){
			return [
				'id'=>$d->id,
				'name'=>$d->name
			];
		});;
	}

	public function getProducts(){
		return \AlphaDirect\Product::get()->whereIn('id',[7,8,16,17,18,20,22,23,24])->keyBy('id')->map(function($d){
			return [
				'id'=>$d->id,
				'name'=>$d->name
			];
		});
	}

    public function updatedTermStartDate($value){
        $this->updatedPolicies($value, "term_start_date");
    }
	public function updatedPolicies($value, $key)
    {
        // dd($value, $key);
		if ($key=="product_id") {
			$data= \AlphaDirect\Productplan::where('product_id', $value)
			 ->get()->keyBy('id')->map(function($d){
				return [
					'id'=>$d->id,
					'name'=>$d->name
				];
			});
			$this->dispatchBrowserEvent('dropdown-changed',[
				'key'=>'product_id',
				'data'=>$data
			]);
        }
        if ($key=="premium_freq" || $key=="term_start_date") {
			if($this->term_start_date!="" && $this->policies->premium_freq!=""){
				switch($this->policies->premium_freq){
					case "1":
                        // $this->policies->expiry_date=\Carbon\Carbon::parse($this->term_start_date)->addMonth()->subDay(1)->format('d/m/Y');
                        $this->policies_expiry_date = Carbon::createFromFormat(config('constants.date.format'),$this->term_start_date)->addMonth()->subDay(1)->format(config('constants.date.format'));
					break;
					case "2":
                        // $this->policies->expiry_date=\Carbon\Carbon::parse($this->term_start_date)->addMonth(3)->subDay(1)->format('d/m/Y');
                        $this->policies_expiry_date = Carbon::createFromFormat(config('constants.date.format'),$this->term_start_date)->addMonths(3)->subDay(1)->format(config('constants.date.format'));
					break;
					case "3":
                        // $this->policies->expiry_date=\Carbon\Carbon::parse($this->term_start_date)->addYear(1)->subDay(1)->format('d/m/Y');
                        $this->policies_expiry_date = Carbon::createFromFormat(config('constants.date.format'),$this->term_start_date)->addYear(1)->subDay(1)->format(config('constants.date.format'));
					break;
					case "4":
                        // $this->policies->expiry_date=\Carbon\Carbon::parse($this->term_start_date)->addMonths(6)->subDay(1)->format('d/m/Y');
                        $this->policies_expiry_date = Carbon::createFromFormat(config('constants.date.format'),$this->term_start_date)->addMonths(6)->subDay(1)->format(config('constants.date.format'));
					break;
					case "5":
					default:
                    // $this->policies->expiry_date=\Carbon\Carbon::parse($this->term_start_date)->addMonths(3)->subDay(1)->format('d/m/Y');
                        $this->policies_expiry_date = Carbon::createFromFormat(config('constants.date.format'),$this->term_start_date)->addMonths(3)->subDay(1)->format(config('constants.date.format'));
					break;
				}
			}else{
				$this->policies_expiry_date="";
			}
		}
		if ($key=="agency_id") {

			$data= \AlphaDirect\User::where('agency_id', $value)
			 ->get()->keyBy('id')->map(function($d){
				return [
					'id'=>$d->id,
					'name'=>$d->full_name
				];
			});
			$this->agents = $data;
			$this->dispatchBrowserEvent('dropdown-changed',[
				'key'=>'selectedAgency',
				'data'=>$data
			]);
		}
    }

    public function render()
    {
		//dd($this->actionId);
		$this->previousPremium = PolicyAction::where('policy_id',$this->policies->id)->orderBy('created_at', 'asc')->whereNull('deleted_at')->take(2)->first();	
		$this->newActionDates=PolicyAction::where('id',$this->actionId)->whereNull('deleted_at')->first();
		if($this->newActionDates==null)
		{
			$this->setLatestAction();
		}
		return view('v2.livewire.policy.edit-wizard')->layout('layouts.app-v2');
    }

    public function getStates(){
        return cache()->driver('file')
            ->remember('State::28',now()->addMinutes(20), function (){
                return \AlphaDirect\State::where('country_id',28)->get()->keyBy('id')->map(function($d){
                    return [
                        'id'=>$d->id,
                        'name'=>$d->name
                    ];
                });
            });
    }

	public function getCurrentlyInsure(){
		return \AlphaDirect\Lookup::where('key','are_you_currently_insured')->get()->keyBy('id')->map(function($d){
			return [
				'id'=>$d->id,
				'name'=>$d->value
			];
		});
	}

	public function getHearAboutAlphadirect(){
		return \AlphaDirect\Lookup::where('key','hear_about_alphadirect')->get()->keyBy('id')->map(function($d){
			return [
				'id'=>$d->id,
				'name'=>$d->value
			];
		});
	}

    public function getPolicyTermsProperty(){
        return PolicyTerm::select('id','term_start_date','term_end_date')->policy($this->policies->id)->get();
    }

    public function getPolicyActionsProperty(){
	    return PolicyAction::getActions($this->policies->id,$this->selectedTermId);
    }

    public function updatedSelectedTermId($value)
    {
        $this->actionId = $this->policyActions[0]->id;
        $this->previousActionId = $this->policyActions[0]->previous_action_id;
        $this->policyAction = $this->policyActions[0];
        $this->emit('updateTermId');
    }

    public function updatedActionId($value)
    {
		$this->restricted_editable = 0;
       $this->policyAction = PolicyAction::where('id',$value)->first();
       $this->previousActionId = $this->policyAction->previous_action_id;
	   $checkDate  = config('constants.policy.restrictionDate');
		$policyDate = Carbon::parse($this->policyAction->effective_from)->format('Y-m-d');
		//dd($policyDate, $checkDate);
		// if (strtotime($policyDate) < strtotime($checkDate)) {
		// 	//$this->editable = null;
		// 	$this->restricted_editable = 1;
		// }else{
		// 	$this->restricted_editable = 0;
		// }
		//dd($this->restricted_editable);
    }
	//added  by snehal
 	public function updatedPoliciesAgencyId($value)
    {
		$this->policies->agency_id = $value;
        $this->policies->agent_id = 0;
	 }

    public function getEditableProperty(){
        return  in_array(PolicyAction::find($this->actionId)->status, config('constants.policy_action.allow_to_edit_on')) ? true : false;
//        if ($this->policies->status!=0){
//            if ($this->actionId and $this->IsMorethenOneAction){
//                if (PolicyAction::find($this->actionId)->status == 'QUOTE'){
//                    return true;
//                }
//            }
//            return false;
//        }else{
//            return true;
//        }
    }
// added by snehal
	 public function getLapsedProperty(){
     $action = PolicyAction::find($this->actionId);
		$isValid = $action && $action->transaction_type === 'ANNIVERSARY-RENEW' && $action->status === 'QUOTE';
    return $isValid;
	}

    public function getIsMorethenOneActionProperty(){
        return $this->PolicyActions->count() > 1;
    }

    /**
     * Whether a NEW transaction may be created from the policy's latest
     * action. Rule: you can only create the next transaction once the
     * last one is ISSUED — anything still in the approval pipeline
     * (QUOTE / IN_APPROVAL / APPROVED / REJECTED) must not spawn a new
     * transaction. LAPSED stays allowed because the transition rules
     * already treat a lapsed action like CANCEL (REISSUE / REINSTATE).
     *
     * Scoped policy-wide by latest id to match the exact source
     * AddTransaction::submit() builds the new transaction from.
     */
    public function getCanAddTransactionProperty(){
        // Enable New Transaction whenever the action in view is ISSUED (or
        // LAPSED, so REISSUE/REINSTATE stay possible) — mirrors the React
        // PolicyDetailPage gate. Per Monika 2026-06-03 every issued state
        // (NEWBUSINESS / RENEW / ANNIVERSARY-RENEW / REINSTATE / REISSUE /
        // CANCEL / ENDORSE) may start a follow-up transaction even when a
        // later action is still sitting in QUOTE (e.g. a future DOM/COM
        // renewal quote). Gate on the SELECTED action; fall back to the latest
        // non-deleted action when nothing is explicitly selected. The
        // getTransactionTypes() in-flight filter + store guard prevent
        // conflicting endorse-class transactions.
        $action = PolicyAction::where('policy_id', $this->policies->id)
            ->whereNull('deleted_at')
            ->when($this->actionId, fn($q) => $q->where('id', $this->actionId))
            ->orderBy('id', 'desc')
            ->first();

        return $action && in_array($action->status, ['ISSUED', 'LAPSED'], true);
    }

    public function getCompaniesProperty(){
        return Company::select('id','name')->MainCompanyOnly()->Activated()->orderBy('id','desc')->get()->pluck('name','id');
    }

	public function policyReinsuranceCalculations()
	{
	   $PolicyReinsuranceCalculations = PolicyCoverage::getReinsuranceCoverageCalculations($this->policies->id,$this->selectedTermId,$this->actionId);
	   $this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Policy Reinsurance Calculations are Created Successfully']);
	   return Redirect::route('policy.edit', [\Crypt::encrypt($this->policies->id)]);

	}

//     public function toggleYear($label)
// {
//     if (in_array($label, $this->expandedYears)) {
//         $this->expandedYears = array_diff($this->expandedYears, [$label]);
//     } else {
//         $this->expandedYears[] = $label;
//     }
//     // dd($this->expandedYears);
// }

}
