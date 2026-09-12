"""
payment_anomaly.py — Payment Anomaly Detection Report
──────────────────────────────────────────────────────
Detects payment-related anomalies across DOMCOM products:
  1. Unactivated payments — successful transactions where the policy is inactive
  2. Duplicate transactions — same transaction_id appearing more than once
  3. Realpay contracts on cancelled policies

Output: Excel workbook with 4 sheets + structured summary dict.
"""

from __future__ import annotations
import pandas as pd
from datetime import datetime

from ..db    import fetch_df
from ..utils import get_logger, Timer, report_path, DOMCOM_PRODUCTS, PRODUCT_NAMES

log = get_logger('payment_anomaly')

_PRODS = ','.join(str(x) for x in DOMCOM_PRODUCTS)

# ─────────────────────────────────────────────────────────────────────────────
# SQL QUERIES
# ─────────────────────────────────────────────────────────────────────────────

SQL_UNACTIVATED_PAYMENTS = """
SELECT
    tl.id,
    tl.policy_id,
    tl.transaction_id,
    tl.amount,
    tl.payment_method,
    tl.created_at,
    p.policyNumber  AS policy_number,
    p.status        AS policy_status,
    p.product_id
FROM transaction_log tl
JOIN policies p ON p.id = tl.policy_id
WHERE tl.status = 'success'
  AND p.status = 0
  AND p.product_id IN ({prods})
  AND p.deleted_at IS NULL
  AND tl.created_at > DATE_SUB(NOW(), INTERVAL 90 DAY)
ORDER BY tl.created_at DESC
LIMIT 1000
""".format(prods=_PRODS)

SQL_DUPLICATE_TRANSACTIONS = """
SELECT
    transaction_id,
    COUNT(*) AS cnt,
    SUM(amount) AS total_amount,
    GROUP_CONCAT(DISTINCT policy_id ORDER BY policy_id) AS policy_ids,
    MAX(created_at) AS last_seen
FROM transaction_log
WHERE transaction_id IS NOT NULL
  AND status = 'success'
  AND created_at > DATE_SUB(NOW(), INTERVAL 90 DAY)
GROUP BY transaction_id
HAVING cnt > 1
ORDER BY cnt DESC
LIMIT 500
"""

SQL_REALPAY_CANCELLED = """
SELECT
    rc.id,
    rc.policy_id,
    rc.contract_id,
    rc.status  AS contract_status,
    rc.created_at,
    p.policyNumber AS policy_number,
    p.status       AS policy_status,
    p.product_id
FROM realpay_contracts rc
JOIN policies p ON p.id = rc.policy_id
WHERE rc.status = 'active'
  AND p.status = 0
  AND p.product_id IN ({prods})
  AND p.deleted_at IS NULL
LIMIT 500
""".format(prods=_PRODS)


# ─────────────────────────────────────────────────────────────────────────────
# RUN
# ─────────────────────────────────────────────────────────────────────────────

def run(**kwargs) -> dict:
    """
    Run all payment anomaly checks.

    Returns:
        dict with keys: summary, path, elapsed
    """
    log.info("=== Payment Anomaly Detection ===")

    counts: dict[str, int] = {}
    frames: dict[str, pd.DataFrame] = {}

    total = Timer()
    total.__enter__()

    # Unactivated payments
    try:
        with Timer() as t:
            df_unact = fetch_df(SQL_UNACTIVATED_PAYMENTS)
        log.info(f"  [Unactivated payments]: {len(df_unact):,} found ({t})")
    except Exception as exc:
        log.warning(f"  [Unactivated payments] query failed: {exc}")
        df_unact = pd.DataFrame()
    counts['unactivated_payments'] = len(df_unact)
    frames['unactivated_payments'] = df_unact

    # Duplicate transactions
    try:
        with Timer() as t:
            df_dupl = fetch_df(SQL_DUPLICATE_TRANSACTIONS)
        log.info(f"  [Duplicate transactions]: {len(df_dupl):,} found ({t})")
    except Exception as exc:
        log.warning(f"  [Duplicate transactions] query failed: {exc}")
        df_dupl = pd.DataFrame()
    counts['duplicate_transactions'] = len(df_dupl)
    frames['duplicate_transactions'] = df_dupl

    # Realpay on cancelled — table may not exist on all deployments
    try:
        with Timer() as t:
            df_rp = fetch_df(SQL_REALPAY_CANCELLED)
        log.info(f"  [Realpay on cancelled]: {len(df_rp):,} found ({t})")
    except Exception as exc:
        log.warning(f"  [Realpay on cancelled] query failed (table may not exist): {exc}")
        df_rp = pd.DataFrame()
    counts['realpay_on_cancelled'] = len(df_rp)
    frames['realpay_on_cancelled'] = df_rp

    total.__exit__(None, None, None)

    # ── Build Excel ──────────────────────────────────────────────────────────
    out_path = report_path('payment_anomaly')
    try:
        with pd.ExcelWriter(str(out_path), engine='openpyxl') as xls:
            _sheet_summary(xls, counts)
            for key, sheet_name in [
                ('unactivated_payments',   'Unactivated Payments'),
                ('duplicate_transactions', 'Duplicate Transactions'),
                ('realpay_on_cancelled',   'Realpay on Cancelled'),
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
        'report':                'Payment Anomaly Detection',
        'unactivated_payments':  counts.get('unactivated_payments', 0),
        'duplicate_transactions': counts.get('duplicate_transactions', 0),
        'realpay_on_cancelled':  counts.get('realpay_on_cancelled', 0),
        'total_anomalies':       sum(counts.values()),
        'generated_at':          datetime.now().isoformat(),
        'output_file':           str(out_path) if out_path else None,
    }

    log.info(f"Total anomalies: {summary['total_anomalies']:,} | Elapsed: {total}")
    return {'summary': summary, 'path': str(out_path) if out_path else None, 'elapsed': str(total)}


# ─────────────────────────────────────────────────────────────────────────────
# SHEET BUILDERS
# ─────────────────────────────────────────────────────────────────────────────

def _sheet_summary(xls, counts: dict):
    rows = [
        ['PAYMENT ANOMALY DETECTION REPORT', ''],
        ['Generated', datetime.now().strftime('%Y-%m-%d %H:%M')],
        [''],
        ['CHECK', 'COUNT'],
        ['Successful payments on inactive policies', counts.get('unactivated_payments', 0)],
        ['Duplicate transaction IDs',               counts.get('duplicate_transactions', 0)],
        ['Active Realpay contracts on cancelled',   counts.get('realpay_on_cancelled', 0)],
        [''],
        ['TOTAL ANOMALIES', sum(counts.values())],
    ]
    pd.DataFrame(rows).to_excel(xls, sheet_name='Summary', index=False, header=False)
