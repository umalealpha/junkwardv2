-- ============================================================================
-- RealPay "debited but no Graphite transaction" — production diagnostic
--
-- READ-ONLY. Every statement is a SELECT. Safe to run on a replica.
--
-- WHY THIS FILE EXISTS: the four reported policies (MIS2026215341,
-- MIS2026215350, MIS2026214543, MIS2026215336) do not exist in the
-- graphite-test-write / Graphite_live clone that is reachable from a developer
-- workstation — its newest MIS policy is MIS2026213724 (2026-06-20), so the
-- affected policies were created after that clone's cut-off and live only on
-- real production. Run this there.
--
-- Query 1 is the reproduction. Queries 2-7 separate the failure modes.
-- ============================================================================



-- ─────────────────────────────────────────────────────────────────────────
-- 1. THE REPRODUCTION
--    One row per affected policy: what RealPay collected vs what Graphite
--    recorded. A non-zero `realpay_collected` with `graphite_payments` = 0 is
--    the reported defect.
-- ─────────────────────────────────────────────────────────────────────────
SELECT
    p.policyNumber,
    p.id                          AS policy_id,
    p.product_id,
    p.status                      AS policy_status,
    p.policyActivatedDate,
    p.created_at                  AS policy_created,
    (SELECT COUNT(*) FROM realpay_client_contracts c
       WHERE c.policy_id = p.id OR c.client_number = p.policyNumber)          AS rp_contracts,
    (SELECT COUNT(*) FROM realpay_contract_installments i
       WHERE i.clientNumber = p.policyNumber
          OR i.clientNumber LIKE CONCAT(p.policyNumber, '/%')
          OR i.policy_id = p.id)                                              AS rp_installments,
    (SELECT COUNT(*) FROM realpay_contract_installments i
       WHERE (i.clientNumber = p.policyNumber
              OR i.clientNumber LIKE CONCAT(p.policyNumber, '/%')
              OR i.policy_id = p.id)
         AND i.InstalmentStatus = 'S')                                        AS realpay_collected,
    (SELECT COALESCE(SUM(i.InstalmentAmount), 0) FROM realpay_contract_installments i
       WHERE (i.clientNumber = p.policyNumber
              OR i.clientNumber LIKE CONCAT(p.policyNumber, '/%')
              OR i.policy_id = p.id)
         AND i.InstalmentStatus = 'S')                                        AS realpay_collected_value,
    (SELECT COUNT(*) FROM payment_transactions pt
       WHERE pt.policyNumber = p.policyNumber OR pt.policy_id = p.id)         AS graphite_payments,
    (SELECT COUNT(*) FROM payment_transactions pt
       WHERE (pt.policyNumber = p.policyNumber OR pt.policy_id = p.id)
         AND pt.status = 'SUCCESS')                                           AS graphite_success_payments,
    (SELECT COUNT(*) FROM realpay_webhook_response w
       WHERE w.policyNumber = p.policyNumber
          OR w.policyNumber LIKE CONCAT(p.policyNumber, '/%'))                AS webhook_log_rows
FROM policies p
WHERE p.policyNumber IN ('MIS2026215341', 'MIS2026215350', 'MIS2026214543', 'MIS2026215336');


-- ─────────────────────────────────────────────────────────────────────────
-- 2. THE CONTRACTS
--    `policy_id IS NULL` means the contract cannot be joined back to a policy
--    — one of the ways an inbound instalment webhook becomes unattachable.
-- ─────────────────────────────────────────────────────────────────────────
SELECT c.id, c.policy_id, c.client_number, c.contract_number, c.status,
       c.created_at, c.updated_at
FROM realpay_client_contracts c
JOIN policies p ON (c.policy_id = p.id OR c.client_number = p.policyNumber)
WHERE p.policyNumber IN ('MIS2026215341', 'MIS2026215350', 'MIS2026214543', 'MIS2026215336')
ORDER BY p.policyNumber, c.id;


-- ─────────────────────────────────────────────────────────────────────────
-- 3. THE INSTALMENTS
--    `payment_id IS NULL` on an InstalmentStatus='S' row is a collected debit
--    with no Graphite payment against its reference number.
-- ─────────────────────────────────────────────────────────────────────────
SELECT i.id, i.policy_id, i.clientNumber, i.contractNumber,
       i.InstalmentReferenceNumber, i.InstalmentSequence, i.InstalmentStatus,
       i.InstalmentAmount, i.InstalmentActionDate, i.instalmentResponse,
       i.created_at,
       pt.id      AS payment_id,
       pt.status  AS payment_status,
       pt.amount  AS payment_amount
FROM realpay_contract_installments i
LEFT JOIN payment_transactions pt
       ON pt.referenceNumber = i.InstalmentReferenceNumber
