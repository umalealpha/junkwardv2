# Smart Underwriting Upload — Deep Research & Design (2026-06-11)

Goal: upload tab in Underwriting where staff drop client documents/spreadsheets, an AI
extracts the data, pre-fills the policy forms, and the underwriter issues a DOM/COM
policy in ~5 minutes.

---

## 1. Product scope

Source of truth: `backend/app/Support/KycDomComProducts.php`

| Group | Product IDs | Names |
|---|---|---|
| Commercial (COMG) | 7, 16, 17, 20, 22 | Commercial Insurance, Engineering, Commercial Travel, Commercial Liabilities, Marine |
| Domestic (DOMG) | 8, 18, 19 | Domestic Insurance, Domestic Travel, Specialist Domestic |

## 2. Data model (what AI must fill)

Hierarchy:
```
Customer ── CustomerProfile (entity_type Individual/Organisation)
   └─ Policy (policies, status 0=quote 1=active 2=cancelled 3=expired)
        └─ PolicyAction (QUOTE → IN_APPROVAL → APPROVED → ISSUED)
             └─ PolicyCoverage (policy_coverages, links risk_address_id)
                  ├─ PolicyCoverageDetail   (sum insured, rate, calculated_value)
                  ├─ PolicyExtentionDetail  (riders/extensions)
                  ├─ PolicySpecifiedItems   (named items)
                  ├─ Motor                  (per-vehicle, 17 premium_* extension cols)
                  └─ Specialist row (1 of 10 tables, products 16/17/18/20/22)
        └─ RiskAddress (risk_address — property risk details)
```

10 specialist tables (full columns in `PARITY_AUDITS/schemas/legacy_prod_schema_2026-04-19.sql`):
ear_coverages, car_coverages, par_coverages, medical_malpractice_coverages,
professional_indemnity_coverages (+3 child tables), machinery_breakdown_coverages,
marine_cargo_once_off_coverages, marine_cargo_open_coverages,
marine_directors_officers_coverages, travel_coverages.
CRUD: `SpecialistCoverageController` TYPE_MAP (`backend/app/Http/Controllers/Api/V1/SpecialistCoverageController.php:15-304`).

KYC: `customer_kyc_dom_com` — DOMG 7-doc individual set (omang/passport, proof residence,
proof income, KYC + DP forms); COMG corporate set (cert of incorporation, extract
controllers, resolution, proof business address, directors/shareholders IDs + expiries).
Catalogue: `DomComKycController.php:30-105`.

## 3. Lifecycle quote → issue (the API sequence the upload tab automates)

All in `PolicyCreateController` (11,652 lines), routes `backend/routes/api_v1.php:1155-1298`:

| # | Endpoint | Method ref | Notes |
|---|---|---|---|
| 1 | `POST /policies` | store() :172-440 | customer+profile+policy+term created; QUOTE, is_draft=1 |
| 2 | `POST /policies/{id}/risk-address` | addRiskAddress | required ≥1 before submit |
| 3 | `POST /policies/{id}/coverage` | addCoverage() :1592-2410 | details / extensions / specified_items / fidelity_data |
| 3b | `POST /policies/{id}/coverages/{covId}/motor` | per-vehicle | motor products |
| 3c | `POST .../specialist/{type}` | SpecialistCoverageController | products 16/17/18/20/22 |
| 4 | `POST /policies/{id}/calculate-premium` | calculatePremium() :2414-2700 | sums 6 premium buckets |
| 5 | `POST /policies/{id}/submit-approval` | submitToApproval() :8530-8646 | HARD gates: ≥1 risk addr, ≥1 rated coverage, reinsurance rules |
| 6 | `POST /policies/{id}/approve` | approvePolicy() :8853 | perm `policy_approved` |
| 7 | `POST /policies/{id}/issue` | issuePolicy() :8652-8849 | perm `policy_submit_to_issue`; status→ISSUED, invoices via `Helper::generateInvoiceDomComIssued`, VAT, ledger, activity log |

Timing measured against code path: ~2 min total for 3-coverage policy. 5-min target feasible.

**Rating is manual-entry** — no central rating engine for DOM/COM; operator (or AI) supplies
rate + sum insured, system computes calculated_value. Pro-rata only on endorsements.

**Gates:** KYC and AML are SOFT (logged, not blocking issue). Payment mandate is post-issue.
Reinsurance validation (tb_prvalidationruledetails via `ReinsuranceValidator`) is the only
hard business-rule gate at submit.

## 4. Existing infra to reuse

| Piece | Where | Reuse |
|---|---|---|
| LLM service (Groq primary + Anthropic fallback, agentic tool-use loop, max 5 rounds) | `AiAssistantService.php` (697 lines), keys in Credentials Vault via `AiConfigController::getSettings()` | YES — add extraction methods |
| Tool framework (7 read tools incl. execute_query) | `AiToolsService.php` | pattern for `extract_policy_fields` tool |
| Conversation persistence | `ai_conversations`, `ai_messages` | optional |
| Excel import pipeline (Maatwebsite/PhpSpreadsheet, S3, ExcelImportActivity, queued jobs) | `ExcelImportController`, `app/Imports/*` | YES — file intake pattern |
| Queue | Redis, jobs e.g. `GenerateQuotationPdfJob` | YES — extraction job |
| Storage | S3 + CloudFront, base64 KYC upload pattern in `UploadController` | YES |
| pdf-service | Node + Puppeteer, **generate-only** | NOT for parsing |
| Frontend upload UX | `Imports/PolicyActivationImportPage.tsx` (dropzone), `BatchProcessing/BatchCreatePage.tsx` (FormData) | YES |
| Policy wizard + payload types | `Policies/CreateWizard/*`, `api/policyCreate.ts` | YES — review screen reuses these |
| Underwriting section | `pages/Underwriting/UnderwritingQueuePage.tsx`, route `App.tsx:317`, sidebar `Sidebar.tsx:330` | new tab slots beside Queue |

