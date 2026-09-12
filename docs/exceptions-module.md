# Reconciliation Exceptions module

A new **Exceptions** line under **Reconciliation** in the sidebar. It holds the
weekly DOMG (Domestic) + COMG (Commercial) **vs RealPay mandate** reconciliation
**inside Graphite** (replacing the emailed Excel), so Finance reviews each
exception, changes its status and leaves comments — with a full audit trail.

Branch: `feature/exceptions-module` (off `main`).

## What it does

A routine task (`recon:realpay-exceptions`, scheduled **weekly, Mondays 04:00**)
runs **read-only** over the operational tables and writes the week's exceptions
into three new tables. Finance opens `/finance/exceptions`, sees a dashboard +
filterable list, drills into an exception, sets its status and comments.

### Flags (mutually exclusive per policy — no double-listing)
| Flag | Meaning | Severity |
|---|---|---|
| **A** | active monthly policy (premium_freq=1) with **no** active RealPay mandate | medium |
| **B** | active RealPay mandate on a non-active policy, **no** claim (orphan mandate) | high |
| **C** | active monthly + mandate, RealPay debit **≠** Graphite premium (>P1) | high/medium |
| **D** | non-active policy with active mandate **and a registered claim** (claim-driven) | critical |

Notes baked in: **"Agreement of Loss" is not a stored field in Graphite** —
cancellation ledger + claim presence is the proxy (flag D). RealPay "monthly" =
latest installment (may include arrears), joined by **contractNumber** (the
`realpay_contract_installments.policy_id` column is unreliable).

## Files

**Backend**
- `database/migrations/2026_06_23_000000_create_recon_exception_tables.php` — runs / exceptions / comments
- `database/migrations/2026_06_23_000100_seed_exceptions_permissions.php` — permissions + Finance role
- `app/Console/Commands/ReconRealpayExceptions.php` — the routine (read-only)
- `app/Http/Controllers/Api/V1/ExceptionsController.php` — review API
- `app/Console/Kernel.php` — registered + weekly Mon 04:00 schedule (edited)
- `routes/api_v1.php` — `finance/exceptions/*` (edited)

**Frontend** (Tailwind + Recharts, brand navy `#0D1B2A` / orange `#F4A623`)
- `src/api/exceptions.ts`
- `src/pages/Finance/ExceptionsPage.tsx` — KPIs, grouped bar (flag×product), status donut, trend line, filters, table
- `src/pages/Finance/ExceptionDetailPage.tsx` — facts, status workflow, comment thread
- `src/App.tsx`, `src/components/Layout/Sidebar.tsx` (edited)

## Deploy steps

1. **Migrate** (creates the 3 tables + seeds permissions):
   ```
   php artisan migrate
   ```
2. **Assign reviewers**: give the relevant Finance users the **Finance** role
   (it now carries `view_exceptions` + `comment_exceptions`). Admin / Super Admin
   get all three including `manage_exceptions` (manual re-generate).
3. **Scheduler**: the weekly entry is in `app/Console/Kernel.php`
   (`recon:realpay-exceptions … mondays()->at('04:00')`).
   ⚠️ **Cron-container caveat** — the Laravel scheduler that actually fires in
   prod runs in the **graphite-cron** container (built from `deployment-package/cron/`),
   which is a *different* codebase. If that container is the live scheduler, also
   register the command there **or** add a `cron_kernel` row (Cron Portal) so the
   weekly run fires. Until then it can be triggered from the UI ("Generate now")
   or `php artisan recon:realpay-exceptions`. The command is idempotent per
   `run_date` and never overwrites a Finance-closed run.
4. **Build frontend** as usual (`npm ci && npm run build`).

## First run / verify
```
php artisan recon:realpay-exceptions --dry      # prints counts, writes nothing
php artisan recon:realpay-exceptions            # writes the run + exceptions
```
Then open `/finance/exceptions` — you should see the run, KPIs, charts and the
exceptions table. Click **Review →** on a row to comment and change status.

## Safety
Read-only against `policies`, `realpay_client_contracts`,
`realpay_contract_installments`, `claims`, `cancel_policies`. It **only** writes
the `recon_exception_*` tables. It never changes a mandate, premium or policy —
those remain a separate, sign-off-gated action.

RBAC is frontend-gated by the seeded permissions (same convention as Settlement
Reconciliation — no route-level `can:` gate). Harden at the route layer later if
the whole `finance/*` surface moves to backend gates.
