<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employer_groups', function (Blueprint $table) {
            // Only add columns that don't already exist
            $table->string('account_name')->nullable()->after('broker');
            $table->string('account_number')->nullable()->after('account_name');

                $table->string('bank_name_branch')->nullable()->after('account_number');
            
                $table->string('certificate_file')->nullable()->after('bank_name_branch');
            
                $table->string('certificate_filename')->nullable()->after('certificate_file');
            
                $table->string('tax_certificate_file')->nullable()->after('certificate_filename');
            
                $table->string('tax_certificate_filename')->nullable()->after('tax_certificate_file');
            
                $table->string('proof_address_file')->nullable()->after('tax_certificate_filename');
            
                $table->string('proof_address_filename')->nullable()->after('proof_address_file');
            
                $table->boolean('terms_agreement')->default(false)->after('proof_address_filename');

                $table->string('initials')->nullable()->after('terms_agreement');

                $table->string('other_industry')->after('industry');

                $table->text('notes')->nullable()->after('initials');

                $table->json('metadata')->nullable()->after('notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employer_groups', function (Blueprint $table) {
            // Only drop columns that were added by this migration
            $columnsToDrop = [];

            if (Schema::hasColumn('employer_groups', 'account_name')) {
                $columnsToDrop[] = 'account_name';
            }
            
            if (Schema::hasColumn('employer_groups', 'account_number')) {
                $columnsToDrop[] = 'account_number';
            }
            
            if (Schema::hasColumn('employer_groups', 'bank_name_branch')) {
                $columnsToDrop[] = 'bank_name_branch';
            }
            
            if (Schema::hasColumn('employer_groups', 'certificate_file')) {
                $columnsToDrop[] = 'certificate_file';
            }
            
            if (Schema::hasColumn('employer_groups', 'certificate_filename')) {
                $columnsToDrop[] = 'certificate_filename';
            }
            
            if (Schema::hasColumn('employer_groups', 'tax_certificate_file')) {
                $columnsToDrop[] = 'tax_certificate_file';
            }
            
            if (Schema::hasColumn('employer_groups', 'tax_certificate_filename')) {
                $columnsToDrop[] = 'tax_certificate_filename';
            }
            
            if (Schema::hasColumn('employer_groups', 'proof_address_file')) {
                $columnsToDrop[] = 'proof_address_file';
            }
            
            if (Schema::hasColumn('employer_groups', 'proof_address_filename')) {
                $columnsToDrop[] = 'proof_address_filename';
            }
            
            if (Schema::hasColumn('employer_groups', 'terms_agreement')) {
                $columnsToDrop[] = 'terms_agreement';
            }
            
            if (Schema::hasColumn('employer_groups', 'initials')) {
                $columnsToDrop[] = 'initials';
            }
            
            if (Schema::hasColumn('employer_groups', 'status')) {
                $columnsToDrop[] = 'status';
            }
            
            if (Schema::hasColumn('employer_groups', 'notes')) {
                $columnsToDrop[] = 'notes';
            }
            
            if (Schema::hasColumn('employer_groups', 'metadata')) {
                $columnsToDrop[] = 'metadata';
            }

            // Drop columns if any were added
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
