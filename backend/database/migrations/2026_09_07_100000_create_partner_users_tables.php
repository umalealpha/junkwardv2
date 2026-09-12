<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Partner-company logins for the embedded B2B2C products (Alpha Transit
 * Cover today, AlphaProtect device cover next). Partner companies — courier
 * firms, phone/electronics retailers — are NOT sales agents: they have no
 * `users` row, no Agent ID / PIN. Their staff sign in on the customer SPA
 * (start) with email + password before the partner-only product opens.
 *
 * The company registry is the existing `atc_couriers` table (company_code,
 * name, agency_id, status) — extended here with contact + product-scope
 * columns rather than duplicated. Logins live in `partner_users` (one
 * company, many logins) so `atc_shipments.issued_by_email` stays a real
 * person for audit and a single staff member can be revoked. Sessions are
 * opaque 64-char tokens stored hashed in `partner_user_tokens`, mirroring
 * public_session_tokens.
 *
 * Runs on the mysql_system connection like the other atc_* tables.
 */
return new class extends Migration
{
    private string $conn = 'mysql_system';

    public function up(): void
    {
        $schema = Schema::connection($this->conn);

        if ($schema->hasTable('atc_couriers')) {
            $schema->table('atc_couriers', function (Blueprint $table) use ($schema) {
                if (!$schema->hasColumn('atc_couriers', 'contact_name'))  $table->string('contact_name')->nullable()->after('name');
                if (!$schema->hasColumn('atc_couriers', 'contact_email')) $table->string('contact_email')->nullable()->after('contact_name');
                if (!$schema->hasColumn('atc_couriers', 'contact_phone')) $table->string('contact_phone', 32)->nullable()->after('contact_email');
                // Which partner-only products this company may sell on start.
                // JSON array of product ids, e.g. [25]. Null = none.
                if (!$schema->hasColumn('atc_couriers', 'products'))      $table->json('products')->nullable()->after('agency_id');
                if (!$schema->hasColumn('atc_couriers', 'notes'))         $table->text('notes')->nullable()->after('products');
            });
        }

        if (!$schema->hasTable('partner_users')) {
            $schema->create('partner_users', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id');            // atc_couriers.id
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password')->nullable();              // null until set via signed link
                $table->boolean('is_active')->default(true);
                $table->timestamp('password_set_at')->nullable();
                $table->timestamp('last_login_at')->nullable();
                $table->string('last_login_ip', 45)->nullable();
                $table->unsignedSmallInteger('failed_logins')->default(0);
                $table->timestamp('locked_until')->nullable();
                $table->unsignedBigInteger('created_by_user_id')->nullable(); // admin users.id
                $table->timestamps();
                $table->index('company_id');
            });
        }

        if (!$schema->hasTable('partner_user_tokens')) {
            $schema->create('partner_user_tokens', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('partner_user_id');
                $table->string('token_hash', 64)->unique();
                $table->timestamp('expires_at');
                $table->timestamp('revoked_at')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->string('ip', 45)->nullable();
                $table->timestamps();
                $table->index(['partner_user_id', 'expires_at']);
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection($this->conn);
        $schema->dropIfExists('partner_user_tokens');
        $schema->dropIfExists('partner_users');
        if ($schema->hasTable('atc_couriers')) {
            $schema->table('atc_couriers', function (Blueprint $table) use ($schema) {
                foreach (['contact_name', 'contact_email', 'contact_phone', 'products', 'notes'] as $c) {
                    if ($schema->hasColumn('atc_couriers', $c)) $table->dropColumn($c);
                }
            });
        }
    }
};
