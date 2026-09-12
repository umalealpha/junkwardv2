"""
earned_premium_daily.py
───────────────────────
Daily Earned/Unearned Premium Register Posting

Runs daily (typically 02:00 UTC) and for every active policy:
  1. Calculates earned premium = written_premium × (expired_days / policy_days)
  2. Calculates unearned premium = written_premium - earned_premium
  3. Calculates daily premium increment = written_premium / policy_days
  4. Posts a snapshot row to `premium_register` table

The premium_register table is the financial source-of-truth for:
  - Regulatory returns (NBFIRA quarterly)
  - Reinsurance bordereaux
  - Management accounts
  - Financial dashboards (total earned/unearned org-wide)

Formula: 365-day pro-rata (standard for Botswana general insurance)
  earned   = premium × min(elapsed_days, policy_days) / policy_days
  unearned = premium × max(unexpired_days, 0) / policy_days
  daily    = premium / policy_days
"""

from __future__ import annotations
from datetime import date, datetime

from ..db    import fetch_df, execute, execute_many
from ..utils import get_logger, Timer, bwp, safe_float

log = get_logger('earned_premium_daily')


SQL_ACTIVE_POLICIES = """
SELECT
    p.id                             AS policy_id,
    p.policyNumber                   AS policy_number,
    p.product_id,
    p.agency_id,
    p.agent_id,
    p.premium_freq,
    pa.effective_from,
    pa.effective_to                  AS expiry_date,
    COALESCE(pa.premium, p.premium, 0) AS written_premium,
    COALESCE(p.vat, 0)              AS vat_amount,
    pa.id                           AS action_id,
    pa.transaction_type
FROM policies p
JOIN policy_actions pa
    ON pa.policy_id = p.id
    AND pa.deleted_at IS NULL
    AND pa.status = 'ISSUED'
WHERE p.status = 1
  AND pa.effective_from IS NOT NULL
  AND pa.effective_to IS NOT NULL
  AND pa.effective_from <= CURDATE()
ORDER BY p.id, pa.effective_from DESC
"""

# Only keep the latest ISSUED action per policy (dedup)
SQL_LATEST_ACTION = """
SELECT
    p.id                             AS policy_id,
    p.policyNumber                   AS policy_number,
    p.product_id,
    p.agency_id,
    p.agent_id,
    p.premium_freq,
    pa.effective_from,
    pa.effective_to                  AS expiry_date,
    COALESCE(pa.premium, p.premium, 0) AS written_premium,
    COALESCE(p.vat, 0)              AS vat_amount,
    pa.id                           AS action_id,
    pa.transaction_type
FROM policies p
JOIN policy_actions pa ON pa.id = (
    SELECT pa2.id FROM policy_actions pa2
    WHERE pa2.policy_id = p.id
      AND pa2.deleted_at IS NULL
      AND pa2.status = 'ISSUED'
      AND pa2.effective_from IS NOT NULL
      AND pa2.effective_to IS NOT NULL
    ORDER BY pa2.id DESC
    LIMIT 1
)
WHERE p.status = 1
  AND pa.effective_from <= CURDATE()
"""


def run(posting_date: date | None = None) -> dict:
    """
    Calculate and post earned/unearned premium for all active policies.

    Args:
        posting_date: The date to post for (default: today).

    Returns:
        dict with keys: policies_processed, total_earned, total_unearned, elapsed
    """
    posting_date = posting_date or date.today()
    log.info(f"=== Earned Premium Daily Posting — {posting_date} ===")

    # Check if already posted today
    existing = execute(
        "SELECT COUNT(*) as cnt FROM premium_register WHERE posting_date = %s",
        (str(posting_date),)
    )
    # execute returns rowcount, not result — use fetch for check
    from ..db import fetchone
    check = fetchone(
        "SELECT COUNT(*) as cnt FROM premium_register WHERE posting_date = %s",
        (str(posting_date),)
    )
    if check and check['cnt'] > 0:
        log.info(f"Already posted {check['cnt']} rows for {posting_date}. Skipping.")
        return {
            'posting_date': str(posting_date),
            'status': 'skipped',
            'reason': f"Already posted ({check['cnt']} rows)",
        }

    with Timer() as t:
        df = fetch_df(SQL_LATEST_ACTION)

    log.info(f"Fetched {len(df):,} active policies in {t}")

    if df.empty:
        log.warning("No active policies found")
        return {'posting_date': str(posting_date), 'policies_processed': 0}

    # Calculate earned/unearned for each policy
    import pandas as pd

    for col in ['written_premium', 'vat_amount']:
        df[col] = pd.to_numeric(df[col], errors='coerce').fillna(0)
    for col in ['effective_from', 'expiry_date']:
        df[col] = pd.to_datetime(df[col], errors='coerce')

    posting_dt = pd.Timestamp(posting_date)
    rows_to_insert = []

    for _, row in df.iterrows():
        eff = row['effective_from']
        exp = row['expiry_date']
        prem = float(row['written_premium'])

        if pd.isna(eff) or pd.isna(exp) or prem <= 0:
            continue

        policy_days = max((exp - eff).days, 1)
        elapsed_days = max(min((posting_dt - eff).days, policy_days), 0)
        unexpired_days = max((exp - posting_dt).days, 0)

        earned = round(prem * elapsed_days / policy_days, 2)
        unearned = round(prem * unexpired_days / policy_days, 2)
        daily_premium = round(prem / policy_days, 2)

        rows_to_insert.append((
            row['policy_id'],
            row['policy_number'],
            int(row['product_id']) if pd.notna(row['product_id']) else None,
            int(row['agency_id']) if pd.notna(row['agency_id']) else None,
            int(row['agent_id']) if pd.notna(row['agent_id']) else None,
            round(prem, 2),
            round(float(row['vat_amount']), 2),
            earned,
            unearned,
            daily_premium,
            policy_days,
            elapsed_days,
            unexpired_days,
            str(posting_date),
            row['transaction_type'] if pd.notna(row.get('transaction_type')) else None,
            int(row['action_id']) if pd.notna(row['action_id']) else None,
        ))

    # Bulk insert
    if rows_to_insert:
        with Timer() as ti:
            affected = execute_many(
                """INSERT INTO premium_register
                   (policy_id, policy_number, product_id, agency_id, agent_id,
                    written_premium, vat_amount, earned_premium, unearned_premium,
                    daily_premium, policy_days, elapsed_days, unexpired_days,
                    posting_date, transaction_type, action_id)
                   VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)""",
                rows_to_insert
            )
        log.info(f"Inserted {affected:,} rows in {ti}")

    # Summary totals
    total_earned = sum(r[7] for r in rows_to_insert)     # index 7 = earned
    total_unearned = sum(r[8] for r in rows_to_insert)   # index 8 = unearned
    total_written = sum(r[5] for r in rows_to_insert)     # index 5 = written

    summary = {
        'posting_date':       str(posting_date),
        'policies_processed': len(rows_to_insert),
        'total_written':      round(total_written, 2),
        'total_earned':       round(total_earned, 2),
        'total_unearned':     round(total_unearned, 2),
        'elapsed':            str(t),
    }

    log.info(f"Written: {bwp(total_written)} | Earned: {bwp(total_earned)} | Unearned: {bwp(total_unearned)}")
    log.info(f"=== Earned Premium Daily Posting Complete ===")

    return summary
