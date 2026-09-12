<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FAC slip terms — the fields the REAL Alpha Direct slip carries.
 *
 * Added after reading the 12 signed 2026 Professional Indemnity slips supplied
 * by Kago Tshutlhedi on 30 July 2026 (held, hard-locked, at
 * ~/.claude/skills/prat-skill/facdoc/slip-samples/). The slip is not a table of
 * placement lines — it is a single-risk TERM SHEET followed by an acceptance
 * panel the reinsurer signs and stamps, and it carries eight terms the register
 * did not hold:
 *
 *   Broker/Agent · Description of Risk · Situation/territorial Scope ·
 *   Basis of Cover · Risk Ceded to Re-Insurer(s) · Limit of Indemnity ·
 *   Deductible · Prepared By
 *
 * Without these the generated slip would not match the document the reinsurers
 * already accept, and Reinsurance would keep producing it by hand.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('fac_slips')) {
            return;
        }

        Schema::table('fac_slips', function (Blueprint $t) {
            // "DIRECT" when no broker fronts the placement — the samples print
            // the word, they do not leave the row blank.
            if (!Schema::hasColumn('fac_slips', 'broker_agent')) {
                $t->string('broker_agent', 160)->default('DIRECT')->after('risk_carrier');
            }
            if (!Schema::hasColumn('fac_slips', 'cover_granted')) {
                $t->string('cover_granted', 200)->nullable()->after('broker_agent');
            }
            if (!Schema::hasColumn('fac_slips', 'description_of_risk')) {
                $t->text('description_of_risk')->nullable()->after('cover_granted');
            }
            if (!Schema::hasColumn('fac_slips', 'territorial_scope')) {
                $t->string('territorial_scope', 300)
                    ->default('BOTSWANA and other Territories as Per Policy Document')
                    ->after('description_of_risk');
            }
            if (!Schema::hasColumn('fac_slips', 'basis_of_cover')) {
                // "Claims Made Basis" on the PI samples; Occurrence on others.
                $t->string('basis_of_cover', 120)->nullable()->after('territorial_scope');
            }
            if (!Schema::hasColumn('fac_slips', 'risk_ceded_text')) {
                $t->string('risk_ceded_text', 200)->nullable()->after('basis_of_cover')
                    ->comment('e.g. "100% (Nil Retention by Reinsured)"');
            }
            if (!Schema::hasColumn('fac_slips', 'limit_of_indemnity')) {
                $t->decimal('limit_of_indemnity', 20, 2)->nullable()->after('risk_ceded_text');
            }
            if (!Schema::hasColumn('fac_slips', 'deductible_text')) {
                $t->string('deductible_text', 300)->nullable()->after('limit_of_indemnity')
                    ->comment('e.g. "10% of loss minimum P25,000.00 EEL"');
            }
            if (!Schema::hasColumn('fac_slips', 'period_from')) {
                $t->date('period_from')->nullable()->after('deductible_text');
            }
            if (!Schema::hasColumn('fac_slips', 'period_to')) {
                $t->date('period_to')->nullable()->after('period_from');
            }
            if (!Schema::hasColumn('fac_slips', 'prepared_by_name')) {
                $t->string('prepared_by_name', 160)->nullable()->after('period_to');
            }
        });

        // The acceptance panel. One row per accepting reinsurer, because a slip
        // can be shared — the samples show "100% of 100%" to one company, but the
        // panel is a table precisely so a panel of several can each sign a share.
        if (!Schema::hasTable('fac_slip_acceptances')) {
            Schema::create('fac_slip_acceptances', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('fac_slip_id')->index();
                $t->unsignedBigInteger('reinsurer_id')->nullable();
                $t->string('accepting_company', 160);
                $t->decimal('share_pct', 9, 6)->nullable()->comment('share of the ceded 100%');
                $t->decimal('amount', 20, 2)->nullable()->comment('limit accepted');

                // Filled in when the countersigned slip comes back. Until then the
                // slip is sent-but-not-accepted, and that difference matters:
                // an unaccepted slip is not cover.
                $t->string('signatory_name', 160)->nullable();
                $t->date('accepted_on')->nullable();
                $t->string('signed_document_path', 500)->nullable();

                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fac_slip_acceptances');

        if (!Schema::hasTable('fac_slips')) {
            return;
        }
        Schema::table('fac_slips', function (Blueprint $t) {
            foreach ([
                'broker_agent', 'cover_granted', 'description_of_risk',
                'territorial_scope', 'basis_of_cover', 'risk_ceded_text',
                'limit_of_indemnity', 'deductible_text', 'period_from',
                'period_to', 'prepared_by_name',
            ] as $col) {
                if (Schema::hasColumn('fac_slips', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};
