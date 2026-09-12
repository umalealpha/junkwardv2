<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Models\ClaimsConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * claims:seed-masterdata
 *
 * Run-once seeder for the Claims-Tracker -> Graphite master-data migration.
 * The Claims Tracker DB (claims.db) is deleted after cutover, so the exact
 * tracker snapshot values are EMBEDDED here (read from claims.db master_data,
 * ~May 2026) — the command is fully self-contained and needs no tracker DB.
 *
 * What it does (idempotent, additive):
 *   1. Seeds `assessors` from the tracker's motor + non-motor lists.
 *   2. Marks matching `suppliers` rows as approved panel-beater / glass
 *      supplier (match by name, case-insensitive; NEVER creates or guesses;
 *      REPORTS every unmatched tracker name).
 *   3. Seeds the tracker-only lists into `claims_config`: FAC Clients (111),
 *      Policy-Library Products (40) and Coverages (25).
 *   4. `reinsurer` — reports that the tracker has NO reinsurer master list to
 *      seed from (FAC *clients* are the insured, not reinsurers) so nothing is
 *      invented; reinsurers are added via the existing CRUD / treaty data.
 *
 * SAFETY (data-fix-review gate — no blind writes):
 *   - DEFAULT IS DRY-RUN. With no flags, or with --dry-run, it prints the full
 *     plan and writes NOTHING.
 *   - Pass --commit to actually write. Run --dry-run on staging/UAT first and
 *     have it reviewed before committing on PROD.
 *
 *   php artisan claims:seed-masterdata              # dry-run (default)
 *   php artisan claims:seed-masterdata --dry-run    # dry-run (explicit)
 *   php artisan claims:seed-masterdata --commit      # write
 *
 * Idempotent: re-runs skip assessors/config entries that already exist and
 * only set supplier flags that are not already set.
 */
class SeedClaimsMasterData extends Command
{
    protected $signature = 'claims:seed-masterdata
                            {--commit : Actually write. Without this the command is a dry-run and writes nothing.}
                            {--dry-run : Force dry-run (default behaviour); prints the plan, writes nothing.}';

    protected $description = 'Seed claims master-data (assessors, supplier approval flags, claims_config lists) from the tracker snapshot. Dry-run by default.';

    // ─── Embedded tracker snapshot (claims.db master_data, ~May 2026) ─────────

    /** Motor assessors (tracker `assessors`, placeholder "Non-Motor Assessor" excluded). */
    private const MOTOR_ASSESSORS = [
        'Tumiso Motseko',
        'Lesego Kobe',
        'David Judd - Southern Sky',
    ];

    /** Non-motor assessors (tracker `nonMotorAssessors`). */
    private const NON_MOTOR_ASSESSORS = [
        'LMCI',
        'Loss Adjusters Botswana',
        'ANAK Risk Consultant International',
        'Southern Sky',
        'Claim Consult',
        'Nexus',
    ];

    /** Tracker `panelBeaters` (19) — mark matching suppliers as approved panel-beater. */
    private const PANEL_BEATERS = [
        'Specialised Panel Beaters',
        'Tip Top Panel Beaters',
        'Mogoditshane Motors',
        'Car-fil Services',
        'Rolling Wheels',
        'Optimum Panel Beaters',
        'Classic Auto (Pty) Ltd',
        'Status Premium Auto Detailing',
        'BB Motors',
        'Overland Panel Beaters',
        'Korean Auto',
        'Chikos Panel Beaters',
        'Winners Crown Pty Ltd / Colour Century',
        'Automotix',
        "Neo's Panel Beaters",
        'Matlho-Bona Patel Beaters (Mahalapye)',
        'Achievable Enterprise Pty Ltd (Maun)',
        'Prestige Panel Shop (Selebi Phikwe)',
        'TATSAND Mining Services (Jwaneng)',
    ];

