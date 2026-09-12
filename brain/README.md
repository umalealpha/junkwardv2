# Alpha Brain — the `brain/` bolt-on (branch `alpha-brain`)

A decision engine for Alpha Direct — **claims, finance, KYC, and fraud guards** —
that runs **alongside** Graphite V2, not inside it. It reads records, decides what
needs attention, and produces **one ranked to-do list per team**. It never sends a
message, moves money, or writes to Graphite on its own.

## Why this can't crash Graphite V2

This branch (`alpha-brain`) differs from `main` by **new files only, all under `brain/`**.
No existing Graphite file is edited. Concretely:

| Guarantee | How |
|---|---|
| **Existing code untouched** | The whole branch diff vs `main` is the `brain/` folder. Nothing else changes. |
| **Own process** | Runs as its **own** container via `brain/docker-compose.brain.yml` — a separate compose project. It does not join Graphite's network or touch Graphite's compose file. |
| **Own storage** | State lives in a small JSON file in its own volume. It has **no** write path to Graphite's database. |
| **Read-only to Graphite** | When live data is wired, it connects with a **`GRANT SELECT` only** MySQL user. It cannot alter, insert, or drop anything. |
| **Off by default** | `BRAIN_ENABLED=false` and `BRAIN_LIVE_ARMS=false` out of the box. With arms off it only decides + queues — it sends and executes nothing. |
| **Fails safe** | If the brain container is stopped or crashes, Graphite is completely unaffected. |

## Prove it before deploying

```bash
cd brain

# 1) all 21 decision suites pass (pure logic, no external services)
npm test                     # -> brain suites: PASS=21 FAIL=0

# 2) the morning sweep, against safe synthetic data (sends nothing)
node daily.js                # prints the ranked exception counts per team

# 3) the inbox (a small web page + JSON API)
node server.js               # then open http://127.0.0.1:8090/
```

Endpoints (back-compat): `/health`, `/modules` (proves every module loads in the
image), `/inbox` (the queue as JSON), `POST /approve` (legacy shared-token
approve — records a human decision, does not execute anything).

## Dashboard — roles, permissions, audit, CRUD (`GET /`)

`/` now serves a self-contained branded SPA (vanilla JS, inline CSS/JS, no CDN,
no build step — CSP-safe). It has an Overview/Activity dashboard, an
Exceptions/Affected-Policies table (rows flagged `signalConfidence:'uncertain'`
are visually distinct — they need human verification first), an Approvals view,
and an Admin area (user CRUD + settings).

### Roles → permissions (server-enforced in `lib/rbac.js`)

| Role | Overview/Activity | Queue | Affected | Approve | Export | Admin (users+settings) |
|---|---|---|---|---|---|---|
| **admin** | ✓ | full | ✓ | ✓ | ✓ | ✓ |
| **finance** | ✓ | full | ✓ | ✓ | ✓ | — |
| **underwriting** | ✓ | full | ✓ | ✓ | — | — |
| **claims** | ✓ | Claims-team only | — | — | — | — |
| **viewer** | ✓ | full | ✓ | — | — | — |

Enforcement is **server-side** on every `/api` route (`can(role, action)` → 403
if the role lacks the permission; 401 with no/invalid token). The UI hides
controls too, but that is cosmetic only — the server never trusts the client.

### API (all under `/api`, all role-gated)

`GET /api/me` · `GET /api/queue` · `GET /api/affected?stage=&format=csv` ·
`GET /api/activity?limit=&offset=` · `POST /api/approve {id,approver}` ·
admin: `GET/POST/PUT/DELETE /api/admin/users`, `GET/PUT /api/admin/settings`.

### Auth for the soft launch (⚠️ move to Azure SSO in production)

Each named user has a **role + personal bearer token**, stored in the brain's own
store (`data/users.json`, token **hashed** — never stored or logged in the clear;
the plaintext is shown to the admin exactly once at create/rotate). On first boot
with `BRAIN_APPROVE_TOKEN` set, a single `bootstrap-admin` user is seeded from
that token so there is always a way in; the admin then CRUDs the real users.

> **Production must move to Azure SSO** — the same shared tenant / client app
> registration used by OneDesk / Reporting / Claims (MSAL, ID token audience =
> client_id, scopes `openid profile email`). The per-user token model here is a
> soft-launch stopgap only; it is **not** built for external exposure. This
> service is localhost-only (mapped to 127.0.0.1 by the compose file).

### Audit log (append-only)

Every state-changing action (approve, user create/update/delete, role change,
settings change) appends an event `{ at, actor, actorRole, action, target, meta }`
to `data/audit.jsonl` alongside the daily-sweep rows. The log is **append-only** —
there is no edit/delete path anywhere in `store.js`, even for admin — and it is
surfaced read-only in the Activity view.

### Affected-policy data

The Affected-Policies table renders the shape emitted by the sibling collections
module: `{ policyNumber, customerName, productId, product, agent, channel,
billingType, amountOverdue, monthsUnpaid, daysOverdue, stage, deactivatedAt,
graceEndsAt, signalConfidence, reason }`. Until the live feed is wired it reads
`fixtures/affected.sample.json` (synthetic, PII-free) — the API response and the
UI both clearly mark this as **SAMPLE / not live**.

## Deploy (separate from Graphite — nothing existing changes)

```bash
docker compose -f brain/docker-compose.brain.yml up -d --build
curl -s http://127.0.0.1:8090/health
```

To remove it, `docker compose -f brain/docker-compose.brain.yml down`. Graphite is
never involved.

## Real prod hosts (confirmed 2026-07-10)

- Frontend: `graphite-v2-prod-fe.alphadirect.co.bw`
- Backend/API: `graphite-v2-prod-be.alphadirect.co.bw` (the `/api/claims-tracker/*` link lives here)
- Read-only DB: `graphitebw-rds-ro.alphadirect.co.bw`
- `graphite.alphadirect.co.bw` is the **legacy** host — do not use it.

## Turning it on later (Pramod, from Mon 13 Jul)

1. Create a **read-only** MySQL user on the Graphite DB (`GRANT SELECT`) at
   `graphitebw-rds-ro.alphadirect.co.bw`, put its DSN in `GRAPHITE_RO_DSN`.
2. Set `BRAIN_ENABLED=true` to read live records.
3. Wire the live arms (email / paygate / Graphite actions) and only then set
   `BRAIN_LIVE_ARMS=true`. Money-moving and irreversible actions always wait for a
   human approval in the inbox — by design.

## What's inside

- `lib/` — the 21 decision modules (claims, finance, KYC, fraud/guards) + helpers.
- `brains.js` — runs the pure modules over a batch and builds the ranked queue.
- `daily.js` — the morning cron entry point.
- `server.js` — the zero-dependency inbox.
- `fixtures/sample.json` — synthetic, PII-free test data.
- `db.js` + `better-sqlite3` (optional) — used only by the live notification arm;
  not needed by the runner or the tests. `motolink` test needs it + Monday's key.
