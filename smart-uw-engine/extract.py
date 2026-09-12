"""
extract.py — orchestrator: broker file -> Graphite-ready structured JSON.

Pipeline:
  normalize(file)            -> [segments]            (one per sheet / pdf)
  for each meaningful segment:
      build prompt           (schema + coverage hints + segment text)
      complete()             -> route local|gemini    -> raw JSON
      json-parse + validate  -> structured risk object
  merge segments             -> {customer, policies:[per-risk objects]}

Output is consumed by the Graphite frontend review screen, which lets the
underwriter confirm/edit before replaying the existing create-policy endpoints.
"""
from __future__ import annotations
import json, sys, os

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import normalize as _norm
import schema as _schema
import providers as _prov


def _parse_json(raw: str) -> dict:
    raw = (raw or "").strip()
    # strip accidental code fences
    if raw.startswith("```"):
        raw = raw.split("```", 2)[1] if "```" in raw[3:] else raw.strip("`")
        raw = raw.split("\n", 1)[1] if raw.lower().startswith("json") else raw
    try:
        return json.loads(raw)
    except Exception:
        # last resort: grab first {...} block
        a, b = raw.find("{"), raw.rfind("}")
        if a >= 0 and b > a:
            return json.loads(raw[a:b + 1])
        raise


def extract_segment(name: str, text: str, force_local: bool = False,
                    provider: str | None = None,
                    commercial_key: str | None = None) -> dict:
    # Anonymise, then send. Routing on the RAW segment sent the whole sheet to
    # the local model whenever any single line carried a contact block — and
    # every real broker schedule has one on its cover sheet, so nothing could
    # be read at all, because no local model is deployed. Redact the
    # identifier-bearing lines and read the rest, which is what the PHP reader
    # already does. complete() still routes on the CLEANED text, so anything
    # the redactor could not make safe is a hard local lock as before.
    clean, redacted = _prov.redact_pii(text)

    # BOTH conditions, matching the PHP: nothing left AND something was taken.
    # On `not clean.strip()` alone a whitespace-only or figure-less sheet came
    # back as "every line was ID / contact data — looks like a KYC document",
    # which is the one message that makes an operator go and fetch a different
    # file.
    if not clean.strip() and redacted:
        # Everything was identifiers. Say so rather than billing the provider
        # for an empty prompt and returning a risk with no error, which reads
        # to the underwriter as "the schedule had nothing in it".
        return {
            "_segment": name,
            "_redacted_lines": redacted,
            "_error": "Every line of this segment was ID / contact / financial data, "
                      "so nothing was left to read. It looks like a KYC or contact "
                      "document rather than a schedule — upload the schedule itself.",
        }

    user = _schema.build_user_prompt(_schema.COVERAGE_HINTS, _schema.TARGET_SCHEMA,
                                     name, clean)
    raw, provider_used = _prov.complete(_schema.SYSTEM_PROMPT, user, clean,
                                        force_local=force_local,
                                        provider=provider,
                                        commercial_key=commercial_key)
    obj = _parse_json(raw)
    obj["_segment"] = name
    obj["_provider"] = provider_used
    # The underwriter must be told what was withheld from the model, or a
    # missing contact block reads as "not in the schedule".
    obj["_redacted_lines"] = redacted
    obj["_errors"] = _schema.validate(obj)
    return obj


def extract_file(path: str, force_local: bool = False,
                 provider: str | None = None, commercial_key: str | None = None,
                 max_segments: int | None = None) -> dict:
    segs = _norm.normalize(path)
    if max_segments:
        segs = segs[:max_segments]
    risks = []
    for s in segs:
        try:
            risks.append(extract_segment(s["name"], s["text"], force_local,
                                         provider=provider,
                                         commercial_key=commercial_key))
        except Exception as e:
            risks.append({"_segment": s["name"], "_error": f"{type(e).__name__}: {e}"})
    return {
        "source_file": os.path.basename(path),
        "segment_count": len(segs),
        "risks": risks,
    }


if __name__ == "__main__":
    import argparse
    ap = argparse.ArgumentParser()
    ap.add_argument("file")
    ap.add_argument("--local", action="store_true", help="force local model (PII-safe)")
    ap.add_argument("--max", type=int, default=None, help="limit segments")
    a = ap.parse_args()
    out = extract_file(a.file, force_local=a.local, max_segments=a.max)
    print(json.dumps(out, indent=2, ensure_ascii=False))
