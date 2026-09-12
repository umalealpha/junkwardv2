# Third Party Car Insurance — V2 endpoint scope

**Status:** Shipped & smoke-tested. This is a *scope/contract record*, not a build plan.
The carry-over "controller skeleton + route + validation draft" is superseded — the
production controller already exists, is committed, validated, routed, and tested.
No scaffold required; no merge required.

| | |
|---|---|
| Product | `2` — Third Party Car Insurance (legacy retail, owned by graphiteBWV8) |
| Controller | `backend/app/Http/Controllers/Api/V1/ThirdPartyCarController.php` (committed `c631dd271`) |
| Routes | `backend/routes/api_v1.php` (`third-party-car/*`, committed) |
| Smoke test | `backend/tests/Feature/Public/ThirdPartyCarCreateSmokeTest.php` — 3 tests / 24 assertions, passing |
| Pattern | Mirrors ACD / HCB / Legal dedicated public endpoints |
| Policy number | `MIS{YYYY}{6-digit}` (legacy retail convention) |

## Endpoints

All under `/api/v1/public/policies`, Bearer-gated (OTP session, `purpose=payment_authorize`).

| Method | Path | Purpose |
|---|---|---|
| POST | `third-party-car/premium-options` | Plan catalogue for the FE (accepted plans only) |
| POST | `third-party-car/calculate-premium` | Server-side premium for a `planId` (+ VAT) |
| POST | `create-third-party-car` | Create a pending-payment policy |

## Accepted plans

`ALLOWED_PLAN_IDS = [4, 16, 29]` (product-2 cover tiers). Any other `planId` → `422`.
Server recomputes premium from `product_plans`; client-supplied amounts are never trusted.

## create-third-party-car — request contract

Auth: `Authorization: Bearer <session token>`; the session cellphone must match `phone`
(else `session_phone_mismatch` 403). One of `omang` / `passport` is required.

Customer: `firstName`, `lastName`, `middleName?`, `omang?`/`passport?`, `dob`, `gender`
(`Male|Female|M|F`), `maritalStatus?`, `phone` (8 digits), `email?`, `address?`,
`sourceOfIncome?` + `sourceOfIncomeDetails?` (KYC/AML), `paymentMethod`
(`DPO|RealPay|VCS|Orange|Flutterwave`), `planId` (in `{4,16,29}`).

Vehicle (required):

| Field | Rule |
|---|---|
| `vehicle.plate` | required; BW plate regex `^[Bb]\s?\d{3}\s?[A-Za-z]{3}$` |
| `vehicle.make`  | required, max 60 |
| `vehicle.model` | nullable, max 80 |
| `vehicle.year`  | required, 4 digits, between `currentYear-40` and `currentYear+1` |

## Response (201)

```
{
  ok: true,
  quote_number: "BQ-<policyNumber>",
  policy_number: "MIS…",
  amount_to_pay: <vat-inclusive total>,
  policy: { id, policyNumber, customer_id, product_id: 2, plan_id, status: 0 }
}
```

Persists inside one transaction: `customer` → `customer_profile` → `policies`
(`status=0` pending payment, `has_vehicle=1`) → `vehicle` (plate/make/model/year linked
to the policy).

## Routing / bundle interaction

Product 2 is in `PublicBundleCreateController::DEDICATED_ENDPOINT_PRODUCTS`, so
`/create-bundle` refuses it (`422 product_has_dedicated_endpoint`, pointing here). The
generic bundle materialiser cannot capture the `vehicle` row, hence the dedicated path.

## Tested

`ThirdPartyCarCreateSmokeTest`:
1. bundle gate refuses product 2 (`422` → dedicated endpoint),
2. create persists a `policies` row + linked `vehicle` row, MIS-prefixed, `status=0`, `has_vehicle=1`,
3. an unlisted plan is rejected (`422`).

## Open / follow-up

- Live smoke against a deployed environment (mirror the Legal / M&E
  `backend/scripts/*_live_smoke.ps1` scripts) — not yet authored for TP Car.
- DPO end-to-end payment (BQ- → token → IPN) — shares the bundle-payment path; covered
  by the `initiateDpo` `BQ-` routing fix (`6ac4a0a05`) + `DpoInitiateReferenceRoutingTest`.
