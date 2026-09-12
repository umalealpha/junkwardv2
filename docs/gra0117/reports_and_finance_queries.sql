-- =====================================================================
-- GRA-0117 — analytically-correct report + Finance-verification queries
-- Basis: derive from POLICY SCHEDULE + ACTUAL PAYMENTS, independent of the
-- missing posted invoices. Whole billing months (TIMESTAMPDIFF MONTH + 1),
-- NOT DATEDIFF/30. Run read-only on the reporting replica.
-- Products: fixed-premium 1,2,4,5 (Motor Comp 3 / Hospital 9 excluded — re-rated/special).
-- FY window: 2025-07-01 .. 2026-06-30.
-- =====================================================================

-- Anchor for a policy's billing start (mirror of the engine: billingStartDate
-- else policyActivatedDate else created_at), normalised to a DATE.
-- Used by all three queries below as `bsd`.

-- ---------------------------------------------------------------------
-- (1) AFFECTED-POLICY REPORT  — for Finance verification before flip-ON.
--     Lists every active fixed-premium policy that is BEHIND (no invoice in
--     the current month) with the projected catch-up, split into:
--       validated_set  = in the CFO-validated dry-run population (had an
--                        unposted SUCCESS payment in current FY)
--       additional_set = safe but beyond the validated sample (re-confirm)
--     NOTE: contains customer identifiers — export to a Finance-only file;
--     do NOT paste into chat/email bodies (DPA AD-POL).
-- ---------------------------------------------------------------------
SELECT
    p.policyNumber,
    p.product_id,
    pr.name                                            AS product,
    COALESCE(p.premium,0)                              AS monthly_premium,
    COALESCE(DATE(p.policyActivatedDate), DATE(p.billingStartDate), DATE(p.created_at)) AS bsd,
    (SELECT MAX(l.accounting_date) FROM policy_ledger l
       WHERE l.policy_id=p.id AND l.trans_type='Invoice')                 AS last_invoice_date,
    -- months owed = inclusive months from last invoice (or anchor) to today
    GREATEST(0, TIMESTAMPDIFF(MONTH,
        COALESCE((SELECT MAX(l.accounting_date) FROM policy_ledger l
                    WHERE l.policy_id=p.id AND l.trans_type='Invoice'),
                 COALESCE(p.policyActivatedDate,p.billingStartDate,p.created_at)),
        CURDATE()))                                                       AS months_owed,
    ROUND(COALESCE(p.premium,0) * GREATEST(0, TIMESTAMPDIFF(MONTH,
        COALESCE((SELECT MAX(l.accounting_date) FROM policy_ledger l
                    WHERE l.policy_id=p.id AND l.trans_type='Invoice'),
                 COALESCE(p.policyActivatedDate,p.billingStartDate,p.created_at)),
        CURDATE())),2)                                                    AS projected_backfill_premium,
    CASE WHEN EXISTS (SELECT 1 FROM payment_transactions pt
                        WHERE pt.policyNumber=p.policyNumber AND pt.is_ledger=0
                          AND UPPER(pt.status)='SUCCESS' AND pt.amount NOT IN ('1','1.00')
                          AND (pt.is_refund=0 OR pt.is_refund IS NULL)
                          AND pt.created_at>='2025-07-01')
         THEN 'validated_set' ELSE 'additional_set' END                  AS scope_bucket
FROM policies p
JOIN products pr ON pr.id=p.product_id
WHERE p.product_id IN (1,2,4,5) AND p.status=1
  AND NOT EXISTS (SELECT 1 FROM policy_ledger l2
                    WHERE l2.policy_id=p.id AND l2.trans_type='Invoice'
                      AND l2.accounting_date >= DATE_FORMAT(CURDATE(),'%Y-%m-01'))
  -- exclude premium-changed (Finance pricing) so the file = exactly what the cron will post:
  AND (SELECT COUNT(DISTINCT l3.invoice_amount) FROM policy_ledger l3
         WHERE l3.policy_id=p.id AND l3.trans_type='Invoice' AND l3.invoice_amount>0) <= 1
ORDER BY scope_bucket, p.product_id, p.policyNumber;

-- ---------------------------------------------------------------------
-- (2) WRITTEN PREMIUM (corrected) — schedule-derived, FY 2025-07..2026-06.
--     Replaces the Reporting portal's ledger-invoice-date-filtered query
--     (written-premium.controller.ts:61-98) which drops policies with no
--     posted invoice. Counts whole billing months in the FY window.
-- ---------------------------------------------------------------------
SELECT
    p.product_id,
    pr.name AS product,
    COUNT(*)                                            AS policies,
    ROUND(SUM(
        COALESCE(p.premium,0) *
        GREATEST(0,
            TIMESTAMPDIFF(MONTH,
                GREATEST(COALESCE(p.policyActivatedDate,p.billingStartDate,p.created_at), '2025-07-01'),
                LEAST(COALESCE(p.policyTerminationDate, CURDATE()), '2026-06-30')
            ) + 1)
    ),2)                                                AS written_premium_fy
FROM policies p
JOIN products pr ON pr.id=p.product_id
WHERE p.product_id IN (1,2,4,5) AND p.status=1 AND p.is_test_policy=0
  AND COALESCE(p.policyActivatedDate,p.billingStartDate,p.created_at) <= '2026-06-30'
GROUP BY p.product_id, pr.name;

-- ---------------------------------------------------------------------
-- (3) AGE ANALYSIS balance (corrected) — expected-invoiced (schedule)
--     minus actual payments. Positive owed = in arrears. Replaces the
--     summary_age_analyst_* proc balance that reads missing posted invoices.
--     (Aging buckets would bucket `owed` by months_owed; summary first.)
-- ---------------------------------------------------------------------
SELECT
    p.product_id,
    COUNT(*)                                            AS policies,
    ROUND(SUM(
        COALESCE(p.premium,0) *
        (TIMESTAMPDIFF(MONTH,
            COALESCE(p.policyActivatedDate,p.billingStartDate,p.created_at), CURDATE()) + 1)
        - COALESCE((SELECT SUM(CAST(pt.amount AS DECIMAL(14,2)))
                      FROM payment_transactions pt
                     WHERE pt.policyNumber=p.policyNumber AND pt.is_ledger=0
                       AND UPPER(pt.status)='SUCCESS' AND pt.amount NOT IN ('1','1.00')
                       AND (pt.is_refund=0 OR pt.is_refund IS NULL)),0)
    ),2)                                                AS expected_arrears_total
FROM policies p
WHERE p.product_id IN (1,2,4,5) AND p.status=1 AND p.is_test_policy=0
GROUP BY p.product_id;
