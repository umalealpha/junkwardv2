<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Update `getKYCDataApprovedRejected` SP — the version called by
 * DailyKYCReport::handle() to feed the $action bucket in the daily
 * KYC PDF ("actions for today" section).
 *
 * Same DomCom-blindness bug as getAllKYCDocumentsByDate. The fix is
 * structurally identical:
 *   1. LEFT JOIN customer_kyc_dom_com (latest row per customer_kyc)
 *   2. Surface DomCom doc columns in SELECT
 *   3. Extend the doc-non-null filter to include DomCom columns
 *
 * Verify the SP NAME below matches your DB. If different, change both
 * the DROP and CREATE statements accordingly.
 *
 * Status filter preserved exactly: Approve OR Unapprove only. The
 * original SP used the `||` shorthand for OR; this migration uses
 * explicit OR. Semantically identical today, but `||` flips to string
 * concatenation under `PIPES_AS_CONCAT` sql_mode — defensive.
 */
return new class extends Migration {
    public function up(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS getKYCDataApprovedRejected');

        DB::unprepared(<<<'SQL'
            CREATE PROCEDURE getKYCDataApprovedRejected()
            BEGIN
                SELECT
                    ck.id                              AS kyc_id,
                    p.agent_id,
                    ag.name                            AS agency_name,
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
                  AND (ck.status = 'Approve' OR ck.status = 'Unapprove')
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
                  );
            END
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS getKYCDataApprovedRejected');
    }
};