    /** Tracker `glassSuppliers` (15) — mark matching suppliers as approved glass. */
    private const GLASS_SUPPLIERS = [
        'PG Glass (Gaborone)',
        'PG Glass (Francistown)',
        'PG Glass (Maun)',
        'Auto & General Glass (Commercial Glass)',
        'Shielders BW',
        'Green Edge Agencies Pty (Ltd)',
        'Arcon Crafts',
        'Windscreen and Glass Pty Ltd',
        'Mancon Alarms and Windscreen Centre (Pty)Ltd',
        'Auto Mate Windscreens',
        'P & T Windscreens',
        '3T Windscreens',
        'Laminated Glass Pty Ltd',
        'Glass and Paint Centre',
        'Win Glass & Aluminium Fabricators PTY Ltd',
    ];

    /** Tracker `facClients` (111) — seed into claims_config category fac_clients. */
    private const FAC_CLIENTS = [
        'M P MINING(PTY) LTD (ENDO)', 'THE FIELDS MALL (PTY) LTD', 'PREMIER CLOTHING (PTY) LTD',
        'SEMOLEMO (PTY) LTD', 'DBN DEVELOPMENTS', 'MASTER FARMER FEEDS (PTY) LTD T/A NUTRI FEEDS BOTSWANA',
        'MP MINING (PTY) LTD', 'FLOTEK INDUSTRIES (PTY) LTD',
        'THE FAR PROPERTY (PTY) LTD AND/OR CHOPPIES DISTRIBUTION CENTRE', 'ARC (PTY) LTD',
        'LETSEMA INSURANCE BROKERS (PTY) LTD', 'CARLUMNI PROFESSIONALS (PTY) LTD', 'PEARL KAMOGELO MOHLOMI',
        'DR LOETO MAZHANI', 'PETROLINE ENERGY BOTSWANA (PTY) LTD', 'M P MINING(PTY) LTD',
        'FIDUCIA SERVICES (PTY) LTD', 'VK CONSULTANCY (PTY) LTD T/A NAIK BUREAU DE CHANGE',
        'PURE CURE PHARMACY/DR MALEBOGO HINES', 'MS MALESHOANE KHOARAI', 'MR BARULAGANYE LEETILE',
        'AFZELIA (PTY) LTD', 'RUTH LORATO MOAMPE', 'EWETSE MOSWEU', 'JOEL JESUOROBO OSAKUE',
        'DIAGNOFIRM MEDICAL LABORATORIES (PTY) LTD & ASSOCIATED COMPANIES', 'DR DIPESALEMA JOEL',
        'THE DOCTORS INN (PTY) LTD', 'DAVID DAMBA', 'TSHOKOLO GILBERT SEKGOPHANA', 'KABO OSMAS TSHIAMO',
        'MEGAGATE PTY LTD T/A MEGAGATE HEALTH PHARMACY', 'GAONE MARIRI', 'THATAYAONE JOEL MOATSHE',
        'THE FIELDS BODY CORPORATE (PTY) LTD', 'TSHEGOFATSO BOGATSU', 'DR SPASOJE RADOVANOVIC',
        'DBN DEVELOPMENT (PTY) LTD', 'NEO PHATSIMO HELEN', 'GORATA EMELDAH MAGETSE', 'DR VIPUL BHATIA',
        'JEREMIAH TLADI & COMPANY', 'DR MBUNGU BEN ETIENNE MBUMBA', 'THABO KUSHATA MOKWENA-RAMAROU',
        'DR JEMAL SHIFA', 'DR VINCENT APPATHURAI',
        'YOKER-BASE (PTY) LTD T/AS MINT CONDITION HEALTH CONSULTANCY/ MR OFENTSE MO',
        'DR UNAMI ELIAS MOSEPELE', 'ABARAHAM CYRIL MUNENO', 'RAMACHANDRAN OTTAPATHU',
        'DR TEBOGO TSHIAMO ORAPELENG', 'DR THEMBISILE DINTLE MOSALAKATANE',
        'DR BAPHALENG BALEKANYE MONOKWANE-THUPISO', 'DR BONANI SAMSON KHONYE', 'DR MOTSAMAI K (PTY) LTD',
        'MRS CAROLINE TSHIRE KEKANA', 'DR GERALD MICHAEL SSEBUNNYA', 'DR CHRISTOPHER NAKABALE',
        'PULSAR MEDICAL IMAGING (PTY) LTD/JULIA NTOKE', 'DR MAFIZUR ABM RAHMAN',
        'FAMILY FIRST INSURANCE (PTY) LTD', 'DANIEL MAINA NGUNJU',
        'SERITI MEDICAL CARE (PTY) LTD/ ELDAD KOFI BOAWOLOR BOATEY', 'HEMANTH (PTY) LTD',
        'NAMETSECANG RABOSIGO', 'THE DOCTORS INN(PTY)LTD', 'DR FAUSTIN ETUMBA MBOKUBA',
        'DR HENDRIX CHUAPA MOSES CHOENE', 'DR KESIILWE GAEBOLAE (TIDBITS (PTY) LTD OPTHAMOLOGISTS',
        'PRIOR WORTH (PTY)LTD', 'DR. JULIUS C MWITA', 'ST INVESTMENTS (PTY) LTD',
        'THE FAR PROPERTY (PTY) LTD', 'TK & DK GROUP OF COMPANIES (PTY) LTD', 'DR BOIKANYO TLHONG',
        'DIKOKO TSA BOTSWANA (PTY) LTD', 'DR MILTON OOKEDITSE', 'NGAMILAND PROPERTY CONSULTANTS (PTY) LTD',
        'RAEL TRAVEL AND TOURS', 'GOMOTI RIVER LODGE', 'OAKANTSE MAKABANYANE', 'TAU GRADING (PTY) LTD',
        'TSWANA PRIDE (PTY) LTD', 'MRS LANA MAGDELINE MOKGOSI', 'BARBARA TALJAARD PTY LTD',
        'RADICAL INVESTMENTS (PTY) LTD T/A FLO TEK IRRIGATION & PIPES', 'LEAH KANAIMBA',
        'MY AFRICAN SAFARI CAMPS (PTY) LTD', 'DR TEBOGO POTENTIAL MOSEKI', 'DR PATRICK AMOS NKHOMA',
        'SERWALEDI ROSINAH PHETLHU', 'DEEP SILVER MOTIVE', 'LW ARCHITECTS (PTY) LTD',
        'MASHATU NATURE RESERVE (PTY) LTD', 'VICTORIA MAISWE', 'MOTHIBI KEDISITSE',
        'DR AOBAKWE BETTY SIAMISANG', 'DR THABISO VIVIENE MOGOTSI', 'DR MUSA BAYANI',
        'M4 CONSULTING (PTY) LTD', 'MAUN CAR HIRE T/AS TRAVEL CREATIONS', 'AISYL (PTY) LTD',
        'LIMPOPO LIPADI BOTSWANA INVESTMENTS T/A LIMPOPO LIPADI GAME AND WILDERNE',
        'CHOPPIES ENTERPRISES LIMITED', 'GRANT THORNTON', 'FINE PHARMACEUTICALS (PTY) LTD',
        'DUSTY PTY LTD', 'FM SOLUTIONS (PTY) LTD', 'DR. KEALEBOGA DANIEL SMITH',
    ];

