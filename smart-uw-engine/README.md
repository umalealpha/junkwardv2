# smart-uw-engine

Turns the heterogeneous **commercial policy schedules** brokers email us
(retail chain, multi-subsidiary group, logistics, distribution, game-lodge…) into **Graphite-ready structured
JSON**, so an underwriter can review and issue a commercial policy in minutes
instead of re-keying a 60-sheet workbook.

This is the schedule-extraction capability that `OcrExtractor` (single ID/image
docs) does **not** cover: multi-sheet workbooks and multi-section PDFs →
header + coverage sections + per-vehicle motor + asset registers.

## Why an LLM and not a fixed-column importer

The 5 real broker files in this feature's design set share ONE conceptual schema
but FIVE different layouts:

| Broker | Layout |
|---|---|
| Retail chain | 30 sheets, one per branch, vertical sectioned schedule |
| Multi-subsidiary group | one sheet per subsidiary + summary + fleet sheets |
| Logistics | single multi-section sheet (Multi-Peril) + Domestic |
| Distribution group | Non-Motor sectioned + Motor master-list + asset registers |
| Game lodge group | PDF, per-site property breakdown |

A fixed-column parser cannot absorb that variance. An LLM maps *layout → schema*.

## Pipeline

```
normalize.py   file (.xlsx/.xls/.pdf) -> [segments]  (one per sheet / pdf)
schema.py      Graphite target JSON + extraction prompt + validator
providers.py   split router:  PII/ID -> local (Ollama)   commercial -> Gemini
extract.py     per segment: prompt -> LLM -> JSON -> validate -> risk object
```

Output JSON mirrors `PolicyCreateController::store()` + `CreateWizard/types.ts`
field names, so the frontend review screen replays the existing endpoint
sequence (store → risk-address → coverage → calculate-premium → submit →
approve → issue) — **no new write paths**.

## Split routing (CFO option B, 2026-06-11)

* Customer **PII / ID docs** (Omang, passport, proof-of-residence) → **local
  model only**. PII never leaves Alpha infra (AD-POL-AI-GOV-001, non-waivable).
* **Non-PII commercial schedule data** (sums insured, rates, asset registers)
  → **Gemini** (CFO pick).
* `providers.classify_pii()` decides before any external send; on doubt → local.
* **Anthropic is NOT used** in this pipeline (CFO directive 2026-06-11). Only
  Gemini (commercial) + local Ollama (PII).

## Run

```bash
python3.12 -m venv .venv && source .venv/bin/activate
pip install -r requirements.txt

# extract one file (force local model = fully PII-safe, nothing leaves the box)
python extract.py "/path/to/schedule.xlsx" --local

# 3-pass reliability test over the 5 real broker schedules
python run_test.py            # writes test-results.json + PASS/FAIL table
```

Env:
- `SMARTUW_OLLAMA_MODEL` (default `llama3.1:8b`), `OLLAMA_URL`
- `GEMINI_API_KEY` + `SMARTUW_GEMINI_MODEL` (default `gemini-2.5-flash`) for the
  commercial route in prod. Without a key, everything falls back to local.

## Prod integration (not yet deployed — needs Graphite deploy access)

1. Add a `gemini` branch to `AiConfigController` + `OcrController::callAi()` /
   `OcrExtractor` (today: anthropic|groq only). Per CFO 2026-06-11, the smart-uw
   path uses Gemini + local only — do NOT route smart-uw through Anthropic.
2. Expose this engine to Laravel either as a small Python sidecar (mirrors the
   existing `pdf-service` Node sidecar pattern) or port `normalize.py` to
   PhpSpreadsheet and call the LLM from PHP.
3. New tables `smart_uw_uploads` + `smart_uw_extractions` (file, extracted JSON,
   per-field confidence, human_verified, discrepancies).
4. New route `POST /api/v1/underwriting/smart-upload` + `SmartUploadController`
   + Redis `SmartUnderwritingExtractJob`.
5. Frontend `SmartUnderwritingUploadPage.tsx` (dropzone → poll → review screen
   reusing `CreateWizard`), sidebar child under Underwriting, perm
   `underwriting_smart_upload`.

See `../docs/smart-underwriting-upload-research.md` for the full data-model and
lifecycle map.
