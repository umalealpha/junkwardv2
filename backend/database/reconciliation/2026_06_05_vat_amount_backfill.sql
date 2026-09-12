-- ============================================================================
-- VAT amount backfill — fix policies that were issued with the wrong VAT value
-- ============================================================================
--
-- ROOT CAUSE
-- ----------
-- PolicyCreateController::issuePolicy (V2) wrote the VAT *rate* into the
-- policies.vat column instead of the computed VAT *amount*. Every affected
-- policy has vat = 14.00 (or whatever the region VAT rate is) regardless of
-- the actual premium. The vat_percent column is correct on these rows.
--
-- See code fix at PolicyCreateController.php:~8649 — single-line patch on
-- branch fix/vat-amount-not-rate-2026-06-05.
--
-- This script is the DATA reconciliation step. It corrects historical rows.
--
-- SAFETY
-- ------
-- 1. Run on a non-production target FIRST (staging or a snapshot restore).
-- 2. Take a fresh RDS snapshot of the PROD master BEFORE running on PROD.
-- 3. Run the IDENTIFY queries first and CHECK COUNTS WITH FINANCE before
--    running any UPDATE statement.
-- 4. The UPDATE is wrapped in a transaction — review the row count in the
--    SELECT before COMMITting.
-- 5. NEVER run this against V1 master. V1 keeps its own VAT history; this
--    fix is V2-specific.
--
-- ASSUMPTIONS
-- -----------
-- - policies.premium is the EX-VAT premium (confirmed via parallel patterns
--   in V2 code: CancelRealPayContract uses `premium + vat` to reconstruct
--   total; Helper.php quote sheet sets `vat = premiumExcludingVAT * rate/100`).
-- - policies.vat_percent is the VAT rate that was in force when the policy
--   was issued (e.g. 14 for 14%).
-- - The only buggy row signature is: vat = vat_percent (rate written as amount).
--   Legitimate rows have vat = premium * vat_percent / 100.
-- ============================================================================

USE Graphite_live;

-- ----------------------------------------------------------------------------
-- STEP 1 — IDENTIFY affected rows (read-only, run first; share counts with Finance)
-- ----------------------------------------------------------------------------

-- 1a. How many policies have the bug signature (vat written as the rate)?
SELECT COUNT(*) AS affected_count
FROM policies
WHERE vat IS NOT NULL
  AND vat_percent IS NOT NULL
  AND vat = vat_percent          -- bug signature: rate written into amount column
  AND vat <= 50                  -- guard against legit rare premiums that happen to equal vat_percent
  AND premium IS NOT NULL
  AND premium > 0;

-- 1b. Same query, but broken down by creation date to understand scope
SELECT
  DATE(created_at)  AS day,
  COUNT(*)          AS affected_policies,
  MIN(premium)      AS min_premium,
  MAX(premium)      AS max_premium,
  SUM(premium)      AS sum_premium,
  SUM(vat)          AS sum_vat_as_logged_today,
  SUM(ROUND(premium * vat_percent / 100, 2)) AS sum_vat_correct
FROM policies
WHERE vat = vat_percent
  AND vat <= 50
  AND premium > 0
GROUP BY DATE(created_at)
ORDER BY day DESC;

-- 1c. Sample of the affected rows (verify the bug signature manually)
SELECT
  id, policyNumber, customer_id, product_id,
  premium, vat, vat_percent,
  ROUND(premium * vat_percent / 100, 2) AS vat_corrected,
  ROUND((premium * vat_percent / 100) - vat, 2) AS delta_per_policy,
  created_at
FROM policies
WHERE vat = vat_percent
  AND vat <= 50
  AND premium > 0
ORDER BY created_at DESC
LIMIT 20;

-- 1d. Total GL exposure (run this for the Finance briefing)
SELECT
  SUM(vat)                                       AS total_vat_as_logged_today,
  SUM(ROUND(premium * vat_percent / 100, 2))     AS total_vat_corrected,
  SUM(ROUND(premium * vat_percent / 100, 2)) - SUM(vat) AS net_gl_adjustment_required
FROM policies
WHERE vat = vat_percent
  AND vat <= 50
  AND premium > 0;

-- ----------------------------------------------------------------------------
-- STEP 2 — STOP. Before running STEP 3, confirm with Finance:
--          (i)   Counts from 1a match their expectation
--          (ii)  Sample rows from 1c look legitimately affected
--          (iii) The total GL delta from 1d is acceptable to post as a
--                single adjustment journal entry
--          (iv)  Take a fresh RDS snapshot of the PROD master
-- ----------------------------------------------------------------------------

-- ----------------------------------------------------------------------------
-- STEP 3 — UPDATE (run in transaction, review row count, then COMMIT)
-- ----------------------------------------------------------------------------

START TRANSACTION;

-- The fix: replace the rate-written-as-amount with the correctly computed amount.
UPDATE policies
SET vat = ROUND(premium * vat_percent / 100, 2),
    updated_at = NOW()
WHERE vat = vat_percent
  AND vat <= 50
  AND premium > 0
  AND premium IS NOT NULL
  AND vat_percent IS NOT NULL;

-- The above should affect the same number of rows as the SELECT in 1a.
-- Verify the row-count printed by MySQL matches the expected count.
-- Then run:

-- COMMIT;
-- OR if anything looks off:
-- ROLLBACK;

-- ----------------------------------------------------------------------------
-- STEP 4 — VERIFY (after COMMIT, confirm zero residual bug rows)
-- ----------------------------------------------------------------------------

-- Expected: 0
SELECT COUNT(*) AS residual_buggy_rows
FROM policies
WHERE vat = vat_percent
  AND vat <= 50
  AND premium > 0;

-- Spot-check: pick 5 random affected policies and confirm vat = premium * rate / 100
SELECT
  id, policyNumber, premium, vat_percent, vat,
  ROUND(premium * vat_percent / 100, 2) AS expected_vat,
  (vat = ROUND(premium * vat_percent / 100, 2)) AS matches
FROM policies
WHERE vat > 0
  AND premium > 0
ORDER BY RAND()
LIMIT 5;

-- ----------------------------------------------------------------------------
-- DOWNSTREAM TABLES — DEFER (do NOT modify in this script)
-- ----------------------------------------------------------------------------
--
-- The following tables MAY carry derived VAT values inherited from the buggy
-- policies.vat. DO NOT touch them in this script — they need separate analysis
-- with Finance before any update.
--
--   - ledger / `Ledger` model — Invoice / Invoice VAT / Invoice Premium rows
--   - premium_register — period roll-up records used by reports
--   - policy_action — `vat_percent` may need a parallel sanity check
--   - any journal_entries / GL postings that referenced the bad vat amount
--   - invoices / invoice_items — customer-facing invoice rows
--
-- After Finance reviews this script's output and confirms the scope, schedule
-- a follow-up reconciliation for these tables. The follow-up script will need
-- to:
--   1. Find ledger rows tagged Invoice VAT where policy_id is in the
--      affected set and the amount equals the buggy rate-as-amount.
--   2. Re-post or adjust those rows to the corrected VAT amount.
--   3. Generate an offsetting GL journal entry per Finance's preferred
--      accounting treatment (prior-period adjustment vs current-period
--      correction — Finance decides).
--
-- ============================================================================
-- END OF SCRIPT
-- ============================================================================
