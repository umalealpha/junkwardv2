# Instant Insurance policy-number prefixes (MIS / MIB / BUN)

The CFO MIS review flagged "three different prefix formats being used
simultaneously for Instant Insurance." After tracing every generation site, the
three prefixes are an **intentional product split**, not a bug. This document
records what each one means and where it is generated so the distinction is not
mistaken for data corruption again.

All three share the same format: `<PREFIX><YYYY><6-digit zero-padded next id>`,
e.g. `MIS2026000123`. The numeric suffix is a best-effort `max(id)+1` estimator
(it may not equal the row's final autoincrement id under concurrency — matching
legacy V8 behaviour). `policyNumber` has a UNIQUE index
(`idx_policies_policyNumber_unique`), so two policies can never share a number.

| Prefix | Meaning | Primary generation sites |
| ------ | ------- | ------------------------ |
| `MIS`  | Legacy / single retail Instant products (the default for everything that isn't a bundle). Also used by Legal Insurance, Hospital Cashback, Mobile Electronic, etc. | `Frontend/FrontendController`, `MobileApp/MobileAppController`, `Admin/PolicyController`, `Api/V1/LegalInsuranceController`, `Api/V1/HospitalCashbackController`, `Api/V1/MobileElectronicController`, `AlphaFe/alphaFe`, `ChatBot/ChatBotController`, `USSD/ussd`, `FrontendPay/CustomerController` |
| `MIB`  | Bundled Instant products (multiple covers sold together). | `Api/BundleProductController`, `Api/V1/PublicBundleCreateController` |
| `BUN`  | Legacy bundle prefix, superseded by `MIB`. Retained only for historical rows. A previous `BUN-{date}-{rand}-{line}` format was abandoned because it collided with project conventions (see `MaterialiseBundleQuoteJob`). New bundles use `MIB`. |

Other related prefixes seen in the same code paths — `COMG` (commercial),
`DOMG` (domestic), `MIH` — are separate product families and out of scope here.

## Consequences for tooling

Any query, report, or job that means "all Instant Insurance policies" must match
**all three** prefixes, not just `MIS`. The canonical list lives in code as
`AlphaDirect\Policy::INSTANT_PREFIXES` (`['MIS', 'MIB', 'BUN']`) — reuse it
rather than hard-coding a single prefix.

No code change is being made to consolidate the prefixes: doing so would break
historical lookups and external references (statements, RealPay client numbers,
debit-order mandates) that embed the existing policy numbers.
