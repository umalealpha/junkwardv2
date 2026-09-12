"""
ageing.py — Debtors Ageing Report (Insurance Finance Grade)
─────────────────────────────────────────────────────────────
Standard insurance ageing buckets:
  0-30 days | 31-60 days | 61-90 days | 91-120 days | 120+ days

Based on: invoice_date on policy_ledger (trans_type='Invoice')
Outstanding = invoice_amount - SUM(credits received)

Output: Excel workbook with pivot summaries + full detail
Runtime target: < 30 seconds
"""

from __future__ import annotations
import pandas as pd
import numpy as np
from datetime import datetime, date

from ..db    import fetch_df
from ..utils import get_logger, Timer, bwp, safe_float, report_path, DOMCOM_PRODUCTS, PRODUCT_NAMES

log = get_logger('ageing')

SQL_AGEING = """
SELECT
    pl.id                        AS ledger_id,
    pl.policy_id,
    pl.action_id,
    pl.customer_id,
    pl.trans_type,
    pl.invoice_no,
    pl.invoice_date,
    COALESCE(pl.invoice_amount, 0)  AS invoice_amount,
    COALESCE(pl.balance, 0)         AS balance,
    COALESCE(pl.credit, 0)          AS credit_received,
    pl.status                    AS ledger_status,
    p.policyNumber               AS policy_number,
    p.product_id,
    p.premium_freq,
    p.status                     AS policy_status,
    ag.name                      AS agency_name,
    CONCAT(u.firstName,' ',u.lastName) AS agent_name,
    CONCAT(c.firstName,' ',c.lastName) AS customer_name
FROM policy_ledger pl
JOIN policies p   ON p.id  = pl.policy_id
LEFT JOIN agencies ag ON ag.id = p.agency_id
LEFT JOIN users    u  ON u.id  = p.agent_id
LEFT JOIN customer c  ON c.id  = pl.customer_id
WHERE pl.trans_type = 'Invoice'
  AND pl.deleted_at IS NULL
  AND pl.balance    > 0.01
  AND p.product_id IN ({products})
  AND p.status = 1
ORDER BY pl.invoice_date
""".format(products=','.join(str(x) for x in DOMCOM_PRODUCTS))


def _age_bucket(days: int) -> str:
    if   days <= 30:  return '0-30 days'
    elif days <= 60:  return '31-60 days'
    elif days <= 90:  return '61-90 days'
    elif days <= 120: return '91-120 days'
    else:             return '120+ days'

BUCKET_ORDER = ['0-30 days', '31-60 days', '61-90 days', '91-120 days', '120+ days']


def run(as_at: date | None = None) -> dict:
    """
    Generate the Debtors Ageing Report.

    Args:
        as_at: Reference date for ageing calculation (default today)

    Returns:
        dict with keys: summary, path, elapsed
    """
    as_at = as_at or date.today()
    log.info("=== Debtors Ageing Report ===")
    log.info(f"As at: {as_at}")

    with Timer() as t:
        df = fetch_df(SQL_AGEING)

    log.info(f"Fetched {len(df):,} outstanding invoice records in {t}")

    if df.empty:
        log.info("No outstanding balances — all accounts clear.")
        return {'summary': {'outstanding': 0}, 'path': None, 'elapsed': str(t)}

    # ── Type coercion ────────────────────────────────────────────────────────
    df['invoice_amount']  = pd.to_numeric(df['invoice_amount'],  errors='coerce').fillna(0)
    df['balance']         = pd.to_numeric(df['balance'],         errors='coerce').fillna(0)
    df['credit_received'] = pd.to_numeric(df['credit_received'], errors='coerce').fillna(0)
    df['invoice_date']    = pd.to_datetime(df['invoice_date'],   errors='coerce')
    df['product_name']    = df['product_id'].map(PRODUCT_NAMES).fillna('Unknown')

    # ── Ageing calculation ───────────────────────────────────────────────────
    as_at_ts   = pd.Timestamp(as_at)
    df['days_outstanding'] = (as_at_ts - df['invoice_date']).dt.days.fillna(0).astype(int)
    df['age_bucket']       = df['days_outstanding'].apply(_age_bucket)

    log.info(f"Outstanding debtors: {len(df):,} | "
             f"Total: {bwp(df['balance'].sum())}")

    # ── Build Excel ──────────────────────────────────────────────────────────
    out_path = report_path('ageing')

    with Timer() as tw:
        with pd.ExcelWriter(str(out_path), engine='openpyxl') as xls:
            _sheet_summary(xls, df, as_at)
            _sheet_by_bucket_product(xls, df)
            _sheet_by_agency(xls, df)
            _sheet_by_customer(xls, df)
            _sheet_full_detail(xls, df)

    log.info(f"Excel written in {tw}: {out_path.name}")

    # ── Summary ──────────────────────────────────────────────────────────────
    summary = {
        'report':               'Debtors Ageing',
        'as_at':                str(as_at),
        'total_debtors':        int(df['customer_id'].nunique()),
        'total_policies':       int(df['policy_id'].nunique()),
        'total_outstanding':    round(float(df['balance'].sum()), 2),
        'bucket_0_30':          round(float(df[df['age_bucket']=='0-30 days']['balance'].sum()), 2),
        'bucket_31_60':         round(float(df[df['age_bucket']=='31-60 days']['balance'].sum()), 2),
        'bucket_61_90':         round(float(df[df['age_bucket']=='61-90 days']['balance'].sum()), 2),
        'bucket_91_120':        round(float(df[df['age_bucket']=='91-120 days']['balance'].sum()), 2),
        'bucket_120_plus':      round(float(df[df['age_bucket']=='120+ days']['balance'].sum()), 2),
        'generated_at':         datetime.now().isoformat(),
        'output_file':          str(out_path),
    }

    log.info(f"0-30d: {bwp(summary['bucket_0_30'])} | "
             f"31-60d: {bwp(summary['bucket_31_60'])} | "
             f"61-90d: {bwp(summary['bucket_61_90'])} | "
             f"120+d: {bwp(summary['bucket_120_plus'])}")

    return {'summary': summary, 'path': str(out_path), 'elapsed': str(t)}


