<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add AD Group KYC template fields to template_fields table
        $templateFields = [
            [
                'id' => 73,
                'table_name' => 'ad_group_kyc_link_url',
                'field_name' => 'url',
                'field' => 'AD Group Kyc Url',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 74,
                'table_name' => 'ad_group_kyc_otp_code',
                'field_name' => 'otp_code',
                'field' => 'AD Group Kyc Otp Code',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 75,
                'table_name' => 'ad_group_kyc_otp_expiry',
                'field_name' => 'otp_expiry',
                'field' => 'AD Group Kyc Otp Expiry',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 76,
                'table_name' => 'ad_group_kyc_link_expiry_days',
                'field_name' => 'link_expiry_days',
                'field' => 'AD Group Kyc Link Expiry Days',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 77,
                'table_name' => 'ad_group_kyc_custom_message',
                'field_name' => 'custom_message',
                'field' => 'AD Group Kyc Custom Message',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($templateFields as $field) {
            DB::table('template_fields')->updateOrInsert(
                ['id' => $field['id']],
                $field
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove AD Group KYC template fields
        DB::table('template_fields')->whereIn('id', [73, 74, 75, 76, 77])->delete();
    }
};
