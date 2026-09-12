<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Services\IntegrationSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * AlphaTransitAdminController — read-only ops view over the Alpha Transit
 * Cover ingestion (atc_* tables on mysql_system). Feeds the admin FE's
 * "Alpha Transit" page: summary tiles, shipments, remittances, claims and
 * the webhook event log (including failed events awaiting an ATC retry).
 *
 * Read-only by design: corrections happen on the ATC platform and re-sync
 * through the webhook — never by editing ingested rows here. Registered in
 * the authenticated admin read group next to the integrations routes.
 */
class AlphaTransitAdminController extends Controller
{
    public function summary(): JsonResponse
    {
        try {
            $shipments = $this->sys()->table('atc_shipments')
                ->selectRaw("COUNT(*) as total, COALESCE(SUM(premium),0) as premium, COALESCE(SUM(sum_insured),0) as sum_insured")
                ->first();
            $byStatus = $this->sys()->table('atc_shipments')
                ->selectRaw('payment_status, COUNT(*) as c')
                ->groupBy('payment_status')->pluck('c', 'payment_status');
            $payments = $this->sys()->table('atc_payments')
                ->selectRaw("COUNT(*) as total, COALESCE(SUM(amount),0) as amount")
                ->first();
            $claims = $this->sys()->table('atc_claims')
                ->selectRaw("COUNT(*) as total, SUM(CASE WHEN status IN ('open','under_review','info_required') THEN 1 ELSE 0 END) as open_count")
                ->first();
            $events = $this->sys()->table('atc_webhook_events')
                ->selectRaw("COUNT(*) as total, SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END) as failed, MAX(received_at) as last_received_at")
                ->first();

            return response()->json([
                'enabled'   => IntegrationSettings::isEnabled('alpha_transit'),
                'shipments' => [
                    'total'       => (int) $shipments->total,
                    'premium'     => (float) $shipments->premium,
                    'sum_insured' => (float) $shipments->sum_insured,
                    'unpaid'      => (int) ($byStatus['unpaid'] ?? 0),
                    'paid'        => (int) ($byStatus['paid'] ?? 0),
                    'settled'     => (int) ($byStatus['settled'] ?? 0),
                ],
                'payments'  => ['total' => (int) $payments->total, 'amount' => (float) $payments->amount],
                'claims'    => ['total' => (int) $claims->total, 'open' => (int) $claims->open_count],
                'events'    => [
                    'total'            => (int) $events->total,
                    'failed'           => (int) $events->failed,
                    'last_received_at' => $events->last_received_at,
                ],
            ]);
        } catch (\Throwable $e) {
            // Tables absent (migration not yet run in this env) — render empty.
            return response()->json([
                'enabled'   => IntegrationSettings::isEnabled('alpha_transit'),
                'shipments' => ['total' => 0, 'premium' => 0, 'sum_insured' => 0, 'unpaid' => 0, 'paid' => 0, 'settled' => 0],
                'payments'  => ['total' => 0, 'amount' => 0],
                'claims'    => ['total' => 0, 'open' => 0],
                'events'    => ['total' => 0, 'failed' => 0, 'last_received_at' => null],
                'error'     => 'atc tables unavailable',
            ]);
        }
    }

    public function shipments(Request $request): JsonResponse
    {
        [$page, $perPage] = $this->pageArgs($request);

        $q = $this->sys()->table('atc_shipments');
        if ($request->filled('payment_status')) {
            $q->where('payment_status', $request->input('payment_status'));
        }
        if ($request->filled('company_code')) {
            $q->where('company_code', strtoupper((string) $request->input('company_code')));
        }
        if ($request->filled('search')) {
            $s = '%' . $request->input('search') . '%';
            $q->where(function ($w) use ($s) {
                $w->where('policy_number', 'like', $s)
                    ->orWhere('sender_name', 'like', $s)
                    ->orWhere('receiver_name', 'like', $s)
                    ->orWhere('courier_waybill', 'like', $s);
            });
        }

        return $this->pageOf($q, $page, $perPage);
    }

