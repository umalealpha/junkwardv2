"""
collections_report.py — Full Collections Analysis Report
──────────────────────────────────────────────────────────
Analyses successful payment collections across DOMCOM products over the
trailing 12 months and highlights failed payments from the last 3 months:
  • Summary totals
  • Collections by product
  • Collections by agency
  • Collections by payment method
  • Monthly trend (pivot)
  • Failed payments detail

Output: Excel workbook with 6 sheets + structured summary dict.
"""

from __future__ import annotations
import pandas as pd
from datetime import datetime

from ..db    import fetch_df
from ..utils import get_logger, Timer, bwp, report_path, DOMCOM_PRODUCTS, PRODUCT_NAMES

log = get_logger('collections_report')

_PRODS = ','.join(str(x) for x in DOMCOM_PRODUCTS)

# ─────────────────────────────────────────────────────────────────────────────
# SQL QUERIES
# ─────────────────────────────────────────────────────────────────────────────

SQL_COLLECTIONS = """
SELECT
    tl.id,
    tl.policy_id,
    tl.amount,
    tl.payment_method,
    tl.created_at,
    DATE_FORMAT(tl.created_at, '%%Y-%%m') AS month,
    p.product_id,
    p.premium_freq,
    ag.name                               AS agency_name,
    CONCAT(u.firstName, ' ', u.lastName)  AS agent_name
FROM transaction_log tl
JOIN policies p ON p.id = tl.policy_id
LEFT JOIN agencies ag ON ag.id = p.agency_id
LEFT JOIN users u ON u.id = p.agent_id
WHERE tl.status = 'success'
  AND p.product_id IN ({prods})
  AND tl.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
ORDER BY tl.created_at
""".format(prods=_PRODS)

SQL_FAILED_PAYMENTS = """
SELECT
    tl.id,
    tl.policy_id,
    tl.amount,
    tl.payment_method,
    tl.failure_reason,
    tl.created_at,
    p.product_id,
    p.policyNumber AS policy_number
FROM transaction_log tl
JOIN policies p ON p.id = tl.policy_id
WHERE tl.status = 'failed'
  AND p.product_id IN ({prods})
  AND tl.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
LIMIT 2000
""".format(prods=_PRODS)


# ─────────────────────────────────────────────────────────────────────────────
# RUN
# ─────────────────────────────────────────────────────────────────────────────

