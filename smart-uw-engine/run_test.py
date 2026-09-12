"""
run_test.py — 3-pass extraction test over the 5 real broker schedules.

Proves the engine reliably turns heterogeneous broker files into valid
Graphite-ready JSON. Runs each representative segment 3x (temperature 0 ->
expect stable structure) and asserts:
  * structural validation passes (schema.validate -> [])
  * customer.name extracted
  * >=1 coverage OR >=1 motor row found
  * coverage/motor counts stable across the 3 passes (determinism)

Writes results to test-results.json and prints a PASS/FAIL table.
Forces LOCAL model (PII-safe, no external send) for the test run.
"""
from __future__ import annotations
import json, time, os, sys
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import normalize, extract

import glob as _glob
# Drop the real broker files into ./fixtures (git-ignored — never commit PII).
# The harness globs whatever is there, so no customer filenames live in code.
FIX = os.environ.get("SMARTUW_FIXTURES",
                     os.path.join(os.path.dirname(os.path.abspath(__file__)), "fixtures"))


def _discover():
    files = sorted(_glob.glob(os.path.join(FIX, "*.xlsx")) +
                   _glob.glob(os.path.join(FIX, "*.xls")) +
                   _glob.glob(os.path.join(FIX, "*.pdf")))
    # (path, sheet=None -> first meaningful, expectation)
    return [(os.path.basename(f), None, "expect_any") for f in files]


CASES = _discover()
PASSES = 3


def get_segment_text(path, seg_name):
    segs = normalize.normalize(path)
    if seg_name is None:
        return segs[0]["name"], segs[0]["text"]
    for s in segs:
        if s["name"] == seg_name:
            return s["name"], s["text"]
    return segs[0]["name"], segs[0]["text"]


def run():
    results = []
    for fname, seg_name, expectation in CASES:
        path = os.path.join(FIX, fname)
        name, text = get_segment_text(path, seg_name)
        runs = []
        for p in range(PASSES):
            t = time.time()
            try:
                obj = extract.extract_segment(name, text, force_local=True)
                cust = obj.get("customer") or {}
                runs.append({
                    "ok": True,
                    "elapsed": round(time.time() - t, 1),
                    "errors": obj.get("_errors", []),
                    "customer_name": cust.get("name"),
                    "entity_type": cust.get("entity_type"),
                    "existing_policy": (obj.get("policy") or {}).get("existing_policy_number"),
                    "n_coverages": len(obj.get("coverages") or []),
                    "n_motor": len(obj.get("motor") or []),
                })
            except Exception as e:
                runs.append({"ok": False, "error": f"{type(e).__name__}: {e}",
                             "elapsed": round(time.time() - t, 1)})
            print(f"[{fname[:28]:28} / {name[:14]:14}] pass {p+1}/{PASSES} done "
                  f"({runs[-1].get('elapsed')}s)", flush=True)

        ok_runs = [r for r in runs if r.get("ok")]
        cov_counts = {r["n_coverages"] for r in ok_runs}
        mot_counts = {r["n_motor"] for r in ok_runs}
        has_data = all((r["n_coverages"] + r["n_motor"]) > 0 for r in ok_runs)
        names_ok = all(r["customer_name"] for r in ok_runs)
        errs_ok = all(not r["errors"] for r in ok_runs)
        motor_ok = (any(r["n_motor"] > 0 for r in ok_runs)
                    if expectation == "expect_motor" else True)
        # determinism: structure stable across passes (allow +/-1 line drift)
        stable = (len(ok_runs) == PASSES
                  and (max(cov_counts) - min(cov_counts) <= 1 if cov_counts else True)
                  and (max(mot_counts) - min(mot_counts) <= 1 if mot_counts else True))
        verdict = all([len(ok_runs) == PASSES, errs_ok, names_ok, has_data, motor_ok, stable])
        results.append({
            "file": fname, "segment": name, "expectation": expectation,
            "passes_ok": len(ok_runs), "errs_ok": errs_ok, "names_ok": names_ok,
            "has_data": has_data, "motor_ok": motor_ok, "stable": stable,
            "cov_counts": sorted(cov_counts), "mot_counts": sorted(mot_counts),
            "verdict": "PASS" if verdict else "FAIL",
            "runs": runs,
        })

    with open(os.path.join(os.path.dirname(__file__), "test-results.json"), "w") as f:
        json.dump(results, f, indent=2, ensure_ascii=False)

    print("\n================ 3x EXTRACTION TEST ================")
    print(f"{'FILE':40} {'SEGMENT':14} {'COV':>8} {'MOT':>8} {'VERDICT':>8}")
    for r in results:
        print(f"{r['file'][:40]:40} {r['segment'][:14]:14} "
              f"{str(r['cov_counts']):>8} {str(r['mot_counts']):>8} {r['verdict']:>8}")
    n_pass = sum(1 for r in results if r["verdict"] == "PASS")
    print(f"\nOVERALL: {n_pass}/{len(results)} files PASS over {PASSES} passes each")
    print("RESULT:", "ALL PASS" if n_pass == len(results) else "SOME FAIL")


if __name__ == "__main__":
    run()
