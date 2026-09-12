<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed the failed-recurring-payment SMS template (MIS policies) and its
 * SmsControls toggle.
 *
 * Two rows are needed before a single SMS can go out:
 *   1. sms_templates.slug = 'mis_recurring_payment_failed_sms' — the body Ops
 *      can edit in SMS Messaging without a deploy.
 *   2. sms_controls.function_name = 'sendMisRecurringPaymentFailedSMS' with
 *      status = 1 — every SmsMessaging sender is gated on this row and returns
 *      early (sending nothing) when the row is missing or status != 1.
 *
 * Idempotent: both inserts are guarded by a SELECT, so re-running is a no-op.
 * The template body is NOT overwritten on re-run — Ops edits win.
 */
return new class extends Migration {
    private const TEMPLATE_SLUG  = 'mis_recurring_payment_failed_sms';
    private const CONTROL_NAME   = 'sendMisRecurringPaymentFailedSMS';
    private const TEMPLATE_NAME  = 'MIS recurring premium payment failed';
    private const TEMPLATE_TEXT  = 'Dear [CUSTOMER_NAME], your premium payment for Policy [POLICY_NUMBER] was unsuccessful. Status: Payment Failed. Please ensure sufficient funds are available or update your payment details to keep your cover active.';

    public function up(): void
    {
        $now = now();

        if (Schema::hasTable('sms_templates')) {
            $existing = DB::table('sms_templates')->where('slug', self::TEMPLATE_SLUG)->value('id');
            if ($existing) {
                echo '[' . self::TEMPLATE_SLUG . "] template already exists (id={$existing}); leaving body untouched.\n";
            } else {
                $id = DB::table('sms_templates')->insertGetId([
                    'name'       => self::TEMPLATE_NAME,
                    'slug'       => self::TEMPLATE_SLUG,
                    'hook_slug'  => self::TEMPLATE_SLUG,
                    'text'       => self::TEMPLATE_TEXT,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                echo '[' . self::TEMPLATE_SLUG . "] inserted template row id={$id}.\n";
            }
        }

        if (Schema::hasTable('sms_controls')) {
            $control = DB::table('sms_controls')->where('function_name', self::CONTROL_NAME)->first(['id', 'status']);
            if (!$control) {
                $id = DB::table('sms_controls')->insertGetId([
                    'function_name' => self::CONTROL_NAME,
                    'status'        => 1,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
                echo '[' . self::CONTROL_NAME . "] inserted sms_controls row id={$id} (status=1).\n";
            } else {
                echo '[' . self::CONTROL_NAME . "] sms_controls row already exists (id={$control->id}, status={$control->status}); not changing status.\n";
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sms_templates')) {
            DB::table('sms_templates')->where('slug', self::TEMPLATE_SLUG)->delete();
        }
        if (Schema::hasTable('sms_controls')) {
            DB::table('sms_controls')->where('function_name', self::CONTROL_NAME)->delete();
        }
    }
};
