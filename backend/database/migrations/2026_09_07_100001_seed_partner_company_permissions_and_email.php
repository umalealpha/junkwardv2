<?php

use AlphaDirect\EmailBroadcasting;
use AlphaDirect\TemplateFields;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Auto-run seed for (a) the partner-company permissions — a route gated on a
 * permission that doesn't exist makes Spatie deny everyone incl. Super Admin —
 * and (b) the `partner_login_new` email template the "send credentials"
 * action depends on. Same pattern as 2026_08_13_000002 (employer groups).
 * Idempotent; never overwrites an existing template row.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('permissions') && Schema::hasTable('roles')) {
            (new \Database\Seeders\PartnerCompanyPermissionsSeeder())->run();
        }

        if (!Schema::hasTable('email_broadcastings') || !Schema::hasTable('template_fields')) {
            return;
        }

        $tokens = [
            'partner_company_name' => 'Partner Company Name',
            'partner_user_name'    => 'Partner User Name',
            'partner_email'        => 'Partner Email',
            'partner_set_link'     => 'Partner Set Password Link',
            'partner_login_link'   => 'Partner Login Link',
        ];
        $t = [];
        foreach ($tokens as $tableName => $label) {
            $row = TemplateFields::where('table_name', $tableName)->first();
            if (!$row) {
                $row = new TemplateFields();
                $row->table_name = $tableName;
                $row->field      = $label;
                $row->save();
            }
            $t[$tableName] = '[[' . $row->field . '_' . $row->id . ']]';
        }

        if (!EmailBroadcasting::where('hook_slug', 'partner_login_new')->exists()) {
            EmailBroadcasting::create([
                'hook_slug' => 'partner_login_new',
                'subject'   => 'Alpha Direct — Partner Portal Access',
                'text'      => <<<TXT
Dear {$t['partner_user_name']},

You have been granted access to the Alpha Direct partner portal for {$t['partner_company_name']}.

Set your password using the secure link below (valid for 48 hours):
{$t['partner_set_link']}

After setting your password, sign in here:
{$t['partner_login_link']}

Your login email: {$t['partner_email']}

Regards,
Alpha Direct Insurance
TXT,
            ]);
        }
    }

    public function down(): void
    {
        // Permissions and template left in place (see class docblock).
    }
};
