# Help Desk — SLA Management System (Design)

Status: **approved & implemented** (Phases 1–6). This document is the as-built
design reference for reviewers and operators.

The SLA subsystem is **additive** to the existing Help Desk and ships behind a
feature flag (`config('help_desk.sla.enabled')`, default **false**). No existing
Help Desk table, endpoint, or workflow is modified; everything below is inert
until the flag is enabled.

---

## 1. Solution architecture

SLA is a **side-car** on the ticket lifecycle. The ticket remains the source of
truth for *state*; the SLA subsystem **observes** transitions and maintains its
own timing records.

```
ticket create / status change
   → HelpDeskTicketObserver (thin, best-effort)
       → SlaService (start / respond / resolve / pause / resume)
           → help_desk_slas (state)  +  help_desk_sla_events (audit)
           → BusinessHoursCalculator (Botswana clock + holidays)

scheduler:
   hd:sla-evaluate (5 min) → SlaEvaluator → SlaNotifier (warnings/breaches)
   hd:sla-digest  (08:00)  → SlaMetricsService → SlaDigestMail

read/UI:
   SlaController         → ticket SLA panel
   SlaDashboardController→ management dashboard (role-gated, cached)
   SlaReportController   → Excel/CSV exports
```

Why an observer + service (not edits to each controller): status is mutated in
`store`, `updateStatus`, `close`, `reopen`. A single Eloquent observer
centralises SLA reactions, never misses a transition, and keeps controllers
untouched. All SLA logic lives in `SlaService` (unit-testable, no HTTP).

Reused existing infrastructure: the editable `help_desk_email_templates` +
`help_desk_notifications` dedup log + `HelpDeskNotificationMail`, the
append-only audit pattern, the `maatwebsite/excel` export pattern, and the
grouped-count dashboard pattern.

Timezone: all business-hours math is done in **Africa/Gaborone** (UTC+2, no DST);
stored timestamps remain in the app timezone.

---

## 2. Data model (additive)

| Table | Purpose |
|---|---|
| `help_desk_sla_policies` | Configurable target matrix (business minutes) per priority |
| `help_desk_business_calendar` | Weekly business hours (ISO day 1–7) |
| `help_desk_holidays` | Holiday calendar (seeded empty; engine honours it) |
| `help_desk_slas` | Live per-ticket SLA state (due dates, breach flags, pause accounting) |
| `help_desk_sla_events` | Append-only SLA audit trail |

`help_desk_slas` due-date / breach / priority columns are indexed for fast
"nearing breach" range scans at 10k+ tickets. `external_ref` + `bridge_synced_at`
exist on the policy + slas tables for future Alpha Bridge sync (not implemented).

New ticket statuses (enum-by-convention, no migration): `pending_customer`,
`pending_third_party` — both **pause** the SLA clock.

---

## 3. SLA matrix & business hours

Targets are stored in **business minutes** (the engine only counts business
time). Seeded matrix (1 business day = **9 business hours**):

| Priority | Response | Resolution |
|---|---|---|
| Critical | 1 h (60) | 6 h (360) |
| High | 2 h (120) | 8 business h (480) |
| Medium | 8 business h (480) | 3 business days (1620) |
| Low | 1 business day (540) | 5 business days (2700) |

Default business calendar: **Mon–Fri 08:00–17:00, Sat 08:00–13:00, Sun closed**
(Saturday's 5 h is counted as business time; the 9 h figure only converts
"N business days" targets into minutes). Holidays are treated as fully closed.

`BusinessHoursCalculator` (pure, unit-tested) provides `addBusinessMinutes`,
`businessMinutesBetween`, `isOpen`, `nextOpen`.

---

## 4. SLA semantics

- **Response** — satisfied the first time a ticket reaches `open` or
  `in_progress`. Tracks `response_due_at`, `first_response_at`, `response_breached`.
- **Resolution** — satisfied the first time a ticket reaches `resolved` or
  `closed`. Tracks `resolution_due_at`, `resolved_at`, `resolution_breached`.
- **Pause** — entering `pending_customer` / `pending_third_party` stops the
  clock; on resume the paused business-minutes are added to `total_paused_minutes`
  and the still-open deadlines are pushed forward by that amount.
- **Warnings** — at **75%** and **90%** consumption (response & resolution),
  once each.
- **Breach** — flagged the moment the due date passes; triggers escalation.

---

## 5. API

Ticket-scoped (visible to any ticket viewer):
- `GET /help-desk-tickets/{id}/sla` — live panel (due, remaining business-min,
  consumed %, breach flags, RAG, paused).
- `GET /help-desk-tickets/{id}/sla/events` — SLA audit timeline.

Management (gated to `config('help_desk.sla.dashboard_roles')`):
- `GET /help-desk/sla/dashboard` — summary cards, aging, priority breakdown,
  assignee performance, heat map (cached).
