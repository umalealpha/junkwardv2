# Claims Tracker → Graphite V2 API

Bridge endpoint that lets Alpha Direct's Claims Tracker
(`claims.alphadirect.co.bw`, separate Node.js/SQLite app at
`/opt/claims-tracker`, repo `D:\ADRisk\claims`) create claims in Graphite
V2 and receive back a Graphite-generated `claim_number`.

This is a **drop-in port of the V1 endpoint** (`graphiteBWV8`
`feat/claims-tracker-api`). The path, request body, response shape and
`api-key` header are byte-for-byte identical, so the Claims Tracker app
only needs to re-point `GRAPHITE_API_BASE` at the V2 backend — no
Claims-Tracker code change. The live Claims Tracker adapter that calls
this is `lib/graphiteErp.js`.

## Why a dedicated endpoint (and not the in-app claim store)

Graphite V2 has `POST /api/v1/claims-v2` (`ClaimsV2Controller::store`) for
in-app, session-authenticated claim creation. It is unsuitable for Claims
Tracker because:

- It requires `auth:sanctum` and reads `auth()->id()` for `created_by` and
  the reserve payee — it crashes / mis-attributes with no authed user.
- It mints `claim_number` with a race-prone `max(id)+1` formula.
- It returns the in-app JSON envelope, not the `{claim_id, claim_number,
  status, idempotent}` contract Claims Tracker already expects.
- It has no `external_ref` idempotency.

## Endpoint

```
POST /api/claims-tracker/create
Headers:
  Content-Type: application/json
  api-key: <API_KEY_CLAIMS_TRACKER>
```

Gated by `VerifyClaimsTrackerApiKey`, which uses a dedicated env var
distinct from the shared `API_KEY`. If `API_KEY_CLAIMS_TRACKER` is not set
on the server the endpoint returns **503 — fail-closed, no fallback**.

Throttled to 60 requests/min/IP.

> **V2 routing note:** this route lives in `routes/api.php` (mounted at
> `/api`, no version prefix), NOT `routes/api_v1.php` (mounted at
> `/api/v1`). That keeps the public path identical to V1
> (`/api/claims-tracker/create`).

## Request body

```json
{
  "policy_number": "COMG2026128234",
  "claim_type": "accident",
  "date_of_loss": "2026-05-22",
  "description": "Driver-side collision at Riverwalk roundabout, no injuries.",
  "external_ref": "CT-2026-0042",
  "claim_handler_email": "handler@alphadirect.co.bw",
  "accident": {
    "date_of_accident": "2026-05-22",
    "place_of_accident": "Riverwalk roundabout",
    "time_of_accident": "14:30",
    "cause": "Other driver failed to yield"
  }
}
```

### Required fields

Provide exactly one of `policy_id` OR `policy_number` — both identify the
same row.

| Field           | Type    | Notes                                                        |
|-----------------|---------|--------------------------------------------------------------|
| `policy_id`     | integer | Graphite `policies.id` PK. Use if you have the integer.      |
| `policy_number` | string  | Visible identifier, e.g. `COMG2026128234`. **This is what Claims Tracker sends** (`claim.policyNumber`). |
| `claim_type`    | string  | See "Accepted claim types" below. Case-insensitive.          |
| `date_of_loss`  | date    | `YYYY-MM-DD`. Cannot be in the future. Claims Tracker sends `claim.claimReportedDate`. |
| `description`   | string  | ≤ 2000 chars. Stored on `claims.note`.                       |

### Optional fields

| Field                 | Type   | Notes                                                                 |
|-----------------------|--------|-----------------------------------------------------------------------|
| `external_ref`        | string | Claims Tracker's own claim id (`String(claim.id)`). ≤ 80 chars. Idempotency key — always send it. |
| `claim_handler_email` | string | Resolved to an active `users.id` and written into `claims.created_by` so the admin list shows the handler. No match → sentinel `claims-tracker:{external_ref}`. |
| `accident`            | object | Per-column writes to `claim_accidents` (Accident / Motor Traders types). |
| `life`                | object | Per-column writes to `claim_life`.                                    |
| `cellphone`           | object | Per-column writes to `claim_cellphones`.                              |
| `key_loss`            | object | Per-column writes to `claim_key_loss`.                                |
| `glass`               | object | Per-column writes to `glass_claims`.                                  |

Sub-table writes are pass-through — keys in the object become column
names on the row. Unknown columns fail the whole request with HTTP 500.

### Accepted `claim_type` values

Accepts the friendly slug (lowercase) or the canonical Graphite label.
**Claims Tracker's admin-configured `graphiteClaimTypeMap` must emit one
of these slugs.** Motor convenience aliases `motor` and `motoraccident`
both map to `Accident`.

