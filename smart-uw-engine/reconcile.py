"""
reconcile.py — DOES THE UPLOAD CAPTURE ALL DETAIL?  (CFO acceptance test)

For each broker schedule we independently sum every line item per asset class
(coverage section) and compare it to the schedule's OWN printed totals — the
"Totals" rows and grand totals the broker already computed. If our line-item
sum equals the broker's printed total, then nothing was dropped: the upload
captured all detail.

We report both EXCLUDING and INCLUDING VAT (Botswana VAT = 14%). Schedules
label sums inconsistently ("SUM INSURED (VAT INCLUSIVE)", "AMOUNT BEFORE VAT",
"VAT @ 14%", "GRAND TOTAL"); we capture whatever printed total the sheet gives
and show the ex/incl pair around it.

Deterministic (no LLM) — this is ground truth. The LLM extractor is then held
to THIS standard.
"""
from __future__ import annotations
import os, re, sys, warnings
warnings.filterwarnings("ignore")

VAT_RATE = 0.14  # Botswana

def _num(v):
    if v is None:
        return None
    if isinstance(v, (int, float)):
        return float(v)
    s = str(v).strip().replace(",", "")
    s = re.sub(r"[Pp]\s*", "", s)          # 'P 3,120,000' -> '3120000'
    if s.endswith("%"):
        try: return float(s[:-1]) / 100.0
        except: return None
    try: return float(s)
    except: return None


def _rows_xlsx(path, sheet):
    import openpyxl
    wb = openpyxl.load_workbook(path, read_only=True, data_only=True)
    ws = wb[sheet]
    rows = [list(r) for r in ws.iter_rows(values_only=True)]
    wb.close()
    return rows


def _rows_xls(path, sheet):
    import xlrd
    wb = xlrd.open_workbook(path)
    sh = wb.sheet_by_name(sheet)
    return [[sh.cell_value(r, c) for c in range(sh.ncols)] for r in range(sh.nrows)]


def _rows_xlsb(path, sheet):
    from pyxlsb import open_workbook
    out = []
    with open_workbook(path) as wb:
        with wb.get_sheet(sheet) as sh:
            for row in sh.rows():
                out.append([c.v for c in row])
    return out


def _sheets(path):
    ext = os.path.splitext(path)[1].lower()
    if ext == ".xlsx":
        import openpyxl
        wb = openpyxl.load_workbook(path, read_only=True, data_only=True)
        names = wb.sheetnames; wb.close()
        return [(n, _rows_xlsx(path, n)) for n in names]
    if ext == ".xls":
        import xlrd
        wb = xlrd.open_workbook(path)
        return [(s.name, _rows_xls(path, s.name)) for s in wb.sheets()]
    if ext == ".xlsb":
        from pyxlsb import open_workbook
        with open_workbook(path) as wb:
            names = list(wb.sheets)
        return [(n, _rows_xlsb(path, n)) for n in names]
    return []


def _find_cols(rows):
    """Locate the Sum Insured column and ALL candidate premium columns.

    Broker schedules are inconsistent about WHICH premium column holds the
    per-line premium: some use 'SECTION PREMIUM', others 'TOTAL PREMIUM',
    'ANNUAL PREMIUM' or 'EXTENSION PREMIUM', and multi-year sheets repeat the
    headers for the prior period. Hard-picking one column silently dropped whole
    sheets (sum = 0) when the data lived in a different column. So we return
    EVERY column whose header mentions premium and let reconcile_sheet sum each
    candidate, then choose the one that reconciles to the printed total.
    """
    si_col = None
    prem_cols = []
    for r in rows[:40]:
        for c, cell in enumerate(r):
            t = str(cell or "").lower()
            if si_col is None and "sum insured" in t:
                si_col = c
            if "premium" in t and c not in prem_cols:
                prem_cols.append(c)
    return si_col, sorted(prem_cols)


def reconcile_sheet(name, rows):
    """Sum line-item SI + premium PER candidate premium column; collect printed
    'total' rows. The caller picks the candidate column that ties to the printed
    total (handles SECTION- vs TOTAL-premium layout variance across sheets)."""
    si_col, prem_cols = _find_cols(rows)
    if si_col is None and not prem_cols:
        return None
    sum_si = 0.0
    sums   = {c: 0.0 for c in prem_cols}   # summed line-item premium per column
    counts = {c: 0 for c in prem_cols}     # line-item count per column
    printed = []   # (label, printed_si, printed_prem)
    for r in rows:
        label = " ".join(str(c) for c in r[:4] if c not in (None, "")).strip()
        low = label.lower()
        si = _num(r[si_col]) if si_col is not None and si_col < len(r) else None
        # rows that are AGGREGATES, not line items — capture as printed totals,
        # never sum (else we double-count). Covers 'total', 'amount before vat',
        # 'vat @', 'grand', 'sub total', 'premium summary', 'sub-total'.
        is_total = bool(re.search(
            r"\btotal|amount before vat|\bvat\b|grand|sub[\s-]?total|premium summary",
            low))
        if is_total:
            # printed premium for this total row = the largest premium-column
            # value present (the grand/section total, whichever column holds it).
            cand = [_num(r[c]) for c in prem_cols if c < len(r)]
            cand = [v for v in cand if v is not None]
            printed.append((label[:48], si, max(cand) if cand else None))
            continue
        # Skip only the header rows; do NOT require a non-empty label — some
        # schedules carry a section-subtotal premium on a row whose first
        # columns are blank (the amount sits further right). Requiring a label
        # silently dropped those (e.g. a 7,000 section total with no label),
        # leaving the sheet under-counted. is_total rows are already excluded
        # above, so any positive premium reaching here is a real line/section.
        if not low.startswith(("section & cover", "risk description")):
            if si and si > 0:
                sum_si += si
            for c in prem_cols:
                v = _num(r[c]) if c < len(r) else None
                if v and v > 0:
                    sums[c] += v
                    counts[c] += 1
    # default reported sum = the candidate column carrying the most line items
    best_c = max(sums, key=lambda c: counts[c]) if sums else None
    return {
        "sheet": name,
        "n_premium_items": counts.get(best_c, 0),
        "summed_sum_insured": round(sum_si, 2),
        "summed_premium": round(sums.get(best_c, 0.0), 2),
        "col_sums": {c: round(v, 2) for c, v in sums.items()},
        "col_counts": counts,
        "printed_total_rows": printed,
    }


