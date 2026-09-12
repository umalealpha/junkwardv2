"""
schema.py — the Graphite-ready extraction target + prompt + validator.

The engine emits ONE object per insured risk (per sheet for multi-location/
multi-subsidiary workbooks). The shape mirrors Graphite v2's create-policy
payloads so the frontend review screen can replay the existing endpoint
sequence (store -> risk-address -> coverage -> calculate-premium -> submit ->
approve -> issue) with NO new write paths.

Field names chosen to line up with:
  backend/app/Http/Controllers/Api/V1/PolicyCreateController.php::store()
  frontend/src/pages/Policies/CreateWizard/types.ts  (CoverageForm, RiskAddressForm)
  backend/app/Support/KycDomComProducts.php           (commercial product ids)
"""
from __future__ import annotations

# Commercial product ids per KycDomComProducts::COMMERCIAL_IDS
COMMERCIAL_PRODUCT_IDS = [7, 16, 17, 20, 22]
DOMESTIC_PRODUCT_IDS = [8, 18, 19]

# Coverage-section synonyms -> a normalized coverage hint. The LLM returns the
# raw section title; we also pass these hints so it buckets consistently.
COVERAGE_HINTS = [
    "fire", "buildings_combined", "office_contents", "business_interruption",
    "public_liability", "products_liability", "goods_in_transit",
    "motor", "fidelity_guarantee", "money", "glass", "theft",
    "machinery_breakdown", "electronic_equipment", "all_risks",
    "plant_all_risks", "contractors_all_risks", "marine", "group_personal_accident",
    "employers_liability", "professional_indemnity", "loss_of_rent",
]

# JSON the LLM must return (one per risk segment).
TARGET_SCHEMA = {
    "policy": {
        "product_group": "commercial|domestic",
        "existing_policy_number": "string|null   # if this is a renewal of a live policy",
        "is_renewal": "bool",
        "insurer": "string|null",
        "broker": "string|null",
        "account_executive": "string|null",
        "premium_freq": "annual|monthly|quarterly|semi_annual|null",
        "term_start_date": "YYYY-MM-DD|null",
        "expiry_date": "YYYY-MM-DD|null",
        "currency": "string|null",
    },
    "customer": {
        "entity_type": "Organisation|Individual",
        "name": "string",
        "company_reg_no": "string|null",
        "vat_reg_no": "string|null",
        "physical_address": "string|null",
        "postal_address": "string|null",
        "occupation": "string|null   # business description",
        "email": "string|null",
        "phone": "string|null",
    },
    "risk_location": {
        "name": "string|null   # branch/camp/site name for this segment",
        "physical_address": "string|null",
    },
    "coverages": [
        {
            "section": "string   # raw section title from the schedule",
            "coverage_hint": "one of COVERAGE_HINTS, or 'other'",
            "section_premium": "number|null",
            "details": [
                {
                    "description": "string",
                    "sum_insured": "number|null",
                    "rate": "number|null   # decimal e.g. 0.00101 or % as given",
                    "premium": "number|null",
                    # Flagged, not withheld: the line is still placed, and the
                    # underwriter sees "please check" against it.
                    "needs_check": "bool   # true when unsure this is a Coverage line",
                    "check_reason": "string|null   # one short line: what you were unsure about",
                }
            ],
            # A Graphite coverage carries three child buckets besides its
            # detail rows, and a schedule states all three inline. Splitting
            # them here is what lets the wizard write them to
            # policy_coverage_extension / specified_coverage_items /
            # policy_coverage_excess instead of stranding them in prose.
            "extensions": [
                {
                    "name": "string   # extension / clause / warranty title",
                    "sum_insured": "number|null",
                    "premium": "number|null",
                    "text": "string|null   # wording, when the extension is words not a limit",
                    "needs_check": "bool   # true when unsure this is an Extension",
                    "check_reason": "string|null",
                }
            ],
            "specified_items": [
                {
                    "name": "string   # the item as named on the schedule",
                    "sum_insured": "number|null",
                    "rate": "number|null",
                    "premium": "number|null",
                    "needs_check": "bool   # true when unsure this is a Miscellaneous Item",
                    "check_reason": "string|null",
                }
            ],
            "excesses": [
                {
                    "text": "string   # the clause verbatim",
                    "min_percent": "number|null   # 10 for '10% of the claim'",
                    "min_amount": "number|null    # 10000 for 'min P10,000.00'",
                    "needs_check": "bool   # true when unsure this is an Excess",
                    "check_reason": "string|null",
                }
            ],
            # The fifth bucket, and the only honest answer for a line that
            # cannot be placed: NOT a guess. Nothing here is written to a
            # policy until an underwriter picks its bucket on the review
            # screen, so an unplaced line costs one dropdown — where a line
            # guessed into Excess or Coverage quietly changes the premium.
            "unclassified": [
                {
                    "text": "string   # the line exactly as printed",
                    "sum_insured": "number|null",
                    "rate": "number|null",
                    "premium": "number|null",
                    "reason": "string|null   # why you could not place it",
                }
            ],
            "notes": "string|null   # anything about THIS section the underwriter should read",
        }
    ],
    "motor": [
        {
            "registration": "string|null",
            "make_model": "string|null",
            "year": "number|null",
            "sum_insured": "number|null   # estimated/insured value",
            "rate": "number|null",
            "premium": "number|null",
        }
    ],
    # Lines that belong to no section at all. Same rule as the per-section
    # bucket: unplaced, never guessed.
    "unclassified": [
        {
            "text": "string   # the line exactly as printed",
            "section": "string|null   # the heading it sat under, if any",
            "sum_insured": "number|null",
            "rate": "number|null",
            "premium": "number|null",
            "reason": "string|null",
        }
    ],
    "notes": "string|null   # anything ambiguous the underwriter should check",
}

