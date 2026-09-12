<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fix the daily KYC report emailing empty/null PDFs.
 *
 * Two compounding bugs, both proven against live data (2026-07-28):
 *
 * BUG 1 — wrong date column.
 *   The procedures filtered `CAST(ck.created_at AS DATE) = CURDATE()`
 *   (submission date). Only ~2 docs are *created* per day; KYC *decisions*
 *   happen on older docs and are recorded on `updated_at`. So the report
 *   counted the wrong day and came out empty.
 *
 * BUG 2 — wrong status vocabulary.
 *   customer_kyc.status carries TWO live vocabularies:
 *     - capitalized  : 'Approve' / 'Unapprove' / 'Unchecked'  (admin flow)
 *     - lowercase    : 'approved' / 'rejected'                (re-KYC flow)
 *   The procedures only matched the capitalized ones, but the lowercase flow
 *   now does the bulk of decisions (28 Jul: 23 approved + 140 rejected vs
 *   13 Approve + 7 Unapprove). So even after Bug 1 the report caught ~11%.
 *
 * Fix: filter on the action date and treat both vocabularies as one bucket.
 *   - APPROVED   bucket → status IN ('Approve','approved')
 *   - REJECTED   bucket → status IN ('Unapprove','rejected')
 *   - UNCHECKED  bucket → status = 'Unchecked', still keyed on created_at
 *     (never decided, so no action date exists — behaviour preserved).
 * Recheck / Recheck(KYC Expired) are intentionally NOT counted (unchanged).
 *
 * Validation (live, 2026-07-28, full logic incl. joins + doc-non-null):
 *   current procedure  → 0 rows
 *   corrected          → 168 decided docs across 179 agents (30 appr/138 rej)
 *
 * Only the WHERE (date + status) changes; SELECT list, DomCom join and the
 * doc-non-null filter are identical to 2026_06_29_1200xx.
 */
