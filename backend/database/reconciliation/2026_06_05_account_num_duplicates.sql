-- ============================================================================
-- accounts.account_num duplicates — disambiguation pack for Finance
-- ============================================================================
--
-- CONTEXT
-- -------
-- CFO 11 PM list #2: Chart of Accounts has duplicate account_num codes (the
-- example flagged was "123" used by 8 accounts). Journals posted via the
-- accounting layer can therefore land against the wrong account_id, and the
-- Chart of Accounts admin page shows duplicate rows for the same code.
--
-- A companion Laravel migration:
--     2026_06_05_220000_add_unique_index_on_accounts_account_num.php
-- adds a UNIQUE constraint on accounts.account_num. That migration refuses
-- to run until this script's duplicates are resolved — it aborts with a
-- clear error listing the dups.
--
-- THIS SCRIPT IS FOR FINANCE
-- --------------------------
-- Step 1 identifies the duplicates.
-- Step 2 shows the affected rows in full so Finance can decide which is
--   the legitimate account_num for each code (vs which need to be renamed).
-- Step 3 is left BLANK — Finance updates each duplicate row manually with
--   the correct account_num value. There is no safe automatic merge because
--   the policy_subledger / journal_entries rows that reference these account
--   ids may need to follow the renamed account.
-- Step 4 re-verifies that no duplicates remain.
-- Step 5 deploys the migration (CI/CD) and confirms the unique index landed.
--
-- DO NOT RUN STEP 3 UNTIL FINANCE HAS DECIDED PER ACCOUNT
-- ============================================================================

USE Graphite_live;

-- ----------------------------------------------------------------------------
-- STEP 1 — find the duplicate account_num values
-- ----------------------------------------------------------------------------

SELECT
  account_num,
  COUNT(*)         AS duplicate_count,
  GROUP_CONCAT(id) AS account_ids
FROM accounts
WHERE account_num IS NOT NULL
  AND account_num <> ''
GROUP BY account_num
HAVING duplicate_count > 1
ORDER BY duplicate_count DESC, account_num;

-- ----------------------------------------------------------------------------
-- STEP 2 — show the affected rows in full so Finance can decide
-- ----------------------------------------------------------------------------
-- For each duplicate account_num, print every row that shares it. Finance
-- decides which row keeps that account_num and what the renamed value(s)
-- should be for the others.
--
-- Adjust the column list below if the accounts table has additional columns
-- worth seeing (e.g. account_type, parent_id, created_at, last_used_at).

SELECT a.*
FROM accounts a
INNER JOIN (
    SELECT account_num
    FROM accounts
    WHERE account_num IS NOT NULL AND account_num <> ''
    GROUP BY account_num
    HAVING COUNT(*) > 1
) d ON d.account_num = a.account_num
ORDER BY a.account_num, a.id;

-- ----------------------------------------------------------------------------
-- STEP 2a — how much downstream data references each duplicate id?
-- ----------------------------------------------------------------------------
-- For each duplicate account row, show how many journal / sub-ledger lines
-- already point at it. This helps Finance decide which row "owns" the
-- account_num (typically the one with the most historical journal volume)
-- and which row needs renaming.

SELECT
  a.id           AS account_id,
  a.account_num,
  a.account_name,
  (SELECT COUNT(*) FROM policy_subledger sl WHERE sl.account_id = a.id) AS subledger_rows
FROM accounts a
INNER JOIN (
    SELECT account_num
    FROM accounts
    WHERE account_num IS NOT NULL AND account_num <> ''
    GROUP BY account_num
    HAVING COUNT(*) > 1
) d ON d.account_num = a.account_num
ORDER BY a.account_num, subledger_rows DESC;

-- ----------------------------------------------------------------------------
-- STEP 3 — DISAMBIGUATE (Finance edits this section manually per duplicate)
-- ----------------------------------------------------------------------------
-- Finance reviews the output of Steps 1 / 2 / 2a, then runs UPDATE statements
-- like the templates below — one per row that needs renaming. There is no
-- safe automatic rule because each duplicate code needs a human decision.
--
-- TEMPLATES (do NOT run as-is; substitute real ids and values):
--
--   START TRANSACTION;
--   -- Example: account id 17 currently has account_num='123', rename to '123A'
--   UPDATE accounts SET account_num = '123A', updated_at = NOW() WHERE id = 17;
--   -- ... repeat per duplicate row ...
--   -- Then verify counts return to 1 each (see STEP 4) before COMMIT.
--   -- COMMIT;
--   -- or ROLLBACK if anything looks off
--
-- DO NOT update accounts.id — downstream foreign keys (policy_subledger,
-- journal_entries, etc.) reference id, not account_num. The id is stable.
-- Only rename account_num.

-- ----------------------------------------------------------------------------
-- STEP 4 — verify zero duplicates remain (re-run Step 1)
-- ----------------------------------------------------------------------------

SELECT
  account_num,
  COUNT(*) AS duplicate_count
FROM accounts
WHERE account_num IS NOT NULL
  AND account_num <> ''
GROUP BY account_num
HAVING duplicate_count > 1;
-- Expected: 0 rows returned

-- ----------------------------------------------------------------------------
-- STEP 5 — deploy and confirm the unique index landed
-- ----------------------------------------------------------------------------
-- 1. Trigger the standard CI/CD deploy so the migration
--    2026_06_05_220000_add_unique_index_on_accounts_account_num runs. With
--    duplicates resolved, it will succeed and add the UNIQUE constraint.
-- 2. Confirm:
--
--      SHOW INDEX FROM accounts WHERE Key_name = 'uniq_accounts_account_num';
--      -- Expected: one row, Non_unique = 0, Column_name = account_num

-- ============================================================================
-- END OF SCRIPT
-- ============================================================================
