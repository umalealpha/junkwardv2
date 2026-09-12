"""
test_providers.py — routing + PII hard-lock tests for the Smart UW engine.

No network: the three call_* providers are monkeypatched. The load-bearing
assertion is that a PII segment NEVER reaches a commercial provider, even when
the caller explicitly asks for one (AD-POL-AI-GOV-001, non-waivable).

Run:  python test_providers.py
"""
import os, sys
os.environ.setdefault("SMARTUW_COMMERCIAL_PROVIDER", "gemini")
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import providers as p

CALLS = []


def _fake(name, payload):
    def f(*a, **k):
        CALLS.append(name)
        return payload
    return f


def _reset():
    CALLS.clear()
    p.call_ollama = _fake("ollama", '{"_":"local"}')
    p._COMMERCIAL_FNS = {"gemini": _fake("gemini", '{"_":"g"}'),
                         "deepseek": _fake("deepseek", '{"_":"d"}')}


_FAILED = 0


def check(cond, msg):
    global _FAILED
    print(("PASS" if cond else "FAIL"), "-", msg)
    if not cond:
        _FAILED += 1


# ── classify_pii: must catch ID / contact / financial identifiers ────────────
PII = [
    "Owner ID Number: 999999999",
    "OMANG_999999999",                 # underscore separator (the old miss)
    "ID-Number 999999999",             # dash separator
    "I.D. Number 999999999",           # dotted
    "ID.NO 999999999",                 # dotted + abbreviated (audit C2)
    "IDNO 999999999",                  # no separator at all (audit C2)
    "ID# 999999999",                   # hash suffix (audit C2)
    "Identity No 999999999",
    "Driver's Licence: B1234",
    "Next of Kin: Jane Doe",
    "NextofKin: Jane Doe",             # compound, no spaces (audit C2)
    "ProofofResidence attached",       # compound (audit C2)
    "Date of Birth 1980-01-01",
    "D.O.B 1980-01-01",
    "Contact: somebody@example.com",   # email address
    "Contact Person: John",            # contact label, no no/number suffix (audit H1)
    "Phone# 71234567",                 # hash-delimited contact (audit C2)
    "Tel: +267 71 123 456",            # abbreviation + BW phone (audit H/C2)
    "Fax: 3185000",                    # fax abbreviation (audit C2)
    "Mobile +267 71 234 567",          # BW phone pattern
    "Bank Account 0123456789",
    "Cell Number: 71000000",
    "Passport: A1234567",
]
for s in PII:
    check(p.classify_pii(s), f"classify_pii TRUE: {s!r}")

# Pure commercial schedule text must NOT be classified PII (else the cloud
# provider would never be used — the feature would be dead). Guards against
# the widened classifier over-matching.
COMMERCIAL = [
    "Sum Insured: P 500,000  Rate 0.45%  Premium P2,250",
    "Section: Fire | Buildings | Sum Insured 1,000,000",
    "Fleet: Toyota Hilux 2021  Value 350000  Reg B 123 ABC",
    "Company: Acme (Pty) Ltd  Risk Address: Plot 123 Gaborone",
    "Company Name: Acme (Pty) Ltd",          # 'name' alone (no contact word) -> commercial
    "Risk Address: Plot 267 Gaborone",       # '267' but not phone-shaped
    "Account Manager: regional  Premium 2250",  # 'account' w/o no/number -> commercial
]
for s in COMMERCIAL:
    check(not p.classify_pii(s), f"classify_pii FALSE: {s!r}")

# ── route_for: PII first, non-overridable; fail-closed ───────────────────────
check(p.route_for("Sum Insured 500000", provider="deepseek") == "deepseek", "commercial -> deepseek")
check(p.route_for("Sum Insured 500000", provider="gemini") == "gemini", "commercial -> gemini")
check(p.route_for("Sum Insured 500000", provider=None) == "gemini", "commercial, no provider -> env default gemini")
check(p.route_for("Sum Insured 500000", provider="bogus") == "local", "unknown provider -> local (fail-closed)")
check(p.route_for("Owner ID Number 999999999", provider="deepseek") == "local", "PII overrides deepseek -> local")
check(p.route_for("Sum Insured 500000", force_local=True, provider="deepseek") == "local", "force_local overrides -> local")