`accident` · `motor` · `motoraccident` · `motortradersexternal` ·
`motortradersinternal` · `glass` · `life` · `cellphone` · `key_loss` /
`locksandkeys` · `legal` · `hospital_cash` · `businessallrisks` ·
`personalallrisks` · `businessinterruption` · `theft` ·
`workerscompensation` · `statedbenefits` · `defectiveworkmanship` ·
`fidelityguarantee` · `travelinsurance` · `goodsintransit` · `fire` ·
`buildingscombined` · `accidentaldamage` · `liability` ·
`mobileelectronicdevices` · `officecontents` · `contractorsallrisks` ·
`erectionallrisk` · `plantallrisks` · `professionalindemnity` ·
`medicalmalpractice`

Sub-table writes are supported for `accident`/motor, `glass`, `life`,
`cellphone`, `key_loss`. For every other type, v1 creates the parent
`claims` row only — the description lives in `claims.note` and an admin
fills in the sub-table via the UI when they pick up the claim.

## Response

### 201 Created (new claim)

```json
{
  "status": true,
  "claim_id": 47286,
  "claim_number": "G2026047286",
  "claim_type": "Accident",
  "policy_id": 12345
}
```

`claim_number` format: `G` + 4-digit year + 6-digit zero-padded
`claims.id`, minted via a race-safe two-phase save.

### 200 OK (idempotent — claim already exists for this `external_ref`)

```json
{
  "status": true,
  "claim_id": 47286,
  "claim_number": "G2026047286",
  "idempotent": true,
  "message": "Claim already exists for this external_ref"
}
```

### 401 / 404 / 422 / 503

- **401** — missing or wrong `api-key`.
- **404** — policy not found.
- **422** — validation error (Laravel field-keyed messages).
- **503** — `API_KEY_CLAIMS_TRACKER` not set on this environment.

## Idempotency

If Claims Tracker passes `external_ref` (it always does —
`String(claim.id)`), at most one Graphite claim is created per ref:

1. **Pre-insert check** — existing row with that `external_ref` → 200 +
   existing `claim_number`.
2. **DB unique index** (`claims_external_ref_unique`) — concurrent retries
   collide at insert; the MySQL 1062 error is caught and returns 200 with
   the existing claim.

## Side effects

| Side effect                               | This endpoint |
|-------------------------------------------|---------------|
| Parent `claims` row                       | yes           |
| `claim_number` generation (two-phase)     | yes           |
| Opening reserve (`transaction_type=86`)   | yes — every claim (matches `ClaimsV2Controller::store`) |
| Per-type sub-table write                  | yes (for 5 types, when supplied) |
| Motor "shell" rows (`new_claims`, `accident_driver`, `claim_accidents`) | yes — motor types on motor products (7/8), so the legacy Blade admin view doesn't crash |
| OwenIt audit log                          | yes (models are Auditable) |
| Customer "create_claim" email (Mailgun)   | **no** — Claims Tracker owns notifications |
| KYC upload                                | no            |

## Server setup

Required env var on every V2 environment that should accept Claims Tracker
traffic:

```
API_KEY_CLAIMS_TRACKER=<rotated-secret>
```

Generate with `openssl rand -hex 32`. The **same value** must be set in the
Claims Tracker `.env` (`API_KEY_CLAIMS_TRACKER`). On the Claims Tracker
side also set `GRAPHITE_API_BASE` to the V2 backend host, e.g.
`https://graphite-v2-be.alphadirect.co.bw`.

Migration:
`2026_06_05_000001_add_claims_tracker_columns_to_claims_table` adds
`claims.external_ref`, `claims.source`, a unique index on `external_ref`,
and makes `claims.created_by` nullable. Run via `php artisan migrate`.

## Audit identity

External callers have no authed user. `claims.created_by` is the resolved
handler `users.id` when `claim_handler_email` matches an active user;
otherwise the sentinel `claims-tracker:{external_ref}` (or
`claims-tracker:no-ref`). Query origin with `claims.source =
'claims-tracker'` or `created_by LIKE 'claims-tracker:%'`.

## Test plan

```bash
curl -i -X POST https://graphite-v2-be.alphadirect.co.bw/api/claims-tracker/create \
  -H "Content-Type: application/json" \
  -H "api-key: $API_KEY_CLAIMS_TRACKER" \
  -d '{
    "policy_number": "COMG2026128234",
    "claim_type": "accident",
    "date_of_loss": "2026-05-22",
    "description": "Test claim from curl",
    "external_ref": "CT-TEST-001"
  }'
```

Expected: 201 with a fresh `claim_number`. Re-running returns 200 with
`idempotent: true` and the same `claim_number`.

Negative cases:
- Omit / wrong `api-key` → 401
- `API_KEY_CLAIMS_TRACKER` unset on server → 503
- Invalid `policy_id` / unknown `policy_number` → 404
- `date_of_loss` in the future → 422
- Unknown `claim_type` → 422
