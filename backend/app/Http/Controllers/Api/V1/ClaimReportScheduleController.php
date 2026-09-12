<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Models\ClaimReportSchedule;
use AlphaDirect\Services\Claims\ClaimReportAssembler;
use AlphaDirect\Services\Claims\ClaimReportRenderer;
use AlphaDirect\Services\IntegrationSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Admin CRUD for scheduled claims KPI reports (Claims → Admin → Report
 * Schedules), plus a "send now (preview)" that renders the report HTML without
 * sending, and a "run now" that runs the schedule through the send-gated
 * command.
 *
 * Role-gated (Admin | Super Admin | Claims Manager) — the route group applies
 * the same middleware; this controller re-checks defensively.
 *
 * The whole feature is additive and inert until an admin creates + enables a
 * schedule AND the `claims_scheduled_reports` flag is turned on.
 */
class ClaimReportScheduleController extends Controller
{
    private const MANAGE_ROLES = ['Admin', 'admin', 'Super Admin', 'Claims Manager'];

    public function index(): JsonResponse
    {
        $rows = ClaimReportSchedule::query()->orderByDesc('id')->get()->map(fn ($s) => $this->present($s));

        return response()->json([
            'data'       => $rows,
            'flagOn'     => IntegrationSettings::isEnabled('claims_scheduled_reports', false),
            'reportTypes'=> $this->reportTypeOptions(),
            'canManage'  => $this->canManage(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (!$this->canManage()) {
            return response()->json(['message' => 'Not authorised.'], 403);
        }

        $data = $this->validatePayload($request);
        $data['created_by'] = optional(Auth::user())->id;
        $data['updated_by'] = optional(Auth::user())->id;

        $schedule = ClaimReportSchedule::create($data);

        return response()->json(['data' => $this->present($schedule), 'message' => 'Schedule created.'], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if (!$this->canManage()) {
            return response()->json(['message' => 'Not authorised.'], 403);
        }

        $schedule = ClaimReportSchedule::find($id);
        if (!$schedule) {
            return response()->json(['message' => 'Schedule not found.'], 404);
        }

        $data = $this->validatePayload($request);
        $data['updated_by'] = optional(Auth::user())->id;
        $schedule->update($data);

        return response()->json(['data' => $this->present($schedule->fresh()), 'message' => 'Schedule updated.']);
    }

    public function destroy(int $id): JsonResponse
    {
        if (!$this->canManage()) {
            return response()->json(['message' => 'Not authorised.'], 403);
        }

        $schedule = ClaimReportSchedule::find($id);
        if (!$schedule) {
            return response()->json(['message' => 'Schedule not found.'], 404);
        }
        $schedule->delete();

        return response()->json(['message' => 'Schedule deleted.']);
    }

    /**
     * Send-now PREVIEW — assembles + renders the report and returns the HTML.
     * Never sends mail, regardless of the flag. Used by the admin "Preview"
     * button so an operator sees exactly what would go out.
     */
    public function preview(Request $request, ClaimReportAssembler $assembler, ClaimReportRenderer $renderer): JsonResponse
    {
        if (!$this->canManage()) {
            return response()->json(['message' => 'Not authorised.'], 403);
        }

        $validated = $request->validate([
            'report_type' => ['nullable', Rule::in(array_keys(ClaimReportAssembler::REPORT_TYPES))],
            'frequency'   => ['nullable', Rule::in(['daily', 'weekly', 'monthly'])],
            'schedule_id' => ['nullable', 'integer'],
        ]);

        $reportType = $validated['report_type'] ?? 'executive_kpi';
        $frequency  = $validated['frequency'] ?? 'weekly';

        // If previewing an existing schedule, use its type/frequency/period.
        if (!empty($validated['schedule_id'])) {
            $schedule = ClaimReportSchedule::find($validated['schedule_id']);
            if ($schedule) {
                $reportType = $schedule->report_type;
                $frequency  = $schedule->frequency;
            }
        }

        $now  = Carbon::now();
        [$from, $to] = $this->periodForFrequency($frequency, $now);
        $report = $assembler->assemble($reportType, $from, $to);

        return response()->json([
            'data' => [
                'subject' => $renderer->subject($report),
                'html'    => $renderer->render($report),
                'report'  => $report,
                'note'    => 'Preview only — no email was sent.',
            ],
        ]);
    }

    /**
     * Run a single schedule NOW through the send-gated command. Sends only if
     * the flag is ON + the schedule is enabled + it has recipients; otherwise
     * it renders/logs and reports back why nothing was sent.
     */
    public function runNow(int $id): JsonResponse
    {
        if (!$this->canManage()) {
            return response()->json(['message' => 'Not authorised.'], 403);
        }

        $schedule = ClaimReportSchedule::find($id);
        if (!$schedule) {
            return response()->json(['message' => 'Schedule not found.'], 404);
        }

        Artisan::call('claims:run-report-schedules', ['--schedule' => $id, '--force' => true]);

        $fresh = $schedule->fresh();
        return response()->json([
            'data'    => $this->present($fresh),
            'output'  => trim(Artisan::output()),
            'message' => 'Run complete — last status: ' . ($fresh->last_status ?? 'unknown'),
        ]);
    }

    // ── helpers ───────────────────────────────────────────────────────────

    private function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:150'],
            'report_type'   => ['required', Rule::in(array_keys(ClaimReportAssembler::REPORT_TYPES))],
            'frequency'     => ['required', Rule::in(['daily', 'weekly', 'monthly'])],
            'day_of_week'   => ['nullable', 'integer', 'between:0,6', 'required_if:frequency,weekly'],
            'day_of_month'  => ['nullable', 'integer', 'between:1,31', 'required_if:frequency,monthly'],
            'hour'          => ['required', 'integer', 'between:0,23'],
            'recipients'    => ['nullable', 'array'],
            'recipients.*'  => ['email'],
            'enabled'       => ['nullable', 'boolean'],
        ]);

        return [
            'name'         => $validated['name'],
            'report_type'  => $validated['report_type'],
            'frequency'    => $validated['frequency'],
            'day_of_week'  => $validated['frequency'] === 'weekly' ? ($validated['day_of_week'] ?? null) : null,
            'day_of_month' => $validated['frequency'] === 'monthly' ? ($validated['day_of_month'] ?? null) : null,
            'hour'         => $validated['hour'],
            'recipients'   => array_values(array_unique($validated['recipients'] ?? [])),
            'enabled'      => (bool) ($validated['enabled'] ?? false),
        ];
    }

    private function present(ClaimReportSchedule $s): array
    {
        return [
            'id'           => $s->id,
            'name'         => $s->name,
            'reportType'   => $s->report_type,
            'reportLabel'  => ClaimReportAssembler::REPORT_TYPES[$s->report_type] ?? $s->report_type,
            'frequency'    => $s->frequency,
            'dayOfWeek'    => $s->day_of_week,
            'dayOfMonth'   => $s->day_of_month,
            'hour'         => $s->hour,
            'recipients'   => $s->recipientList(),
            'enabled'      => (bool) $s->enabled,
            'lastRunAt'    => optional($s->last_run_at)->toIso8601String(),
            'lastStatus'   => $s->last_status,
            'lastNote'     => $s->last_note,
        ];
    }

    private function reportTypeOptions(): array
    {
        $out = [];
        foreach (ClaimReportAssembler::REPORT_TYPES as $code => $label) {
            $out[] = ['code' => $code, 'label' => $label];
        }
        return $out;
    }

    private function periodForFrequency(string $frequency, Carbon $now): array
    {
        switch ($frequency) {
            case 'daily':
                return [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()];
            case 'monthly':
                return [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()];
            case 'weekly':
            default:
                return [$now->copy()->subDays(7)->startOfDay(), $now->copy()->subDay()->endOfDay()];
        }
    }

    private function canManage(): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }
        try {
            return $user->hasAnyRole(self::MANAGE_ROLES);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
