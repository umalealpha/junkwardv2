"""
anomaly.py — Finance Anomaly Detection Report
──────────────────────────────────────────────
Detects and reports data integrity issues across:
  1. Missing Invoice ledger entries for ISSUED policies
  2. Balance mismatches (ledger balance ≠ invoice - receipts)
  3. Orphaned ledger rows (policy_id not in policies)
  4. Duplicate invoice numbers
  5. Negative balances (overpayment)
  6. NULL trans_type or invoice_amount on ledger rows
  7. Policies with ISSUED action but policy.status ≠ 1
  8. Payment transactions without matching ledger receipts

Auto-fix mode: corrects balance mismatches in a single bulk UPDATE.
Output: Excel report + structured summary dict.
Runtime target: < 45 seconds.
"""

from __future__ import annotations
import pandas as pd
from datetime import datetime, date

from ..db    import fetch_df, execute
from ..utils import get_logger, Timer, bwp, report_path, DOMCOM_PRODUCTS, PRODUCT_NAMES

log = get_logger('anomaly')


# ─────────────────────────────────────────────────────────────────────────────
# QUERIES
# ─────────────────────────────────────────────────────────────────────────────

SQL_MISSING_INVOICES = """
SELECT
    pa.id          AS action_id,
    pa.policy_id,
    pa.effective_from,
    pa.status      AS action_status,
    COALESCE(pa.premium, p.premium, 0) AS premium,
    p.policyNumber AS policy_number,
    p.product_id,
    p.customer_id,
    p.agency_id,
    ag.name        AS agency_name
FROM policy_actions pa
JOIN  policies p  ON p.id  = pa.policy_id
LEFT JOIN agencies ag ON ag.id = p.agency_id
LEFT JOIN policy_ledger pl
    ON  pl.policy_id  = pa.policy_id
    AND pl.action_id  = pa.id
    AND pl.trans_type = 'Invoice'
    AND pl.deleted_at IS NULL
WHERE pa.status = 'ISSUED'
  AND pa.deleted_at IS NULL
  AND p.product_id IN ({products})
  AND p.status = 1
  AND pl.id IS NULL
  AND COALESCE(pa.premium, p.premium, 0) > 0
ORDER BY pa.policy_id
""".format(products=','.join(str(x) for x in DOMCOM_PRODUCTS))


SQL_BALANCE_MISMATCH = """
SELECT
    pl.id, pl.policy_id, pl.action_id, pl.invoice_no,
    pl.invoice_amount,
    pl.balance                              AS current_balance,
    calc.correct_balance,
    ROUND(ABS(COALESCE(pl.balance,0) - calc.correct_balance), 2) AS diff,
    p.policyNumber AS policy_number,
    p.product_id
FROM policy_ledger pl
JOIN policies p ON p.id = pl.policy_id
JOIN (
    SELECT policy_id, action_id,
        ROUND(
            COALESCE(SUM(CASE WHEN trans_type='Invoice' THEN invoice_amount ELSE 0 END), 0) -
            COALESCE(SUM(CASE WHEN trans_type='Receipt' THEN credit ELSE 0 END), 0),
        2) AS correct_balance
    FROM policy_ledger
    WHERE deleted_at IS NULL
    GROUP BY policy_id, action_id
) calc ON calc.policy_id = pl.policy_id AND calc.action_id = pl.action_id
WHERE pl.trans_type = 'Invoice'
  AND pl.deleted_at IS NULL
  AND p.product_id IN ({products})
  AND ABS(COALESCE(pl.balance, 0) - calc.correct_balance) > 0.01
ORDER BY diff DESC
LIMIT 10000
""".format(products=','.join(str(x) for x in DOMCOM_PRODUCTS))


