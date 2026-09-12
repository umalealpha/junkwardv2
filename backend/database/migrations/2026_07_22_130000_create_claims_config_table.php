<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Claims-Tracker migration — Graphite home for the tracker-only master lists.
 *
 * The Claims Tracker DB (claims.db) is being DELETED after migration, so the
 * small operational lists it owned that have NO existing Graphite table must
 * live somewhere in Graphite. This creates one simple, admin-CRUD'able
 * key/category + value store for them:
 *
 *   category (slug)   e.g. comment_priorities, mention_domains,
 *                     notification_settings, notification_pilot_numbers,
 *                     policy_library_branches, policy_library_products,
 *                     policy_library_coverages, fac_clients
 *   label             the display value (a list entry, or a setting key)
 *   value             optional payload (used for key/value settings; NULL for
 *                     plain list entries)
 *   sort_order        manual ordering within a category
 *   is_active         soft on/off without deleting
 *
 * A UNIQUE (category, label) index makes seeding idempotent (re-runs are
 * no-ops / upserts) and stops accidental duplicate entries.
 *
 * Idempotent: guarded with Schema::hasTable so a re-run (or an env where the
 * table already exists) is a no-op. Purely additive — nothing existing is
 * touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('claims_config')) {
            return;
        }

        Schema::create('claims_config', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('category', 64)->index();
            // 191 keeps (category + label) inside the utf8mb4 index-length
            // limit on every MariaDB/MySQL version we run; no list value we
            // migrate exceeds ~120 chars.
            $table->string('label', 191);
            $table->text('value')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // One entry per (category, label) — keeps seeding idempotent and
            // blocks duplicate list entries.
            $table->unique(['category', 'label'], 'claims_config_category_label_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claims_config');
    }
};