def run(**kwargs) -> dict:
    """
    Generate the full collections analysis report.

    Returns:
        dict with keys: summary, path, elapsed
    """
    log.info("=== Collections Report ===")

    total = Timer()
    total.__enter__()

    # Successful collections
    try:
        with Timer() as t:
            df = fetch_df(SQL_COLLECTIONS)
        log.info(f"  [Collections data]: {len(df):,} transactions ({t})")
    except Exception as exc:
        log.warning(f"  [Collections] query failed: {exc}")
        df = pd.DataFrame()

    # Failed payments
    try:
        with Timer() as t:
            df_fail = fetch_df(SQL_FAILED_PAYMENTS)
        log.info(f"  [Failed payments]: {len(df_fail):,} records ({t})")
    except Exception as exc:
        log.warning(f"  [Failed payments] query failed: {exc}")
        df_fail = pd.DataFrame()

    total.__exit__(None, None, None)

    # ── Compute pivot views ──────────────────────────────────────────────────
    by_product = pd.DataFrame()
    by_agency  = pd.DataFrame()
    by_method  = pd.DataFrame()
    monthly    = pd.DataFrame()

    if not df.empty:
        df['amount'] = pd.to_numeric(df['amount'], errors='coerce').fillna(0)
        if 'product_id' in df.columns:
            df['product_name'] = df['product_id'].map(PRODUCT_NAMES).fillna('Unknown')

        by_product = df.groupby('product_name').agg(
            transactions=('id', 'count'),
            total_amount=('amount', 'sum'),
        ).round(2).reset_index()
        by_product.columns = ['Product', 'Transactions', 'Total Amount']

        by_agency = df.groupby('agency_name').agg(
            transactions=('id', 'count'),
            total_amount=('amount', 'sum'),
        ).round(2).reset_index().sort_values('total_amount', ascending=False)
        by_agency.columns = ['Agency', 'Transactions', 'Total Amount']

        by_method = df.groupby('payment_method').agg(
            transactions=('id', 'count'),
            total_amount=('amount', 'sum'),
        ).round(2).reset_index().sort_values('total_amount', ascending=False)
        by_method.columns = ['Payment Method', 'Transactions', 'Total Amount']

        monthly = df.groupby('month').agg(
            transactions=('id', 'count'),
            total_amount=('amount', 'sum'),
        ).round(2).reset_index()
        monthly.columns = ['Month', 'Transactions', 'Total Amount']

    # ── Summary stats ────────────────────────────────────────────────────────
    total_collections = int(len(df)) if not df.empty else 0
    total_amount      = round(float(df['amount'].sum()), 2) if not df.empty else 0.0
    failed_count      = int(len(df_fail)) if not df_fail.empty else 0
    failed_amount     = round(float(df_fail['amount'].sum()), 2) if not df_fail.empty and 'amount' in df_fail.columns else 0.0

    top_payment_method = ''
    if not by_method.empty:
        top_payment_method = str(by_method.iloc[0]['Payment Method'])

    # ── Build Excel ──────────────────────────────────────────────────────────
    out_path = report_path('collections_report')
    try:
        with pd.ExcelWriter(str(out_path), engine='openpyxl') as xls:
            _sheet_summary(xls, total_collections, total_amount, failed_count, failed_amount, top_payment_method)

            if not by_product.empty:
                by_product.to_excel(xls, sheet_name='By Product', index=False)
            if not by_agency.empty:
                by_agency.to_excel(xls, sheet_name='By Agency', index=False)
            if not by_method.empty:
                by_method.to_excel(xls, sheet_name='By Payment Method', index=False)
            if not monthly.empty:
                monthly.to_excel(xls, sheet_name='Monthly Trend', index=False)
            if not df_fail.empty:
                df_fail_out = df_fail.copy()
                if 'product_id' in df_fail_out.columns:
                    df_fail_out['product_name'] = df_fail_out['product_id'].map(PRODUCT_NAMES).fillna('Unknown')
                df_fail_out.to_excel(xls, sheet_name='Failed Payments', index=False)

        log.info(f"Report saved: {out_path.name}")
    except Exception as exc:
        log.error(f"Excel write failed: {exc}")
        out_path = None

    summary = {
        'report':             'Collections Report',
        'total_collections':  total_collections,
        'total_amount':       total_amount,
        'failed_count':       failed_count,
        'failed_amount':      failed_amount,
        'top_payment_method': top_payment_method,
        'generated_at':       datetime.now().isoformat(),
        'output_file':        str(out_path) if out_path else None,
    }

    log.info(f"Collections: {total_collections:,} | Amount: {bwp(total_amount)} | Failed: {failed_count:,} | Elapsed: {total}")
    return {'summary': summary, 'path': str(out_path) if out_path else None, 'elapsed': str(total)}


# ─────────────────────────────────────────────────────────────────────────────
# SHEET BUILDERS
# ─────────────────────────────────────────────────────────────────────────────

def _sheet_summary(xls, total_collections, total_amount, failed_count, failed_amount, top_payment_method):
    rows = [
        ['COLLECTIONS REPORT', ''],
        ['Generated', datetime.now().strftime('%Y-%m-%d %H:%M')],
        ['Period', 'Last 12 months (collections) / Last 3 months (failed)'],
        [''],
        ['METRIC', 'VALUE'],
        ['Total Successful Collections', total_collections],
        ['Total Amount Collected',       total_amount],
        ['Failed Payment Count',         failed_count],
        ['Failed Payment Amount',        failed_amount],
        ['Top Payment Method',           top_payment_method],
    ]
    pd.DataFrame(rows).to_excel(xls, sheet_name='Summary', index=False, header=False)