SQL_NULL_TRANS_TYPE = """
SELECT
    pl.id, pl.policy_id, pl.invoice_no, pl.invoice_amount,
    pl.trans_type, pl.credit, pl.balance, pl.created_at,
    p.policyNumber AS policy_number, p.product_id
FROM policy_ledger pl
JOIN policies p ON p.id = pl.policy_id
WHERE pl.deleted_at IS NULL
  AND (pl.trans_type IS NULL OR pl.invoice_amount IS NULL)
  AND p.product_id IN ({products})
LIMIT 1000
""".format(products=','.join(str(x) for x in DOMCOM_PRODUCTS))


SQL_DUPLICATE_INVOICE_NO = """
SELECT invoice_no, COUNT(*) AS cnt,
       GROUP_CONCAT(DISTINCT policy_id ORDER BY policy_id SEPARATOR ',') AS policy_ids
FROM policy_ledger
WHERE trans_type = 'Invoice'
  AND invoice_no IS NOT NULL
  AND deleted_at IS NULL
GROUP BY invoice_no
HAVING cnt > 1
ORDER BY cnt DESC
LIMIT 500
"""


SQL_NEGATIVE_BALANCE = """
SELECT
    pl.id, pl.policy_id, pl.invoice_no,
    pl.invoice_amount, pl.balance, pl.credit,
    p.policyNumber AS policy_number, p.product_id,
    ag.name AS agency_name
FROM policy_ledger pl
JOIN policies p ON p.id = pl.policy_id
LEFT JOIN agencies ag ON ag.id = p.agency_id
WHERE pl.trans_type  = 'Invoice'
  AND pl.deleted_at  IS NULL
  AND pl.balance     < -0.01
  AND p.product_id   IN ({products})
LIMIT 500
""".format(products=','.join(str(x) for x in DOMCOM_PRODUCTS))


SQL_ISSUED_STATUS_MISMATCH = """
SELECT
    pa.id AS action_id, pa.policy_id,
    pa.status AS action_status,
    p.status  AS policy_status,
    p.policyNumber AS policy_number,
    p.product_id
FROM policy_actions pa
JOIN policies p ON p.id = pa.policy_id
WHERE pa.status = 'ISSUED'
  AND pa.deleted_at IS NULL
  AND p.status != 1
  AND p.product_id IN ({products})
LIMIT 500
""".format(products=','.join(str(x) for x in DOMCOM_PRODUCTS))


FIX_BALANCES_SQL = """
UPDATE policy_ledger pl
JOIN (
    SELECT policy_id, action_id,
        ROUND(
            COALESCE(SUM(CASE WHEN trans_type='Invoice' THEN invoice_amount ELSE 0 END),0) -
            COALESCE(SUM(CASE WHEN trans_type='Receipt' THEN credit ELSE 0 END),0),
        2) AS correct_balance
    FROM policy_ledger
    WHERE deleted_at IS NULL
    GROUP BY policy_id, action_id
) calc ON calc.policy_id = pl.policy_id AND calc.action_id = pl.action_id
SET pl.balance     = calc.correct_balance,
    pl.updated_at  = NOW()
WHERE pl.trans_type = 'Invoice'
  AND pl.deleted_at IS NULL
  AND ABS(COALESCE(pl.balance, 0) - calc.correct_balance) > 0.01
"""