# ── complete(): the compliance assertions (no commercial call on PII) ────────
_reset()
_, prov = p.complete("sys", "usr", "Owner ID Number 999999999", provider="deepseek")
check(prov == "local", "PII segment -> provider used = local")
check("deepseek" not in CALLS and "gemini" not in CALLS, "PII segment -> NO commercial call made")
check(CALLS == ["ollama"], "PII segment -> only ollama called")

_reset()
_, prov = p.complete("sys", "usr", "Sum Insured 500000", provider="deepseek")
check(prov == "deepseek" and CALLS == ["deepseek"], "commercial -> deepseek called")

_reset()
_, prov = p.complete("sys", "usr", "Sum Insured 500000", provider="gemini")
check(prov == "gemini" and CALLS == ["gemini"], "commercial -> gemini called")

# fallback: commercial provider raises -> local, never the other cloud
_reset()
def _boom(*a, **k):
    raise RuntimeError("deepseek down")
p._COMMERCIAL_FNS["deepseek"] = _boom
_, prov = p.complete("sys", "usr", "Sum Insured 500000", provider="deepseek")
# The label carries the reason the mapping engine was abandoned — without it a
# DeepSeek 404 model_not_found reached the operator as an Ollama connection
# error, naming a local model they never chose.
check(prov.startswith("local(deepseek-fallback") and CALLS == ["ollama"],
      "deepseek failure -> local fallback (not gemini)")
check("deepseek down" in prov, "fallback label carries the provider's reason")

# the reason must never carry the credential
_reset()
def _leak(*a, **k):
    raise RuntimeError("401 Unauthorized: Bearer sk-live-abc123 rejected")
p._COMMERCIAL_FNS["deepseek"] = _leak
_, prov = p.complete("sys", "usr", "Sum Insured 500000", provider="deepseek")
check("sk-live-abc123" not in prov and "Bearer ***" in prov,
      "fallback label masks the api key")

# A key NOT preceded by the word Bearer must still be masked. An earlier mask
# captured the secret in group 1 and re-emitted it with '***' appended, so it
# printed the key in full and this check passed only because the Bearer pattern
# happened to consume the key first.
for leaked, label in [
    ("Invalid API key: sk-live-abc9999999 provided", "bare sk- key"),
    ("error for AIzaSyABCDEFGHIJKLMNOP", "bare AIza key"),
    ("x-goog-api-key: AIzaSyABCDEFGHIJKLMNOP", "x-goog-api-key header"),
    ("Authorization: gsk_ABCDEFGHIJKLMNOPQRST", "bare Authorization header"),
    ("GROQ_API_KEY=gsk_livekey999999", "GROQ_API_KEY env name"),
]:
    masked = p._mask(leaked)
    secret = [t for t in leaked.replace(":", " ").replace("=", " ").split()
              if t.startswith(("sk-", "gsk_", "AIza"))]
    check(all(s not in masked for s in secret), f"_mask hides the {label}")

# --- redact_pii: anonymise-then-send, not route-the-whole-sheet-local -------
# A contact block on a cover sheet used to send the entire segment to the local
# model, which is not deployed, so no real schedule could be read at all.
_seg = (
    "ACME (PTY) LTD - SCHEDULE 2026\n"
    "CONTACT PERSON: Thabo Molefe\n"
    "TELEPHONE NUMBER: +267 71 234 567\n"
    "SECTION | SUM INSURED | PREMIUM\n"
    "Fire | 1000000 | 12500\n"
    "MOBILE PLANT ALL RISKS | 450000 | 5400"
)
_clean, _dropped = p.redact_pii(_seg)
check(all(s in _clean for s in ["1000000", "12500", "450000", "5400"]),
      "redact_pii keeps the cover figures")
check("Thabo" not in _clean and "71 234 567" not in _clean,
      "redact_pii removes the contact block")
check(p.route_for(_clean, provider="deepseek") == "deepseek",
      "a schedule with a contact block still reaches DeepSeek")
check(_dropped and all("Thabo" not in d and "267" not in d for d in _dropped),
      "dropped entries are labels, never values")

