"""
claims_anomaly.py — Claims Anomaly Detection Report
─────────────────────────────────────────────────────
Detects suspicious or data-quality issues in claims across DOMCOM products:
  1. Claims on lapsed/cancelled policies
  2. Early claims filed within 30 days of policy inception
  3. High-value claims (> 50,000)

All DB calls are wrapped in try/except because the claims table structure
varies across deployment environments.

Output: Excel workbook with 4 sheets + structured summary dict.
"""

from __future__ import annotations
import pandas as pd
from datetime import datetime

from ..db    import fetch_df
from ..utils import get_logger, Timer, bwp, report_path, DOMCOM_PRODUCTS, PRODUCT_NAMES

log = get_logger('claims_anomaly')

_PRODS = ','.join(str(x) for x in DOMCOM_PRODUCTS)

# ─────────────────────────────────────────────────────────────────────────────
# SQL QUERIES
# ─────────────────────────────────────────────────────────────────────────────

SQL_CLAIMS_LAPSED = """
SELECT
    c.id            AS claim_id,
    c.policy_id,
    c.claim_date,
    c.claim_amount,
    c.status        AS claim_status,
    p.policyNumber  AS policy_number,
    p.status        AS policy_status,
    p.product_id
FROM claims c
JOIN policies p ON p.id = c.policy_id
WHERE p.status = 0
  AND p.product_id IN ({prods})
  AND c.deleted_at IS NULL
LIMIT 500
""".format(prods=_PRODS)

SQL_EARLY_CLAIMS = """
SELECT
    c.id            AS claim_id,
    c.policy_id,
    c.claim_date,
    c.claim_amount,
    pa.effective_from,
    DATEDIFF(c.claim_date, pa.effective_from) AS days_from_inception,
    p.policyNumber  AS policy_number,
    p.product_id
FROM claims c
JOIN policies p ON p.id = c.policy_id
JOIN policy_actions pa ON pa.policy_id = p.id
    AND pa.status = 'ISSUED'
    AND pa.deleted_at IS NULL
WHERE p.product_id IN ({prods})
  AND DATEDIFF(c.claim_date, pa.effective_from) < 30
  AND DATEDIFF(c.claim_date, pa.effective_from) >= 0
  AND c.deleted_at IS NULL
LIMIT 500
""".format(prods=_PRODS)

SQL_HIGH_VALUE_CLAIMS = """
SELECT
    c.id            AS claim_id,
    c.policy_id,
    c.claim_amount,
    c.claim_date,
    c.status,
    p.policyNumber  AS policy_number,
    p.product_id,
    COALESCE(p.premium, 0) AS premium
FROM claims c
JOIN policies p ON p.id = c.policy_id
WHERE p.product_id IN ({prods})
  AND c.claim_amount > 50000
  AND c.deleted_at IS NULL
ORDER BY c.claim_amount DESC
LIMIT 500
""".format(prods=_PRODS)


# ─────────────────────────────────────────────────────────────────────────────
# RUN
# ─────────────────────────────────────────────────────────────────────────────

def run(**kwargs) -> dict:
    """
    Run all claims anomaly checks.

    All queries are wrapped individually in try/except as the claims table
    schema varies across deployment environments.

    Returns:
        dict with keys: summary, path, elapsed
    """
    log.info("=== Claims Anomaly Detection ===")

    counts: dict[str, int] = {}
    frames: dict[str, pd.DataFrame] = {}

    total = Timer()
    total.__enter__()

    # Claims on lapsed policies
    try:
        with Timer() as t:
            df_lapsed = fetch_df(SQL_CLAIMS_LAPSED)
        log.info(f"  [Claims on lapsed policies]: {len(df_lapsed):,} found ({t})")
    except Exception as exc:
        log.warning(f"  [Claims on lapsed] query failed: {exc}")
        df_lapsed = pd.DataFrame()
    counts['claims_on_lapsed'] = len(df_lapsed)
    frames['claims_on_lapsed'] = df_lapsed

    # Early claims (within 30 days of inception)
    try:
        with Timer() as t:
            df_early = fetch_df(SQL_EARLY_CLAIMS)
        log.info(f"  [Early claims (<30 days)]: {len(df_early):,} found ({t})")
    except Exception as exc:
        log.warning(f"  [Early claims] query failed: {exc}")
        df_early = pd.DataFrame()
    counts['early_claims'] = len(df_early)
    frames['early_claims'] = df_early

    # High-value claims
    try:
        with Timer() as t:
            df_hv = fetch_df(SQL_HIGH_VALUE_CLAIMS)
        log.info(f"  [High-value claims >50k]: {len(df_hv):,} found ({t})")
    except Exception as exc:
        log.warning(f"  [High-value claims] query failed: {exc}")
        df_hv = pd.DataFrame()
    counts['high_value_claims'] = len(df_hv)
    frames['high_value_claims'] = df_hv

    total.__exit__(None, None, None)

    # ── Build Excel ──────────────────────────────────────────────────────────
    out_path = report_path('claims_anomaly')
    try:
        with pd.ExcelWriter(str(out_path), engine='openpyxl') as xls:
            _sheet_summary(xls, counts, frames)
            for key, sheet_name in [
                ('claims_on_lapsed',  'Claims on Lapsed Policies'),
                ('early_claims',      'Early Claims'),
                ('high_value_claims', 'High Value Claims'),
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
        'report':            'Claims Anomaly Detection',
        'claims_on_lapsed':  counts.get('claims_on_lapsed', 0),
        'early_claims':      counts.get('early_claims', 0),
        'high_value_claims': counts.get('high_value_claims', 0),
        'total_anomalies':   sum(counts.values()),
        'generated_at':      datetime.now().isoformat(),
        'output_file':       str(out_path) if out_path else None,
    }

    if not frames['high_value_claims'].empty:
        df_hv = frames['high_value_claims']
        hv_total = float(df_hv['claim_amount'].sum()) if 'claim_amount' in df_hv.columns else 0
        summary['high_value_total_amount'] = round(hv_total, 2)
        log.info(f"High-value claims total: {bwp(hv_total)}")

    log.info(f"Total anomalies: {summary['total_anomalies']:,} | Elapsed: {total}")
    return {'summary': summary, 'path': str(out_path) if out_path else None, 'elapsed': str(total)}


# ─────────────────────────────────────────────────────────────────────────────
# SHEET BUILDERS
# ─────────────────────────────────────────────────────────────────────────────

def _sheet_summary(xls, counts: dict, frames: dict):
    df_hv = frames.get('high_value_claims', pd.DataFrame())
    hv_total = round(float(df_hv['claim_amount'].sum()), 2) if not df_hv.empty and 'claim_amount' in df_hv.columns else 0

    rows = [
        ['CLAIMS ANOMALY DETECTION REPORT', ''],
        ['Generated', datetime.now().strftime('%Y-%m-%d %H:%M')],
        [''],
        ['CHECK', 'COUNT'],
        ['Claims on lapsed/cancelled policies',     counts.get('claims_on_lapsed', 0)],
        ['Early claims (within 30 days of start)',  counts.get('early_claims', 0)],
        ['High-value claims (>50,000)',             counts.get('high_value_claims', 0)],
        [''],
        ['Total high-value claim amount',           hv_total],
        ['TOTAL ANOMALIES',                         sum(counts.values())],
    ]
    pd.DataFrame(rows).to_excel(xls, sheet_name='Summary', index=False, header=False)
