"""
normalize.py — broker schedule file -> clean text segments for the LLM.

Brokers send heterogeneous workbooks: one sheet per risk-location (a 30-branch chain),
one sheet per subsidiary (a multi-subsidiary group), or a single multi-section sheet (a logistics insured),
plus PDFs (a game-lodge PDF). We do NOT try to force a fixed column layout — we hand the
LLM clean markdown/text and let it map layout -> schema.

Strategy:
  * .xlsx / .xls : iterate sheets, emit one segment per *meaningful* sheet
                   (markitdown renders each sheet as a markdown table).
                   Skip filler sheets ("Sheet1", tiny "<name> 2026" rate stubs).
  * .pdf         : single segment, full text (markitdown / pdfplumber).

Each segment = {"name": <sheet or doc name>, "text": <markdown>, "kind": ...}.
"""
from __future__ import annotations
import os, re, warnings
warnings.filterwarnings("ignore")

# --- sheet filters -----------------------------------------------------------
_SKIP_EXACT = {"sheet1", "sheet2", "sheet3"}
# tiny per-location rate stubs in a 30-branch chain are named "<Location> 2026"
_SKIP_SUFFIX_2026 = re.compile(r"\b2026$")


def _is_filler_sheet(name: str, n_rows: int, n_cols: int) -> bool:
    low = name.strip().lower()
    if low in _SKIP_EXACT:
        return True
    # the "<name> 2026" stubs are ~54 rows x 5 cols summary copies; the real
    # schedule sheets are wider (>=20 cols). Drop the narrow 2026 duplicates.
    if _SKIP_SUFFIX_2026.search(name.strip()) and n_cols <= 6:
        return True
    if n_rows <= 2 or n_cols <= 1:
        return True
    return False


def _xlsx_segments(path: str, max_rows_per_sheet: int):
    import openpyxl
    wb = openpyxl.load_workbook(path, read_only=True, data_only=True)
    segs = []
    for ws in wb.worksheets:
        if _is_filler_sheet(ws.title, ws.max_row or 0, ws.max_column or 0):
            continue
        rows = []
        for i, row in enumerate(ws.iter_rows(values_only=True)):
            if i >= max_rows_per_sheet:
                rows.append("... (truncated)")
                break
            cells = ["" if v is None else str(v) for v in row]
            if any(c.strip() for c in cells):
                rows.append(" | ".join(cells).rstrip(" |"))
        if rows:
            segs.append({"name": ws.title, "kind": "sheet", "text": "\n".join(rows)})
    wb.close()
    return segs


def _xls_segments(path: str, max_rows_per_sheet: int):
    import xlrd
    wb = xlrd.open_workbook(path)
    segs = []
    for sh in wb.sheets():
        if _is_filler_sheet(sh.name, sh.nrows, sh.ncols):
            continue
        rows = []
        for r in range(min(sh.nrows, max_rows_per_sheet)):
            cells = ["" if sh.cell_value(r, c) == "" else str(sh.cell_value(r, c))
                     for c in range(sh.ncols)]
            if any(c.strip() for c in cells):
                rows.append(" | ".join(cells).rstrip(" |"))
        if sh.nrows > max_rows_per_sheet:
            rows.append("... (truncated)")
        if rows:
            segs.append({"name": sh.name, "kind": "sheet", "text": "\n".join(rows)})
    return segs


def _xlsb_segments(path: str, max_rows_per_sheet: int):
    # Binary .xlsb (macro-enabled) — neither openpyxl nor xlrd read it. Big
    # commercial accounts (fleet lists, multi-section group renewals) routinely
    # arrive as .xlsb, so this path is required, not optional. Added 2026-06-11
    # after the Choppies fleet + Reddy's filling-station schedules (both .xlsb)
    # failed to load at all.
    from pyxlsb import open_workbook
    segs = []
    with open_workbook(path) as wb:
        for name in wb.sheets:
            rows = []
            ncols = 0
            truncated = False
            with wb.get_sheet(name) as sheet:
                for r, row in enumerate(sheet.rows()):
                    if r >= max_rows_per_sheet:
                        truncated = True
                        break
                    cells = ["" if c.v is None else str(c.v) for c in row]
                    ncols = max(ncols, len(cells))
                    if any(c.strip() for c in cells):
                        rows.append(" | ".join(cells).rstrip(" |"))
            if _is_filler_sheet(name, len(rows), ncols):
                continue
            if truncated:
                rows.append("... (truncated)")
            if rows:
                segs.append({"name": name, "kind": "sheet", "text": "\n".join(rows)})
    return segs


def _pdf_segments(path: str):
    try:
        import pdfplumber
        out = []
        with pdfplumber.open(path) as pdf:
            for pg in pdf.pages:
                out.append(pg.extract_text() or "")
        return [{"name": os.path.basename(path), "kind": "pdf", "text": "\n".join(out)}]
    except Exception:
        from markitdown import MarkItDown
        r = MarkItDown().convert(path)
        return [{"name": os.path.basename(path), "kind": "pdf", "text": r.text_content}]


def normalize(path: str, max_rows_per_sheet: int | None = None):
    """Return list of text segments for the given broker file.

    max_rows_per_sheet defaults to SMARTUW_MAX_ROWS_PER_SHEET (env) or 400.
    Raised from the original 120 because fleet registers run long — Choppies
    FORKLIFTS (225) and TRUCKS (125) were being truncated mid-fleet at 120,
    silently dropping insured vehicles.
    """
    if max_rows_per_sheet is None:
        max_rows_per_sheet = int(os.environ.get("SMARTUW_MAX_ROWS_PER_SHEET", "400"))
    ext = os.path.splitext(path)[1].lower()
    if ext == ".xlsx":
        return _xlsx_segments(path, max_rows_per_sheet)
    if ext == ".xls":
        return _xls_segments(path, max_rows_per_sheet)
    if ext == ".xlsb":
        return _xlsb_segments(path, max_rows_per_sheet)
    if ext == ".pdf":
        return _pdf_segments(path)
    raise ValueError(f"unsupported file type: {ext}")


if __name__ == "__main__":
    import sys, json
    segs = normalize(sys.argv[1])
    print(json.dumps([{"name": s["name"], "kind": s["kind"], "chars": len(s["text"])}
                      for s in segs], indent=2))
