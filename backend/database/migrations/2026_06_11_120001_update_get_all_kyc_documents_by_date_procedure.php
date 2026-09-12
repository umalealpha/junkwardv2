<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Update `getAllKYCDocumentsByDate` SP — the version called from
 * DailyKYCReport::handle() that feeds the $all bucket in the daily
 * KYC PDF.
 *
 * Why
 * ───
 * Daily KYC Report has been emailing reviewers with "0 documents
 * today" even when there WAS activity. Root cause: the SP's
 * doc-non-null filter only checks columns on customer_kyc
 * (omang / passport / driving_license / etc.). For DOM/COM customers
 * those columns are NULL on the customer_kyc stub — the actual docs
 * live on customer_kyc_dom_com. The filter excluded every DomCom row
 * from the report.
 *
 * Fix
 * ───
 * 1. LEFT JOIN customer_kyc_dom_com (latest row per customer_kyc,
 *    via the customer_kyc_id link with a customer_id fallback for
 *    historical rows written before that column was wired up).
 * 2. Surface the DomCom doc columns in SELECT so the PDF renderer
 *    has access to them.
 * 3. Extend the WHERE "at least one doc non-null" filter to include
 *    the DomCom columns alongside the existing customer_kyc ones.
 *
 * Verify the SP NAME below matches your DB before applying. If your
 * production SP is registered under a different name, change both the
 * DROP and CREATE statements accordingly.
 */
return new class extends Migration {
    public function up(): void
    {
        // Idempotent — re-applying this migration overwrites the SP
        // body cleanly without manual rollback.
        DB::unprepared('DROP PROCEDURE IF EXISTS getAllKYCDocumentsByDate');

        DB::unprepared(<<<'SQL'
            CREATE PROCEDURE getAllKYCDocumentsByDate()
            BEGIN
                SELECT
                    ck.id                              AS kyc_id,
                    ag.name                            AS agency_name,
                    p.agent_id,
                    u.firstName,
                    u.lastName,
                    ck.customer_id,
                    -- MIS document columns (customer_kyc)
                    ck.omang,
                    ck.omangBack,
                    ck.passport,
                    ck.proof_income,
                    ck.proof_residence,
                    ck.driving_license,
                    -- DomCom document columns (customer_kyc_dom_com)
                    kd.kyc_form,
                    kd.data_protection_form,
                    kd.certificate_of_incorporation,
                    kd.extract_controllers,
                    kd.resolution,
                    kd.proof_business_address,
                    kd.proof_residential_address,
                    kd.directors_id_front,
                    kd.directors_id_back,
                    kd.directors_passport,
                    kd.shareholders_id_front,
                    kd.shareholders_id_back,
                    kd.shareholders_passport,
                    ck.created_at
                FROM customer_kyc ck
                INNER JOIN policies p
                    ON p.customer_id = ck.customer_id
                INNER JOIN users u
                    ON p.agent_id = u.id
                LEFT JOIN agencies ag
                    ON u.agency_id = ag.id
                -- Latest customer_kyc_dom_com row per customer_kyc.
                -- Prefers the customer_kyc_id link; falls back to
                -- customer_id for older rows pre-dating that column.
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
                WHERE CAST(ck.created_at AS DATE) = CURDATE()
                  AND (
                      -- MIS docs
                      ck.omang             IS NOT NULL
                   OR ck.omangBack         IS NOT NULL
                   OR ck.driving_license   IS NOT NULL
                   OR ck.proof_income      IS NOT NULL
                   OR ck.passport          IS NOT NULL
                   OR ck.proof_residence   IS NOT NULL
                      -- DomCom docs
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
                  );
            END
            SQL);
    }

    public function down(): void
    {
        // Rollback simply drops the procedure. To restore the previous
        // body, paste the prior SP definition into a fresh up() block.
        DB::unprepared('DROP PROCEDURE IF EXISTS getAllKYCDocumentsByDate');
    }
};