SYSTEM_PROMPT = (
    "You are a precise commercial-insurance underwriting data extractor for "
    "Alpha Direct Insurance (Botswana). You read a broker policy schedule and "
    "return STRICT JSON matching the given schema. Rules:\n"
    "- Output JSON ONLY. No prose, no markdown fences.\n"
    "- A schedule is organised in SECTIONS (Fire & Allied Perils, Buildings "
    "Combined, Public Liability, Goods in Transit, Motor, etc.). Each section "
    "becomes one entry in coverages[]; its line items become details[].\n"
    "- Per-vehicle rows (registration + make/model + value) go in motor[], "
    "NOT in coverages[].\n"
    "- Map every section title to the closest coverage_hint; use 'other' if none fit.\n"
    "- Within a section, split the lines by KIND into our four buckets: a cover "
    "limit / sum insured line goes in details[] (Coverage); a named extension, "
    "clause, warranty or memorandum in extensions[] (Extension); a specified or "
    "miscellaneous item in specified_items[] (Miscellaneous Item); an excess, "
    "deductible or first amount payable in excesses[] (Excess), parsing "
    "'10% ... min P10,000' into min_percent 10 and min_amount 10000 while "
    "keeping the clause verbatim in text. Never put an excess in details[].\n"
    # Keep in step with PhpScheduleExtractor::systemPrompt — the two readers
    # must classify identically, or a schedule read by the sidecar and one read
    # in-process would hand the underwriter different exceptions.
    "- NEVER GUESS a bucket. If you cannot tell which of the four a line "
    "belongs to, put it in that section's unclassified[] — the line text "
    "verbatim, whatever figures it carries, and a short reason. A wrong Excess "
    "or Coverage changes the premium, so an unplaced line is always better than "
    "a guessed one.\n"
    "- If you DO place a line but are not confident, place it and set "
    "needs_check=true with a short check_reason. Every flagged line is read by "
    "an underwriter, who can move it.\n"
    "- A line that belongs to no section at all goes in the TOP-LEVEL "
    "unclassified[], with whatever heading it sat under in `section`.\n"
    "- Never drop a line. Every printed line ends up in exactly one of "
    "details[], extensions[], specified_items[], excesses[] or unclassified[].\n"
    "- The client's layout is never wrong — it is just their layout. Read "
    "whatever wording, column order or spelling they used and map it to our "
    "format; never expect a house format.\n"
    "- Numbers: strip currency symbols/spaces ('P 3,120,000' -> 3120000; "
    "'0.4%' -> 0.004; '0.00101' stays 0.00101). Keep null when truly absent.\n"
    "- Dates -> YYYY-MM-DD. Frequency words -> annual/monthly/quarterly/semi_annual.\n"
    "- entity_type is 'Organisation' for company schedules (Pty Ltd, Group, etc.).\n"
    "- If a POLICY NUMBER appears, set existing_policy_number and is_renewal=true.\n"
    "- Never invent values. Put uncertainties in notes."
)


def build_user_prompt(coverage_hints, schema, segment_name, segment_text):
    import json
    return (
        f"COVERAGE_HINTS = {coverage_hints}\n\n"
        f"SCHEMA (return exactly this shape):\n{json.dumps(schema, indent=2)}\n\n"
        f"SCHEDULE SEGMENT NAME: {segment_name}\n"
        f"SCHEDULE CONTENT:\n-----\n{segment_text}\n-----\n\n"
        "Return the JSON object now."
    )


# --- lightweight structural validation (no external jsonschema dep) ----------
def validate(obj: dict) -> list[str]:
    """Return list of structural problems; empty list = OK."""
    errs = []
    if not isinstance(obj, dict):
        return ["root is not an object"]
    for key in ("policy", "customer", "coverages"):
        if key not in obj:
            errs.append(f"missing top-level key: {key}")
    cust = obj.get("customer") or {}
    if not cust.get("name"):
        errs.append("customer.name is empty")
    if cust.get("entity_type") not in ("Organisation", "Individual", None):
        errs.append(f"bad entity_type: {cust.get('entity_type')}")
    covs = obj.get("coverages")
    if covs is not None and not isinstance(covs, list):
        errs.append("coverages is not a list")
    if isinstance(covs, list):
        for i, c in enumerate(covs):
            if not isinstance(c, dict):
                errs.append(f"coverages[{i}] not an object"); continue
            if "section" not in c:
                errs.append(f"coverages[{i}] missing section")
            d = c.get("details")
            if d is not None and not isinstance(d, list):
                errs.append(f"coverages[{i}].details not a list")
    mot = obj.get("motor")
    if mot is not None and not isinstance(mot, list):
        errs.append("motor is not a list")
    return errs
