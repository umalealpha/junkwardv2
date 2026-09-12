<?php
namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Customer;
use AlphaDirect\CustomerFeedback;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Events\SendMail;
use AlphaDirect\Http\Controllers\Admin\PolicyController as AdminPolicyController;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\Models\CancelPolicyRequest;
use AlphaDirect\Policy;
use AlphaDirect\Services\AuthGate;
use AlphaDirect\Services\Claims\OpenClaimHold;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CancelRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|string|max:50',
            'search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = DB::table('cancel_policy_requests as cpr')
            ->leftJoin('policies as p', 'p.policyNumber', '=', 'cpr.policyNumber')
            ->leftJoin('products as pr', 'pr.id', '=', 'p.product_id')
            ->select([
                'cpr.id', 'cpr.policyNumber', 'cpr.reason', 'cpr.circumstances',
                'cpr.other_company', 'cpr.status', 'cpr.action_by',
                'cpr.created_at', 'cpr.updated_at',
                'pr.name as product_name',
            ])
            ->when($validated['status'] ?? null, fn($q, $v) => $q->where('cpr.status', $v))
            ->when($validated['search'] ?? null, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('cpr.policyNumber', 'like', "%{$search}%")
                      ->orWhere('cpr.reason', 'like', "%{$search}%");
                });
            })
            ->orderBy('cpr.id', 'desc');

        $results = $query->paginate($validated['per_page'] ?? 25);

        return response()->json([
            'data' => collect($results->items())->map(fn($r) => [
                'id' => $r->id,
                'policyNumber' => $r->policyNumber,
                'productName' => $r->product_name,
                'reason' => $r->reason,
                'circumstances' => $r->circumstances,
                'otherCompany' => $r->other_company,
                'status' => $r->status,
                'actionBy' => $r->action_by,
                'createdAt' => $r->created_at,
                'updatedAt' => $r->updated_at,
            ]),
            'meta' => [
                'total' => $results->total(),
                'per_page' => $results->perPage(),
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'from' => $results->firstItem(),
                'to' => $results->lastItem(),
            ],
        ]);
    }

    /**
     * GET /api/v1/cancel-requests/feedback-options
     *
     * Active cancellation-reason options (+ their active sub-options), sourced
     * from the SAME customer_feedback_options table the start frontend reads
     * (Admin\CustomerFeedbackOptionController@data). Returned in a normalised
     * shape so the Policy Details > Cancel Policy modal can render the exact
     * same DB-driven reasons. input_type: 1 = sub-options as radios,
     * 2 = sub-options as a select.
     */
    public function feedbackOptions(): JsonResponse
    {
        $options = \AlphaDirect\Admin\CustomerFeedbackOption::getActiveSuboptions();

        return response()->json([
            'data' => $options->map(fn($o) => [
                'id'          => $o->id,
                'name'        => $o->name,
                'description' => $o->description,
                'input_type'  => (int) $o->input_type,
                'suboptions'  => $o->suboptions->map(fn($s) => [
                    'id'   => $s->id,
                    'name' => $s->name,
                ])->values(),
            ])->values(),
        ]);
    }

    /**
     * GET /api/v1/policies/{id}/cancel-reason
     *
     * The stored cancellation reason for a policy — the newest
     * cancel_policy_requests row for its policy number (immediate cancels and
     * approved requests both write here). Returns { data: null } when none
     * exists. The FE shows this on the policy detail page for cancelled policies.
     */
    public function cancelReason(int $id): JsonResponse
    {
        $policy = Policy::find($id);
        if (!$policy) {
            return response()->json(['error' => 'Policy not found.'], 404);
        }

        $req = CancelPolicyRequest::where('policyNumber', $policy->policyNumber)
            ->orderBy('id', 'desc')
            ->first();

        // Old-Graphite parity: cancellations done in the V1 admin (feedback
        // modal) wrote customer_feedback (reason + cancelled_by), not
        // cancel_policy_requests — fall back to it so those policies still
        // surface a reason, and use it as the source for "Cancelled By"
        // (same as the V1 policy view/edit pages).
        $feedback = CustomerFeedback::where('policy_id', $policy->id)
            ->orderBy('id', 'desc')
            ->first();

        if (!$req && !$feedback) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => [
                'reason'        => $req->reason        ?? $feedback?->reason,
                'circumstances' => $req->circumstances ?? $feedback?->circumstances,
                'otherCompany'  => $req->other_company ?? $feedback?->other_company,
                'cancelledBy'   => $feedback?->cancelled_by,
                'status'        => $req->status ?? null,
                'createdAt'     => (string) ($req->created_at ?? $feedback?->created_at),
            ],
        ]);
    }

    public function approve(int $id): JsonResponse
    {
        $req = CancelPolicyRequest::findOrFail($id);

        // graphiteBWV8 parity: the authoritative cancellation payload is the JSON
        // in the `requestdata` column (v8: json_decode($cancel->requestdata)).
        // policy_id / customer_id / product_id ONLY live there — there are no
        // such columns on cancel_policy_requests — so decode it, falling back to
        // the flat columns (policyNumber/reason/…) for resolution.
        $rd = json_decode((string) $req->requestdata) ?: new \stdClass();

        // Resolve the linked policy: prefer requestdata.policy_id, else policyNumber.
        $policy = null;
        if (!empty($rd->policy_id)) {
            $policy = Policy::find($rd->policy_id);
        }
        if (!$policy && $req->policyNumber) {
            $policy = Policy::where('policyNumber', $req->policyNumber)->first();
        }
        if (!$policy) {
            return response()->json(['error' => 'Linked policy not found.'], 404);
        }

        // Approving a request cancels the policy, so it is gated exactly like an
        // immediate cancel: product-scoped, fail-closed, Managers/Admins bypass.
        // Products outside the cancellable book require an admin-equivalent role.
        $perm = AuthGate::cancelPermissionForProduct((int) $policy->product_id);
        if (!AuthGate::canPerform(auth()->user(), $perm ?? 'policy_cancel_none')) {
            return response()->json(['error' => 'You do not have permission to approve policy cancellations.'], 403);
        }

        // Open claim on this policy → Claims decide before cover is cancelled.
        if ($held = $this->openClaimHoldResponse($policy)) {
            return $held;
        }

        // graphiteBWV8 parity: CancelPolicyRequestController::approved →
        // customerFeedbackFromStart. Approving the request cancels the policy
        // (status 2), records the cancellation feedback, logs the cancelled
        // date, then cancels the payment arrangement by billing method —
        // DPO schedules / RealPay contract / VCS / N-Genius / PayM8 — via
        // CancelPaymentsForPolicy (a faithful port of the v8 method).
        //
        // Idempotency is keyed on the POLICY status, NOT the request's status
        // flag — a request previously marked Approved by the old stub (which
        // never cancelled the policy) must still cancel here on re-approval.
        $paymentCancel = ['status' => true, 'message' => 'Policy already cancelled'];

        if ((int) $policy->status !== 2) {
            try {
                $paymentCancel = $this->cancelPolicyCore(
                    $policy,
                    $req->reason ?? ($rd->reason ?? null),
                    $req->circumstances ?? ($rd->circumstances ?? null),
                    $req->other_company ?? ($rd->other_company ?? null)
                );
            } catch (\Throwable $e) {
                return response()->json(['error' => $e->getMessage()], 500);
            }
        }

        $req->update(['status' => 'Approved', 'action_by' => auth()->id() ?? 0]);

        return response()->json([
            'message'         => 'Request approved and policy cancelled.',
            'payment_cancel'  => $paymentCancel,
        ]);
    }

    public function decline(int $id): JsonResponse
    {
        // Declining doesn't cancel anything, but actioning the queue is a
        // reviewer function — restrict to Managers/Admins or the existing
        // Cancel Requests queue permission.
        if (!AuthGate::canPerform(auth()->user(), 'cancelpolicyrequest_list')) {
            return response()->json(['error' => 'You do not have permission to action cancellation requests.'], 403);
        }

        $req = CancelPolicyRequest::findOrFail($id);
        $req->update(['status' => 'Declined', 'action_by' => $this->actingUserName()]);
        return response()->json(['message' => 'Request declined.']);
    }

    /**
     * MIS retail products that expose the "Cancel Policy" button directly on
     * the policy view page (PolicyDetailPage). These mirror the products the
     * old Graphite policy view offered an inline cancel for. Anything else must
     * still go through the request → approve flow.
     */
    private const IMMEDIATE_CANCEL_PRODUCT_IDS = [1, 2, 3, 4, 5, 9];

    /**
     * Immediate cancellation from the policy view page.
     *
     * Unlike approve(), there is no pending-request step: submitting the
     * feedback cancels the policy in one shot. It still records the same
     * CustomerFeedback + cancel_policy_requests audit trail and runs the exact
     * same channel cancellation (CancelPaymentsForPolicy) as the approval flow,
     * so the schedule/contract is cancelled identically.
     */
    public function cancelImmediate(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason'        => 'required|string|max:500',
            'circumstances' => 'nullable|string|max:1000',
            'other_company' => 'nullable|string|max:255',
        ]);

        $policy = Policy::find($id);
        if (!$policy) {
            return response()->json(['error' => 'Policy not found.'], 404);
        }

        if (!in_array((int) $policy->product_id, self::IMMEDIATE_CANCEL_PRODUCT_IDS, true)) {
            return response()->json(['error' => 'Cancellation is not available for this product.'], 422);
        }

        // Product-scoped, fail-CLOSED authorisation. Motor Comprehensive
        // (product 3) and the rest of the instant book use separate
        // permissions; Managers/Admins bypass via role. Replaces the previous
        // fail-OPEN cancelpolicyrequest_list check.
        $perm = AuthGate::cancelPermissionForProduct((int) $policy->product_id);
        if (!AuthGate::canPerform(auth()->user(), $perm)) {
            return response()->json(['error' => 'You do not have permission to cancel this policy.'], 403);
        }

        // Open claim on this policy → Claims decide before cover is cancelled.
        if ($held = $this->openClaimHoldResponse($policy)) {
            return $held;
        }

        if ((int) $policy->status === 2) {
            return response()->json(['error' => 'Policy is already cancelled.'], 422);
        }

        try {
            $paymentCancel = $this->cancelPolicyCore(
                $policy,
                $validated['reason'],
                $validated['circumstances'] ?? null,
                $validated['other_company'] ?? null
            );
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

        // Audit trail: record the cancellation as an already-approved request,
        // mirroring the requestdata JSON shape the approve() flow reads.
        $cancelReq = new CancelPolicyRequest();
        $cancelReq->policyNumber  = $policy->policyNumber;
        $cancelReq->reason        = $validated['reason'];
        $cancelReq->circumstances = $validated['circumstances'] ?? null;
        $cancelReq->other_company = $validated['other_company'] ?? null;
        $cancelReq->requestdata   = json_encode([
            'policy_id'     => $policy->id,
            'customer_id'   => $policy->customer_id,
            'product_id'    => $policy->product_id,
            'reason'        => $validated['reason'],
            'circumstances' => $validated['circumstances'] ?? null,
            'other_company' => $validated['other_company'] ?? null,
            'cancelled_by'  => $this->actingUserName(),
            'source'        => 'policy_view_immediate',
        ]);
        $cancelReq->status    = 'Approved';
        $cancelReq->action_by = auth()->id() ?? 0;
        $cancelReq->save();

        return response()->json([
            'message'        => 'Policy cancelled.',
            'payment_cancel' => $paymentCancel,
        ]);
    }

    /**
     * Display name of the authenticated user for cancelled_by / action_by
     * audit fields. users has firstName/lastName (no `name` column), so this
     * mirrors the V1 admin flow's "firstName lastName" format.
     */
    private function actingUserName(): string
    {
        $user = auth()->user();
        if (!$user) {
            return 'System';
        }

        $name = trim(($user->firstName ?? '') . ' ' . ($user->lastName ?? ''));

        return $name !== '' ? $name : 'System';
    }

    /**
     * Shared cancellation core used by both approve() and cancelImmediate().
     *
     * Flips the policy to status 2, records the CustomerFeedback, logs the
     * cancelled date via updatePolicyDates(), then cancels the payment
     * arrangement by billing method (DPO / RealPay / VCS / N-Genius / PayM8)
     * through CancelPaymentsForPolicy. The policy-state change is transactional
     * and re-thrown on failure; the channel cancellation runs afterwards and
     * swallows its own errors (it makes external API calls and is retryable),
     * returning the channel result array.
     *
     * CancelPaymentsForPolicy now sweeps RealPay for every policy rather than
     * only where customer_banking.billing reads 'RealPay', so a cancelled
     * policy's debit order is cancelled here too — see
     * RealPayPolicyCancellationService. Its result comes back under the
     * 'realpay' key of the returned array.
     */
    /**
     * The open-claim hold on cancelling COVER (CFO instruction, 7 Sep 2026).
     *
     * Returns a 409 to send back, or null when there is nothing to hold. It sits
     * in the two public entry points rather than in {@see cancelPolicyCore}
     * because both callers turn a throw from the core into an HTTP 500 — this is
     * a business rule, not a server fault, and it should not be logged as one.
     *
     * A cancellation dated after we already knew of a loss reads as retroactive
     * withdrawal of cover, which is why the CFO wants Claims to decide first.
     * Overridable by OpenClaimHold::PERM_OVERRIDE, recorded on the policy's
     * activity trail.
     */
    private function openClaimHoldResponse(Policy $policy): ?JsonResponse
    {
        $hold     = app(OpenClaimHold::class);
        $blocking = $hold->blockingClaims((int) $policy->id, $policy->policyNumber);

        if ($blocking === []) {
            return null;
        }

        if ($hold->canOverride(auth()->user())) {
            activity('Policy')
                ->performedOn($policy)
                ->causedBy(auth()->user())
                ->withProperties(['open_claims' => $blocking])
                ->log('Policy cancelled despite ' . count($blocking)
                      . ' open claim(s) — open-claim hold overridden');
            return null;
        }

        return response()->json([
            'error'       => $hold->message($blocking, 'cancelling this policy'),
            'open_claims' => $blocking,
        ], 409);
    }

    private function cancelPolicyCore(Policy $policy, ?string $reason, ?string $circumstances, ?string $otherCompany): array
    {
        if ((int) $policy->status === 2) {
            return ['status' => true, 'message' => 'Policy already cancelled'];
        }

        DB::beginTransaction();
        try {
            $feedback = new CustomerFeedback();
            $feedback->policy_id     = $policy->id;
            $feedback->customer_id   = $policy->customer_id;
            $feedback->product_id    = $policy->product_id;
            $feedback->reason        = $reason;
            $feedback->circumstances = $circumstances;
            $feedback->other_company = $otherCompany;
            // users has no `name` column — record firstName lastName like the
            // V1 admin cancel flow (CancelPolicyRequestController) does.
            $feedback->cancelled_by  = $this->actingUserName();
            $feedback->save();

            // v8 parity: only the status flips to 2 here. The cancelled date is
            // recorded separately in policy_status_logs by updatePolicyDates()
            // below — there is no cancelled-date column on the policies table.
            $policy->status = 2;
            $policy->save();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('CancelRequest.cancelPolicyCore.cancel_policy: ' . $e->getMessage());
            throw $e;
        }

        // Payment-channel cancellation makes external API calls (RealPay SOAP /
        // DPO events) and manages its own errors, so it runs after the policy
        // state is committed — a failed contract cancellation still leaves the
        // policy cancelled and is retryable, exactly as v8.
        $paymentCancel = ['status' => true, 'message' => 'No payment arrangement'];
        $admin = new AdminPolicyController();
        try {
            $admin->updatePolicyDates($policy->policyNumber, 2);
            $resp = $admin->CancelPaymentsForPolicy($policy);
            $paymentCancel = $resp->getData(true);
        } catch (\Throwable $e) {
            Log::error('CancelRequest.cancelPolicyCore.cancel_payments: ' . $e->getMessage());
            $paymentCancel = ['status' => false, 'message' => $e->getMessage()];
        }

        // Name the RealPay outcome explicitly on the policy's Logs tab. A
        // cancellation whose debit order could not be stopped is the one thing
        // back-office still has to act on, and "Payment cancel: failed" on its
        // own never said whether money could still leave the customer's account.
        $realpay    = $paymentCancel['realpay'] ?? null;
        $realpayNote = '';
        if (is_array($realpay)) {
            if (!empty($realpay['cancelled'])) {
                $realpayNote = ' RealPay contract(s) cancelled: ' . implode(', ', $realpay['cancelled']) . '.';
            }
            if (!empty($realpay['failed'])) {
                $realpayNote .= ' RealPay contract(s) STILL ACTIVE: ' . implode(', ', $realpay['failed'])
                    . ' — queued for realpay:retry-policy-cancellations.';
            }
        }

        activity('Policy')->performedOn($policy)->causedBy(auth()->user())
            ->log('Policy cancelled. Payment cancel: '
                . (($paymentCancel['status'] ?? false) ? 'success' : 'failed')
                . '.' . $realpayNote);

        // Old-Graphite parity: notify the customer that their policy has been
        // cancelled (SMS + email), mirroring CancelPolicyRequestController::
        // approved. Runs after the state change is committed; each channel is
        // best-effort and swallows its own errors, so a delivery failure never
        // affects the cancellation itself.
        $this->sendCancellationNotice($policy, 'email');
        $this->sendCancellationNotice($policy, 'sms');

        return $paymentCancel;
    }

    /**
     * POST /api/v1/policies/{id}/resend-cancellation/{type}
     *
     * Re-send the cancellation SMS or email for an already-cancelled policy —
     * parity with the old Graphite Edit Policy page's "Send policy cancelled
     * email" / "Send policy cancelled sms" buttons
     * (PolicyController::cancelledPolicySmsEmail). type ∈ {email, sms}.
     */
    public function resendCancellationNotice(int $id, string $type): JsonResponse
    {
        $type = strtolower($type);
        if (!in_array($type, ['email', 'sms'], true)) {
            return response()->json(['error' => 'Invalid notification type.'], 422);
        }

        $policy = Policy::find($id);
        if (!$policy) {
            return response()->json(['error' => 'Policy not found.'], 404);
        }
        if ((int) $policy->status !== 2) {
            return response()->json(['error' => 'Policy is not cancelled.'], 422);
        }

        $result = $this->sendCancellationNotice($policy, $type);

        return response()->json($result, ($result['status'] ?? false) ? 200 : 422);
    }

    /**
     * Send the policy-cancellation notice to the customer on a single channel
     * ('email' or 'sms'). A faithful port of the old Graphite admin behaviour
     * (CancelPolicyRequestController::approved / PolicyController::
     * cancelledPolicySmsEmail):
     *   - email: cancel_policy EmailBroadcasting subject + Mail.mailTemplate
     *            body, dispatched through the SendMail event.
     *   - sms:   SmsMessaging::SendSMSEmailPolicyCancelled (Setswana greeting +
     *            policy number), itself gated by its SmsControls row.
     *
     * Returns ['status' => bool, 'message' => string] and never throws — callers
     * (auto-send + resend endpoint) treat it as best-effort.
     */
    private function sendCancellationNotice(Policy $policy, string $type): array
    {
        $customer = Customer::find($policy->customer_id);
        if (!$customer) {
            return ['status' => false, 'message' => 'Customer not found for this policy.'];
        }

        try {
            if ($type === 'email') {
                if (empty($customer->email)) {
                    return ['status' => false, 'message' => 'Customer has no email address.'];
                }

                $data = new \stdClass();
                $data->user_id     = null;
                $data->policy_id   = $policy->id;
                $data->customer_id = $customer->id;
                $data->hook        = 'cancel_policy';
                $data->attachment  = null;

                $emailTemplate = EmailBroadcasting::where('hook_slug', $data->hook)->first(['subject']);
                $markdown = new MailTemplate($data);
                $html = $markdown->render('Mail.mailTemplate', ['data' => $data]);

                event(new SendMail(
                    $customer->email,
                    $emailTemplate->subject ?? 'Policy Cancelled',
                    '',
                    $html,
                    null,
                    ['policyNumber' => $policy->policyNumber, 'hook' => $data->hook]
                ));

                activity('Policy Cancel Email')->performedOn($policy)->causedBy(auth()->user())
                    ->log('Policy Cancel Email sent');

                return ['status' => true, 'message' => 'Cancellation email sent.'];
            }

            if ($type === 'sms') {
                if (empty($customer->cellphone)) {
                    return ['status' => false, 'message' => 'Customer has no cellphone number.'];
                }

                $sms = new SmsMessaging();
                $sms->SendSMSEmailPolicyCancelled($customer->cellphone, $customer->firstName, $policy->policyNumber);

                activity('Policy Cancel SMS')->performedOn($policy)->causedBy(auth()->user())
                    ->log('Policy Cancel SMS sent');

                return ['status' => true, 'message' => 'Cancellation SMS sent.'];
            }

            return ['status' => false, 'message' => 'Unknown notification type.'];
        } catch (\Throwable $e) {
            Log::error("CancelRequest.sendCancellationNotice.{$type}: " . $e->getMessage());
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }
}