# The classifier matches a LABEL, so an identifier column's data rows are bare
# digits that classify clean. The column must be carried down the table.
_reg = (
    "Name | Omang | Address\n"
    "Kabelo M | 1234567890123 | Plot 5\n"
    "Thabo K | 9876543210987 | Plot 9\n"
    "SECTION | SUM INSURED | PREMIUM\n"
    "Fire | 1000000 | 12500"
)
_rclean, _rdropped = p.redact_pii(_reg)
check("1234567890123" not in _rclean and "9876543210987" not in _rclean,
      "redact_pii withholds an identifier column's data rows")
check("1000000" in _rclean and "12500" in _rclean,
      "a new header row stops the column scrubbing")
check(any("withheld" in d for d in _rdropped),
      "the withheld column is reported to the underwriter")

# Nothing but identifiers: the caller must be told, not billed for an empty
# prompt that comes back as a risk with no error.
_kclean, _kdropped = p.redact_pii("Omang Number: 1234567890123\nContact Person: X")
check(not _kclean.strip() and _kdropped, "an all-PII segment leaves nothing to read")

# A blank or figure-less sheet is NOT a KYC document. redact_pii must report
# nothing withheld, so extract_segment's all-PII branch (which requires both an
# empty result AND something redacted) does not fire and mislabel the cause.
_bclean, _bdropped = p.redact_pii("   \n  \n")
check(not _bdropped, "a blank segment reports nothing withheld")
check(not _bclean.strip(), "a blank segment stays blank")

# Figure-with-suffix cells must read as a data row on both sides, or the column
# scrubbing stops a row early here and not in the PHP.
check(not p._looks_like_header_row("Fire | 12 500 P | 3%"),
      "a trailing-P figure is a data row, not a heading")
check(p._looks_like_header_row("SECTION | SUM INSURED | PREMIUM"),
      "a real heading row is still a heading")

# ── The delimiter must be the one the line USES, never pipe-or-comma ────────
# toArray($formatData = true) hands the reader money that already carries
# thousands separators. Splitting a pipe row on commas too re-joined a sum
# insured of "1,250,000" as "1 | 250 | 000" and a premium of "18,750" as
# "18 | 750", so the model priced a nonsense figure with nothing to flag it —
# and every cell index right of the first comma shifted, so the learned
# identifier column addressed the wrong column on the rows below.
_mclean, _mdropped = p.redact_pii(
    "REG NO | MAKE | ID NUMBER | VALUE | PREMIUM\n"
    "B123ABC | Hilux D/C | 1234567890123 | 1,250,000 | 18,750"
)
check("1,250,000" in _mclean and "18,750" in _mclean,
      "thousands-separated money survives the identifier-column blanking")
check("1 | 250 | 000" not in _mclean,
      "a pipe row is never re-split on its thousands separators")
check("1234567890123" not in _mclean,
      "the identifier column is still blanked on the data row")
check(p._row_cells("A | 1,250,000 | 18,750") == ["A", "1,250,000", "18,750"],
      "_row_cells splits a pipe line on pipes only")
check(p._row_cells("A,1250000,18750") == ["A", "1250000", "18750"],
      "_row_cells splits a comma line on commas")

# ── A date is a value, so a date-bearing row is not a header ────────────────
# "Period | 01/01/2026 | Kabelo Molefe" read as a HEADER, which cleared the
# learned identifier column and sent the contact person's name to the
# commercial provider on the first row under the header that marked it.
check(not p._looks_like_header_row("Period | 01/01/2026 | Kabelo Molefe"),
      "a date-bearing row is a data row, not a heading")
check(not p._looks_like_header_row("Inception | 2026-01-01 | X"),
      "an ISO date is a data row too")
_dclean, _ddropped = p.redact_pii(
    "SCHEDULE OF INSURANCE | ACME (PTY) LTD | CONTACT PERSON\n"
    "Period | 01/01/2026 | Kabelo Molefe\n"
    "SECTION | SUM INSURED | PREMIUM\n"
    "Fire | 1000000 | 12500"
)
check("Kabelo Molefe" not in _dclean,
      "the contact name under an identifier column never reaches the provider")
check("Fire | 1000000 | 12500" in _dclean,
      "a real header row still resets the column so the cover table survives")

print()
if _FAILED:
    print(f"{_FAILED} CHECK(S) FAILED")
    sys.exit(1)
print("ALL CHECKS PASSED")