**CORRECTION (2026-06-11): Graphite ALREADY has document-AI.** Earlier scan missed it.
- `backend/app/Http/Controllers/Api/V1/OcrController.php` (685 lines): `POST /ocr/parse`
  (text → structured fields + fraud checks + DB cross-ref), `POST /ocr/extract`
  (vision-LLM: image → JSON, skips Tesseract). Used live by customer
  start.alphadirect.co.bw for Omang / licence / vehicle valuation / proof-of-residence.
- `backend/app/Services/OcrExtractor.php` (978 lines): vision_llm path (Claude / Llama-Scout
  direct image→JSON) + OCR.space fallback + PDF text-layer + `structureFromText`. Env
  `OCR_PROVIDER`. `OcrExtractor::TYPES` = id/vehicle/property/passport/insurance/invoice/...
- Provider plumbing: `AiConfigController` Credentials Vault, today `ai_provider in (anthropic,groq)`.
  `callAi()` in OcrController switches provider. **Gemini = add one branch here + in OcrExtractor.**

So OcrExtractor covers **single ID/image docs**. What it does NOT cover — and what smart-uw
adds — is **multi-sheet workbook / multi-section PDF SCHEDULES → full policy schema**
(header + N coverage sections + per-vehicle motor + asset registers). That is the
`smart-uw-engine/` module (this repo).

**Still to build:** schedule-extraction engine (done — see §7), Gemini provider branch,
extraction-review table/UI, per-user AI usage metering, local-vision branch for PII docs
(NOTE: today OcrExtractor sends ID docs to Groq/Anthropic *external* — that is an existing
PII-to-external path worth reviewing under AD-POL-AI-GOV-001, separate from this feature).

## 5. Proposed architecture — Smart Underwriting Upload

```
[FE] /underwriting/smart-upload  (tab beside Queue)
  drop files (xlsx/pdf/img: asset registers, schedules, broker slips, KYC docs)
  ↓ POST /api/v1/underwriting/smart-upload (multipart)
[BE] store S3 → smart_uw_uploads row → dispatch SmartUnderwritingExtractJob (Redis)
  job: per file → text layer:
       xlsx/csv → PhpSpreadsheet rows
       pdf      → pdftotext (poppler) → if empty (scan) → vision-capable LLM (image blocks)
       img      → vision LLM
  → AiAssistantService::extractStructured(prompt = target JSON schema per product)
       schema = CreatePolicyPayload + RiskAddressPayload + CoveragePayload[+specialist]
       (field lists already typed in frontend/src/pages/Policies/CreateWizard/types.ts)
  → confidence per field + source snippet → smart_uw_extractions table
[FE] poll job → REVIEW SCREEN: pre-filled wizard (reuse CreateWizard steps),
     low-confidence fields highlighted, underwriter edits/confirms
  → on confirm, FE replays existing call sequence §3 (store → risk → coverages →
     rate → submit → approve → issue) — NO new write paths, all existing
     validations + permissions + audit logs intact
```

Design decisions (CFO-CONFIRMED 2026-06-11):
1. **LLM provider — SPLIT ROUTING (option B)**. Gemini for NON-PII commercial data
   (asset registers, schedules, sums insured, broker slips, valuations). LOCAL model on
   prod EC2 for PII/ID docs (Omang, passport, proof-of-residence, full KYC set) — customer
   PII never leaves Alpha infra per AD-POL-AI-GOV-001 (non-waivable). **Anthropic REMOVED
   from this pipeline (CFO directive 2026-06-11): Gemini + local only, no Anthropic.**
   A doc-classifier step decides PII vs non-PII before routing.
2. **No auto-issue (CONFIRMED)**. AI fills → underwriter review screen → confirm. Issue
   stays under `policy_approved` + `policy_submit_to_issue`; no gate bypass.
3. **New permission**: `underwriting_smart_upload` gating the tab.
4. **Scope of v1**: PENDING sample file from CFO to size; default suggestion 7 + 8,
   specialist tables phase 2.

Provider-routing implications:
- Gemini = new provider branch in `AiAssistantService` (Gemini REST API, vision-capable for
  scanned slips). Key in Credentials Vault.
- Local PII model = stand up a vision/extraction model ON prod EC2 (af-south) — NOT the Mac
  boardroom Ollama (different host). Cost/infra item: GPU-less EC2 → small CPU model or
  managed local inference container. Size after seeing sample doc.
- Classifier: cheap heuristic (filename + content keywords: omang/passport/id ⇒ PII) before
  any external send; on doubt → treat as PII → local.

New backend pieces: routes, SmartUploadController, SmartUnderwritingExtractJob,
2 migrations (smart_uw_uploads, smart_uw_extractions), extraction prompt builder per
product, poppler-utils in backend image.
New frontend: SmartUnderwritingUploadPage + review screen wiring into CreateWizard,
sidebar children [Queue, Smart Upload], api/underwriting.ts.

## 6. Risks

- PolicyCreateController is a god-file (11.6k lines) — do NOT add extraction there; new controller.
- Rating is manual: AI can propose rates from the source docs, but premium correctness is
  underwriter's confirm step. Never auto-issue without the review click (v1).
- Customer PII inside uploaded docs → extraction provider must be approved; log no PII.
- Reinsurance validation may reject big-SI commercial — surface the validator errors on review screen.
