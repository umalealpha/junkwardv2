<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Exports\UnionPaymentsTemplateExport;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Imports\UnionPaymentsImport;
use AlphaDirect\Models\Union;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Union monthly premium collection (BONU brief, 2026-09-08).
 *
 * Two artefacts per union per calendar month:
 *  1. The PAYMENT LIST — who paid. Imported from the union's spreadsheet and
 *     stored one row per member per month in `union_member_payments`. The
 *     Payments page shows every active member as Paid / Unpaid for the month;
 *     Claims reads the same table to flag whether the claimant had paid for
 *     the month before their claim is processed.
 *  2. PROOF OF PAYMENT files — the union's bank confirmation / remittance,
 *     uploaded to S3 and listed for Underwriting, Accounts and Claims.
 *
 * Reads are gated by `view_unions`; imports/uploads/deletes by
 * `manage_union_payments` (route middleware, same convention as the module).
 */
class UnionPaymentsController extends Controller
{
    private const IMPORT_HEADINGS = ['id_number'];

    public const PROOF_MIMES = 'pdf,jpg,jpeg,png,xlsx,xls,csv';

    // ─── Payment list ────────────────────────────────────────────────────────

    /**
     * GET /unions/{id}/payments?period=YYYY-MM&search=&status=paid|unpaid&page=
     * Every active member for the month with their paid/unpaid state, plus
     * payment-list rows whose ID is not on the roster (so they are not lost).
     */
    public function index(Request $request, int $id): JsonResponse
    {
        $union = $this->union($id);
        if (!$union) return response()->json(['error' => 'Union not found.'], 404);

        $period = $this->period($request->get('period'));
        if (!$period) return response()->json(['error' => 'period must be YYYY-MM.'], 422);

        $search  = trim((string) $request->get('search', ''));
        $status  = $request->get('status'); // paid | unpaid | null
        $perPage = min(200, max(10, (int) $request->get('per_page', 50)));

        $q = DB::table('union_members as m')
            ->leftJoin('union_member_payments as p', function ($j) use ($period) {
                $j->on('p.union_member_id', '=', 'm.id')->where('p.period', '=', $period);
            })
            ->where('m.union_id', $id)
            ->whereNull('m.deleted_at')
            ->where('m.status', 1)
            ->select([
                'm.id', 'm.id_number', 'm.member_name', 'm.member_type', 'm.contact_number',
                'p.id as payment_id', 'p.amount', 'p.paid_on', 'p.reference', 'p.source',
            ]);

        if ($search !== '') {
            $q->where(function ($w) use ($search) {
                $w->where('m.member_name', 'like', "%{$search}%")
                  ->orWhere('m.id_number', 'like', "%{$search}%")
                  ->orWhere('m.contact_number', 'like', "%{$search}%");
            });
        }
        if ($status === 'paid')   $q->whereNotNull('p.id');
        if ($status === 'unpaid') $q->whereNull('p.id');

        $page = $q->orderBy('m.member_name')->paginate($perPage);

        $rows = collect($page->items())->map(fn ($r) => [
            'member_id'      => (int) $r->id,
            'id_number'      => $r->id_number,
            'member_name'    => $r->member_name,
            'member_type'    => $r->member_type,
            'contact_number' => $r->contact_number,
            'paid'           => $r->payment_id !== null,
            'amount'         => $r->amount !== null ? (float) $r->amount : null,
            'paid_on'        => $r->paid_on,
            'reference'      => $r->reference,
            'source'         => $r->source,
        ])->values();

        return response()->json([
            'period'       => $period,
            'summary'      => $this->summary($id, $period),
            'unmatched'    => $this->unmatched($id, $period),
            'data'         => $rows,
            'current_page' => $page->currentPage(),
            'last_page'    => $page->lastPage(),
            'from'         => $page->firstItem(),
            'to'           => $page->lastItem(),
            'total'        => $page->total(),
        ]);
    }

    /** GET /unions/{id}/payments/periods — months that have any data, newest first. */
    public function periods(int $id): JsonResponse
    {
        if (!$this->union($id)) return response()->json(['error' => 'Union not found.'], 404);

        $pay = DB::table('union_member_payments')->where('union_id', $id)
            ->select('period', DB::raw('COUNT(*) as paid'))->groupBy('period')->pluck('paid', 'period');
        $proofs = DB::table('union_payment_proofs')->where('union_id', $id)->whereNull('deleted_at')
            ->select('period', DB::raw('COUNT(*) as proofs'))->groupBy('period')->pluck('proofs', 'period');

        $periods = collect($pay->keys())->merge($proofs->keys())->unique()->sortDesc()->values()
            ->map(fn ($p) => ['period' => $p, 'paid' => (int) ($pay[$p] ?? 0), 'proofs' => (int) ($proofs[$p] ?? 0)]);

        return response()->json(['data' => $periods]);
    }

