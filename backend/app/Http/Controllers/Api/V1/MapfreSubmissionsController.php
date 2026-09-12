<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * MAPFRE / MAWDY travel binds — read-only ops console.
 *
 * A travel sale made through the Start portal never becomes a Graphite policy:
 * the contract is bound in MAPFRE's book and all we keep is the audit row
 * MapfreTravelClient writes to `mapfre_quote_submissions` (V2 ops DB), plus the
 * proposal form beside it in `travel_proposal_forms`. Nothing about that sale
 * therefore appears on any policy screen, PDF, ledger or renewal list — this
 * console is the only place ops can see it.
 *
 * Endpoints (api_v1.php, behind auth:sanctum + the read group):
 *   GET /api/v1/admin/mapfre-submissions/summary  KPI tiles
 *   GET /api/v1/admin/mapfre-submissions          paginated + filtered list
 *   GET /api/v1/admin/mapfre-submissions/export   the same rows as CSV
 *
 * Authorisation mirrors IntegrationSettingsController::canManage — admin /
 * Super Admin / developer, or the optional `manage_integrations` permission.
 *
 * DPA 2024: the stored `payload` is the full MAPFRE contract body and holds
 * the policyholder's fiscal id (Omang / passport), residential address, date
 * of birth, email and mobile. NONE of that is returned here. Only the trip
 * facts an operator needs to recognise a sale — destination, dates, traveller
 * count, and the holder's name — leave this controller, and the name is the
 * only personal field among them. There is deliberately no "show full payload"
 * action: reading the raw body is a DB-level task with its own approval.
 */
class MapfreSubmissionsController extends Controller
{
    /** Statuses MapfreTravelClient can write. */
    private const STATUSES = ['pending', 'submitted', 'failed'];

    // ─── Endpoints ──────────────────────────────────────────────────────────

    /**
     * KPI tiles. The one that matters operationally is `submitted_unnumbered`:
     * a contract bound upstream that carries no TRVL number is a sold policy
     * we cannot name to the customer, and it needs a human.
     */
    public function summary(Request $request): JsonResponse
    {
        if ($denied = $this->denyUnlessPermitted()) return $denied;

        $now        = Carbon::now();
        $dayStart   = (clone $now)->startOfDay();
        $monthStart = (clone $now)->startOfMonth();

        $countWhere = fn (callable $scope): int => (int) $scope($this->submissions())->count();

        $boundToday = $countWhere(fn ($q) => $q
            ->where('status', 'submitted')
            ->where('submitted_at', '>=', $dayStart));

        $boundMonth = $countWhere(fn ($q) => $q
            ->where('status', 'submitted')
            ->where('submitted_at', '>=', $monthStart));

        $failedMonth = $countWhere(fn ($q) => $q
            ->where('status', 'failed')
            ->where('submitted_at', '>=', $monthStart));

        // Still 'pending' well after the call means the contract response never
        // came back to update the row — neither a clean sale nor a clean
        // failure, so it is called out separately rather than folded into one
        // of the two.
        $stuckPending = $countWhere(fn ($q) => $q
            ->where('status', 'pending')
            ->where('created_at', '<', (clone $now)->subMinutes(30)));

        $unnumbered = $countWhere(fn ($q) => $q
            ->where('status', 'submitted')
            ->where(fn ($w) => $w->whereNull('policy_number')->orWhere('policy_number', '')));

        $attemptsMonth = $boundMonth + $failedMonth;
        $failureRate   = $attemptsMonth > 0 ? round(($failedMonth / $attemptsMonth) * 100, 1) : 0.0;

        return response()->json([
            'bound_today'          => $boundToday,
            'bound_month'          => $boundMonth,
            'failed_month'         => $failedMonth,
            'failure_rate_pct'     => $failureRate,
            'stuck_pending'        => $stuckPending,
            'submitted_unnumbered' => $unnumbered,
            'as_of'                => $now->toIso8601String(),
        ]);
    }

