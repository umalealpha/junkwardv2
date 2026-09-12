<?php

/**
 * Comment-status workflow + priority reminders — canonical option lists.
 *
 * Single source of truth shared by the set/read API (ClaimsV2Controller),
 * the reminder tick (ClaimMentionReminderTick) and the frontend (which reads
 * these lists back from the GET /claims/{id}/comment-status payload, so the
 * selectors and the server validation can never drift).
 *
 * The whole feature is gated by the `claims_comment_status` runtime flag
 * (Admin > Integrations, default OFF) — this file only defines the vocabulary.
 */
return [

    // "Comment status" a claim can sit in (Claims Tracker parity).
    'statuses' => [
        'Awaiting',
        'Outstanding Premiums',
        'Repudiated',
        'Claim Withdrawn',
        'Claim Closed',
        'Management Review',
        'System Issue',
        'Recovery & Legal',
        'File with Accounts',
        'Claim Below Excess',
    ],

    // "Awaiting — what?" sub-reasons. Only meaningful when status = 'Awaiting'.
    'awaiting_sub_reasons' => [
        'Claim Documents',
        'Invoices',
        'Assessment Report',
        'Signed CIL/FOR/Ex-Gratia',
        'Proof of Payment',
        'Signed AOL',
        'KYC',
        '3rd Party Insurance',
        'Demand Letter',
        'Salvage',
        'Excess',
    ],

    // The status that unlocks the sub-reason selector.
    'sub_reason_status' => 'Awaiting',

    // Comment priorities + the age (minutes) after which an UNREAD @mention on a
    // note of that priority triggers an email reminder. `null` threshold = never
    // remind (the "none" option).
    'priorities' => [
        ['value' => 'urgent', 'label' => 'Urgent (30 min)',  'threshold_minutes' => 30],
        ['value' => 'high',   'label' => 'High (2 hours)',    'threshold_minutes' => 120],
        ['value' => 'normal', 'label' => 'Normal (4 hours)',  'threshold_minutes' => 240],
        ['value' => 'low',    'label' => 'Low (24 hours)',    'threshold_minutes' => 1440],
        ['value' => 'none',   'label' => 'None',              'threshold_minutes' => null],
    ],
];
