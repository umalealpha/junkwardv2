"""
providers.py — LLM router for the split-routing decision (CFO option B, 2026-06-11;
DeepSeek added as a selectable commercial provider 2026-06-17).

  * PII / ID documents (Omang, passport, proof-of-residence, KYC set, contact
        and financial identifiers)
        -> LOCAL model only. Customer PII never leaves Alpha infra
           (org policy AD-POL-AI-GOV-001, NON-WAIVABLE). The PII branch in
           route_for() is FIRST and cannot be overridden by the commercial
           selector — a commercial provider can only ever see already-non-PII
           data.
  * Non-PII commercial schedule data (sums insured, rates, asset registers)
        -> the chosen COMMERCIAL provider: deepseek (default) or gemini.
           The choice + key are passed PER-REQUEST by the Laravel caller, which
           reads them from the Credentials Vault, so the CFO can switch the
           "AI brain" from his side with no redeploy. Absent per-request values
           fall back to SMARTUW_COMMERCIAL_PROVIDER / the *_API_KEY env vars.

A cheap classifier (classify_pii) decides PII-vs-commercial before any external
send; on doubt it treats the document as PII -> local. Separators are normalised
before matching so "OMANG_123", "ID-Number", "I.D." are caught (the old
\\bomang\\b missed "OMANG_123" because '_' is a word char — PR review note).

Dev/test runs force LOCAL (Ollama) so nothing leaves the machine while building.
"""
from __future__ import annotations
import os, re, requests

OLLAMA_URL = os.environ.get("OLLAMA_URL", "http://localhost:11434")
OLLAMA_MODEL = os.environ.get("SMARTUW_OLLAMA_MODEL", "llama3.1:8b")

GEMINI_API_KEY = os.environ.get("GEMINI_API_KEY", "")
GEMINI_MODEL = os.environ.get("SMARTUW_GEMINI_MODEL", "gemini-2.5-flash")

DEEPSEEK_API_KEY = os.environ.get("DEEPSEEK_API_KEY", "")
DEEPSEEK_MODEL = os.environ.get("SMARTUW_DEEPSEEK_MODEL", "deepseek-chat")

# Which commercial provider handles NON-PII data when the caller doesn't pass
# one per-request. PII is ALWAYS local regardless of this value.
# DeepSeek is the schedule-mapping engine (CFO, 2026-09-09), and the backend
# job defaults to it too — a sidecar defaulting to Gemini while the caller says
# DeepSeek is the mismatch that makes an extraction impossible to explain.
# Overridden per request by the provider the job passes in.
COMMERCIAL_PROVIDER = os.environ.get("SMARTUW_COMMERCIAL_PROVIDER", "deepseek").lower()

# Commercial providers we know how to call. Anything else -> no valid provider
# -> data stays local (fail-closed).
_COMMERCIAL = {"gemini", "deepseek"}

# Keywords / patterns that mark a segment as carrying individual PII -> local
# only. Scoped to ID / contact / financial identifiers — NOT names or addresses,
# which are inherent to every commercial schedule and already flow to the
# commercial provider by design.
_PII_MARKERS = re.compile(
    r"\b("
    r"omang|passport|national\s*id|"
    r"(?:id|ident(?:ification|ity)?)\s*(?:no|number|#)|"
    r"date\s*of\s*birth|dob|next\s*of\s*kin|"
    r"proof\s*of\s*(?:residence|income)|driver'?s?\s*licen[cs]e|"
    r"social\s*security|bank\s*account|account\s*(?:no|number)|"
    # A contact word needs EVIDENCE that it is a contact field: a suffix
    # ("CONTACT PERSON", "MOBILE NUMBER"). The suffix used to be optional
    # here while the PHP port required it, so the two readers disagreed: the
    # sidecar sent MOBILE PLANT ALL RISKS, TELEPHONE INSTALLATION and FAX AND
    # OFFICE EQUIPMENT to the local model, and because extract.py routes on
    # the WHOLE segment text one such cover line diverted the entire sheet to
    # Ollama, which is not deployed. plant_all_risks is one of this engine's
    # own COVERAGE_HINTS.
    r"(?:tel|phone|mobile|cell|fax|telephone|whatsapp|contact)"
    r"\s*(?:no|number|person|name|details|#)"
    r")\b",
    re.I,
)
# The same words immediately followed by a number: "Phone: 76517110",
# "Mobile,26771234567". A cover line ("TELEPHONE INSTALLATION 120000") has
# words in between, so it is left alone. Port of the PHP second marker.
_PII_CONTACT_NUM = re.compile(
    r"\b(?:tel|phone|mobile|cell|fax|telephone|whatsapp)\b\s*[:,]?\s*\+?\d",
    re.I,
)
_EMAIL = re.compile(r"[^\s@]+@[^\s@]+\.[^\s@]+")
# Botswana phone WITH country code — specific enough not to match plain sums
# insured / policy numbers, which lack the 267 prefix. Anchored so '267' cannot
# match INSIDE a longer number: a chassis "WDB26712345678" and a sum insured
# "126712345678" both read as phone numbers before.
_PHONE = re.compile(r"(?<![0-9A-Za-z])\+?267[\s\-]?\d{2}[\s\-]?\d{3}[\s\-]?\d{3}(?![0-9])")