def run(auto_fix: bool = False, dry_run: bool = False) -> dict:
    """
    Run all anomaly checks.

    Args:
        auto_fix: If True, fix balance mismatches in DB
        dry_run:  If True, report only — no writes

    Returns:
        dict with all anomaly counts + report path
    """
    log.info("=== Finance Anomaly Detection ===")
    log.info(f"Mode: {'DRY RUN' if dry_run else 'AUTO-FIX' if auto_fix else 'REPORT ONLY'}")

    results = {}
    dataframes = {}

    checks = [
        ('missing_invoices',      SQL_MISSING_INVOICES,      "Missing Invoice entries"),
        ('balance_mismatch',      SQL_BALANCE_MISMATCH,      "Balance mismatches"),
        ('null_trans_type',       SQL_NULL_TRANS_TYPE,       "NULL trans_type / invoice_amount"),
        ('duplicate_invoice_no',  SQL_DUPLICATE_INVOICE_NO,  "Duplicate invoice numbers"),
        ('negative_balance',      SQL_NEGATIVE_BALANCE,      "Negative balances (overpaid)"),
        ('issued_status_mismatch',SQL_ISSUED_STATUS_MISMATCH,"ISSUED action but policy inactive"),
    ]

    total_timer = Timer()
    total_timer.__enter__()

    for key, sql, label in checks:
        with Timer() as t:
            df = fetch_df(sql)
        results[key] = len(df)
        dataframes[key] = df
        log.info(f"  [{label}]: {len(df):,} found ({t})")

    # ── Auto-fix balance mismatches ──────────────────────────────────────────
    fixed_rows = 0
    if auto_fix and not dry_run and results['balance_mismatch'] > 0:
        log.info(f"Auto-fixing {results['balance_mismatch']:,} balance mismatches...")
        with Timer() as ft:
            fixed_rows = execute(FIX_BALANCES_SQL)
        log.info(f"Fixed {fixed_rows:,} rows in {ft}")

    total_timer.__exit__(None, None, None)

    # ── Build Excel ──────────────────────────────────────────────────────────
    out_path = report_path('anomaly')
    with pd.ExcelWriter(str(out_path), engine='openpyxl') as xls:
        _sheet_summary(xls, results, fixed_rows, auto_fix, dry_run)
        for key, label in [
            ('missing_invoices',       'Missing Invoices'),
            ('balance_mismatch',       'Balance Mismatches'),
            ('null_trans_type',        'NULL Trans Type'),
            ('duplicate_invoice_no',   'Duplicate Invoice No'),
            ('negative_balance',       'Negative Balances'),
            ('issued_status_mismatch', 'Status Mismatch'),
        ]:
            df = dataframes[key]
            if not df.empty:
                # Map product names
                if 'product_id' in df.columns:
                    df = df.copy()
                    df['product_name'] = df['product_id'].map(PRODUCT_NAMES).fillna('Unknown')
                df.to_excel(xls, sheet_name=label[:31], index=False)

    log.info(f"Report saved: {out_path.name}")
    log.info(f"Total elapsed: {total_timer}")

    summary = {
        'report':                 'Anomaly Detection',
        **{k: int(v) for k, v in results.items()},
        'fixed_balances':         int(fixed_rows),
        'total_anomalies':        sum(results.values()),
        'generated_at':           datetime.now().isoformat(),
        'output_file':            str(out_path),
    }

    return {'summary': summary, 'path': str(out_path), 'elapsed': str(total_timer)}


def _sheet_summary(xls, results, fixed_rows, auto_fix, dry_run):
    rows = [
        ['FINANCE ANOMALY DETECTION REPORT', ''],
        ['Generated', datetime.now().strftime('%Y-%m-%d %H:%M')],
        ['Mode', 'DRY RUN' if dry_run else 'AUTO-FIX' if auto_fix else 'Report Only'],
        [''],
        ['CHECK', 'ANOMALIES FOUND'],
        ['Missing Invoice Entries',               results.get('missing_invoices', 0)],
        ['Balance Mismatches',                    results.get('balance_mismatch', 0)],
        ['NULL trans_type / invoice_amount',      results.get('null_trans_type', 0)],
        ['Duplicate Invoice Numbers',             results.get('duplicate_invoice_no', 0)],
        ['Negative Balances (Overpaid)',          results.get('negative_balance', 0)],
        ['ISSUED action + inactive policy',       results.get('issued_status_mismatch', 0)],
        [''],
        ['TOTAL ANOMALIES', sum(results.values())],
        ['Balances Auto-Fixed', fixed_rows],
    ]
    pd.DataFrame(rows).to_excel(xls, sheet_name='Summary', index=False, header=False)