    /** GET /unions/{id}/payments/template */
    public function template(int $id)
    {
        if (!$this->union($id)) return response()->json(['error' => 'Union not found.'], 404);
        return Excel::download(new UnionPaymentsTemplateExport(), 'union_payment_list_template.xlsx');
    }

    /**
     * POST /unions/{id}/payments/import  (file, period, commit)
     * Preview (commit=0) classifies every row; commit=1 upserts on
     * (union, id_number, period) so re-importing a corrected list is safe.
     */
    public function import(Request $request, int $id): JsonResponse
    {
        $union = $this->union($id);
        if (!$union) return response()->json(['error' => 'Union not found.'], 404);

        $request->validate([
            'file'   => 'required|file|mimes:xlsx,xls,csv,txt|max:20480',
            'period' => 'required|string',
        ]);
        $period = $this->period($request->get('period'));
        if (!$period) return response()->json(['error' => 'period must be YYYY-MM.'], 422);
        $commit = $request->boolean('commit');

        @set_time_limit(300);

        try {
            $sheets = Excel::toArray(new UnionPaymentsImport(), $request->file('file'));
        } catch (\Throwable $e) {
            Log::error('UnionPayments.import.parse_error', ['union_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'Could not read that spreadsheet. Make sure it is a valid, unprotected .xlsx / .xls / .csv file.'], 422);
        }
        $rows = $sheets[0] ?? [];
        if (!$rows) return response()->json(['error' => 'The first sheet has no data rows below the heading row.'], 422);

        $missing = array_diff(self::IMPORT_HEADINGS, array_keys($rows[0]));
        if ($missing) {
            return response()->json(['error' => 'This file does not match the payment list template. It needs an "ID Number" column. Download the template and use those headings.'], 422);
        }

        $fileIds = collect($rows)->map(fn ($r) => $this->cleanId($r['id_number'] ?? ''))->filter()->unique()->values();
        $members = collect();
        foreach ($fileIds->chunk(1000) as $chunk) {
            $members = $members->union(
                DB::table('union_members')->where('union_id', $id)->whereNull('deleted_at')
                    ->whereIn('id_number', $chunk->all())
                    ->get(['id', 'id_number', 'member_name', 'status'])->keyBy('id_number')
            );
        }

        $seen = [];
        $preview = [];
        $valid = [];
        $counts = ['total' => 0, 'valid' => 0, 'unmatched' => 0, 'duplicates' => 0, 'failed' => 0, 'imported' => 0];

        foreach ($rows as $i => $row) {
            $counts['total']++;
            $idNumber = $this->cleanId($row['id_number'] ?? '');
            $name     = trim((string) ($row['name'] ?? ''));
            $amount   = $this->amount($row['amount'] ?? null);
            $paidOn   = $this->date($row['payment_date'] ?? null);
            $ref      = trim((string) ($row['reference'] ?? '')) ?: null;
            $msgs = [];
            $status = 'valid';

            if ($idNumber === '') {
                $status = 'failed';
                $msgs[] = 'ID Number is missing';
            } elseif (isset($seen[$idNumber])) {
                $status = 'duplicate';
                $msgs[] = "Same ID as row {$seen[$idNumber]} — first occurrence kept";
            } else {
                $seen[$idNumber] = $i + 2;
                $member = $members->get($idNumber);
                if (!$member) {
                    $status = 'unmatched';
                    $msgs[] = 'Not on this union\'s member register — saved for Accounts, not counted as a member payment';
                } elseif ((int) $member->status !== 1) {
                    $msgs[] = 'Member is inactive';
                }
                if ($amount === null && isset($row['amount']) && trim((string) $row['amount']) !== '') {
                    $msgs[] = 'Amount not numeric — ignored';
                }
                if ($paidOn === null && isset($row['payment_date']) && trim((string) $row['payment_date']) !== '') {
                    $msgs[] = 'Payment Date not recognised — ignored';
                }
            }

            $preview[] = [
                'row' => $i + 2, 'id_number' => $idNumber, 'name' => $name ?: ($members->get($idNumber)->member_name ?? ''),
                'amount' => $amount, 'paid_on' => $paidOn, 'status' => $status, 'messages' => $msgs,
            ];

            if ($status === 'valid' || $status === 'unmatched') {
                $counts[$status === 'valid' ? 'valid' : 'unmatched']++;
                $valid[] = [
                    'union_id'        => $id,
                    'union_member_id' => $members->get($idNumber)->id ?? null,
                    'id_number'       => $idNumber,
                    'member_name'     => $name ?: ($members->get($idNumber)->member_name ?? null),
                    'period'          => $period,
                    'amount'          => $amount,
                    'paid_on'         => $paidOn,
                    'reference'       => $ref,
                    'source'          => 'import',
                ];
            } else {
                $counts[$status === 'duplicate' ? 'duplicates' : 'failed']++;
            }
        }

        if ($commit && $valid) {
            $batch = (string) Str::uuid();
            $now = now();
            $userId = auth()->id();
            DB::transaction(function () use ($valid, $batch, $now, $userId, &$counts) {
                foreach (array_chunk($valid, 500) as $chunk) {
                    $chunk = array_map(fn ($r) => $r + ['batch_id' => $batch, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now], $chunk);
                    DB::table('union_member_payments')->upsert(
                        $chunk,
                        ['union_id', 'id_number', 'period'],
                        ['union_member_id', 'member_name', 'amount', 'paid_on', 'reference', 'source', 'batch_id', 'updated_at']
                    );
                    $counts['imported'] += count($chunk);
                }
            });

            activity('Union Payments')->performedOn($union)->causedBy(auth()->user())
                ->withProperties(['period' => $period, 'imported' => $counts['imported'], 'unmatched' => $counts['unmatched'], 'batch_id' => $batch])
                ->log("Payment list imported for {$period}");
        }

        return response()->json([
            'committed' => $commit,
            'period'    => $period,
            'summary'   => $counts + ['validation_errors' => $counts['failed']],
            'preview'   => array_slice($preview, 0, 500),
        ]);
    }

    /**
     * PUT /unions/{id}/payments/members/{memberId}  { period, paid, amount?, paid_on?, reference? }
     * Manual correction: mark one member paid or unpaid for a month.
     */
    public function setMember(Request $request, int $id, int $memberId): JsonResponse
    {
        $union = $this->union($id);
        if (!$union) return response()->json(['error' => 'Union not found.'], 404);

        $data = $request->validate([
            'period'    => 'required|string',
            'paid'      => 'required|boolean',
            'amount'    => 'nullable|numeric|min:0',
            'paid_on'   => 'nullable|date',
            'reference' => 'nullable|string|max:100',
        ]);
        $period = $this->period($data['period']);
        if (!$period) return response()->json(['error' => 'period must be YYYY-MM.'], 422);

        $member = DB::table('union_members')->where('union_id', $id)->where('id', $memberId)->whereNull('deleted_at')->first();
        if (!$member) return response()->json(['error' => 'Member not found in this union.'], 404);

        if ($data['paid']) {
            DB::table('union_member_payments')->updateOrInsert(
                ['union_id' => $id, 'id_number' => $member->id_number, 'period' => $period],
                [
                    'union_member_id' => $member->id,
                    'member_name'     => $member->member_name,
                    'amount'          => $data['amount'] ?? null,
                    'paid_on'         => $data['paid_on'] ?? null,
                    'reference'       => $data['reference'] ?? null,
                    'source'          => 'manual',
                    'created_by'      => auth()->id(),
                    'updated_at'      => now(),
                    'created_at'      => now(),
                ]
            );
        } else {
            DB::table('union_member_payments')
                ->where('union_id', $id)->where('period', $period)
                ->where(fn ($w) => $w->where('union_member_id', $member->id)->orWhere('id_number', $member->id_number))
                ->delete();
        }

        activity('Union Payments')->performedOn($union)->causedBy(auth()->user())
            ->withProperties(['period' => $period, 'member_id' => $member->id, 'id_number' => $member->id_number, 'paid' => (bool) $data['paid']])
            ->log(($data['paid'] ? 'Marked paid' : 'Marked unpaid') . " for {$period}");

        return response()->json(['message' => 'Saved.', 'summary' => $this->summary($id, $period)]);
    }

    // ─── Proof of payment files ──────────────────────────────────────────────

    /** GET /unions/{id}/payments/proofs?period=YYYY-MM (period optional = all). */
    public function proofs(Request $request, int $id): JsonResponse
    {
        if (!$this->union($id)) return response()->json(['error' => 'Union not found.'], 404);
        $period = $request->filled('period') ? $this->period($request->get('period')) : null;

        $rows = DB::table('union_payment_proofs')->where('union_id', $id)->whereNull('deleted_at')
            ->when($period, fn ($q) => $q->where('period', $period))
            ->orderByDesc('period')->orderByDesc('id')->limit(500)->get();

        return response()->json(['data' => $rows->map(fn ($r) => $this->presentProof($r))->values()]);
    }

    /** POST /unions/{id}/payments/proofs  (file, period, amount?, note?) */
    public function storeProof(Request $request, int $id): JsonResponse
    {
        $union = $this->union($id);
        if (!$union) return response()->json(['error' => 'Union not found.'], 404);

        $data = $request->validate([
            'file'   => 'required|file|mimes:' . self::PROOF_MIMES . '|max:20480',
            'period' => 'required|string',
            'amount' => 'nullable|numeric|min:0',
            'note'   => 'nullable|string|max:500',
        ]);
        $period = $this->period($data['period']);
        if (!$period) return response()->json(['error' => 'period must be YYYY-MM.'], 422);

        $file = $request->file('file');
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName());
        $path = "MIS/Unions/{$id}/ProofOfPayment/{$period}/" . time() . '_' . $safe;
        try {
            $ok = Storage::disk('s3')->put($path, file_get_contents($file->getRealPath()), 'public');
            if ($ok === false) throw new \RuntimeException('S3 put returned false');
        } catch (\Throwable $e) {
            Log::error('UnionPayments.storeProof.s3_failed', ['union_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'Upload failed: document storage is unavailable. Try again or contact IT.'], 500);
        }

        $user = auth()->user();
        $proofId = DB::table('union_payment_proofs')->insertGetId([
            'union_id'         => $id,
            'period'           => $period,
            'original_name'    => $file->getClientOriginalName(),
            'path'             => $path,
            'mime'             => $file->getClientMimeType(),
            'size'             => $file->getSize(),
            'amount'           => $data['amount'] ?? null,
            'note'             => $data['note'] ?? null,
            'uploaded_by'      => $user?->id,
            'uploaded_by_name' => $user ? trim(($user->firstName ?? '') . ' ' . ($user->lastName ?? '')) : null,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        activity('Union Payments')->performedOn($union)->causedBy($user)
            ->withProperties(['period' => $period, 'proof_id' => $proofId, 'file' => $file->getClientOriginalName(), 'amount' => $data['amount'] ?? null])
            ->log("Proof of payment uploaded for {$period}");

        $row = DB::table('union_payment_proofs')->where('id', $proofId)->first();
        return response()->json(['message' => 'Proof of payment uploaded.', 'data' => $this->presentProof($row)], 201);
    }

    /** DELETE /unions/{id}/payments/proofs/{proofId} — soft delete, file kept on S3 as evidence. */
    public function destroyProof(int $id, int $proofId): JsonResponse
    {
        $union = $this->union($id);
        if (!$union) return response()->json(['error' => 'Union not found.'], 404);

        $row = DB::table('union_payment_proofs')->where('union_id', $id)->where('id', $proofId)->whereNull('deleted_at')->first();
        if (!$row) return response()->json(['error' => 'Proof not found.'], 404);

        DB::table('union_payment_proofs')->where('id', $proofId)->update(['deleted_at' => now(), 'updated_at' => now()]);

        activity('Union Payments')->performedOn($union)->causedBy(auth()->user())
            ->withProperties(['period' => $row->period, 'proof_id' => $proofId, 'file' => $row->original_name])
            ->log("Proof of payment removed for {$row->period}");

        return response()->json(['message' => 'Removed.']);
    }

    // ─── Shared lookup used by Claims ────────────────────────────────────────

    /**
     * Paid/unpaid for one member for a month plus the month before, for the
     * "has the client paid before we process the claim" flag on claim detail.
     *
     * @return array{period:string, paid:bool, amount:?float, paid_on:?string, previous_period:string, previous_paid:bool, proofs:int}
     */
    public static function memberStatus(int $unionId, int $memberId, ?string $idNumber, string $period): array
    {
        $prev = Carbon::createFromFormat('Y-m', $period)->subMonth()->format('Y-m');
        $find = function (string $p) use ($unionId, $memberId, $idNumber) {
            return DB::table('union_member_payments')->where('union_id', $unionId)->where('period', $p)
                ->where(function ($w) use ($memberId, $idNumber) {
                    $w->where('union_member_id', $memberId);
                    if ($idNumber) $w->orWhere('id_number', $idNumber);
                })->first();
        };
        $cur = $find($period);
        $before = $find($prev);
        $proofs = DB::table('union_payment_proofs')->where('union_id', $unionId)->where('period', $period)->whereNull('deleted_at')->count();

        return [
            'period'          => $period,
            'paid'            => $cur !== null,
            'amount'          => $cur && $cur->amount !== null ? (float) $cur->amount : null,
            'paid_on'         => $cur->paid_on ?? null,
            'previous_period' => $prev,
            'previous_paid'   => $before !== null,
            'proofs'          => $proofs,
        ];
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function union(int $id): ?Union
    {
        return Union::whereNull('deleted_at')->find($id);
    }

    private function summary(int $unionId, string $period): array
    {
        $active = DB::table('union_members')->where('union_id', $unionId)->whereNull('deleted_at')->where('status', 1)->count();
        $paidQ = DB::table('union_member_payments as p')
            ->join('union_members as m', 'm.id', '=', 'p.union_member_id')
            ->where('p.union_id', $unionId)->where('p.period', $period)
            ->whereNull('m.deleted_at')->where('m.status', 1);
        $paid = (clone $paidQ)->count();
        $collected = (float) (clone $paidQ)->sum('p.amount');
        $premium = (float) (DB::table('unions')->where('id', $unionId)->value('monthly_premium') ?? 0);

        return [
            'active_members'   => $active,
            'paid'             => $paid,
            'unpaid'           => max(0, $active - $paid),
            'collected'        => round($collected, 2),
            'expected'         => round($active * $premium, 2),
            'monthly_premium'  => $premium,
            'proofs'           => DB::table('union_payment_proofs')->where('union_id', $unionId)->where('period', $period)->whereNull('deleted_at')->count(),
        ];
    }

    /** Payment-list rows for the month whose ID is not on the register. */
    private function unmatched(int $unionId, string $period): array
    {
        return DB::table('union_member_payments')->where('union_id', $unionId)->where('period', $period)
            ->whereNull('union_member_id')->orderBy('member_name')->limit(200)
            ->get(['id_number', 'member_name', 'amount', 'paid_on', 'reference'])
            ->map(fn ($r) => (array) $r)->values()->all();
    }

    private function presentProof(object $r): array
    {
        return [
            'id'               => (int) $r->id,
            'period'           => $r->period,
            'original_name'    => $r->original_name,
            'url'              => $this->cdnUrl($r->path),
            'mime'             => $r->mime,
            'size'             => $r->size !== null ? (int) $r->size : null,
            'amount'           => $r->amount !== null ? (float) $r->amount : null,
            'note'             => $r->note,
            'uploaded_by_name' => $r->uploaded_by_name,
            'created_at'       => $r->created_at,
        ];
    }

    private function cdnUrl(?string $path): ?string
    {
        if (empty($path)) return null;
        $path = str_replace(' ', '%20', $path);
        $cdn = env('AWS_CLOUDFRONT');
        if ($cdn) return rtrim($cdn, '/') . '/' . ltrim($path, '/');
        try {
            return Storage::disk('s3')->url($path);
        } catch (\Throwable $e) {
            return $path;
        }
    }

    /** 'YYYY-MM' or null. Accepts 'YYYY-MM-DD' and trims to the month. */
    private function period($raw): ?string
    {
        $raw = trim((string) $raw);
        if (preg_match('/^(\d{4})-(0[1-9]|1[0-2])/', $raw, $m)) {
            return "{$m[1]}-{$m[2]}";
        }
        return null;
    }

    /** Spreadsheet IDs arrive as floats ("419217634.0") or with spaces. */
    private function cleanId($raw): string
    {
        $s = trim((string) $raw);
        if (preg_match('/^\d+\.0+$/', $s)) $s = (string) (int) $s;
        return preg_replace('/\s+/', '', $s);
    }

    private function amount($raw): ?float
    {
        if ($raw === null || $raw === '') return null;
        $s = preg_replace('/[^\d.\-]/', '', (string) $raw);
        return is_numeric($s) ? round((float) $s, 2) : null;
    }

    /** Excel serials, ISO, d/m/Y, d-m-Y → 'Y-m-d' or null. */
    private function date($raw): ?string
    {
        if ($raw === null || $raw === '') return null;
        if (is_numeric($raw) && (float) $raw > 20000 && (float) $raw < 80000) {
            try { return ExcelDate::excelToDateTimeObject((float) $raw)->format('Y-m-d'); } catch (\Throwable $e) { return null; }
        }
        $s = trim((string) $raw);
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'Y/m/d', 'd M Y', 'j M Y'] as $fmt) {
            try {
                $d = Carbon::createFromFormat($fmt, $s);
                if ($d && $d->year > 2000) return $d->format('Y-m-d');
            } catch (\Throwable $e) {
            }
        }
        try { return Carbon::parse($s)->format('Y-m-d'); } catch (\Throwable $e) { return null; }
    }
}