def classify_pii(text: str) -> bool:
    """True if the segment likely contains individual PII (route to local).

    Normalises the abbreviated / delimited / compound label formats common in
    real broker schedules (ID.NO, IDNO, ID#, Phone#, NextofKin, Tel:, Fax:,
    Contact Person) so they are still caught — see the Smart UW PII audit
    2026-06-18. Biases to safety: an ambiguous label routes local.
    """
    if not text:
        return False
    norm = re.sub(r"([a-z0-9])([A-Z])", r"\1 \2", text)      # camelCase -> spaced
    norm = re.sub(r"([A-Z]+)([A-Z][a-z])", r"\1 \2", norm)   # IDNumber -> ID Number
    norm = norm.replace(".", "")                             # I.D. -> ID, D.O.B -> DOB
    norm = norm.replace("#", " number ")                     # ID# / Phone# -> "... number"
    norm = re.sub(r"[_\-:;=/]+", " ", norm)                  # other delimiters -> space
    # _EMAIL / _PHONE run on the ORIGINAL text ('.' / '+' are part of their format).
    return bool(
        _PII_MARKERS.search(norm)
        or _PII_CONTACT_NUM.search(norm)
        or _EMAIL.search(text)
        or _PHONE.search(text)
    )


_PIPE_SPLIT = re.compile(r"\s*\|\s*")
_COMMA_SPLIT = re.compile(r"\s*,\s*")
# A date is a VALUE, not a column name. _NUMERIC cannot say so (no "/" or "-" in
# its class), so "01/01/2026" read as a heading cell — see _looks_like_header_row.
_DATEISH = re.compile(r"^\d{1,4}[/-]\d{1,2}[/-]\d{1,4}$")
_LABEL_SPLIT = re.compile(r"[|,;:\t]")
_VALUEISH = re.compile(r"\S*\d{3,}\S*|\S+@\S+")
# Trailing P too ("12 500 P") — the PHP strips a P from anywhere in the cell,
# so without it a figure-with-suffix read as a heading here and as a data row
# there, and the column scrubbing stopped one row early.
_NUMERIC = re.compile(r"^[Pp]?\s*[\d,\. ]+%?\s*[Pp]?$")


def _safe_label(line: str) -> str:
    """A label safe to store: never the value it labelled. Port of safeLabel()."""
    first = (_LABEL_SPLIT.split(line)[0] if line else "").strip()
    first = _VALUEISH.sub("…", first).strip()
    return first[:40] if first else "(unlabelled line)"


def _row_cells(line: str) -> list[str]:
    """Split one line on the delimiter it ACTUALLY uses: "|" if present, else ",".

    Port of PhpScheduleExtractor::rowCells, and never on both. Splitting on
    r"\\s*\\|\\s*|," broke every money column in a pipe-delimited row, because the
    sheet reader formats cells and a broker workbook's figures arrive carrying
    thousands separators: "1,250,000" became three cells re-joined as
    "1 | 250 | 000", and "18,750" became "18 | 750". It also shifted every cell
    index right of the first comma, so pii_columns indexes learned from a header
    stopped addressing the same columns on the rows below — blanking a sum insured
    or premium column and leaving the identifier. The re-join sites already chose
    their delimiter this way; splitting the same way makes the two agree.
    """
    return _PIPE_SPLIT.split(line) if "|" in line else _COMMA_SPLIT.split(line)


def _looks_like_header_row(line: str) -> bool:
    """Heading row rather than a data row — no cell reads as a figure or a date."""
    cells = [c.strip() for c in _row_cells(line) if c.strip()]
    if len(cells) < 2:
        return False
    for c in cells:
        if _NUMERIC.match(c) and any(ch.isdigit() for ch in c):
            return False
        # A date is a value too. Without this a row like
        # "Period | 01/01/2026 | Kabelo Molefe" read as a HEADER, which cleared
        # pii_columns and sent the contact person's name to the commercial
        # provider on the first row under the header that had just marked that
        # column as an identifier column.
        if _DATEISH.match(c):
            return False
    return True


