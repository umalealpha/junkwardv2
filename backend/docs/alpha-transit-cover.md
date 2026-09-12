# Alpha Transit Cover (Courier GIT) — Graphite V2 receiver

Phase-1 inbound integration for the standalone Alpha Transit Cover platform
(`transit.alphadirect.co.bw`). One-way: ATC → Graphite. Contract documents:
*Graphite V2 Integration Brief* and *Reply & Sample Payloads* (30 June 2026,
handover package).

## Endpoint

```
POST /api/v1/webhooks/alpha-transit/event
Authorization: Bearer <ALPHA_TRANSIT_WEBHOOK_TOKEN>   ← ATC's GRAPHITE_API_KEY
X-Event-Type / X-Product-Code: ATC / X-Idempotency-Key
```

- Middleware `atc.webhook` (`VerifyAlphaTransitToken`) — constant-time bearer
  compare, fail-closed 500 when unconfigured, 401 otherwise.
- Runtime toggle: Admin > Integrations → **alpha_transit** (default OFF).
  While off the endpoint answers **503** and ATC's retry queue holds every
  event with `status='skipped'` — replayable, nothing lost.
- Responses: `200 {graphite_id}` · `200 {graphite_id, duplicate:true}` on
  replay · `400 invalid_product | value_out_of_band | excluded_category |
  policy_not_found` · `422 malformed_payload` · `500` (ATC retries with
  backoff 30s→24h, 6 attempts, then dead-letters).

## What each event writes

| Event | Legacy (mysql) | V2 ops (mysql_system) |
|---|---|---|
| `policy.created` | `customer` (+`customer_profile` shell, dedup §06), `policies` (status=1, `premium_freq='once'`), `policy_terms`, `policy_actions` (NEWBUSINESS/ISSUED) | `atc_shipments` (route, goods, parties, financials) |
| `policy.payment_status_changed` | — | `atc_shipments.payment_status` |
| `payment.received` | one `payment_transactions` row **per settled policy** (amount = that policy's premium, ref = bank_reference) | `atc_payments` (the remittance), shipments → `settled` |
| `recon.monthly_settled` | — | duplicate-safe ack of the same `atc_payments` row (parks `status='partial'` if it arrives first) |
| `claim.created` | `claims` (status `New`, ATC claim_number kept verbatim) | `atc_claims` (incl. claim_amount) |
| `claim.updated` | `claims.status` (mapped), note appended | `atc_claims` status verbatim + `settled_amount` |

Every envelope is persisted to `atc_webhook_events` (unique
`idempotency_key`) before processing; handlers are additionally idempotent on
natural keys (`policyNumber`, `atc_payment_id`, `atc_claim_id`).

## Deliberate divergences from the Integration Brief

The brief names idealised tables that don't exist in this schema:

- **`mis_premium_data` is NOT written.** The table has no schema and no writer
  anywhere in the repo — premium / sum-insured live on `atc_shipments` until
  Finance defines the shape (flagged to the product owner).
- **`policies` has no `payment_status` column** — settlement state lives on
  `atc_shipments.payment_status`. Cover is active from issuance regardless:
  the courier collects premium and remits monthly (net-7).
- **`claims` has no amount columns** — `claim_amount` / `settled_amount` live
  on `atc_claims`.
- **Policy numbers are never minted here.** ATC allocates `ATC-NNNNNNN` from
  base 9,000,000 (~42x above the legacy `policies.id` max); the unique index
  on `policies.policyNumber` is the collision backstop.

## Couriers

`atc_couriers` maps ATC `company_code` → `agencies` row (policies scope under
`policies.agency_id`). Seeded: EGC → EG Couriers (pilot), KTU → KTU Express,
ARX → Aramex Botswana. Unknown codes auto-register (named by code) so a live
policy is never blocked; rename from the registry afterwards.

## Config

```
ALPHA_TRANSIT_ENABLED=false        # fallback only — DB toggle is authoritative
ALPHA_TRANSIT_WEBHOOK_TOKEN=       # SSM SecureString; equals ATC's GRAPHITE_API_KEY
```

## Go-live checklist (receiver side, Brief §11 reply form)

1. Send Bharath the sandbox + production URLs (`/api/v1/webhooks/alpha-transit/event`).
2. Issue two bearer tokens (sandbox/prod) via the secrets vault — never email.
3. Confirm sequence reservation 9,000,000–9,999,999 (informational — we store, never mint).
4. Confirm dedup rules §06 (implemented: phone+email exact, else phone+name ≥90%).
5. Add ATC's NAT-gateway EIP to the IP allowlist on cut-over.
6. RI treaty share for transit — Phase 2, blocked on the `mis_premium_data` schema question.

Tests: `tests/Feature/Api/AlphaTransitWebhookTest.php` (auth fail-closed,
toggle, product gate, envelope validation). Happy-path ingestion is exercised
in the sandbox copy-test (Brief §10, 5 policies / settle / claim / 500-retry).