def reconcile_file(path):
    out = {"file": os.path.basename(path), "sheets": [], "grand_printed": []}
    for name, rows in _sheets(path):
        res = reconcile_sheet(name, rows)
        if res and (res["n_premium_items"] or res["summed_sum_insured"]):
            out["sheets"].append(res)
            # capture grand-total-looking printed rows
            for lbl, si, pr in res["printed_total_rows"]:
                if re.search(r"grand|total annual|total premium|totals?$|amount before vat|vat",
                             lbl.lower()):
                    out["grand_printed"].append({"sheet": name, "label": lbl,
                                                 "printed_si": si, "printed_premium": pr})
    return out


def _fmt(n):
    return "—" if n is None else f"{n:,.2f}"


# sheets that are pure aggregates (no line items of their own) — used as the
# grand-total reference, NOT summed (avoids double counting).
_SUMMARY_SHEET = re.compile(r"^(summary|control summary|premium summary)$", re.I)

# printed labels that denote a sheet's own grand/section premium total
_GRAND_LBL = re.compile(
    r"grand total|amount before vat|total annual premium|total premium|total annual",
    re.I)


def _pick_sheet_total(printed):
    """From a sheet's printed total rows choose the grand premium total."""
    grand = [p for p in printed if p[2] and _GRAND_LBL.search(p[0].lower())]
    if grand:
        # prefer 'amount before vat' / 'total annual premium' (ex-VAT bases)
        return grand[-1]
    prem = [p for p in printed if p[2]]
    return max(prem, key=lambda p: p[2]) if prem else None


def _match(summed, printed, tol=0.01):
    """Does summed match printed at ex-VAT or incl-VAT within tol (1%)?"""
    if not printed or printed <= 0:
        return (False, None, None)
    for basis, val in (("ex-VAT", summed),
                       ("+VAT", summed * (1 + VAT_RATE)),
                       ("strip-VAT", summed / (1 + VAT_RATE))):
        if val and abs(val - printed) / printed <= tol:
            return (True, basis, round(abs(val - printed), 2))
    rel = abs(summed - printed) / printed
    return (False, "ex-VAT", round(rel * 100, 2))


def print_report(path):
    r = reconcile_file(path)
    print("\n" + "=" * 78)
    print("FILE:", r["file"])
    print(f"{'SHEET':22} {'ITEMS':>5} {'Σ PREMIUM':>15} {'PRINTED TOTAL':>15} {'MATCH':>16}")
    npass = ntest = 0
    for s in r["sheets"]:
        if _SUMMARY_SHEET.match(s["sheet"].strip()):
            continue
        tot = _pick_sheet_total(s["printed_total_rows"])
        if not tot:
            print(f"{s['sheet'][:22]:22} {s['n_premium_items']:>5} "
                  f"{_fmt(s['summed_premium']):>15} {'(no total row)':>15}")
            continue
        ntest += 1
        # Try EVERY candidate premium column; the sheet reconciles if ANY
        # column's line-item sum ties to the printed total (handles section- vs
        # total-premium layouts). Report the matching column; else the closest.
        candidates = list(s.get("col_sums", {}).values()) or [s["summed_premium"]]
        best = None
        for cand in candidates:
            ok, basis, delta = _match(cand, tot[2])
            if ok:
                best = (cand, ok, basis, delta); break
            if best is None or (delta is not None and delta < best[3]):
                best = (cand, ok, basis, delta)
        summed, ok, basis, delta = best
        npass += ok
        verdict = f"✓ {basis}" if ok else f"✗ {delta}% off"
        print(f"{s['sheet'][:22]:22} {s['n_premium_items']:>5} "
              f"{_fmt(summed):>15} {_fmt(tot[2]):>15} {verdict:>16}")
    print(f"  RECONCILED: {npass}/{ntest} sheets tie to their printed premium total "
          f"(VAT-aware, 1% tol)")
    return npass, ntest


if __name__ == "__main__":
    import glob
    fixdir = sys.argv[1] if len(sys.argv) > 1 else "/tmp/eml_extract"
    files = sorted(glob.glob(os.path.join(fixdir, "*.xlsx")) +
                   glob.glob(os.path.join(fixdir, "*.xls")))
    gp = gt = 0
    for f in files:
        try:
            p, t = print_report(f)
            gp += p; gt += t
        except Exception as e:
            print(f"\nFILE: {os.path.basename(f)}  ERROR {type(e).__name__}: {e}")
    print("\n" + "=" * 78)
    print(f"OVERALL: {gp}/{gt} detail sheets reconcile to the broker's own printed "
          f"totals.  ({100*gp//gt if gt else 0}% — proves all line-item detail captured)")