    /** Tracker `wordingProducts` (40) — seed into claims_config policy_library_products. */
    private const POLICY_LIBRARY_PRODUCTS = [
        'Motor Comprehensive', 'Third Party Motor (P49)', 'Motor Traders External', 'Motor Traders Internal',
        'Mobile & Electronic Device', 'Hospital Cashback (P99)', 'Legal Insurance', 'Funeral Cover',
        'Accidental Death Insurance', 'Glass', 'Key Loss', 'Commercial Building (PAR)', 'Home Owners',
        'Household Contents', 'Business All Risks', 'Business Interruption', 'Fire & Allied Perils', 'Money',
        'Goods In Transit', "Workmen's Compensation", "Employer's Liability (EAR)", 'Public Liability',
        'Fidelity Guarantee', 'Group Personal Accident', 'Personal Accident', 'Travel Insurance',
        'Commercial Car (CAR)', 'Professional Indemnity', 'Medical Malpractice', 'Machinery Breakdown',
        'Marine Cargo (Once Off)', 'Marine Cargo (Open)', 'Marine Directors & Officers', 'Theft',
        'Stated Benefits', 'Computer Equipment', 'Electronic Equipment', 'Office Contents',
        'Accounts Received', 'Accidental Damage',
    ];

    /** Tracker `wordingCoverages` (25) — seed into claims_config policy_library_coverages. */
    private const POLICY_LIBRARY_COVERAGES = [
        'Comprehensive', 'Third Party', 'Third Party Fire & Theft', 'All Risks',
        'Building (Fire & Allied Perils)', 'Fixtures & Fittings', 'Stock & Inventory', 'Burglary & Theft',
        'Money', 'Public Liability', 'Employers Liability', 'Business Interruption', 'Liquor Liability',
        'Commercial Motor Vehicle', 'Professional Indemnity', 'Machinery Breakdown', 'Electronics All Risk',
        'Refrigeration Breakdown', 'Glass Only', 'Key Loss', 'Personal Accident', 'Funeral Cover',
        'Hospital Cashback', 'Legal Cover', 'Mobile Device',
    ];