    public function payments(Request $request): JsonResponse
    {
        [$page, $perPage] = $this->pageArgs($request);

        $q = $this->sys()->table('atc_payments');
        if ($request->filled('company_code')) {
            $q->where('company_code', strtoupper((string) $request->input('company_code')));
        }

        return $this->pageOf($q, $page, $perPage);
    }

    public function claims(Request $request): JsonResponse
    {
        [$page, $perPage] = $this->pageArgs($request);

        $q = $this->sys()->table('atc_claims');
        if ($request->filled('status')) {
            $q->where('status', $request->input('status'));
        }
        if ($request->filled('search')) {
            $s = '%' . $request->input('search') . '%';
            $q->where(function ($w) use ($s) {
                $w->where('claim_number', 'like', $s)
                    ->orWhere('policy_number', 'like', $s)
                    ->orWhere('claimant_name', 'like', $s);
            });
        }

        return $this->pageOf($q, $page, $perPage);
    }

    /**
     * Everything ATC stores for one legacy policy — the shipment risk record,
     * the courier registry row it belongs to and any ATC-side claims. Feeds
     * the "Product Details" tab on the V2 policy view for Alpha Transit
     * Cover policies (product 25).
     */
    public function policyShipment(int $policyId): JsonResponse
    {
        try {
            $shipment = $this->sys()->table('atc_shipments')
                ->where('policy_id', $policyId)
                ->orderByDesc('id')
                ->first();

            if (!$shipment) {
                return response()->json(['shipment' => null, 'courier' => null, 'claims' => []]);
            }

            $courier = $this->sys()->table('atc_couriers')
                ->where('company_code', $shipment->company_code)
                ->first();

            $claims = $this->sys()->table('atc_claims')
                ->where('policy_number', $shipment->policy_number)
                ->orderByDesc('id')
                ->get();

            return response()->json([
                'shipment' => $shipment,
                'courier'  => $courier,
                'claims'   => $claims,
            ]);
        } catch (\Throwable $e) {
            // Tables absent (migration not yet run in this env) — render empty.
            return response()->json([
                'shipment' => null,
                'courier'  => null,
                'claims'   => [],
                'error'    => 'atc tables unavailable',
            ]);
        }
    }

    public function events(Request $request): JsonResponse
    {
        [$page, $perPage] = $this->pageArgs($request, 50);

        // payload is excluded from the listing (raw envelopes can be large);
        // error carries the rejection reason for failed events.
        $q = $this->sys()->table('atc_webhook_events')
            ->select(['id', 'idempotency_key', 'event_type', 'status', 'entity_type', 'graphite_id', 'error', 'received_at', 'processed_at']);
        if ($request->filled('status')) {
            $q->where('status', $request->input('status'));
        }
        if ($request->filled('event_type')) {
            $q->where('event_type', $request->input('event_type'));
        }

        return $this->pageOf($q, $page, $perPage);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /** @return array{0:int,1:int} */
    private function pageArgs(Request $request, int $defaultPerPage = 25): array
    {
        $page    = max(1, (int) $request->input('page', 1));
        $perPage = min(100, max(1, (int) $request->input('per_page', $defaultPerPage)));
        return [$page, $perPage];
    }

    private function pageOf($query, int $page, int $perPage): JsonResponse
    {
        try {
            // Fetch one extra row to answer has_more without a COUNT(*).
            $rows = $query->orderByDesc('id')
                ->offset(($page - 1) * $perPage)
                ->limit($perPage + 1)
                ->get();
        } catch (\Throwable $e) {
            return response()->json([
                'data' => [],
                'meta' => ['page' => $page, 'per_page' => $perPage, 'has_more' => false],
                'error' => 'atc tables unavailable',
            ]);
        }

        $hasMore = $rows->count() > $perPage;

        return response()->json([
            'data' => $rows->take($perPage)->values(),
            'meta' => ['page' => $page, 'per_page' => $perPage, 'has_more' => $hasMore],
        ]);
    }

    private function sys()
    {
        return DB::connection('mysql_system');
    }
}
