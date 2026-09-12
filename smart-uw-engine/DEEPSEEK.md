# Smart UW — commercial AI provider (Gemini / DeepSeek), CFO-controllable

The Smart Underwriting engine splits every segment two ways:

- **PII / ID / contact / financial identifiers → LOCAL model only** (Ollama).
  Customer PII never leaves Alpha infra. **Non-waivable** (AD-POL-AI-GOV-001).
- **Non-PII commercial schedule data → the chosen cloud provider**: `gemini`
  (default) **or `deepseek`**.

The PII gate is the **first, non-overridable** branch in
[`providers.py`](providers.py) `route_for()`. The commercial selector can only
ever pick which *cloud* provider sees data that has **already** been classified
non-PII. A failure of the cloud provider falls back to **local**, never to the
other cloud. An unknown provider value falls back to **local** (fail-closed).

## How the CFO switches the "AI brain" (no redeploy)

The choice is read from the **Credentials Vault** per upload, so it takes effect
on the **next** upload — no deploy, no restart.

1. Open **Admin → Credentials Vault** (PIN-locked) in Graphite.
2. Set `smartuw_commercial_provider` = `deepseek` (or `gemini`).
3. Set `deepseek_api_key` = your DeepSeek key (`sk-…`). (Gemini already works
   from the engine env; only DeepSeek needs a key added here.)
4. Save. The next upload routes non-PII segments to DeepSeek.

To switch back, set `smartuw_commercial_provider` = `gemini`.

> Priority is **.env → vault → default** (`VaultController::get`). On the backend
> container `SMARTUW_COMMERCIAL_PROVIDER` is **not** set in env, so the vault
> wins. `GEMINI_API_KEY` **is** in the backend env, so the existing Gemini key
> keeps working without any vault entry.

## How it reaches the engine

`SmartUnderwritingExtractJob` reads the choice + key from the vault and passes
them to the engine **per request**: `&provider=…` on `/extract`, key in the
`X-Commercial-Key` header (travels only over the in-task localhost link to the
sidecar, never the internet; never logged — error paths are secrets-masked).

## PII classifier

`classify_pii()` catches ID / contact / financial **labels** (omang, passport,
national/id/identity no, DOB, next-of-kin, proof of residence/income, driver's
licence, bank/account no, tel/phone/mobile/cell/fax/whatsapp/contact, email
addresses, +267 phone numbers) across the messy real-world formats brokers use
(`ID.NO`, `IDNO`, `ID#`, `Phone#`, `NextofKin`, `Tel:`, `Fax:`,
`Contact Person`). It is deliberately biased to safety: an ambiguous label
routes **local**. Names and addresses are **not** treated as PII gates (they are
inherent to every commercial schedule and already flow to the cloud by design,
exactly as the Gemini path does today).

Hardened after an adversarial PII audit (2026-06-18) that found the original
regex missed those abbreviated formats.

## Tests

[`test_providers.py`](test_providers.py) — routing + PII hard-lock, no network.
The load-bearing assertions: a PII segment routes `local` with **zero** commercial
calls even when `deepseek` is explicitly requested; commercial failure falls back
to local (not the other cloud); the widened classifier catches the audit formats
without over-matching commercial data. Run: `python test_providers.py`.

## Vendor policy

DeepSeek already carries a written CFO carve-out for the digital-CFO / public
chatbot. Extending it to the Smart UW **commercial (non-PII)** route is a CFO
decision to record in the AI-vendor register. The PII carve-out
(AD-POL-AI-GOV-001) remains binding and is enforced in code regardless of the
provider chosen.