- `GET /help-desk/sla/reports/{type}?format=xlsx|csv&from&to` — `type ∈
  compliance | breach | assignee | monthly`.

---

## 6. Backend services

- `BusinessHoursCalculator` / `BusinessCalendarRepository` — business-hours math
  (DB-backed, cached).
- `SlaService` — lifecycle (start / respond / resolve / pause / resume).
- `SlaEvaluator` — periodic warning/breach scan.
- `SlaNotifier` + `SlaEscalationResolver` — warning/breach emails (assignee +
  escalation recipients), deduped.
- `SlaMetricsService` — dashboard + digest aggregates.
- `SlaReportService` — export datasets.

---

## 7. Scheduler

Both commands are scheduled in `app/Console/Kernel.php` (`onOneServer`,
`withoutOverlapping`) and **no-op while the flag is off**:
- `hd:sla-evaluate` — every 5 minutes.
- `hd:sla-digest` — daily at **08:00 Africa/Gaborone**.
- `hd:sla-backfill` — one-off, idempotent, reconstructs SLA rows for existing
  tickets from the audit trail.

---

## 8. Notifications

- **Warnings** (75/90%) and **breaches** → assignee + escalation recipients.
- Breach subjects: `[SLA BREACH] {ticket} Response|Resolution SLA Breached`.
- Reuses `help_desk_email_templates` (editable) + `help_desk_notifications`
  dedup (one email per recipient per stage). All best-effort.
- **Daily digest** → escalation recipients: compliance %, open by priority,
  nearing breach (24 h), breached & unresolved, top offenders.

Escalation recipients (config, env-overridable): **Pramod Bisen
(pbisen@theriskco.com)**, **Lakshmi Anand (lanand@theriskco.com)**.
Escalation is level-keyed (v1 = level 1) for future L1 Team Lead / L2 Manager /
L3 Director.

---

## 9. Dashboard

`Help Desk → SLA Dashboard` (sidebar, role-gated): summary cards (total, within
SLA, response/resolution breached, open/closed breaches, compliance %), aging
buckets (0–1 / 2–3 / 4–7 / 8–14 / 15+ days), priority breakdown
(open/breached/resolved), assignee performance table, and a click-through
management heat map (breached / nearing / unassigned / critical). The per-ticket
SLA panel is **not** role-gated — visible to anyone who can view the ticket.

---

## 10. Configuration & flags (`config/help_desk.php`)

| Key | Default | Purpose |
|---|---|---|
| `sla.enabled` | `false` | Master switch (kill-switch) |
| `sla.business_day_hours` | `9` | "N business days" → minutes |
| `sla.warning_thresholds` | `[75, 90]` | Warning %s |
| `sla.escalation_recipients` | Pramod, Lakshmi | Always notified |
| `sla.digest_time` | `08:00` | Digest send time (Botswana) |
| `sla.dashboard_roles` | Super Admin, Manager, Admin, COO, CFO | Dashboard/report access |
| `timezone` | `Africa/Gaborone` | Business-hours zone |

---

## 11. Performance

Indexed `response_due_at` / `resolution_due_at` / breach flags / priority;
grouped/aggregate queries (no N+1); dashboard payload cached (~60s); evaluator
chunked (`chunkById(500)`). Designed for 10k+ tickets.

---

## 12. Risks & rollback

- **Business-hours math** is the highest-risk piece → covered by a 10-case unit
  test (`BusinessHoursCalculatorTest`). Run before merge.
- **Backfill** is idempotent; historical pause durations are not reconstructed.
- **Rollback** — set `sla.enabled=false` (instant, no migration revert); for
  full removal, `migrate:rollback` drops only the new SLA tables. Existing Help
  Desk is untouched throughout.

---

## 13. Phase / PR map

| Phase | PR | Scope |
|---|---|---|
| 1 Foundations | #1358 | Tables, seeds, calculator, flag, unit test |
| 2 Engine | #1382 | Statuses, SlaService, observer, backfill |
| 3 Scheduler + notifications | #1385 | Evaluate, warnings/breach, escalation, digest |
| 4 Ticket panel | #1387 | Read API + sidebar SLA panel |
| 5 Dashboard | #1408 | Management dashboard (role-gated, cached) |
| 6 Reports | #1409 | Excel/CSV exports |

---

## 14. Go-live checklist

1. Merge the chain in order: #1358 → #1382 → #1385 → #1387 → #1408 → #1409.
2. `php artisan migrate` (creates tables; seeds matrix, calendar, templates).
3. `php artisan hd:sla-backfill --dry-run`, review, then run for real.
4. Confirm the scheduler runs `hd:sla-evaluate` (5 min) and `hd:sla-digest` (08:00).
5. Set `HELP_DESK_SLA_ENABLED=true`; confirm escalation recipients.
6. Smoke test: New → In Progress (response) → Pending Customer → In Progress
   (pause/resume) → Closed (resolution); check the panel, a breach email, and
   the dashboard.
