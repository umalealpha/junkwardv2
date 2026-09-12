# MotoLink Policy Cover API (read-only)

Gives the **MotoLink** assessment platform (motolink.app) the two underwriting
fields it needs for a vehicle assessment — **Sum Insured** and the **Excess
(First Amount Payable) schedule** — pulled directly from Graphite, using the
**same partner `api-key`** Claims Tracker already holds. No new secret is issued.

> The endpoint returns **only** Sum Insured + excesses. It deliberately exposes
> **no** customer name, omang/ID, passport, contact, or bank details. The output
> is an explicit whitelist.

## Endpoint

```
GET  /api/claims-tracker/policy-cover
POST /api/claims-tracker/policy-cover
Header: api-key: <API_KEY_CLAIMS_TRACKER>
```

Identify the cover by **one** of:

| Query param      | Example            | Meaning                                   |
|------------------|--------------------|-------------------------------------------|
| `claim_number`   | `G2026000123`      | Graphite claim number on a MotoLink job   |
| `policy_number`  | `COMG2026128234`   | Graphite policy number                     |
| `policy_id`      | `12345`            | Graphite internal policy id               |

Middleware: `VerifyClaimsTrackerApiKey` + `throttle:120,1`.

## Response (200)

```json
{
  "status": true,
  "policy_number": "COMG2026128234",
  "claim_number": "G2026000123",
  "resolved_via": "claim_number",
  "sum_insured": 250000.0,
  "currency": "BWP",
  "excesses": [
    { "type": "Own Damage",         "min_percent": 5,    "min_amount": 2500 },
    { "type": "Windscreen / Glass", "min_percent": null, "min_amount": 500  },
    { "type": "Loss of Keys",       "min_percent": null, "min_amount": 350  }
  ],
  "excess_source": "policy",
  "missing_fields": []
}
```

## Data sources (verified against the Graphite V2 schema)

- **Sum Insured** = `policies.sum_assured`. If blank/zero, falls back to the
  latest `motor.estimated_value` for the policy (via
  `motor.policy_coverage_id → policy_coverages.policy_id`).
- **Excess** = per-vehicle motor columns on the latest `motor` row:
  `own_damage_minimun_percent/amount`, `windscreen_minimun_percent/amount`,
  `loss_of_keys_minimun_percent/amount`.
  (Motor excess is **not** stored in `policy_excesses_data` — that table is
  commercial-only.)
- If no per-vehicle excess is captured, the standard **"First Amount Payable"**
  schedule from the policy document is returned with
  `excess_source: "standard_schedule"` (lines unpriced, `min_*` = null).

## Behaviour rules

- **404** only when the *claim/policy itself* doesn't exist.
- A *missing field* never 404s: if Sum Insured can't be found the call still
  returns **200** with `sum_insured: null` and `missing_fields: ["sum_insured"]`,
  so MotoLink/underwriting routes the assessment to a human instead of guessing.
- A `0` Sum Insured is normalized to `null` (never presented as real cover).

## MotoLink integration

MotoLink calls this endpoint with header `api-key: <key>` and reads
`sum_insured` + `excesses`. The Claims-Tracker repo also ships a helper —
`lib/graphiteErp.getPolicyCover({ claimNumber | policyNumber | policyId })` —
that wraps this call with the same key/timeout conventions as the existing
create bridge.

## Tests

- PHP logic (resolution, fallbacks, missing-field routing, PII whitelist):
  `verify/policy_cover_logic_test.php` — 13/13 pass.
- JS adapter (header, URL, mapping, 404, key guard):
  `verify/get_policy_cover_test.js` — 12/12 pass.