def redact_pii(text: str) -> tuple[str, list[str]]:
    """Remove identifier-bearing content, returning cleaned text and the LABELS
    of what went (labels only — never the values).

    Port of PhpScheduleExtractor::redactPii, and it exists for the same reason:
    routing the WHOLE segment to the local model because one line carried a
    contact block made every real broker schedule unreadable, since the local
    model is not deployed. Anonymise and send the rest instead.

    Includes the column carry-forward: the classifier matches a LABEL, not a
    value, so an Omang register's header is caught while its data rows are bare
    digits that classify clean. A header cell that reads as PII marks that
    COLUMN, and the rows below are blanked at the same index. Without this the
    redaction would pass identifiers straight to the commercial provider.
    """
    kept: list[str] = []
    dropped: list[str] = []
    pii_columns: list[int] = []
    columns_redacted = 0

    for line in re.split(r"\r\n|\r|\n", text or ""):
        if not line.strip():
            kept.append(line)
            continue

        # A new header row starts a new table, and its columns mean something
        # else — otherwise an identifier register above a cover table blanks
        # that table's sum insured column.
        if pii_columns and _looks_like_header_row(line):
            pii_columns = []

        if pii_columns:
            row_cells = _row_cells(line)
            if len(row_cells) >= 3:
                blanked = False
                for i in pii_columns:
                    if i < len(row_cells) and row_cells[i].strip():
                        row_cells[i] = ""
                        blanked = True
                if blanked:
                    line = (" | ".join(row_cells) if "|" in line
                            else ",".join(row_cells))
                    columns_redacted += 1

        if not classify_pii(line):
            kept.append(line)
            continue

        # Cell-level first: keep the clean cells and blank only the offending
        # ones, so a header row does not lose its column names because one
        # CONTACT column sat at the end of it. Table rows only (three or more
        # cells), and never when the first cell is the offender — a two-cell
        # "CONTACT PERSON | Kabelo" is a label/value block and goes whole.
        cells = _row_cells(line)
        hits = [i for i, cell in enumerate(cells) if classify_pii(cell)]
        if len(cells) >= 3 and hits and 0 not in hits:
            clean = ["" if classify_pii(c) else c for c in cells]
            rebuilt = (" | ".join(clean) if "|" in line else ",".join(clean))
            if not classify_pii(rebuilt) and rebuilt.replace(",", "").replace("|", "").strip():
                kept.append(rebuilt)
                for i, cell in enumerate(cells):
                    if clean[i] == "" and cell.strip():
                        dropped.append(_safe_label(cell))
                pii_columns = sorted(set(pii_columns) | set(hits))
                continue

        dropped.append(_safe_label(line))

    if columns_redacted:
        dropped.append(
            f"{columns_redacted} further row(s) — identifier column values withheld"
        )

    return "\n".join(kept), dropped


def _commercial_choice(provider: str | None) -> str:
    """Resolve the requested commercial provider to a known one, else ''."""
    name = (provider or COMMERCIAL_PROVIDER or "").lower()
    return name if name in _COMMERCIAL else ""


def route_for(text: str, force_local: bool = False,
              provider: str | None = None) -> str:
    """Return 'local' | 'gemini' | 'deepseek' for this segment.

    PII is the FIRST, non-overridable gate (AD-POL-AI-GOV-001). The commercial
    selector only ever picks between cloud providers for already-non-PII data;
    an unknown/unset provider falls back to local (fail-closed).
    """
    if force_local or classify_pii(text):
        return "local"                          # HARD LOCK — non-waivable
    return _commercial_choice(provider) or "local"


# --- providers ---------------------------------------------------------------
def call_ollama(system: str, user: str, model: str | None = None,
                timeout: int = 300) -> str:
    body = {
        "model": model or OLLAMA_MODEL,
        "messages": [
            {"role": "system", "content": system},
            {"role": "user", "content": user},
        ],
        "format": "json",       # force valid JSON
        "stream": False,
        "options": {"temperature": 0},
    }
    r = requests.post(f"{OLLAMA_URL}/api/chat", json=body, timeout=timeout)
    r.raise_for_status()
    return r.json()["message"]["content"]


def call_gemini(system: str, user: str, model: str | None = None,
                api_key: str | None = None, timeout: int = 120) -> str:
    key = api_key or GEMINI_API_KEY
    if not key:
        raise RuntimeError("GEMINI_API_KEY not set")
    mdl = model or GEMINI_MODEL
    # Key goes in the x-goog-api-key HEADER, never the URL query string — a
    # ?key=... URL leaks the credential into proxy / APM / access logs
    # (flagged on PR review 2026-06-11).
    url = f"https://generativelanguage.googleapis.com/v1beta/models/{mdl}:generateContent"
    body = {
        "system_instruction": {"parts": [{"text": system}]},
        "contents": [{"role": "user", "parts": [{"text": user}]}],
        "generationConfig": {"temperature": 0, "responseMimeType": "application/json"},
    }
    r = requests.post(url, json=body, timeout=timeout,
                      headers={"x-goog-api-key": key})
    r.raise_for_status()
    data = r.json()
    return data["candidates"][0]["content"]["parts"][0]["text"]


