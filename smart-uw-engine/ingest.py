"""
ingest.py — accept a ZIP (or a folder / list) of broker files and process each.

CFO directive 2026-06-11: an underwriter should drop ONE zip of the whole
account and get everything back. This unpacks the zip, runs every supported
file through the extractor + reconciler, and returns one combined result with a
per-file progress callback (the UI shows %/ETA from this).
"""
from __future__ import annotations
import os, zipfile, tempfile, glob

SUPPORTED = (".xlsx", ".xls", ".xlsb", ".pdf")


def _iter_files(path):
    if os.path.isdir(path):
        for ext in SUPPORTED:
            yield from glob.glob(os.path.join(path, "**", "*" + ext), recursive=True)
    elif path.lower().endswith(".zip"):
        tmp = tempfile.mkdtemp(prefix="smartuw_zip_")
        with zipfile.ZipFile(path) as z:
            z.extractall(tmp)
        for ext in SUPPORTED:
            yield from glob.glob(os.path.join(tmp, "**", "*" + ext), recursive=True)
    elif path.lower().endswith(SUPPORTED):
        yield path


def list_payload(path):
    """Return the supported files inside a zip/folder/file (for the progress UI)."""
    return sorted(set(_iter_files(path)))


def ingest(path, on_progress=None, force_local=False):
    """
    Process a zip/folder/file. Returns {files:[{file, extraction, reconciliation}]}.
    on_progress(done, total, current_name) drives the UI progress bar / ETA.
    Import the heavy modules lazily so list_payload stays cheap.
    """
    import extract as _extract
    import reconcile as _reconcile
    files = list_payload(path)
    total = len(files)
    out = []
    for i, f in enumerate(files):
        if on_progress:
            on_progress(i, total, os.path.basename(f))
        rec = None
        try:
            rec = _reconcile.reconcile_file(f)   # deterministic audit (fast)
        except Exception:
            pass
        ext = None
        if f.lower().endswith(SUPPORTED):
            try:
                ext = _extract.extract_file(f, force_local=force_local)
            except Exception as e:
                ext = {"error": f"{type(e).__name__}: {e}"}
        out.append({"file": os.path.basename(f), "reconciliation": rec, "extraction": ext})
    if on_progress:
        on_progress(total, total, "done")
    return {"file_count": total, "files": out}


if __name__ == "__main__":
    import sys, json
    p = sys.argv[1]
    print("payload:", [os.path.basename(x) for x in list_payload(p)])