WHERE i.clientNumber IN ('MIS2026215341', 'MIS2026215350', 'MIS2026214543', 'MIS2026215336')
   OR i.clientNumber LIKE CONCAT('MIS2026215341', '/%')
   OR i.clientNumber LIKE CONCAT('MIS2026215350', '/%')
   OR i.clientNumber LIKE CONCAT('MIS2026214543', '/%')
   OR i.clientNumber LIKE CONCAT('MIS2026215336', '/%')
ORDER BY i.clientNumber, i.id;


-- ─────────────────────────────────────────────────────────────────────────
-- 4. WHAT REALPAY ACTUALLY TOLD US
--    The webhook log is written even on the paths that wrote no payment, so a
--    'SUCCESS' row here with nothing in query 3's payment_id is direct
--    evidence that the notification arrived and was dropped.
-- ─────────────────────────────────────────────────────────────────────────
SELECT w.id, w.policyNumber, w.instalmentSequence, w.instalmentRefNumber,
       w.installmentAmount, w.instalmentActionDate, w.bankResponse, w.status,
       w.created_at,
       pt.id AS payment_id
FROM realpay_webhook_response w
LEFT JOIN payment_transactions pt ON pt.referenceNumber = w.instalmentRefNumber
WHERE w.policyNumber IN ('MIS2026215341', 'MIS2026215350', 'MIS2026214543', 'MIS2026215336')
   OR w.policyNumber LIKE CONCAT('MIS2026215341', '/%')
   OR w.policyNumber LIKE CONCAT('MIS2026215350', '/%')
   OR w.policyNumber LIKE CONCAT('MIS2026214543', '/%')
   OR w.policyNumber LIKE CONCAT('MIS2026215336', '/%')
ORDER BY w.policyNumber, w.id;


-- ─────────────────────────────────────────────────────────────────────────
-- 5. THE BUFFERED DELIVERIES
--    status='processed' on a row whose payment is missing is the smoking gun
--    for the drain defect: the handler returned 401 (its catch-all) or no
--    Response at all, and the old `$statusCode < 500` check recorded that as
--    applied. The delivery was consumed and never retried.
-- ─────────────────────────────────────────────────────────────────────────
SELECT id, source, event_type, status, attempts, created_at, updated_at,
       LEFT(payload, 500) AS payload
FROM webhook_buffer
WHERE payload LIKE CONCAT('%', 'MIS2026215341', '%')
   OR payload LIKE CONCAT('%', 'MIS2026215350', '%')
   OR payload LIKE CONCAT('%', 'MIS2026214543', '%')
   OR payload LIKE CONCAT('%', 'MIS2026215336', '%')
ORDER BY id;


-- ─────────────────────────────────────────────────────────────────────────
-- 6. FLEET-WIDE EXPOSURE
--    How big is this beyond the four reported policies? Collected instalments
--    with no payment record, by month.
-- ─────────────────────────────────────────────────────────────────────────
SELECT DATE_FORMAT(i.InstalmentActionDate, '%Y-%m') AS month,
       COUNT(*)                        AS collected_without_payment,
       ROUND(SUM(i.InstalmentAmount), 2) AS value_at_risk
FROM realpay_contract_installments i
LEFT JOIN payment_transactions pt
       ON pt.referenceNumber = i.InstalmentReferenceNumber
WHERE i.InstalmentStatus = 'S'
  AND pt.id IS NULL
  AND i.InstalmentActionDate >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
GROUP BY month
ORDER BY month DESC;


-- ─────────────────────────────────────────────────────────────────────────
-- 7. UNATTACHABLE CLIENT NUMBERS
--    Instalments whose clientNumber matches no policy at all — the population
--    that could never have produced a payment row, whatever else was fixed.
--    Expect '{policyNumber}/n' suffixes and MQ-/BQ- quote numbers here.
-- ─────────────────────────────────────────────────────────────────────────
SELECT i.clientNumber,
       COUNT(*)                          AS instalments,
       SUM(i.InstalmentStatus = 'S')     AS collected,
       ROUND(SUM(CASE WHEN i.InstalmentStatus = 'S'
                      THEN i.InstalmentAmount ELSE 0 END), 2) AS collected_value,
       MIN(i.created_at) AS first_seen,
       MAX(i.created_at) AS last_seen
FROM realpay_contract_installments i
LEFT JOIN policies p ON p.policyNumber = i.clientNumber
WHERE p.id IS NULL
  AND i.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
GROUP BY i.clientNumber
HAVING collected > 0
ORDER BY collected_value DESC
LIMIT 100;


-- ─────────────────────────────────────────────────────────────────────────
-- 8. AFTER THE FIX IS DEPLOYED
--    The open reconciliation queue. Empty = every RealPay debit is reflected.
--    (Table is created by
--     2026_08_13_000000_create_realpay_reflection_exceptions_table.php)
-- ─────────────────────────────────────────────────────────────────────────
-- SELECT id, instalment_reference, instalment_sequence, client_number,
--        contract_number, instalment_status, amount, action_date, reason,
--        error, occurrences, created_at
-- FROM realpay_reflection_exceptions
-- WHERE resolved_at IS NULL
-- ORDER BY created_at DESC;
