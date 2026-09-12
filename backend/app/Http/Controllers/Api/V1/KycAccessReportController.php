<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Exports\KycAccessReportExport;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * KYC Access Audit Report.
 *
 * Faithful API port of graphiteBWV8's Admin\KycAccessReportController
 * (resources/views/admin/reports/kycAccessReport). Lists every user who
 * currently holds a KYC verification permission (via role or directly) and
 * any user who has modified KYC records within the selected date range,
 * alongside their actual KYC activity from the `audits` table.
 *
 * V8 served this through a Yajra DataTables JSON endpoint + a Blade view.
 * V2 is API + React, so:
 *   - index()  returns the V2-standard { data, meta } paginated shape with
 *              server-side search + date-range filtering.
 *   - export() streams the same data as an .xlsx via maatwebsite/excel.
 *
 * Both endpoints are gated by the `kyc-access-report` permission on the
 * route (see routes/api_v1.php).
 */
class KycAccessReportController extends Controller
{
    /**
     * KYC-related permission slugs — a user holding any of these is treated
     * as having KYC verification access. Mirrors the V8 list verbatim.
     */
    protected array $kycPermissions = [
        'customer-kyc-list',
        'customer-kyc-view',
        'customer-kyc-edit',
        // Since 2026-09-10 this is the ONLY permission that lets a person
        // approve / reject KYC (KYC Approver role). Listed so the audit
        // report shows who actually holds decision rights.
        'customer-kyc-approve',
        'customer-kyc-archive',
        'kyc-compliance-edit',
        'kyc-compliance-delete',
    ];

    /**
     * GET /v1/kyc/access-report
     *
     * Paginated, searchable listing for the React table.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'from_date' => 'nullable|date',
            'to_date'   => 'nullable|date',
            'search'    => 'nullable|string|max:255',
            'page'      => 'nullable|integer|min:1',
            'per_page'  => 'nullable|integer|min:1|max:200',
        ]);

        [$fromDate, $toDate] = $this->resolveDateRange($request);

        $rows = array_map(
            fn ($row) => $this->mapRow($row),
            $this->getKycAccessUsers($fromDate, $toDate)
        );

        // Global search across the displayed columns (mirrors the V8
        // DataTables search box, which searched every visible column).
        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $rows = array_values(array_filter($rows, function ($row) use ($needle) {
                foreach (['id', 'full_name', 'email', 'roles', 'kyc_permissions', 'current_status'] as $col) {
                    if (mb_strpos(mb_strtolower((string) $row[$col]), $needle) !== false) {
                        return true;
                    }
                }
                return false;
            }));
        }

        $total   = count($rows);
        $perPage = (int) $request->input('per_page', 10);
        $page    = (int) $request->input('page', 1);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page    = min($page, $lastPage);
        $offset  = ($page - 1) * $perPage;
        $slice   = array_slice($rows, $offset, $perPage);

        return response()->json([
            'data' => $slice,
            'meta' => [
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => $lastPage,
                'from'         => $total ? $offset + 1 : 0,
                'to'           => $offset + count($slice),
            ],
        ]);
    }

    /**
     * GET /v1/kyc/access-report/export
     *
     * Streams the current filter as an .xlsx download.
     */
    public function export(Request $request)
    {
        $request->validate([
            'from_date' => 'nullable|date',
            'to_date'   => 'nullable|date',
        ]);

        [$fromDate, $toDate] = $this->resolveDateRange($request);

        $fileName = 'KycAccessReport_'
            . Carbon::parse($fromDate)->format('Ymd') . '_to_'
            . Carbon::parse($toDate)->format('Ymd') . '.xlsx';

        return Excel::download(new KycAccessReportExport($fromDate, $toDate), $fileName);
    }

    /**
     * Format a raw query row into the display shape returned to the FE /
     * written to the export. Matches V8's editColumn/map formatting.
     *
     * @return array<string, mixed>
     */
    public function mapRow($row): array
    {
        $fullName = trim(($row->firstName ?? '') . ' ' . ($row->lastName ?? ''));

        return [
            'id'                => (int) $row->id,
            'full_name'         => $fullName !== '' ? $fullName : '--',
            'email'             => $row->email ?: '--',
            'roles'             => $row->roles ?: '--',
            'kyc_permissions'   => $row->kyc_permissions ?: '--',
            'current_status'    => $row->current_status,
            'total_kyc_actions' => (int) ($row->total_kyc_actions ?? 0),
            'first_action'      => $row->first_action ? Carbon::parse($row->first_action)->format('d-m-Y H:i') : '--',
            'last_action'       => $row->last_action ? Carbon::parse($row->last_action)->format('d-m-Y H:i') : '--',
            'user_created'      => $row->created_at ? Carbon::parse($row->created_at)->format('d-m-Y') : '--',
        ];
    }

    /**
     * Resolve the date range from request input. Falls back to
     * "last 1 year up to today" when not provided. A narrower default
     * window keeps the audits subquery scan small for the common case.
     *
     * @return array{0: string, 1: string} [fromDate, toDate] in 'Y-m-d H:i:s'
     */
    protected function resolveDateRange(Request $request): array
    {
        $from = $request->input('from_date');
        $to   = $request->input('to_date');

        $toDate = $to
            ? Carbon::parse($to)->endOfDay()->format('Y-m-d H:i:s')
            : Carbon::now()->endOfDay()->format('Y-m-d H:i:s');

        $fromDate = $from
            ? Carbon::parse($from)->startOfDay()->format('Y-m-d H:i:s')
            : Carbon::now()->subYear()->startOfDay()->format('Y-m-d H:i:s');

        return [$fromDate, $toDate];
    }