    /** Paginated list. Filters: status, date range, search. */
    public function list(Request $request): JsonResponse
    {
        if ($denied = $this->denyUnlessPermitted()) return $denied;

        $request->validate([
            'status'   => 'nullable|string|in:' . implode(',', self::STATUSES),
            'from'     => 'nullable|date_format:Y-m-d',
            'to'       => 'nullable|date_format:Y-m-d',
            'search'   => 'nullable|string|max:64',
            'page'     => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $perPage = min(100, max(1, (int) $request->input('per_page', 25)));
        $rows    = $this->filtered($request)->orderByDesc('id')->paginate($perPage);

        $items = $this->present(collect($rows->items()));

        return response()->json([
            'items' => $items,
            'meta'  => [
                'total'        => $rows->total(),
                'per_page'     => $rows->perPage(),
                'current_page' => $rows->currentPage(),
                'last_page'    => $rows->lastPage(),
            ],
        ]);
    }

    /**
     * The filtered set as CSV. Capped rather than unbounded — this is an ops
     * console, not a bulk extract, and the cap keeps one click from streaming
     * the whole audit table.
     */
    public function export(Request $request)
    {
        if ($denied = $this->denyUnlessPermitted()) return $denied;

        $request->validate([
            'status' => 'nullable|string|in:' . implode(',', self::STATUSES),
            'from'   => 'nullable|date_format:Y-m-d',
            'to'     => 'nullable|date_format:Y-m-d',
            'search' => 'nullable|string|max:64',
        ]);

        $rows = $this->present(
            $this->filtered($request)->orderByDesc('id')->limit(5000)->get()
        );

        $filename = 'mapfre_binds_' . Carbon::now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $fh = fopen('php://output', 'w');

            fputcsv($fh, [
                'id', 'reference', 'status', 'policy_number', 'mapfre_contract_number',
                'mapfre_quote_id', 'product_id', 'holder_name', 'destination',
                'departure_date', 'return_date', 'travellers', 'http_status',
                'error', 'proposal_reference', 'proposal_signed_at',
                'submitted_at', 'created_at',
            ]);

            foreach ($rows as $r) {
                fputcsv($fh, [
                    $r['id'], $r['reference'], $r['status'], $r['policy_number'],
                    $r['mapfre_contract_number'], $r['mapfre_quote_id'], $r['product_id'],
                    $r['holder_name'], $r['destination'], $r['departure_date'],
                    $r['return_date'], $r['travellers'], $r['http_status'],
                    $r['error'], $r['proposal_reference'], $r['proposal_signed_at'],
                    $r['submitted_at'], $r['created_at'],
                ]);
            }

            fclose($fh);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    // ─── Internals ──────────────────────────────────────────────────────────

    /** The MAPFRE bind audit rows — V2 ops DB, same as the client writes. */
    private function submissions()
    {
        return DB::connection('mysql_system')->table('mapfre_quote_submissions');
    }

    /**
     * One filtered query, shared by list() and export() so the CSV can never
     * drift from the table it was exported off.
     */
    private function filtered(Request $request)
    {
        $q = $this->submissions();

        if ($status = $request->input('status')) {
            $q->where('status', $status);
        }

        // Date range reads on created_at, not submitted_at: a row that never
        // got a response has no submitted_at, and those are exactly the rows an
        // operator goes looking for by date.
        if ($from = $request->input('from')) $q->where('created_at', '>=', $from . ' 00:00:00');
        if ($to   = $request->input('to'))   $q->where('created_at', '<=', $to   . ' 23:59:59');

        if ($search = trim((string) $request->input('search'))) {
            $q->where(function ($w) use ($search) {
                $w->where('reference', 'like', "%{$search}%")
                  ->orWhere('policy_number', 'like', "%{$search}%")
                  ->orWhere('mapfre_contract_number', 'like', "%{$search}%")
                  ->orWhere('mapfre_quote_id', 'like', "%{$search}%")
                  ->orWhere('product_id', 'like', "%{$search}%");
            });
        }

        return $q;
    }

    /**
     * Shape the audit rows for the UI: the trip facts pulled out of the stored
     * payload (never the payload itself), plus the proposal form that backs the
     * sale where one exists.
     */
    private function present($rows)
    {
        $rows = collect($rows);

        $proposals = $this->proposalsFor($rows);

        return $rows->map(function ($r) use ($proposals) {
            $trip = $this->tripFacts($r->payload ?? null);

            $proposal = $this->proposalFor($proposals, $r);

            return [
                'id'                     => (int) $r->id,
                'reference'              => $r->reference,
                'status'                 => $r->status,
                'policy_number'          => $r->policy_number ?? null,
                'mapfre_contract_number' => $r->mapfre_contract_number,
                'mapfre_quote_id'        => $r->mapfre_quote_id,
                'product_id'             => $r->product_id,
                'http_status'            => $r->http_status !== null ? (int) $r->http_status : null,
                'error'                  => $r->error,
                'submitted_at'           => $r->submitted_at,
                'created_at'             => $r->created_at,

                // From the payload — trip facts only, no identifiers.
                'holder_name'    => $trip['holder_name'],
                'destination'    => $trip['destination'],
                'departure_date' => $trip['departure_date'],
                'return_date'    => $trip['return_date'],
                'travellers'     => $trip['travellers'],

                // The signed proposal form behind the sale, when we can match one.
                'proposal_reference' => $proposal->reference ?? null,
                'proposal_signed_at' => $proposal->signed_at ?? null,
                'proposal_document'  => $proposal->document_url ?? null,
            ];
        })->values();
    }

    /**
     * Proposal forms for a page of binds, keyed by whatever we can match on.
     *
     * The two tables are linked only loosely: the proposal is filled in before
     * the bind, so it carries the quote id, and the contract number is written
     * back onto it afterwards. Match on either, contract number first — one
     * lookup for the whole page rather than a query per row.
     */
    private function proposalsFor($rows): array
    {
        $contracts = $rows->pluck('mapfre_contract_number')->filter()->unique()->values()->all();
        $quotes    = $rows->pluck('mapfre_quote_id')->filter()->unique()->values()->all();

        if (!$contracts && !$quotes) {
            return [];
        }

        try {
            $forms = DB::connection('mysql_system')->table('travel_proposal_forms')
                ->select('reference', 'signed_at', 'document_url', 'contract_number', 'quote_id')
                ->where(function ($w) use ($contracts, $quotes) {
                    if ($contracts) $w->orWhereIn('contract_number', $contracts);
                    if ($quotes)    $w->orWhereIn('quote_id', $quotes);
                })
                ->orderBy('id')
                ->get();
        } catch (\Throwable $e) {
            // Proposal table absent (pre-migration env) — the binds still list.
            return [];
        }

        $keyed = [];
        foreach ($forms as $f) {
            // A later row for the same key wins: a resubmitted proposal
            // supersedes the draft it replaced.
            if (!empty($f->contract_number)) $keyed['c:' . $f->contract_number] = $f;
            if (!empty($f->quote_id))        $keyed['q:' . $f->quote_id]        = $f;
        }

        return $keyed;
    }

    /**
     * The proposal form behind a bind row, or null.
     *
     * Tries the contract number first, then falls back to the quote id. The
     * fallback is the whole point: the proposal is filled in BEFORE the bind, so
     * it always carries the quote id, while the contract number is only written
     * back afterwards and may never have been. Keying solely on the contract
     * number — as this did — silently returned no proposal for exactly those
     * rows, even though proposalsFor() had already indexed them under 'q:'.
     */
    private function proposalFor(array $proposals, $r)
    {
        $contract = (string) ($r->mapfre_contract_number ?? '');
        $quote    = (string) ($r->mapfre_quote_id ?? '');

        if ($contract !== '' && isset($proposals['c:' . $contract])) {
            return $proposals['c:' . $contract];
        }

        if ($quote !== '' && isset($proposals['q:' . $quote])) {
            return $proposals['q:' . $quote];
        }

        return null;
    }

    /**
     * Trip facts out of the stored MAPFRE contract body.
     *
     * Deliberately narrow: destination, the two dates, the traveller count and
     * the holder's name. `infoPolicyHolder.fiscalId` (Omang / passport),
     * `address`, `birthDate`, `email` and `mobile` are never read here — see
     * the DPA note on the class.
     */
    private function tripFacts($payload): array
    {
        $blank = [
            'holder_name'    => null,
            'destination'    => null,
            'departure_date' => null,
            'return_date'    => null,
            'travellers'     => null,
        ];

        if (!is_string($payload) || $payload === '') {
            return $blank;
        }

        $body = json_decode($payload, true);
        if (!is_array($body)) {
            return $blank;
        }

        $holder = is_array($body['infoPolicyHolder'] ?? null) ? $body['infoPolicyHolder'] : [];
        $name   = trim(implode(' ', array_filter([
            (string) ($holder['name'] ?? ''),
            (string) ($holder['surname'] ?? ''),
        ])));

        return [
            'holder_name'    => $name !== '' ? $name : null,
            'destination'    => $body['destination'] ?? null,
            // MAPFRE's own DD/MM/YYYY, passed through as stored — this is the
            // audit of what we sent, not a re-rendering of it.
            'departure_date' => $body['departureDate'] ?? null,
            'return_date'    => $body['arrivalDate'] ?? null,
            'travellers'     => isset($body['numberOfTravelers']) ? (int) $body['numberOfTravelers'] : null,
        ];
    }

    /**
     * admin / Super Admin / developer, or the optional `manage_integrations`
     * permission. Mirrors IntegrationSettingsController::canManage.
     */
    private function denyUnlessPermitted(): ?JsonResponse
    {
        $user = Auth::user();

        if ($user) {
            if ($user->hasAnyRole(['admin', 'Super Admin', 'developer'])) {
                return null;
            }
            try {
                if ($user->hasPermissionTo('manage_integrations')) {
                    return null;
                }
            } catch (\Throwable $e) {
                // Permission not seeded — roles only.
            }
        }

        return response()->json([
            'error'   => 'forbidden',
            'message' => 'You do not have access to the MAPFRE bind console.',
        ], 403);
    }
}
