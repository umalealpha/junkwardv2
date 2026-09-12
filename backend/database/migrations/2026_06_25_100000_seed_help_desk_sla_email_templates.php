<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed editable email templates for SLA warnings + breaches into the existing
 * help_desk_email_templates table (key = "{event}_{role}", role 'stakeholder').
 * Idempotent. Wording can be edited in the DB without a code change.
 *
 * Placeholders rendered by SlaNotifier: {{ticket_number}} {{title}}
 * {{priority}} {{assignee_name}} {{breach_type}} {{percent}} {{due_at}}
 * {{remaining_time}} {{ticket_link}}.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('help_desk_email_templates')) {
            return; // base Help Desk notification system not present
        }

        $now  = now();
        $rows = [
            [
                'key' => 'sla_response_warning_stakeholder', 'event' => 'sla_response_warning', 'recipient_role' => 'stakeholder',
                'subject' => '[SLA WARNING] {{ticket_number}} Response SLA {{percent}}% consumed',
                'body' => "Response SLA for {{ticket_number}} ({{priority}}) is at {{percent}}%.\n\n{{title}}\nAssignee: {{assignee_name}}\nResponse due: {{due_at}} (about {{remaining_time}} of business time left)\n\n{{ticket_link}}",
            ],
            [
                'key' => 'sla_resolution_warning_stakeholder', 'event' => 'sla_resolution_warning', 'recipient_role' => 'stakeholder',
                'subject' => '[SLA WARNING] {{ticket_number}} Resolution SLA {{percent}}% consumed',
                'body' => "Resolution SLA for {{ticket_number}} ({{priority}}) is at {{percent}}%.\n\n{{title}}\nAssignee: {{assignee_name}}\nResolution due: {{due_at}} (about {{remaining_time}} of business time left)\n\n{{ticket_link}}",
            ],
            [
                'key' => 'sla_response_breach_stakeholder', 'event' => 'sla_response_breach', 'recipient_role' => 'stakeholder',
                'subject' => '[SLA BREACH] {{ticket_number}} Response SLA Breached',
                'body' => "The Response SLA for {{ticket_number}} ({{priority}}) has been BREACHED.\n\n{{title}}\nAssignee: {{assignee_name}}\nResponse was due: {{due_at}}\n\n{{ticket_link}}",
            ],
            [
                'key' => 'sla_resolution_breach_stakeholder', 'event' => 'sla_resolution_breach', 'recipient_role' => 'stakeholder',
                'subject' => '[SLA BREACH] {{ticket_number}} Resolution SLA Breached',
                'body' => "The Resolution SLA for {{ticket_number}} ({{priority}}) has been BREACHED.\n\n{{title}}\nAssignee: {{assignee_name}}\nResolution was due: {{due_at}}\n\n{{ticket_link}}",
            ],
        ];

        foreach ($rows as $r) {
            DB::table('help_desk_email_templates')->updateOrInsert(
                ['key' => $r['key']],
                array_merge($r, ['active' => true, 'updated_at' => $now, 'created_at' => $now]),
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('help_desk_email_templates')) {
            DB::table('help_desk_email_templates')->whereIn('key', [
                'sla_response_warning_stakeholder',
                'sla_resolution_warning_stakeholder',
                'sla_response_breach_stakeholder',
                'sla_resolution_breach_stakeholder',
            ])->delete();
        }
    }
};
