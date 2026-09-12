<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Seed claim_type_forms from the claims team's own mapping, using the claim
 * types that ACTUALLY appear in the data.
 *
 * Why this command exists: the original migration seeded eight tidy placeholder
 * types ('Accident', 'Motor', 'Theft', 'Glass', 'Life', 'Cellphone',
 * 'Third Party', 'Key Loss') with no form attached. Measured against 2,950 live
 * claims on 11-Aug-2026 that list is wrong in both directions — it invents
 * 'Third Party', and it misses WORKERSCOMPENSATION, BUSINESSALLRISKS, FIRE,
 * BUILDINGSCOMBINED and PERSONALALLRISKS, which together are over 400 claims.
 *
 * The strings below are the LIVE values, verbatim, including their odd casing
 * and spacing — the listener matches on an exact lower-cased comparison, so a
 * tidied-up spelling simply never matches and the claimant gets nothing.
 *
 * Authority for type -> form is `Claims in Graphite - Response.pdf` (claims
 * team, Jul-2026) plus the claims team current form pack (11-Aug-2026).
 * Idempotent: safe to run repeatedly; never overwrites a row an admin edited
 * unless --force is given.
 */
class SeedClaimFormLibrary extends Command
{
    protected $signature = 'claims:seed-form-library {--force : overwrite existing rows} {--activate : mark seeded rows active}';

    protected $description = 'Seed the claim-type to claim-form mapping from the claims team pack';

    /**
     * [live claim_type, form title, form file, template key, sort]
     *
     * `template_key` picks the pre-fill template: motor_accident, glass, or null
     * for the generic one. Ordered by live claim volume so the dialog lists the
     * common forms first.
     *
     * DELIBERATELY ABSENT — the two types still without a safe mapping:
     * LIFE (7 claims — no form exists; awaiting a fallback decision) and MONEY
     * (11 claims — needs a classification correction first, Fidelity separate
     * from Burglary, per the claims team, 12-Aug). Seeding a guess would email a claimant
     * the wrong form, so they stay out until settled. 'Accident' IS seeded to
     * the motor form but flagged uncertain in the UI, because a human picks it.
     * Hospital CashBack, Accidental Death, Bonu and STATEDBENEFITS were added
     * once the claims team supplied / confirmed their forms (12-Aug).
     */
    private const MAP = [
        ['Accident',                'Motor Accident Claim Form',              'motor-accident.pdf',            'motor_accident', 10],
        ['Glass',                   'Glass Claim Form',                       'glass.pdf',                     'glass',          20],
        ['Motor',                   'Motor Accident Claim Form',              'motor-accident.pdf',            'motor_accident', 30],
        ['MOTORTRADERSINTERNAL',    'Motor Accident Claim Form',              'motor-accident.pdf',            'motor_accident', 40],
        ['MOTORTRADERSEXTERNAL',    'Motor Accident Claim Form',              'motor-accident.pdf',            'motor_accident', 50],
        ['Legal',                   'Legal Claim Form',                       'legal.pdf',                     null,             60],
        ['WORKERSCOMPENSATION',     "Workmen's Compensation Claim Form",      'workmens-compensation.pdf',     null,             70],
        ['BUSINESSALLRISKS',        'All Risk Claim Form',                    'all-risk.pdf',                  null,             80],
        ['PERSONALALLRISKS',        'All Risk Claim Form',                    'all-risk.pdf',                  null,             90],
        ['FIRE',                    'Fire Claim Form',                        'fire.pdf',                      null,            100],
        ['BUILDINGSCOMBINED',       'Property Loss Claim Form',               'property-loss.pdf',             null,            110],
        ['HOUSEOWNER-BUILDINGS',    'Property Loss Claim Form',               'property-loss.pdf',             null,            120],
        ['HOUSEHOLDERS-CONTENTS',   'Property Loss Claim Form',               'property-loss.pdf',             null,            130],
        ['HOUSEOWNERS',             'Property Loss Claim Form',               'property-loss.pdf',             null,            140],
        ['OFFICECONTENTS',          'Property Loss Claim Form',               'property-loss.pdf',             null,            150],
        ['THEFT',                   'Burglary Claim Form',                    'burglary.pdf',                  null,            160],
        ['Key Loss',                'Locks and Keys Claim Form',              'locks-and-keys.pdf',            null,            170],
        ['Cellphone',               'Mobile & Electronic Device Claim Form',  'mobile-electronic-device.pdf',  null,            180],
        ['MOBILEELECTRONICDEVICES', 'Mobile & Electronic Device Claim Form',  'mobile-electronic-device.pdf',  null,            190],
        ['ELECTRONICEQUIPMENT',     'Mobile & Electronic Device Claim Form',  'mobile-electronic-device.pdf',  null,            200],
        ['LIABILITY',               'Liability Claim Form',                   'liability.pdf',                 null,            210],
        ['GOODSINTRANSIT',          'Goods in Transit Claim Form',            'goods-in-transit.pdf',          null,            220],
        ['FIDELITYGUARANTEE',       'Fidelity Guarantee Claim Form',          'fidelity.pdf',                  null,            230],
        ['DEFECTIVEWORKMANSHIP',    'Defective Workmanship Claim Form',       'defective-workmanship.pdf',     null,            240],
        ['BUSINESSINTERRUPTION',    'Business Interruption Claim Form',       'business-interruption.pdf',     null,            250],
        ['PLANTALLRISKS',           'Plant All Risks Claim Form',             'plant-all-risks.pdf',           null,            260],
        // Newly supplied by the claims team, 12-Aug-2026 — these three claim types
        // previously had NO form, so they were held out. Now mapped.
        ['Hospital CashBack',       'Hospital Cash Back Claim Form',          'hospital-cash-back.pdf',        null,            270],
        ['Accidental Death',        'Accidental Death Claim Form',            'accidental-death.pdf',          null,            280],
        // NOTE: ACCIDENTALDAMAGE is deliberately NOT mapped. It is a PROPERTY
        // claim type — Graphite groups it with PROPERTYDAMAGE / HOUSEHOLDERS /
        // HOUSEOWNERS throughout its claims controllers — NOT a death claim.
        // Mapping it to the Accidental *Death* form on word-resemblance would
        // email a property claimant a death form. Held out until the claims team
        // confirms its form (likely Property Loss), per this seeder's own rule:
        // never seed a guess.
        ['Bonu',                    'Bonu Legal Claim Form',                  'bonu-legal.pdf',                null,            300],
        // the claims team, 12-Aug-2026: Stated Benefits maps to the Workmen's
        // Compensation (WCA) form. Confirmed, previously undocumented.
        ['STATEDBENEFITS',          "Workmen's Compensation Claim Form",      'workmens-compensation.pdf',     null,            310],
    ];

