<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class CreatePolicyViews extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // View: Policy with Customer and Product (Most Common Query)
        DB::statement("
            CREATE OR REPLACE VIEW v_policy_details AS
            SELECT 
                p.id as policy_id,
                p.policyNumber,
                p.status,
                p.premium,
                p.created_at,
                p.updated_at,
                p.customer_id,
                p.product_id,
                c.firstName as customer_first_name,
                c.lastName as customer_last_name,
                c.email as customer_email,
                c.cellphone as customer_phone,
                pr.name as product_name
            FROM policies p
            LEFT JOIN customer c ON c.id = p.customer_id
            LEFT JOIN products pr ON pr.id = p.product_id
        ");

        // View: Policy with Payment Transactions (Optimized for payment queries)
        DB::statement("
            CREATE OR REPLACE VIEW v_policy_payments AS
            SELECT 
                p.id as policy_id,
                p.policyNumber,
                p.status as policy_status,
                pt.id as transaction_id,
                pt.status as payment_status,
                pt.paymentMethod,
                pt.amount,
                pt.created_at as payment_date
            FROM policies p
            LEFT JOIN payment_transactions pt ON pt.policy_id = p.id
            WHERE pt.deleted_at IS NULL
        ");

        // View: Policy with Vehicle (For motor policies)
        DB::statement("
            CREATE OR REPLACE VIEW v_policy_vehicles AS
            SELECT 
                p.id as policy_id,
                p.policyNumber,
                p.status as policy_status,
                v.id as vehicle_id,
                v.vehiclePlate,
                v.make,
                v.model,
                v.year,
                v.status as vehicle_status
            FROM policies p
            LEFT JOIN vehicle v ON v.policy_id = p.id
            WHERE v.deleted_at IS NULL
        ");

        // View: Policy with Ledger Summary (For accounting)
        DB::statement("
            CREATE OR REPLACE VIEW v_policy_ledger_summary AS
            SELECT 
                p.id as policy_id,
                p.policyNumber,
                SUM(CASE WHEN l.debit IS NOT NULL THEN l.debit ELSE 0 END) as total_debit,
                SUM(CASE WHEN l.credit IS NOT NULL THEN l.credit ELSE 0 END) as total_credit,
                SUM(CASE WHEN l.invoice_amount IS NOT NULL THEN l.invoice_amount ELSE 0 END) as total_invoice,
                COUNT(l.id) as transaction_count
            FROM policies p
            LEFT JOIN policy_ledger l ON l.policy_id = p.id AND l.deleted_at IS NULL
            GROUP BY p.id, p.policyNumber
        ");

        // View: Policy with Latest Transaction (For quick status checks)
        DB::statement("
            CREATE OR REPLACE VIEW v_policy_latest_transaction AS
            SELECT 
                p.id as policy_id,
                p.policyNumber,
                pt.id as transaction_id,
                pt.status as payment_status,
                pt.paymentMethod,
                pt.amount,
                pt.created_at
            FROM policies p
            LEFT JOIN (
                SELECT pt1.*
                FROM payment_transactions pt1
                INNER JOIN (
                    SELECT policy_id, MAX(id) as max_id
                    FROM payment_transactions
                    WHERE deleted_at IS NULL
                    GROUP BY policy_id
                ) pt2 ON pt1.id = pt2.max_id
            ) pt ON pt.policy_id = p.id
        ");

        // View: Policy with KYC Status
        DB::statement("
            CREATE OR REPLACE VIEW v_policy_kyc_status AS
            SELECT 
                p.id as policy_id,
                p.policyNumber,
                p.status as policy_status,
                k.id as kyc_id,
                k.compliance as kyc_compliance,
                k.status as kyc_status,
                k.created_at as kyc_created_at
            FROM policies p
            LEFT JOIN customer_kyc k ON k.customer_id = p.customer_id
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW IF EXISTS v_policy_details");
        DB::statement("DROP VIEW IF EXISTS v_policy_payments");
        DB::statement("DROP VIEW IF EXISTS v_policy_vehicles");
        DB::statement("DROP VIEW IF EXISTS v_policy_ledger_summary");
        DB::statement("DROP VIEW IF EXISTS v_policy_latest_transaction");
        DB::statement("DROP VIEW IF EXISTS v_policy_kyc_status");
    }
}
