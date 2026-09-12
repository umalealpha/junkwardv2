<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\EmailBroadcasting;
use AlphaDirect\TemplateFields;
use Illuminate\Console\Command;

/**
 * Seeds the two employer-group email templates the send actions depend on:
 *   hr_login_new              — HR portal access / set-password credentials
 *   employer_group_onboarding — contact onboarding + employer-group KYC link
 *
 * Both were DB-only rows in V8 with no repo seed — a fresh environment
 * throws "Email template not found" on the send buttons without them.
 *
 * Idempotent and non-destructive: template_fields rows are firstOrCreate'd
 * (token ids come from the live table, never hardcoded) and existing
 * email_broadcastings rows are NEVER overwritten (ops may have customised
 * them) — use --force to rewrite the template text.
 */
class AddEmployerGroupEmailTemplates extends Command
{
    protected $signature = 'add:employer-group-email-templates {--force : Overwrite existing template text}';
    protected $description = 'Seed hr_login_new + employer_group_onboarding email templates (idempotent)';

    public function handle()
    {
        // Virtual token fields understood by MailTemplate's substitution
        // switch — table_name is the lookup key, field is the display label
        // used inside [[Label_id]].
        $tokens = [
            'employer_group_name'            => 'Employer Group Name',
            'hr_reset_link'                  => 'HR Reset Link',
            'hr_login_link'                  => 'HR Login Link',
            'hr_email'                       => 'HR Email',
            'employer_group_code'            => 'Employer Group Code',
            'employer_group_contact_name'    => 'Employer Group Contact Name',
            'employer_group_onboarding_link' => 'Employer Group Onboarding Link',
            'employer_group_kyc_link'        => 'Employer Group Kyc Link',
            'employer_group_kyc_link_expiry' => 'Employer Group Kyc Link Expiry',
        ];

        $t = [];
        foreach ($tokens as $tableName => $label) {
            // TemplateFields has an empty $fillable — assign explicitly.
            $row = TemplateFields::where('table_name', $tableName)->first();
            if (!$row) {
                $row = new TemplateFields();
                $row->table_name = $tableName;
                $row->field = $label;
                $row->field_name = $tableName;
                $row->save();
            }
            // Token text uses the row's own label in case a pre-existing row
            // carries a different one.
            $t[$tableName] = '[[' . $row->field . '_' . $row->id . ']]';
        }

        $this->seedTemplate('hr_login_new', 'Alpha Direct — HR Portal Access', <<<TXT
Dear HR Team,

You have been granted access to the Alpha Direct HR portal for {$t['employer_group_name']}.

Set your password using the secure link below (valid for 48 hours):
{$t['hr_reset_link']}

After setting your password, log in here:
{$t['hr_login_link']}

Your login email: {$t['hr_email']}

If you did not expect this email, please contact Alpha Direct support.

Regards,
Alpha Direct Insurance
TXT);

        $this->seedTemplate('employer_group_onboarding', 'Welcome to Alpha Direct — Employer Group Onboarding', <<<TXT
Dear {$t['employer_group_contact_name']},

Thank you for registering {$t['employer_group_name']} with Alpha Direct.

Your Employer Group ID: {$t['employer_group_code']}

Complete your onboarding here:
{$t['employer_group_onboarding_link']}

KYC verification is required for your employer group. Complete the KYC form here:
{$t['employer_group_kyc_link']}

Important: the KYC link expires in {$t['employer_group_kyc_link_expiry']}. Please complete it as soon as possible.

Regards,
Alpha Direct Insurance
TXT);

        return 0;
    }

    private function seedTemplate(string $hook, string $subject, string $text): void
    {
        $existing = EmailBroadcasting::where('hook_slug', $hook)->first();

        if ($existing && !$this->option('force')) {
            $this->info("✓ '{$hook}' already exists — left untouched (use --force to rewrite).");
            return;
        }

        EmailBroadcasting::updateOrCreate(
            ['hook_slug' => $hook],
            ['subject' => $subject, 'text' => $text]
        );

        $this->info(($existing ? '↻ rewrote' : '✅ created') . " template '{$hook}'.");
    }
}
