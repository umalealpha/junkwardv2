"""
policy_audit.py — Policy Completeness Audit
─────────────────────────────────────────────
Checks that issued DOMCOM policies meet data completeness requirements:
  1. Policies missing a risk address
  2. Issued policies with no coverages attached
  3. Coverages with zero sum insured on issued policies
  4. Policies issued without an APPROVED action step

Output: Excel workbook with 5 sheets + structured summary dict.
"""

from __future__ import annotations
import pandas as pd
from datetime import datetime

from ..db    import fetch_df
from ..utils import get_logger, Timer, report_path, DOMCOM_PRODUCTS, PRODUCT_NAMES

log = get_logger('policy_audit')

_PRODS = ','.join(str(x) for x in DOMCOM_PRODUCTS)

# ─────────────────────────────────────────────────────────────────────────────
# SQL QUERIES
# ─────────────────────────────────────────────────────────────────────────────

SQL_MISSING_RISK_ADDRESSES = """
SELECT
    p.id            AS policy_id,
    p.policyNumber  AS policy_number,
    p.product_id,
    p.status,
    pa.status       AS action_status
FROM policies p
JOIN policy_actions pa ON pa.policy_id = p.id
    AND pa.status = 'ISSUED'
    AND pa.deleted_at IS NULL
LEFT JOIN risk_address ra ON ra.policy_id = p.id
    AND ra.deleted_at IS NULL
WHERE p.status = 1
  AND p.product_id IN ({prods})
  AND ra.id IS NULL
ORDER BY p.id
LIMIT 1000
""".format(prods=_PRODS)

SQL_MISSING_COVERAGES = """
SELECT
    p.id            AS policy_id,
    p.policyNumber  AS policy_number,
    p.product_id
FROM policies p
JOIN policy_actions pa ON pa.policy_id = p.id
    AND pa.status = 'ISSUED'
    AND pa.deleted_at IS NULL
LEFT JOIN policy_coverages pc ON pc.policy_id = p.id
    AND pc.deleted_at IS NULL
WHERE p.product_id IN ({prods})
  AND pc.id IS NULL
LIMIT 1000
""".format(prods=_PRODS)

SQL_ZERO_SUM_INSURED = """
SELECT
    pc.id,
    pc.policy_id,
    pc.coverage_id,
    p.policyNumber  AS policy_number,
    p.product_id,
    COALESCE(pcd.coverage_value, 0) AS sum_insured
FROM policy_coverages pc
JOIN policies p ON p.id = pc.policy_id
JOIN policy_actions pa ON pa.policy_id = p.id
    AND pa.status = 'ISSUED'
    AND pa.deleted_at IS NULL
LEFT JOIN policy_coverage_detail pcd ON pcd.policy_coverage_id = pc.id
    AND pcd.deleted_at IS NULL
WHERE p.product_id IN ({prods})
  AND COALESCE(pcd.coverage_value, 0) = 0
  AND pc.deleted_at IS NULL
LIMIT 1000
""".format(prods=_PRODS)

SQL_MISSING_APPROVAL = """
SELECT
    p.id            AS policy_id,
    p.policyNumber  AS policy_number,
    p.product_id,
    pa.status       AS action_status,
    pa.created_at
FROM policies p
JOIN policy_actions pa ON pa.policy_id = p.id
    AND pa.status = 'ISSUED'
    AND pa.deleted_at IS NULL
WHERE p.product_id IN ({prods})
  AND NOT EXISTS (
      SELECT 1
      FROM policy_actions pa2
      WHERE pa2.policy_id = p.id
        AND pa2.status = 'APPROVED'
        AND pa2.deleted_at IS NULL
  )
LIMIT 500
""".format(prods=_PRODS)


# ─────────────────────────────────────────────────────────────────────────────
# RUN
# ─────────────────────────────────────────────────────────────────────────────

def run(**kwargs) -> dict:
    """
    Run all policy completeness audit checks.

    Returns:
        dict with keys: summary, path, elapsed
    """
    log.info("=== Policy Completeness Audit ===")

    counts: dict[str, int] = {}
    frames: dict[str, pd.DataFrame] = {}

    checks = [
        ('missing_risk_addresses', SQL_MISSING_RISK_ADDRESSES, "Missing risk addresses"),
        ('missing_coverages',      SQL_MISSING_COVERAGES,      "Missing coverages"),
        ('zero_sum_insured',       SQL_ZERO_SUM_INSURED,       "Zero sum insured"),
        ('missing_approval',       SQL_MISSING_APPROVAL,       "Missing approval step"),
    ]

    total = Timer()
    total.__enter__()

    for key, sql, label in checks:
        try:
            with Timer() as t:
                df = fetch_df(sql)
            log.info(f"  [{label}]: {len(df):,} found ({t})")
        except Exception as exc:
            log.warning(f"  [{label}] query failed: {exc}")
            df = pd.DataFrame()
        counts[key] = len(df)
        frames[key] = df

    total.__exit__(None, None, None)

    # ── Build Excel ──────────────────────────────────────────────────────────
    out_path = report_path('policy_audit')
    try:
        with pd.ExcelWriter(str(out_path), engine='openpyxl') as xls:
            _sheet_summary(xls, counts)
            for key, sheet_name in [
                ('missing_risk_addresses', 'Missing Risk Addresses'),
                ('missing_coverages',      'Missing Coverages'),
                ('zero_sum_insured',       'Zero Sum Insured'),
                ('missing_approval',       'Missing Approval Step'),
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
        'report':                'Policy Completeness Audit',
        'missing_risk_addresses': counts.get('missing_risk_addresses', 0),
        'missing_coverages':     counts.get('missing_coverages', 0),
        'zero_sum_insured':      counts.get('zero_sum_insured', 0),
        'missing_approval':      counts.get('missing_approval', 0),
        'total_issues':          sum(counts.values()),
        'generated_at':          datetime.now().isoformat(),
        'output_file':           str(out_path) if out_path else None,
    }

    log.info(f"Total issues: {summary['total_issues']:,} | Elapsed: {total}")
    return {'summary': summary, 'path': str(out_path) if out_path else None, 'elapsed': str(total)}


# ─────────────────────────────────────────────────────────────────────────────
# SHEET BUILDERS
# ─────────────────────────────────────────────────────────────────────────────

def _sheet_summary(xls, counts: dict):
    rows = [
        ['POLICY COMPLETENESS AUDIT', ''],
        ['Generated', datetime.now().strftime('%Y-%m-%d %H:%M')],
        [''],
        ['CHECK', 'COUNT'],
        ['Active policies missing risk address', counts.get('missing_risk_addresses', 0)],
        ['Issued policies with no coverages',   counts.get('missing_coverages', 0)],
        ['Coverages with zero sum insured',     counts.get('zero_sum_insured', 0)],
        ['Policies issued without approval',    counts.get('missing_approval', 0)],
        [''],
        ['TOTAL ISSUES', sum(counts.values())],
    ]
    pd.DataFrame(rows).to_excel(xls, sheet_name='Summary', index=False, header=False)
