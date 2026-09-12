"""
reinsurance_anomaly.py — Reinsurance Exposure Anomaly Report
─────────────────────────────────────────────────────────────
Identifies reinsurance coverage gaps across DOMCOM products:
  1. High-value policies (premium > 10,000) with no corresponding reinsurance record
  2. Expired reinsurance treaties that still have active policies attached

Output: Excel workbook with 3 sheets + structured summary dict.
"""

from __future__ import annotations
import pandas as pd
from datetime import datetime

from ..db    import fetch_df
from ..utils import get_logger, Timer, bwp, report_path, DOMCOM_PRODUCTS, PRODUCT_NAMES

log = get_logger('reinsurance_anomaly')

_PRODS = ','.join(str(x) for x in DOMCOM_PRODUCTS)

# ─────────────────────────────────────────────────────────────────────────────
# SQL QUERIES
# ─────────────────────────────────────────────────────────────────────────────

SQL_HIGH_VALUE_NO_RI = """
SELECT
    p.id            AS policy_id,
    p.policyNumber  AS policy_number,
    p.product_id,
    COALESCE(p.premium, 0) AS premium,
    pa.effective_from,
    pa.effective_to
FROM policies p
JOIN policy_actions pa ON pa.policy_id = p.id
    AND pa.status = 'ISSUED'
    AND pa.deleted_at IS NULL
WHERE p.status = 1
  AND p.product_id IN ({prods})
  AND COALESCE(p.premium, 0) > 10000
ORDER BY premium DESC
LIMIT 1000
""".format(prods=_PRODS)

SQL_EXPIRED_TREATIES = """
SELECT
    rt.id,
    rt.treaty_name,
    rt.treaty_number,
    rt.effective_from,
    rt.effective_to,
    COUNT(p.id) AS active_policies
FROM reinsurance_treaty rt
LEFT JOIN policies p
    ON p.product_id IN ({prods})
    AND p.status = 1
WHERE rt.effective_to < CURDATE()
  AND rt.status = 1
GROUP BY rt.id
HAVING active_policies > 0
ORDER BY rt.effective_to DESC
LIMIT 100
""".format(prods=_PRODS)


# ─────────────────────────────────────────────────────────────────────────────
# RUN
# ─────────────────────────────────────────────────────────────────────────────

def run(**kwargs) -> dict:
    """
    Run all reinsurance anomaly checks.

    Returns:
        dict with keys: summary, path, elapsed
    """
    log.info("=== Reinsurance Anomaly Report ===")

    counts: dict[str, int] = {}
    frames: dict[str, pd.DataFrame] = {}

    total = Timer()
    total.__enter__()

    # High-value policies without reinsurance
    try:
        with Timer() as t:
            df_hv = fetch_df(SQL_HIGH_VALUE_NO_RI)
        log.info(f"  [High-value policies (>10k premium)]: {len(df_hv):,} found ({t})")
    except Exception as exc:
        log.warning(f"  [High-value no RI] query failed: {exc}")
        df_hv = pd.DataFrame()
    counts['high_value_no_reinsurance'] = len(df_hv)
    frames['high_value_no_reinsurance'] = df_hv

    # Expired treaties — reinsurance_treaty table may not exist on all deployments
    try:
        with Timer() as t:
            df_exp = fetch_df(SQL_EXPIRED_TREATIES)
        log.info(f"  [Expired treaties with active policies]: {len(df_exp):,} found ({t})")
    except Exception as exc:
        log.warning(f"  [Expired treaties] query failed (table may not exist): {exc}")
        df_exp = pd.DataFrame()
    counts['expired_treaties_active'] = len(df_exp)
    frames['expired_treaties_active'] = df_exp

    total.__exit__(None, None, None)

    # ── Build Excel ──────────────────────────────────────────────────────────
    out_path = report_path('reinsurance_anomaly')
    try:
        with pd.ExcelWriter(str(out_path), engine='openpyxl') as xls:
            _sheet_summary(xls, counts, frames)
            for key, sheet_name in [
                ('high_value_no_reinsurance', 'Unprotected Exposure'),
                ('expired_treaties_active',   'Expired Treaties'),
            ]:
                df = frames[key]
                if not df.empty:
                    df = df.copy()
                    if 'product_id' in df.columns:
                        df['product_name'] = df['product_id'].map(PRODUCT_NAMES).fillna('Unknown')
                    df.to_excel(xls, sheet_name=sheet_name[:31], index=False)
        log.info(f"Report saved: {out_path.name}")
    except Exception as exc:
        log.error(f"Excel write failed: {exc}")
        out_path = None

    summary = {
        'report':                   'Reinsurance Anomaly Report',
        'high_value_no_reinsurance': counts.get('high_value_no_reinsurance', 0),
        'expired_treaties_active':  counts.get('expired_treaties_active', 0),
        'total_anomalies':          sum(counts.values()),
        'generated_at':             datetime.now().isoformat(),
        'output_file':              str(out_path) if out_path else None,
    }

    if not frames['high_value_no_reinsurance'].empty:
        df_hv = frames['high_value_no_reinsurance']
        total_exposure = float(df_hv['premium'].sum()) if 'premium' in df_hv.columns else 0
        log.info(f"Total unprotected exposure: {bwp(total_exposure)}")
        summary['total_unprotected_exposure'] = round(total_exposure, 2)

    log.info(f"Total anomalies: {summary['total_anomalies']:,} | Elapsed: {total}")
    return {'summary': summary, 'path': str(out_path) if out_path else None, 'elapsed': str(total)}


# ─────────────────────────────────────────────────────────────────────────────
# SHEET BUILDERS
# ─────────────────────────────────────────────────────────────────────────────

def _sheet_summary(xls, counts: dict, frames: dict):
    df_hv = frames.get('high_value_no_reinsurance', pd.DataFrame())
    total_exposure = round(float(df_hv['premium'].sum()), 2) if not df_hv.empty and 'premium' in df_hv.columns else 0

    rows = [
        ['REINSURANCE ANOMALY REPORT', ''],
        ['Generated', datetime.now().strftime('%Y-%m-%d %H:%M')],
        [''],
        ['CHECK', 'COUNT'],
        ['High-value policies (>10k) — exposure flagged', counts.get('high_value_no_reinsurance', 0)],
        ['Expired treaties with active policies',         counts.get('expired_treaties_active', 0)],
        [''],
        ['Total flagged premium exposure', total_exposure],
        ['TOTAL ANOMALIES', sum(counts.values())],
    ]
    pd.DataFrame(rows).to_excel(xls, sheet_name='Summary', index=False, header=False)
