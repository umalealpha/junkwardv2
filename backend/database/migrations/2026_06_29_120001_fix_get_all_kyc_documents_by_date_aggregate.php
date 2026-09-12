<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fix `getAllKYCDocumentsByDate` — restore agent-level aggregation.
 *
 * Regression: 2026_06_11_120001 rewrote this SP to return one DETAIL
 * row per KYC document (no `count` column, no GROUP BY agent) while
 * fixing DomCom-blindness. But DailyKYCReport::handle() sums
 * `$sub_array->count` and the Blade prints `{{ $doc->count }}` per
 * agent row. With no `count` column, every total computed to 0 and the
 * PDF rendered "No documents today" everywhere — even though running
 * the SP by hand returned rows.
 *
 * This restores the aggregated shape the report contract expects
 * (one row per agent + COUNT) while KEEPING the DomCom LEFT JOIN and
 * the extended doc-non-null filter. COUNT(DISTINCT ck.id) guards
 * against row fan-out from the policies join and the DomCom OR-join.
 */
return new class extends Migration {
    public function up(): void
    {
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
                WHERE CAST(ck.created_at AS DATE) = CURDATE()
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
        DB::unprepared('DROP PROCEDURE IF EXISTS getAllKYCDocumentsByDate');
    }
};