    /** Panel-beater suppliers live under this supplierType. */
    private const SUPPLIER_TYPE_PANEL = 'Motor Vehicle Accident';
    private const SUPPLIER_TYPE_GLASS = 'Glass';

    public function handle(): int
    {
        // Dry-run is the default. Only --commit writes; --dry-run forces preview.
        $commit = (bool) $this->option('commit') && !$this->option('dry-run');
        $mode   = $commit ? 'COMMIT (writing)' : 'DRY-RUN (no writes)';

        $this->info('claims:seed-masterdata — ' . $mode);
        $this->line(str_repeat('─', 60));

        $this->seedAssessors($commit);
        $this->markSuppliers($commit);
        $this->seedConfigList('fac_clients', self::FAC_CLIENTS, $commit);
        $this->seedConfigList('policy_library_products', self::POLICY_LIBRARY_PRODUCTS, $commit);
        $this->seedConfigList('policy_library_coverages', self::POLICY_LIBRARY_COVERAGES, $commit);
        $this->reportReinsurers();

        $this->line(str_repeat('─', 60));
        if (!$commit) {
            $this->warn('DRY-RUN complete. Nothing was written. Re-run with --commit to apply (after staging/UAT review).');
        } else {
            $this->info('COMMIT complete. Changes written.');
        }

        return self::SUCCESS;
    }