# ─────────────────────────────────────────────────────────────────────────────
# SHEET BUILDERS
# ─────────────────────────────────────────────────────────────────────────────

def _sheet_summary(xls, df, as_at):
    rows = [
        ['DEBTORS AGEING REPORT', ''],
        ['Generated', datetime.now().strftime('%Y-%m-%d %H:%M')],
        ['As At Date', str(as_at)],
        [''],
        ['SUMMARY BY AGE BUCKET', '', 'Policies', 'Balance Outstanding'],
    ]
    for bucket in BUCKET_ORDER:
        sub = df[df['age_bucket'] == bucket]
        rows.append([bucket, '', int(sub['policy_id'].nunique()), round(sub['balance'].sum(), 2)])
    rows.append(['TOTAL', '', int(df['policy_id'].nunique()), round(df['balance'].sum(), 2)])
    rows.append([''])
    rows.append(['SUMMARY BY PRODUCT', '', 'Invoices', 'Balance Outstanding'])
    for prod, sub in df.groupby('product_name'):
        rows.append([prod, '', len(sub), round(sub['balance'].sum(), 2)])
    pd.DataFrame(rows).to_excel(xls, sheet_name='Summary', index=False, header=False)


def _sheet_by_bucket_product(xls, df):
    pivot = df.pivot_table(
        values='balance',
        index='product_name',
        columns='age_bucket',
        aggfunc='sum',
        fill_value=0,
    ).round(2)
    # Ensure correct column order
    for b in BUCKET_ORDER:
        if b not in pivot.columns:
            pivot[b] = 0
    pivot = pivot[BUCKET_ORDER]
    pivot['TOTAL'] = pivot.sum(axis=1)
    pivot.reset_index().rename(columns={'product_name': 'Product'}).to_excel(
        xls, sheet_name='By Product & Bucket', index=False)


def _sheet_by_agency(xls, df):
    pivot = df.pivot_table(
        values='balance',
        index=['agency_name', 'product_name'],
        columns='age_bucket',
        aggfunc='sum',
        fill_value=0,
    ).round(2)
    for b in BUCKET_ORDER:
        if b not in pivot.columns:
            pivot[b] = 0
    pivot = pivot[BUCKET_ORDER]
    pivot['TOTAL'] = pivot.sum(axis=1)
    pivot.reset_index().rename(columns={'agency_name': 'Agency', 'product_name': 'Product'}).to_excel(
        xls, sheet_name='By Agency', index=False)


def _sheet_by_customer(xls, df):
    grp = df.groupby(['customer_name', 'agency_name']).agg(
        policies=('policy_id', 'nunique'),
        invoices=('ledger_id', 'count'),
        total_invoiced=('invoice_amount', 'sum'),
        total_outstanding=('balance', 'sum'),
        oldest_days=('days_outstanding', 'max'),
    ).round(2).reset_index()
    grp.columns = ['Customer', 'Agency', 'Policies', 'Invoices',
                   'Total Invoiced', 'Outstanding Balance', 'Oldest (Days)']
    grp.sort_values('Outstanding Balance', ascending=False).to_excel(
        xls, sheet_name='By Customer', index=False)


def _sheet_full_detail(xls, df):
    detail = df[[
        'invoice_no', 'policy_number', 'customer_name', 'product_name',
        'agency_name', 'agent_name', 'invoice_date', 'days_outstanding',
        'age_bucket', 'invoice_amount', 'credit_received', 'balance',
        'policy_status', 'ledger_status',
    ]].copy()
    detail.columns = [
        'Invoice No', 'Policy', 'Customer', 'Product',
        'Agency', 'Agent', 'Invoice Date', 'Days Outstanding',
        'Age Bucket', 'Invoice Amount', 'Credit Received', 'Balance',
        'Policy Status', 'Ledger Status',
    ]
    detail.sort_values(['Age Bucket', 'Balance'], ascending=[False, False]).to_excel(
        xls, sheet_name='Full Detail', index=False)
