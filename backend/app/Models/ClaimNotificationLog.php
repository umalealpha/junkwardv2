<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * claim_notification_log — one row per claim notification Graphite sent
 * (SMS via Infobip / email via Mailgun), written passively by
 * ClaimNotificationRecorder from the existing send listeners.
 *
 * Read-only in practice (only the recorder writes it, and only while the
 * `claims_notifications` flag is on). Lives on the default connection alongside
 * `claims`. The `recipient` column is always a MASKED destination (DPA).
 *
 * See: app/Services/Claims/ClaimNotificationRecorder.php,
 *      app/Http/Controllers/Api/V1/ClaimNotificationController.php,
 *      database/migrations/*_create_claim_notification_log.php
 */
class ClaimNotificationLog extends Model
{
    protected $table = 'claim_notification_log';

    protected $guarded = ['id'];

    protected $casts = [
        'claim_id'     => 'integer',
        'cost_units'   => 'float',
        'delivered_at' => 'datetime',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    /** Statuses that count as a hard failure in the dashboard. */
    public const FAILURE_STATUSES = ['failed', 'error', 'curl_error', 'undeliverable', 'rejected'];

    /** Statuses that count as a confirmed delivery. */
    public const DELIVERED_STATUSES = ['delivered', 'sent'];
}