def call_deepseek(system: str, user: str, model: str | None = None,
                  api_key: str | None = None, timeout: int = 120) -> str:
    # DeepSeek is OpenAI-compatible. Key goes in the Authorization HEADER, never
    # the URL — same rule as call_gemini (no credential in proxy/APM logs).
    key = api_key or DEEPSEEK_API_KEY
    if not key:
        raise RuntimeError("DEEPSEEK_API_KEY not set")
    mdl = model or DEEPSEEK_MODEL
    body = {
        "model": mdl,
        "messages": [
            {"role": "system", "content": system},
            {"role": "user", "content": user},
        ],
        "temperature": 0,
        "response_format": {"type": "json_object"},   # force valid JSON
        # A multi-section schedule's JSON runs long, and DeepSeek's default
        # output cap (4096) truncates it mid-object — which reaches the caller
        # as "not JSON" rather than as a length error. Same budget the Gemini
        # path and PhpScheduleExtractor::callDeepseek use.
        "max_tokens": 8192,
        "stream": False,
    }
    r = requests.post("https://api.deepseek.com/chat/completions", json=body,
                      timeout=timeout,
                      headers={"Authorization": f"Bearer {key}"})
    r.raise_for_status()
    return r.json()["choices"][0]["message"]["content"]


_COMMERCIAL_FNS = {"gemini": call_gemini, "deepseek": call_deepseek}

# Anything that could carry a credential out of a provider error string. The
# keys travel in an Authorization header, in an x-goog-api-key header and in a
# ?key= query parameter, and a provider is free to echo the request back in its
# error body.
#
# (pattern, replacement) pairs, and the replacement replaces the WHOLE match —
# mirroring the PHP mask() this is the port of. An earlier version captured the
# secret itself in group 1 and substituted "\1***", which printed the key in
# full and appended three asterisks to it. Keep every group here non-capturing
# except a deliberate label, and never interpolate a match into the output.
_SECRET_PATTERNS = [
    (re.compile(r"(?i)bearer\s+\S+"),                        "Bearer ***"),
    (re.compile(r"(?i)authorization\s*[=:]\s*\S+"),          "Authorization: ***"),
    (re.compile(r"(?i)x-goog-api-key\s*[=:]\s*\S+"),         "x-goog-api-key: ***"),
    (re.compile(r"(?i)[?&]key=[^&\s]+"),                     "?key=***"),
    (re.compile(
        r"(?i)(?:(?:GEMINI|DEEPSEEK|ANTHROPIC|GROQ)_API_KEY|X-Commercial-Key)"
        r"\s*[=:]\s*\S+"),                                   "API_KEY=***"),
    (re.compile(r"sk-[A-Za-z0-9_\-]{6,}"),                   "sk-***"),
    (re.compile(r"gsk_[A-Za-z0-9_\-]{6,}"),                  "gsk_***"),
    (re.compile(r"AIza[A-Za-z0-9_\-]{6,}"),                  "AIza***"),
    (re.compile(r"AQ\.[A-Za-z0-9_\-]{6,}"),                  "AQ.***"),
]


def _mask(text: str) -> str:
    """Strip credentials out of a provider error before it is passed on."""
    out = str(text or "")
    for pat, replacement in _SECRET_PATTERNS:
        out = pat.sub(replacement, out)
    return out


def complete(system: str, user: str, segment_text: str,
             force_local: bool = False, provider: str | None = None,
             commercial_key: str | None = None) -> tuple[str, str]:
    """Route + call. Returns (raw_json_text, provider_used).

    On ANY commercial-provider error we fall back to LOCAL (never to the other
    cloud provider) — a failure must never escalate data exposure.
    """
    p = route_for(segment_text, force_local=force_local, provider=provider)
    if p in _COMMERCIAL_FNS:
        try:
            return _COMMERCIAL_FNS[p](system, user, api_key=commercial_key), p
        except Exception as exc:
            # Keep WHY the mapping engine was abandoned. Discarding it and
            # returning Ollama's own error made a DeepSeek 404
            # model_not_found unrecoverable from any log: the operator was
            # shown a connection failure to a local model they never chose.
            # The reason is carried in the provider label, which the job and
            # the review screen already surface. _mask() strips the key, and
            # the label is truncated so it stays readable.
            reason = _mask(f"{type(exc).__name__}: {exc}")[:180]
            return call_ollama(system, user), f"local({p}-fallback: {reason})"
    return call_ollama(system, user), "local"
