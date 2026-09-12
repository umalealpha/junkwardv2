"""
premium_anomaly.py — Premium Anomaly Detection Report
──────────────────────────────────────────────────────
Detects premium-related data quality issues across DOMCOM products:
  1. Zero-premium active policies (should have a non-zero premium)
  2. Rate outliers in policy_coverage_detail (unusually high or near-zero rates)
  3. Duplicate policies (same customer + product, both active)
  4. Round-number premiums (multiples of 500, flagged for manual review)

Output: Excel workbook with 5 sheets + structured summary dict.
"""

from __future__ import annotations
import pandas as pd
from datetime import datetime

from ..db    import fetch_df
from ..utils import get_logger, Timer, bwp, safe_float, report_path, DOMCOM_PRODUCTS, PRODUCT_NAMES

log = get_logger('premium_anomaly')

_PRODS = ','.join(str(x) for x in DOMCOM_PRODUCTS)

# ─────────────────────────────────────────────────────────────────────────────
# SQL QUERIES
# ─────────────────────────────────────────────────────────────────────────────

SQL_ZERO_PREMIUM = """
SELECT
    p.id          AS policy_id,
    p.policyNumber AS policy_number,
    p.product_id,
    COALESCE(p.premium, 0) AS premium,
    p.created_at,
    p.agency_id,
    ag.name       AS agency_name
FROM policies p
JOIN policy_actions pa ON pa.policy_id = p.id
    AND pa.status = 'ISSUED'
    AND pa.deleted_at IS NULL
LEFT JOIN agencies ag ON ag.id = p.agency_id
WHERE p.status = 1
  AND p.product_id IN ({prods})
  AND COALESCE(p.premium, 0) = 0
ORDER BY p.id
""".format(prods=_PRODS)

SQL_RATE_OUTLIERS = """
SELECT
    p.id          AS policy_id,
    p.policyNumber AS policyNumber,
    p.product_id,
    pcd.coverage_id,
    pcd.rate,
    pcd.coverage_value,
    pcd.calculated_value
FROM policy_coverage_detail pcd
JOIN policy_coverages pc ON pc.id = pcd.policy_coverage_id
JOIN policies p ON p.id = pc.policy_id
WHERE p.product_id IN ({prods})
  AND pcd.rate IS NOT NULL
  AND pcd.rate > 0
  AND (pcd.rate > 25 OR (pcd.rate < 0.1 AND pcd.rate > 0))
LIMIT 1000
""".format(prods=_PRODS)

SQL_DUPLICATE_POLICIES = """
SELECT
    customer_id,
    product_id,
    COUNT(*) AS policy_count,
    GROUP_CONCAT(policyNumber ORDER BY id SEPARATOR ', ') AS policy_numbers,
    SUM(COALESCE(premium, 0)) AS total_premium
FROM policies
WHERE status = 1
  AND product_id IN ({prods})
  AND deleted_at IS NULL
GROUP BY customer_id, product_id
HAVING policy_count > 1
ORDER BY policy_count DESC
LIMIT 500
""".format(prods=_PRODS)

SQL_ROUND_PREMIUMS = """
SELECT
    policyNumber AS policy_number,
    product_id,
    premium,
    created_at,
    agency_id
FROM policies
WHERE status = 1
  AND product_id IN ({prods})
  AND premium > 0
  AND MOD(COALESCE(premium, 0), 500) = 0
  AND deleted_at IS NULL
ORDER BY premium DESC
LIMIT 1000
""".format(prods=_PRODS)


# ─────────────────────────────────────────────────────────────────────────────
# RUN
# ─────────────────────────────────────────────────────────────────────────────

def run(**kwargs) -> dict:
    """
    Run all premium anomaly checks.

    Returns:
        dict with keys: summary, path, elapsed
    """
    log.info("=== Premium Anomaly Detection ===")

    counts: dict[str, int] = {}
    frames: dict[str, pd.DataFrame] = {}

    checks = [
        ('zero_premium',        SQL_ZERO_PREMIUM,        "Zero-premium active policies"),
        ('rate_outliers',       SQL_RATE_OUTLIERS,        "Rate outliers"),
        ('duplicate_policies',  SQL_DUPLICATE_POLICIES,  "Duplicate policies"),
        ('round_number_premiums', SQL_ROUND_PREMIUMS,    "Round-number premiums"),
    ]

    total = Timer()
    total.__enter__()

    for key, sql, label in checks:
        try:
            with Timer() as t:
                df = fetch_df(sql)
        except Exception as exc:
            log.warning(f"  [{label}] query failed: {exc}")
            df = pd.DataFrame()
        counts[key] = len(df)
        frames[key] = df
        log.info(f"  [{label}]: {len(df):,} found")

    total.__exit__(None, None, None)

    # ── Build Excel ──────────────────────────────────────────────────────────
    out_path = report_path('premium_anomaly')
    try:
        with pd.ExcelWriter(str(out_path), engine='openpyxl') as xls:
            _sheet_summary(xls, counts)
            for key, sheet_name in [
                ('zero_premium',           'Zero Premium Policies'),
                ('rate_outliers',          'Rate Outliers'),
                ('duplicate_policies',     'Duplicate Policies'),
                ('round_number_premiums',  'Round Number Premiums'),
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
        'report':              'Premium Anomaly Detection',
        'zero_premium':        counts.get('zero_premium', 0),
        'rate_outliers':       counts.get('rate_outliers', 0),
        'duplicate_policies':  counts.get('duplicate_policies', 0),
        'round_number_premiums': counts.get('round_number_premiums', 0),
        'total_anomalies':     sum(counts.values()),
        'generated_at':        datetime.now().isoformat(),
        'output_file':         str(out_path) if out_path else None,
    }

    log.info(f"Total anomalies: {summary['total_anomalies']:,} | Elapsed: {total}")
    return {'summary': summary, 'path': str(out_path) if out_path else None, 'elapsed': str(total)}


# ─────────────────────────────────────────────────────────────────────────────
# SHEET BUILDERS
# ─────────────────────────────────────────────────────────────────────────────

def _sheet_summary(xls, counts: dict):
    rows = [
        ['PREMIUM ANOMALY DETECTION REPORT', ''],
        ['Generated', datetime.now().strftime('%Y-%m-%d %H:%M')],
        [''],
        ['CHECK', 'COUNT'],
        ['Zero-Premium Active Policies',  counts.get('zero_premium', 0)],
        ['Rate Outliers',                 counts.get('rate_outliers', 0)],
        ['Duplicate Active Policies',     counts.get('duplicate_policies', 0)],
        ['Round-Number Premiums (×500)',  counts.get('round_number_premiums', 0)],
        [''],
        ['TOTAL ANOMALIES', sum(counts.values())],
    ]
    pd.DataFrame(rows).to_excel(xls, sheet_name='Summary', index=False, header=False)