    /**
     * Build the unified KYC-access user list combining:
     *  - users with KYC permissions via roles (user_roles → roles →
     *    role_has_permissions → permissions)
     *  - users with direct KYC permissions (model_has_permissions)
     *  - actual verification activity from the `audits` table in range
     *
     * Faithful port of the V8 SQL. The only V2 adjustment is the audit
     * auditable_type: V2's KYC model is AlphaDirect\KYC (table customer_kyc)
     * with no morph map, so the stored value is the single class string
     * 'AlphaDirect\KYC' (V8 also matched a legacy 'AlphaDirect\Models\KYC'
     * which does not exist in V2 — both are kept here for safety).
     *
     * @return array<int, \stdClass>
     */
    public function getKycAccessUsers(string $fromDate, ?string $toDate = null): array
    {
        if ($toDate === null) {
            $toDate = Carbon::now()->endOfDay()->format('Y-m-d H:i:s');
        }

        $permissionList = "'" . implode("','", $this->kycPermissions) . "'";

        // NOTE: the KYC permissions are pre-filtered inside a derived table
        // (kp) rather than in the role_has_permissions join condition. The V8
        // original joined role_has_permissions directly, which fanned out
        // EVERY permission of each role (a Super Admin role can carry
        // hundreds) before nulling the non-KYC ones — an O(users x role-perms)
        // row explosion that ran for >30s and tripped the FE's 30s timeout.
        // Pre-filtering to the <=6 KYC permissions keeps the output identical
        // while collapsing the intermediate set. The audits subquery uses the
        // morphs index on auditable_type.
        //
        // The kp derived table UNIONs two sources of KYC permission, keyed on
        // the user:
        //   1. role-based  — user_roles → role_has_permissions → permissions
        //   2. direct      — model_has_permissions → permissions
        // Both feed the `kyc_permissions` column AND the current_status CASE.
        // A prior version only joined role-based permissions here and checked
        // direct permissions in the WHERE clause alone — so a user granted a
        // KYC permission DIRECTLY (not via a role) was pulled into the report
        // but shown with `--` permissions and mislabelled "Activity Only (No
        // Permission)". Unioning both sources fixes that: a directly-granted
        // KYC agent now lists their permissions and reads "Active".
        $sql = "
            SELECT
                u.id,
                u.firstName,
                u.lastName,
                u.email,
                u.created_at,
                GROUP_CONCAT(DISTINCT r.name SEPARATOR ', ')        AS roles,
                GROUP_CONCAT(DISTINCT kp.perm_name SEPARATOR ', ')  AS kyc_permissions,
                CASE
                    WHEN COUNT(DISTINCT kp.perm_id) = 0 AND COUNT(DISTINCT ur.id) = 0 THEN 'No KYC Permission'
                    WHEN COUNT(DISTINCT kp.perm_id) = 0 THEN 'Activity Only (No Permission)'
                    WHEN COUNT(DISTINCT ur.id) = 0 THEN 'No Active Role'
                    ELSE 'Active'
                END AS current_status,
                COALESCE(act.total_kyc_actions, 0) AS total_kyc_actions,
                act.first_action,
                act.last_action
            FROM users u
            LEFT JOIN user_roles ur ON ur.user_id = u.id
            LEFT JOIN roles r       ON r.id = ur.role_id
            LEFT JOIN (
                -- Role-based KYC permissions, resolved to the user.
                SELECT ur2.user_id AS user_id, p.id AS perm_id, p.name AS perm_name
                FROM user_roles ur2
                JOIN role_has_permissions rhp ON rhp.role_id = ur2.role_id
                JOIN permissions p ON p.id = rhp.permission_id
                WHERE p.name IN ({$permissionList})
                UNION
                -- Directly-assigned KYC permissions (model_has_permissions).
                SELECT mhp.model_id AS user_id, p.id AS perm_id, p.name AS perm_name
                FROM model_has_permissions mhp
                JOIN permissions p ON p.id = mhp.permission_id
                WHERE p.name IN ({$permissionList})
            ) kp ON kp.user_id = u.id
            LEFT JOIN (
                SELECT
                    a.user_id,
                    COUNT(*) AS total_kyc_actions,
                    MIN(a.created_at) AS first_action,
                    MAX(a.created_at) AS last_action
                FROM audits a
                WHERE a.auditable_type IN ('AlphaDirect\\\\KYC', 'AlphaDirect\\\\Models\\\\KYC')
                  AND a.user_id IS NOT NULL
                  AND a.created_at BETWEEN ? AND ?
                GROUP BY a.user_id
            ) act ON act.user_id = u.id
            WHERE
                -- kp now already covers BOTH role-based and direct KYC
                -- permissions (see the UNION above), so a single non-null
                -- check replaces the former separate model_has_permissions
                -- subquery. Users with only in-range activity still qualify.
                kp.perm_id IS NOT NULL
                OR act.user_id IS NOT NULL
            GROUP BY
                u.id, u.firstName, u.lastName, u.email,
                u.created_at,
                act.total_kyc_actions, act.first_action, act.last_action
            ORDER BY
                act.last_action DESC,
                u.firstName ASC
        ";

        return DB::select($sql, [$fromDate, $toDate]);
    }
}