    private function seedAssessors(bool $commit): void
    {
        $this->newLine();
        $this->info('1) Assessors (assessors table)');

        if (!Schema::hasTable('assessors')) {
            $this->error('   assessors table not present — skipping.');
            return;
        }

        $plan = [];
        foreach (self::MOTOR_ASSESSORS as $name) {
            $plan[] = ['name' => $name, 'category' => 'motor'];
        }
        foreach (self::NON_MOTOR_ASSESSORS as $name) {
            $plan[] = ['name' => $name, 'category' => 'non_motor'];
        }
        $this->line('   Skipped placeholder from tracker: "Non-Motor Assessor" (not a real assessor).');

        $created = 0;
        $skipped = 0;
        foreach ($plan as $a) {
            $exists = DB::table('assessors')->whereRaw('LOWER(name) = ?', [mb_strtolower($a['name'])])->exists();
            if ($exists) {
                $skipped++;
                $this->line("   = exists  [{$a['category']}] {$a['name']}");
                continue;
            }
            $created++;
            $this->line("   + create  [{$a['category']}] {$a['name']}");
            if ($commit) {
                DB::table('assessors')->insert([
                    'name'       => $a['name'],
                    'category'   => $a['category'],
                    'is_active'  => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
        $this->line("   → {$created} to create, {$skipped} already present.");
    }

    private function markSuppliers(bool $commit): void
    {
        $this->newLine();
        $this->info('2) Supplier approval flags (suppliers table)');

        $flagsAvailable = Schema::hasColumn('suppliers', 'is_approved_panel_beater')
            && Schema::hasColumn('suppliers', 'is_approved_glass_supplier');
        if (!$flagsAvailable) {
            $this->error('   is_approved_panel_beater / is_approved_glass_supplier columns not present — deploy the add_incentive_flags_to_suppliers migration first. Skipping.');
            return;
        }

        $this->markSupplierGroup('panel-beater', self::PANEL_BEATERS, 'is_approved_panel_beater', self::SUPPLIER_TYPE_PANEL, $commit);
        $this->markSupplierGroup('glass',        self::GLASS_SUPPLIERS, 'is_approved_glass_supplier', self::SUPPLIER_TYPE_GLASS, $commit);
    }

    private function markSupplierGroup(string $label, array $names, string $flagCol, string $supplierType, bool $commit): void
    {
        $this->line("   -- {$label} ({$supplierType}) --");
        $matched = 0;
        $already = 0;
        $unmatched = [];

        foreach ($names as $name) {
            // Case-insensitive exact match on supplierName. Match within the
            // expected supplierType first; fall back to any type so a
            // mis-typed supplierType still gets flagged (reported either way).
            $row = DB::table('suppliers')
                ->whereRaw('LOWER(supplierName) = ?', [mb_strtolower($name)])
                ->where('supplierType', $supplierType)
                ->first();
            if (!$row) {
                $row = DB::table('suppliers')
                    ->whereRaw('LOWER(supplierName) = ?', [mb_strtolower($name)])
                    ->first();
            }

            if (!$row) {
                $unmatched[] = $name;
                $this->line("   ? UNMATCHED  {$name}");
                continue;
            }

            if ((int) ($row->{$flagCol} ?? 0) === 1) {
                $already++;
                $this->line("   = already   [{$row->id}] {$name}");
                continue;
            }

            $matched++;
            $note = $row->supplierType === $supplierType ? '' : "  (supplierType='{$row->supplierType}')";
            $this->line("   + mark      [{$row->id}] {$name}{$note}");
            if ($commit) {
                DB::table('suppliers')->where('id', $row->id)->update([
                    $flagCol     => 1,
                    'updated_at' => now(),
                ]);
            }
        }

        $this->line("   → {$matched} to mark, {$already} already approved, " . count($unmatched) . ' unmatched.');
        if (!empty($unmatched)) {
            $this->warn('   UNMATCHED ' . $label . ' (NOT created — needs manual review): ' . implode(' | ', $unmatched));
        }
    }

    private function seedConfigList(string $category, array $labels, bool $commit): void
    {
        $this->newLine();
        $catLabel = ClaimsConfig::CATEGORIES[$category] ?? $category;
        $this->info("3) claims_config → {$category} ({$catLabel})");

        if (!Schema::hasTable('claims_config')) {
            $this->error('   claims_config table not present — run migrations first. Skipping.');
            return;
        }

        $created = 0;
        $skipped = 0;
        $order = 0;
        foreach ($labels as $labelValue) {
            $order++;
            $exists = ClaimsConfig::where('category', $category)->where('label', $labelValue)->exists();
            if ($exists) {
                $skipped++;
                continue;
            }
            $created++;
            if ($commit) {
                ClaimsConfig::create([
                    'category'   => $category,
                    'label'      => $labelValue,
                    'sort_order' => $order,
                    'is_active'  => true,
                ]);
            }
        }
        $this->line("   → {$created} to create, {$skipped} already present (of " . count($labels) . ' tracker entries).');
    }

    private function reportReinsurers(): void
    {
        $this->newLine();
        $this->info('4) reinsurer table');
        $count = Schema::hasTable('reinsurer') ? DB::table('reinsurer')->count() : 0;
        $treaties = Schema::hasTable('reinsurance_treaty') ? DB::table('reinsurance_treaty')->count() : 0;
        $this->line("   reinsurer currently has {$count} rows; reinsurance_treaty has {$treaties} rows.");
        $this->warn('   No seed performed: the tracker has NO reinsurer master list (FAC *clients* are the insured, not reinsurers). '
            . 'Populate reinsurers via the existing Reinsurance CRUD; the FAC Reinsurers tile falls back to reinsurance_treaty until then.');
    }
}
