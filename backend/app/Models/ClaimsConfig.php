<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Graphite home for the Claims-Tracker-only master lists (see the
 * create_claims_config_table migration). One row = one entry inside a
 * category. List categories use `label` only; key/value setting categories
 * use `label` (the key) + `value`.
 */
class ClaimsConfig extends Model
{
    protected $table = 'claims_config';

    protected $fillable = [
        'category',
        'label',
        'value',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
    ];

    /**
     * The tracker-only categories that have a first-class Master-Data tile.
     * Anything not in this list is rejected by the config CRUD controller so
     * the surface stays predictable.
     */
    public const CATEGORIES = [
        'comment_priorities'         => 'Comment Priorities',
        'mention_domains'            => 'Mention Allowed Domains',
        'notification_settings'      => 'Notification Settings',
        'notification_pilot_numbers' => 'Notification Pilot Numbers',
        'policy_library_branches'    => 'Policy Library — Branches',
        'policy_library_products'    => 'Policy Library — Products',
        'policy_library_coverages'   => 'Policy Library — Coverages',
        'fac_clients'                => 'FAC Clients',
    ];

    public static function isValidCategory(string $category): bool
    {
        return array_key_exists($category, self::CATEGORIES);
    }
}
