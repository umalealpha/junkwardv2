<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Create the two missing Unicoin agencies so Unicoin staff can be assigned
 * and policy reports / agency filters resolve them by name instead of
 * leaving the records unassigned.
 *
 * Idempotent: each agency is only inserted when an agency of that exact
 * name does not already exist, so the migration is safe to re-run and will
 * not duplicate rows if the agencies were created manually in the UI first.
 *
 * NOTE: staff-to-agency assignment is intentionally NOT done here — the set
 * of Unicoin field vs call-centre staff is operational data, not something
 * derivable from the schema. Assign them via the Staff admin (agency_id) or
 * a follow-up data migration once the mapping is confirmed.
 */
class CreateUnicoinAgencies extends Migration
{
    /** Agencies to ensure exist. */
    private array $agencies = [
        'Unicoin Field Agent Agency',
        'Unicoin Call Center Agency',
    ];

    public function up()
    {
        foreach ($this->agencies as $name) {
            $exists = DB::table('agencies')->where('name', $name)->exists();
            if (! $exists) {
                DB::table('agencies')->insert([
                    'name'       => $name,
                    'status'     => 1, // active
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down()
    {
        // Only remove an agency if no users are still attached to it, to avoid
        // orphaning staff records on a rollback.
        foreach ($this->agencies as $name) {
            $agency = DB::table('agencies')->where('name', $name)->first();
            if (! $agency) {
                continue;
            }

            $hasStaff = DB::table('users')->where('agency_id', $agency->id)->exists();
            if (! $hasStaff) {
                DB::table('agencies')->where('id', $agency->id)->delete();
            }
        }
    }
}
