"""
gaps.py — turn problems into QUESTIONS, never a failure.

CFO directive 2026-06-11: "don't say upload failed. Do maximum from the file;
where you don't understand, ask the question in a text box where the user can
fill in quickly, or give options to make the underwriter's life easy."

Two kinds of gap become a question for the review screen:
  1. Reconciliation gap — extracted/summed premium != the broker's printed total.
     -> ask the underwriter to confirm the final premium to bill (pre-filled with
        the broker's printed total when we have it).
  2. Missing / low-confidence field — a value we could not read (e.g. VAT reg no
     cut off in a PDF). -> a text box, or a select with our best guess + options.
"""
from __future__ import annotations

VAT_RATE = 0.14
REQUIRED_FIELDS = [
    ("customer.name", "Insured name", "text"),
    ("customer.vat_reg_no", "VAT registration number", "text"),
    ("policy.term_start_date", "Cover start date", "date"),
    ("policy.expiry_date", "Cover end date", "date"),
]


def _get(obj, dotted):
    cur = obj
    for k in dotted.split("."):
        if not isinstance(cur, dict):
            return None
        cur = cur.get(k)
    return cur


def questions_for_extraction(risk: dict) -> list[dict]:
    """Build the review-screen questions for one extracted risk object."""
    qs = []
    for path, label, kind in REQUIRED_FIELDS:
        if not _get(risk, path):
            qs.append({"field": path, "label": f"Couldn't read {label} — please enter",
                       "kind": kind, "options": None, "best_guess": None})
    # any line item with a sum insured but no premium (or vice-versa) -> confirm
    for ci, c in enumerate(risk.get("coverages") or []):
        for di, d in enumerate(c.get("details") or []):
            si, pr = d.get("sum_insured"), d.get("premium")
            if (si and not pr) or (pr and si is None and d.get("rate")):
                qs.append({
                    "field": f"coverages[{ci}].details[{di}].premium",
                    "label": f"{c.get('section','section')} – '{d.get('description','item')}' "
                             f"has a value but no premium. Confirm premium",
                    "kind": "number", "options": None, "best_guess": None})
    return qs


def reconciliation_question(summed_premium, printed_total, label="this risk"):
    """
    If summed != printed (ex/incl VAT), return a 'final premium to bill' question
    pre-filled with the printed total and an explanation. None if it ties.
    """
    if not printed_total or printed_total <= 0:
        return None
    for basis, val in (("ex-VAT", summed_premium),
                       ("incl-VAT", summed_premium * (1 + VAT_RATE)),
                       ("ex-VAT (stripped)", summed_premium / (1 + VAT_RATE))):
        if abs(val - printed_total) / printed_total <= 0.01:
            return None  # ties — no question
    return {
        "field": "policy.final_premium",
        "label": (f"Premium gap on {label}: line items sum to "
                  f"{summed_premium:,.2f}, but the schedule's printed total is "
                  f"{printed_total:,.2f} (often a deposit %/admin-fee convention). "
                  f"Confirm the final premium to bill"),
        "kind": "number",
        "best_guess": round(printed_total, 2),     # pre-fill the broker's number
        "options": [round(printed_total, 2), round(summed_premium, 2)],
    }


if __name__ == "__main__":
    import json
    demo = {"customer": {"name": "Demo Ltd"}, "coverages": [
        {"section": "Fire", "details": [{"description": "Stock", "sum_insured": 7000000, "rate": 0.001}]}]}
    print(json.dumps(questions_for_extraction(demo), indent=2))
    print(json.dumps(reconciliation_question(1431608, 1013600.60, "Logistics Co"), indent=2))
