<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Help Desk email-notification system.
 *
 *  - help_desk_tickets.assignee_email : so the assignee is always reachable by
 *    email (assign() only stored id + display name before).
 *  - help_desk_email_templates        : editable subject/body per event×role,
 *    seeded with defaults → wording changes need no code change.
 *  - help_desk_notifications          : append-only audit log of every email,
 *    with a unique key that prevents duplicate sends for the same event.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('help_desk_tickets', 'assignee_email')) {
            Schema::table('help_desk_tickets', function (Blueprint $table) {
                $table->string('assignee_email', 150)->nullable()->after('assignee_name');
            });
        }

        Schema::create('help_desk_email_templates', function (Blueprint $table) {
            $table->id();
            // "{event}_{role}" e.g. created_reporter, assigned_assignee, closed_reporter
            $table->string('key', 60)->unique();
            $table->string('event', 30);           // created | assigned | reassigned | closed
            $table->string('recipient_role', 20);  // reporter | assignee
            $table->string('subject', 255);
            $table->text('body');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('help_desk_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id')->index();
            $table->string('event', 30);
            $table->string('recipient_role', 20);
            $table->string('recipient_name')->nullable();
            $table->string('to_email', 150);
            // Discriminator so recurring events (re/assignment) notify each
            // distinct assignee once, while one-time events (created/closed)
            // use '' and are deduped per ticket+event+role+email.
            $table->string('ref', 150)->default('');
            $table->string('subject', 255)->nullable();
            $table->string('status', 12)->default('sent'); // sent | failed
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['ticket_id', 'event', 'recipient_role', 'to_email', 'ref'], 'help_desk_notif_dedup');
        });

        $this->seedTemplates();
    }

    public function down(): void
    {
        Schema::dropIfExists('help_desk_notifications');
        Schema::dropIfExists('help_desk_email_templates');
        if (Schema::hasColumn('help_desk_tickets', 'assignee_email')) {
            Schema::table('help_desk_tickets', function (Blueprint $table) {
                $table->dropColumn('assignee_email');
            });
        }
    }

    private function seedTemplates(): void
    {
        $now = now();
        $rows = [
            [
                'key' => 'created_reporter', 'event' => 'created', 'recipient_role' => 'reporter',
                'subject' => 'Ticket Created – {{ticket_number}}',
                'body' => "Hello {{reporter_name}},\n\nYour support ticket has been successfully created.\n\nTicket Number: {{ticket_number}}\nTitle: {{title}}\nStatus: {{status}}\n\nWe will keep you updated on the progress of this ticket.\n\nView your ticket: {{ticket_link}}\n\nRegards,\nHelp Desk Team",
            ],
            [
                'key' => 'created_assignee', 'event' => 'created', 'recipient_role' => 'assignee',
                'subject' => 'New Ticket Assigned – {{ticket_number}}',
                'body' => "Hello {{assignee_name}},\n\nA new ticket has been assigned to you.\n\nTicket Number: {{ticket_number}}\nTitle: {{title}}\nRaised By: {{reporter_name}}\nStatus: {{status}}\n\nPlease review and take the necessary action.\n\nView the ticket: {{ticket_link}}\n\nRegards,\nHelp Desk Team",
            ],
            [
                'key' => 'assigned_reporter', 'event' => 'assigned', 'recipient_role' => 'reporter',
                'subject' => 'Ticket {{ticket_number}} Assigned',
                'body' => "Hello {{reporter_name}},\n\nYour ticket {{ticket_number}} has now been assigned to {{assignee_name}}.\n\nThe assigned team member will review and work on your request.\n\nStatus: {{status}}\nView your ticket: {{ticket_link}}\n\nRegards,\nHelp Desk Team",
            ],
            [
                'key' => 'assigned_assignee', 'event' => 'assigned', 'recipient_role' => 'assignee',
                'subject' => 'Ticket Assigned – {{ticket_number}}',
                'body' => "Hello {{assignee_name}},\n\nTicket {{ticket_number}} has been assigned to you.\n\nRaised By: {{reporter_name}}\nTitle: {{title}}\nStatus: {{status}}\n\nPlease review and take the necessary action.\n\nView the ticket: {{ticket_link}}\n\nRegards,\nHelp Desk Team",
            ],
            [
                'key' => 'reassigned_reporter', 'event' => 'reassigned', 'recipient_role' => 'reporter',
                'subject' => 'Ticket {{ticket_number}} Reassigned',
                'body' => "Hello {{reporter_name}},\n\nYour ticket {{ticket_number}} has been reassigned to {{assignee_name}}.\n\nThe assigned team member will review and work on your request.\n\nStatus: {{status}}\nView your ticket: {{ticket_link}}\n\nRegards,\nHelp Desk Team",
            ],
            [
                'key' => 'reassigned_assignee', 'event' => 'reassigned', 'recipient_role' => 'assignee',
                'subject' => 'Ticket Reassigned – {{ticket_number}}',
                'body' => "Hello {{assignee_name}},\n\nTicket {{ticket_number}} has been reassigned to you.\n\nRaised By: {{reporter_name}}\nTitle: {{title}}\nStatus: {{status}}\n\nPlease review and take the necessary action.\n\nView the ticket: {{ticket_link}}\n\nRegards,\nHelp Desk Team",
            ],
            [
                'key' => 'closed_reporter', 'event' => 'closed', 'recipient_role' => 'reporter',
                'subject' => 'Ticket Closed – {{ticket_number}}',
                'body' => "Hello {{reporter_name}},\n\nYour ticket {{ticket_number}} has been marked as Closed.\n\nTitle: {{title}}\nStatus: {{status}}\n\nIf you believe the issue is not fully resolved, please contact the support team or reopen the ticket if that functionality exists.\n\nView the ticket: {{ticket_link}}\n\nThank you.\n\nRegards,\nHelp Desk Team",
            ],
        ];

        foreach ($rows as &$r) {
            $r['active'] = true;
            $r['created_at'] = $now;
            $r['updated_at'] = $now;
        }
        // Idempotent: don't duplicate if the migration is re-run after a reset.
        foreach ($rows as $r) {
            DB::table('help_desk_email_templates')->updateOrInsert(['key' => $r['key']], $r);
        }
    }
};