return new class extends Migration {
    public function up(): void
    {
        // ── getKYCDataByAgent ────────────────────────────────────────────
        // Called once each with 'Approve' / 'Unapprove' / 'Unchecked'. Map
        // each call to its full vocabulary; Unchecked stays on created_at.
        DB::unprepared('DROP PROCEDURE IF EXISTS getKYCDataByAgent');
        DB::unprepared(<<<'SQL'
            CREATE PROCEDURE getKYCDataByAgent(IN inStatus VARCHAR(50))
            BEGIN
                SELECT
                    p.agent_id,
                    ag.name      AS agency_name,
                    u.firstName,
                    u.lastName,
                    COUNT(DISTINCT ck.id) AS count,
                    GROUP_CONCAT(DISTINCT NULLIF(ck.reason, '') SEPARATOR '<br>') AS reason
                FROM customer_kyc ck
                INNER JOIN policies p
                    ON p.customer_id = ck.customer_id
                INNER JOIN users u
                    ON p.agent_id = u.id
                LEFT JOIN agencies ag
                    ON u.agency_id = ag.id
                LEFT JOIN (
                    SELECT kd1.*
                    FROM customer_kyc_dom_com kd1
                    INNER JOIN (
                        SELECT
                            COALESCE(customer_kyc_id, 0) AS customer_kyc_id,
                            customer_id,
                            MAX(id) AS max_id
                        FROM customer_kyc_dom_com
                        GROUP BY COALESCE(customer_kyc_id, 0), customer_id
                    ) latest
                        ON latest.max_id = kd1.id
                ) kd
                    ON  (kd.customer_kyc_id = ck.id)
                     OR (kd.customer_kyc_id IS NULL AND kd.customer_id = ck.customer_id)
                WHERE CAST(
                          CASE WHEN inStatus = 'Unchecked'
                               THEN ck.created_at
                               ELSE ck.updated_at
                          END AS DATE) = CURDATE()
                  AND (
                         (inStatus = 'Approve'   AND ck.status IN ('Approve','approved'))
                      OR (inStatus = 'Unapprove' AND ck.status IN ('Unapprove','rejected'))
                      OR (inStatus = 'Unchecked' AND ck.status = 'Unchecked')
                  )
                  AND (
                      ck.omang             IS NOT NULL
                   OR ck.omangBack         IS NOT NULL
                   OR ck.driving_license   IS NOT NULL
                   OR ck.proof_income      IS NOT NULL
                   OR ck.passport          IS NOT NULL
                   OR ck.proof_residence   IS NOT NULL
                   OR kd.kyc_form                       IS NOT NULL
                   OR kd.data_protection_form           IS NOT NULL
                   OR kd.certificate_of_incorporation   IS NOT NULL
                   OR kd.extract_controllers            IS NOT NULL
                   OR kd.resolution                     IS NOT NULL
                   OR kd.proof_business_address         IS NOT NULL
                   OR kd.proof_residential_address      IS NOT NULL
                   OR kd.directors_id_front             IS NOT NULL
                   OR kd.directors_id_back              IS NOT NULL
                   OR kd.directors_passport             IS NOT NULL
                   OR kd.shareholders_id_front          IS NOT NULL
                   OR kd.shareholders_id_back           IS NOT NULL
                   OR kd.shareholders_passport          IS NOT NULL
                  )
                GROUP BY p.agent_id, ag.name, u.firstName, u.lastName;
            END
            SQL);

        // ── getKYCDataApprovedRejected ───────────────────────────────────
        // Decided today = any of the four decision statuses, keyed on updated_at.
        DB::unprepared('DROP PROCEDURE IF EXISTS getKYCDataApprovedRejected');
        DB::unprepared(<<<'SQL'
            CREATE PROCEDURE getKYCDataApprovedRejected()
            BEGIN
                SELECT
                    p.agent_id,
                    ag.name      AS agency_name,
                    u.firstName,
                    u.lastName,
                    COUNT(DISTINCT ck.id) AS count
                FROM customer_kyc ck
                INNER JOIN policies p
                    ON p.customer_id = ck.customer_id
                INNER JOIN users u
                    ON p.agent_id = u.id
                LEFT JOIN agencies ag
                    ON u.agency_id = ag.id
                LEFT JOIN (
                    SELECT kd1.*
                    FROM customer_kyc_dom_com kd1
                    INNER JOIN (
                        SELECT
                            COALESCE(customer_kyc_id, 0) AS customer_kyc_id,
                            customer_id,
                            MAX(id) AS max_id
                        FROM customer_kyc_dom_com
                        GROUP BY COALESCE(customer_kyc_id, 0), customer_id
                    ) latest
                        ON latest.max_id = kd1.id
                ) kd
                    ON  (kd.customer_kyc_id = ck.id)
                     OR (kd.customer_kyc_id IS NULL AND kd.customer_id = ck.customer_id)
                WHERE CAST(ck.updated_at AS DATE) = CURDATE()
                  AND ck.status IN ('Approve','approved','Unapprove','rejected')
                  AND (
                      ck.omang             IS NOT NULL
                   OR ck.omangBack         IS NOT NULL
                   OR ck.driving_license   IS NOT NULL
                   OR ck.proof_income      IS NOT NULL
                   OR ck.passport          IS NOT NULL
                   OR ck.proof_residence   IS NOT NULL
                   OR kd.kyc_form                       IS NOT NULL
                   OR kd.data_protection_form           IS NOT NULL
                   OR kd.certificate_of_incorporation   IS NOT NULL
                   OR kd.extract_controllers            IS NOT NULL
                   OR kd.resolution                     IS NOT NULL
                   OR kd.proof_business_address         IS NOT NULL
                   OR kd.proof_residential_address      IS NOT NULL
                   OR kd.directors_id_front             IS NOT NULL
                   OR kd.directors_id_back              IS NOT NULL
                   OR kd.directors_passport             IS NOT NULL
                   OR kd.shareholders_id_front          IS NOT NULL
                   OR kd.shareholders_id_back           IS NOT NULL
                   OR kd.shareholders_passport          IS NOT NULL
                  )
                GROUP BY p.agent_id, ag.name, u.firstName, u.lastName;
            END
            SQL);

        // ── getAllKYCDocumentsByDate ─────────────────────────────────────
        // "All documents with activity today" — updated_at, no status filter,
        // so the summary table reflects the same actioned-today window.
        DB::unprepared('DROP PROCEDURE IF EXISTS getAllKYCDocumentsByDate');
        DB::unprepared(<<<'SQL'
            CREATE PROCEDURE getAllKYCDocumentsByDate()
            BEGIN
                SELECT
                    p.agent_id,
                    ag.name      AS agency_name,
                    u.firstName,
                    u.lastName,
                    COUNT(DISTINCT ck.id) AS count
                FROM customer_kyc ck
                INNER JOIN policies p
                    ON p.customer_id = ck.customer_id
                INNER JOIN users u
                    ON p.agent_id = u.id
                LEFT JOIN agencies ag
                    ON u.agency_id = ag.id
                LEFT JOIN (
                    SELECT kd1.*
                    FROM customer_kyc_dom_com kd1
                    INNER JOIN (
                        SELECT
                            COALESCE(customer_kyc_id, 0) AS customer_kyc_id,
                            customer_id,
                            MAX(id) AS max_id
                        FROM customer_kyc_dom_com
                        GROUP BY COALESCE(customer_kyc_id, 0), customer_id
                    ) latest
                        ON latest.max_id = kd1.id
                ) kd
                    ON  (kd.customer_kyc_id = ck.id)
                     OR (kd.customer_kyc_id IS NULL AND kd.customer_id = ck.customer_id)
                WHERE CAST(ck.updated_at AS DATE) = CURDATE()
                  AND (
                      ck.omang             IS NOT NULL
                   OR ck.omangBack         IS NOT NULL
                   OR ck.driving_license   IS NOT NULL
                   OR ck.proof_income      IS NOT NULL
                   OR ck.passport          IS NOT NULL
                   OR ck.proof_residence   IS NOT NULL
                   OR kd.kyc_form                       IS NOT NULL
                   OR kd.data_protection_form           IS NOT NULL
                   OR kd.certificate_of_incorporation   IS NOT NULL
                   OR kd.extract_controllers            IS NOT NULL
                   OR kd.resolution                     IS NOT NULL
                   OR kd.proof_business_address         IS NOT NULL
                   OR kd.proof_residential_address      IS NOT NULL
                   OR kd.directors_id_front             IS NOT NULL
                   OR kd.directors_id_back              IS NOT NULL
                   OR kd.directors_passport             IS NOT NULL
                   OR kd.shareholders_id_front          IS NOT NULL
                   OR kd.shareholders_id_back           IS NOT NULL
                   OR kd.shareholders_passport          IS NOT NULL
                  )
                GROUP BY p.agent_id, ag.name, u.firstName, u.lastName;
            END
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS getKYCDataByAgent');
        DB::unprepared('DROP PROCEDURE IF EXISTS getKYCDataApprovedRejected');
        DB::unprepared('DROP PROCEDURE IF EXISTS getAllKYCDocumentsByDate');
    }
};
