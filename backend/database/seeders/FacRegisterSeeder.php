<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Seeds the FAC register's permissions and the counterparties already in use.
 *
 * Safe to re-run: everything is an upsert or a firstOrCreate.
 */
class FacRegisterSeeder extends Seeder
{
    /**
     * Capture, settlement and cancellation are DELIBERATELY separate. The
     * underwriter who raises a line must not be able to sign off its own payment.
     */
    private const PERMISSIONS = [
        'reinsurance-fac-list'     => 'View the FAC register',
        'reinsurance-fac-create'   => 'Capture a FAC placement',
        'reinsurance-fac-edit'     => 'Amend a FAC placement',
        'reinsurance-fac-settle'   => 'Record a settlement to a reinsurer or broker',
        'reinsurance-fac-cancel'   => 'Cancel a placement and reverse the payable',
        'reinsurance-fac-slip'     => 'Generate and send FAC slips',
        'reinsurance-fac-close'    => 'Close a FAC period and capture GL comparatives',
    ];

    /**
     * The counterparties on the June FY26 master sheet's SUMMARY tab.
     *
     * `vat_applicable` is not a guess: it is read off the sheet itself. Where a
     * counterparty's lines carry an "Excl VAT" figure equal to the gross, that
     * counterparty is VAT-exclusive. Verified for all 20 blocks.
     *
     * [name, short_code, type, country, currency, vat_applicable]
     */
    private const COUNTERPARTIES = [
        // Reinsurers
        ['Grand Re',                'GRANDRE',   'reinsurer', 'BW', 'BWP', true],
        ['FMRE',                    'FMRE',      'reinsurer', 'ZW', 'BWP', true],
        ['SSRE',                    'SSRE',      'reinsurer', 'SZ', 'BWP', true],
        ['Continental Re',          'CONTRE',    'reinsurer', 'NG', 'BWP', true],
        ['Emeritus Re',             'EMERITUS',  'reinsurer', 'ZW', 'BWP', true],
        ['P & C Re',                'PCRE',      'reinsurer', 'ZW', 'BWP', true],
        ['TRUM',                    'TRUM',      'reinsurer', 'BW', 'BWP', true],
        ['CG Re',                   'CGRE',      'reinsurer', 'BW', 'BWP', true],
        ['Saha Re',                 'SAHARE',    'reinsurer', 'TZ', 'BWP', true],
        ['FBC Re',                  'FBCRE',     'reinsurer', 'ZW', 'BWP', true],
        ['GIC Re',                  'GICRE',     'reinsurer', 'IN', 'BWP', true],
        ['Nile Capital Re',         'NILERE',    'reinsurer', 'EG', 'BWP', false],
        ['Waica Re',                'WAICARE',   'reinsurer', 'SL', 'BWP', true],
        ['Ezulwini Re',             'EZULWINI',  'reinsurer', 'SZ', 'BWP', true],
        ['Eswatini Re',             'ESWATINI',  'reinsurer', 'SZ', 'BWP', true],

        // Brokers — these front the placement, so they are who we PAY. The party
        // carrying the risk is recorded separately on each placement row.
        ['Redhill Risk Solutions',  'REDHILL',   'broker', 'ZA', 'BWP', true],
        ['Maksure',                 'MAKSURE',   'broker', 'BW', 'BWP', false],
        ['Mukfin Botswana',         'MUKFIN',    'broker', 'BW', 'BWP', true],
        ['Genesis Risk Managers',   'GENESIS',   'broker', 'BW', 'BWP', false],
        ['Reinsurance Solutions',   'REINSOL',   'broker', 'MU', 'BWP', false],
        ['Solid Risk Advisors',     'SOLIDRISK', 'broker', 'BW', 'BWP', false],
        ['Oak Tree Intermediaries', 'OAKTREE',   'broker', 'ZA', 'USD', false],
    ];

    public function run(): void
    {
        $this->seedPermissions();
        $this->seedCounterparties();
    }

    private function seedPermissions(): void
    {
        foreach (array_keys(self::PERMISSIONS) as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // Super Admin gets the full set. Admin gets READ + capture only — the
        // comment used to say that while the code granted Admin everything,
        // including settle, cancel and period-close. That silently handed every
        // Admin the right to sign off a payment on a line they had just raised,
        // which is the exact separation this module is built around.
        //
        // Settlement, cancellation, slip-sending and closing are assigned
        // deliberately, per person, on the Roles & Permissions screen.
        if ($superAdmin = Role::where('name', 'Super Admin')->first()) {
            $superAdmin->givePermissionTo(array_keys(self::PERMISSIONS));
        }
        if ($admin = Role::where('name', 'Admin')->first()) {
            $admin->givePermissionTo([
                'reinsurance-fac-list',
                'reinsurance-fac-create',
                'reinsurance-fac-edit',
            ]);
        }

        $this->command?->info('FAC permissions seeded: ' . count(self::PERMISSIONS) . '.');
    }

    private function seedCounterparties(): void
    {
        $hasExtendedColumns = \Schema::hasColumn('reinsurer', 'counterparty_type');

        foreach (self::COUNTERPARTIES as [$name, $code, $type, $country, $currency, $vat]) {
            $base = ['company_name' => $name, 'updated_at' => now()];

            if ($hasExtendedColumns) {
                $base = array_merge($base, [
                    'short_code'        => $code,
                    'counterparty_type' => $type,
                    'country'           => $country,
                    'default_currency'  => $currency,
                    'vat_applicable'    => $vat,
                    'status'            => 1,
                ]);
            }

            $existing = DB::table('reinsurer')->where('company_name', $name)->first();

            if ($existing) {
                // Never overwrite an email or phone number somebody has filled in.
                DB::table('reinsurer')->where('id', $existing->id)->update($base);
            } else {
                DB::table('reinsurer')->insert(array_merge($base, ['created_at' => now()]));
            }
        }

        $this->command?->info('FAC counterparties seeded: ' . count(self::COUNTERPARTIES) . '.');
        $this->command?->warn('Email addresses are NOT seeded — slips cannot be sent until each counterparty has one on the Reinsurers screen.');
    }
}
