"""
kyc_compliance_report.py — Customer KYC Compliance Report
──────────────────────────────────────────────────────────
Identifies KYC and customer data compliance issues across DOMCOM products:
  1. Active policies where customer KYC is not approved/verified or is missing
  2. Age violations — customers over 65 with active policies
  3. Duplicate omang (national ID) numbers in the customer table

Output: Excel workbook with 4 sheets + structured summary dict.
"""

from __future__ import annotations
import pandas as pd
from datetime import datetime

from ..db    import fetch_df
from ..utils import get_logger, Timer, report_path, DOMCOM_PRODUCTS, PRODUCT_NAMES

log = get_logger('kyc_compliance_report')

_PRODS = ','.join(str(x) for x in DOMCOM_PRODUCTS)

# ─────────────────────────────────────────────────────────────────────────────
# SQL QUERIES
# ─────────────────────────────────────────────────────────────────────────────

SQL_EXPIRED_KYC = """
SELECT
    p.id            AS policy_id,
    p.policyNumber  AS policy_number,
    p.product_id,
    p.customer_id,
    c.firstName,
    c.lastName,
    c.omang,
    c.kyc_status,
    c.kyc_expiry_date,
    ag.name         AS agency_name
FROM policies p
JOIN customer c ON c.id = p.customer_id
LEFT JOIN agencies ag ON ag.id = p.agency_id
WHERE p.status = 1
  AND p.product_id IN ({prods})
  AND p.deleted_at IS NULL
  AND (c.kyc_status NOT IN ('approved', 'verified') OR c.kyc_status IS NULL)
ORDER BY p.id
LIMIT 2000
""".format(prods=_PRODS)

SQL_AGE_VIOLATIONS = """
SELECT
    p.id            AS policy_id,
    p.policyNumber  AS policy_number,
    p.product_id,
    c.firstName,
    c.lastName,
    c.dob,
    TIMESTAMPDIFF(YEAR, c.dob, CURDATE()) AS age_years,
    ag.name         AS agency_name
FROM policies p
JOIN customer c ON c.id = p.customer_id
LEFT JOIN agencies ag ON ag.id = p.agency_id
WHERE p.status = 1
  AND p.product_id IN ({prods})
  AND c.dob IS NOT NULL
  AND TIMESTAMPDIFF(YEAR, c.dob, CURDATE()) > 65
ORDER BY age_years DESC
LIMIT 500
""".format(prods=_PRODS)

SQL_DUPLICATE_OMANG = """
SELECT
    omang,
    COUNT(*) AS cnt,
    GROUP_CONCAT(id ORDER BY id) AS customer_ids
FROM customer
WHERE omang IS NOT NULL
  AND omang != ''
GROUP BY omang
HAVING cnt > 1
ORDER BY cnt DESC
LIMIT 500
"""


# ─────────────────────────────────────────────────────────────────────────────
# RUN
# ─────────────────────────────────────────────────────────────────────────────

def run(**kwargs) -> dict:
    """
    Run all KYC compliance checks.

    Returns:
        dict with keys: summary, path, elapsed
    """
    log.info("=== KYC Compliance Report ===")

    counts: dict[str, int] = {}
    frames: dict[str, pd.DataFrame] = {}

    total = Timer()
    total.__enter__()

    # Expired / missing KYC — customer schema may vary
    try:
        with Timer() as t:
            df_kyc = fetch_df(SQL_EXPIRED_KYC)
        log.info(f"  [Expired/missing KYC]: {len(df_kyc):,} found ({t})")
    except Exception as exc:
        log.warning(f"  [Expired/missing KYC] query failed: {exc}")
        df_kyc = pd.DataFrame()
    counts['expired_kyc'] = len(df_kyc)
    frames['expired_kyc'] = df_kyc

    # Age violations
    try:
        with Timer() as t:
            df_age = fetch_df(SQL_AGE_VIOLATIONS)
        log.info(f"  [Age violations >65]: {len(df_age):,} found ({t})")
    except Exception as exc:
        log.warning(f"  [Age violations] query failed: {exc}")
        df_age = pd.DataFrame()
    counts['age_violations'] = len(df_age)
    frames['age_violations'] = df_age

    # Duplicate omang
    try:
        with Timer() as t:
            df_omang = fetch_df(SQL_DUPLICATE_OMANG)
        log.info(f"  [Duplicate omang]: {len(df_omang):,} found ({t})")
    except Exception as exc:
        log.warning(f"  [Duplicate omang] query failed: {exc}")
        df_omang = pd.DataFrame()
    counts['duplicate_omang'] = len(df_omang)
    frames['duplicate_omang'] = df_omang

    total.__exit__(None, None, None)

    # ── Build Excel ──────────────────────────────────────────────────────────
    out_path = report_path('kyc_compliance_report')
    try:
        with pd.ExcelWriter(str(out_path), engine='openpyxl') as xls:
            _sheet_summary(xls, counts)
            for key, sheet_name in [
                ('expired_kyc',    'Expired KYC'),
                ('age_violations', 'Age Violations'),
                ('duplicate_omang','No Documents'),
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
        'report':          'KYC Compliance Report',
        'expired_kyc':     counts.get('expired_kyc', 0),
        'age_violations':  counts.get('age_violations', 0),
        'duplicate_omang': counts.get('duplicate_omang', 0),
        'total_issues':    sum(counts.values()),
        'generated_at':    datetime.now().isoformat(),
        'output_file':     str(out_path) if out_path else None,
    }

    log.info(f"Total issues: {summary['total_issues']:,} | Elapsed: {total}")
    return {'summary': summary, 'path': str(out_path) if out_path else None, 'elapsed': str(total)}


# ─────────────────────────────────────────────────────────────────────────────
# SHEET BUILDERS
# ─────────────────────────────────────────────────────────────────────────────

def _sheet_summary(xls, counts: dict):
    rows = [
        ['KYC COMPLIANCE REPORT', ''],
        ['Generated', datetime.now().strftime('%Y-%m-%d %H:%M')],
        [''],
        ['CHECK', 'COUNT'],
        ['Active policies with expired/missing KYC', counts.get('expired_kyc', 0)],
        ['Age violations (customer >65, active policy)', counts.get('age_violations', 0)],
        ['Duplicate omang (national ID)',              counts.get('duplicate_omang', 0)],
        [''],
        ['TOTAL ISSUES', sum(counts.values())],
    ]
    pd.DataFrame(rows).to_excel(xls, sheet_name='Summary', index=False, header=False)