    /** Where the blank forms live in the file store. */
    private const PREFIX = 'ClaimForms/';

    public function handle(): int
    {
        $force    = (bool) $this->option('force');
        $activate = (bool) $this->option('activate');
        $now      = now();
        $added    = $updated = $skipped = 0;

        foreach (self::MAP as [$type, $title, $file, $template, $sort]) {
            $existing = DB::table('claim_type_forms')->whereRaw('LOWER(claim_type) = ?', [strtolower($type)])->first();

            $row = [
                'form_title'   => $title,
                'pdf_path'     => self::PREFIX . $file,
                'template_key' => $template,
                'sort_order'   => $sort,
                'updated_at'   => $now,
            ];
            if ($activate) {
                $row['active'] = 1;
            }

            if (!$existing) {
                DB::table('claim_type_forms')->insert($row + [
                    'claim_type' => $type,
                    'active'     => $activate ? 1 : 0,
                    'created_at' => $now,
                ]);
                $added++;
                continue;
            }

            if (!$force && !empty($existing->pdf_path)) {
                $skipped++;
                continue;
            }

            DB::table('claim_type_forms')->where('id', $existing->id)->update($row);
            $updated++;
        }

        $this->info("Claim form library: {$added} added, {$updated} updated, {$skipped} left alone.");
        $this->line('Still not seeded: Life (no form — 7 claims awaiting a fallback decision) and MONEY (classification correction needed first — Fidelity separate from Burglary, per the claims team, 12-Aug).');
        $this->line('Blank forms live in the repo at backend/resources/claim-forms/. Publish them to '
            . self::PREFIX . ' in the file store (php artisan claims:publish-form-library) before arming.');

        return 0;
    }
}
