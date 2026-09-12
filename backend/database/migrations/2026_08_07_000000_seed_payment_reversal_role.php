<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Restrict payment REVERSAL + manual REFUND to Super Admin + 10 named users
 * (2026-08). Previously "reverse" piggybacked on the shared payment_delete_*
 * permissions (over-broad) and "refund" was completely ungated.
 *
 * Creates the dedicated `payment_reversal` permission + a `Payment Reversal`
 * role holding it, revokes the permission from everyone else (fail-closed),
 * and assigns the 10 users to the role by email. Super Admin is NOT granted
 * the permission — it bypasses in code (AuthGate::canReversePayment), and
 * Managers/Admins deliberately do NOT get this right.
 *
 * Idempotency + morph-type convention copied from
 * 2026_08_05_000000_seed_policy_cancel_reinstate_roles.php (morph AlphaDirect\User).
 */
return new class extends Migration {
    private const GUARD = 'web';
    private const CATEGORY = 'Payment';
    private const PERM = 'payment_reversal';
    private const ROLE = 'Payment Reversal';

    private const USER_EMAILS = [
        'btendani@alphadirect.co.bw',   // Bontle Tendani
        'kmokhendo@alphadirect.co.bw',  // Keetile Mokhendo
        'kkgetse@alphadirect.co.bw',    // Koketso Kgetse
        'kkeitumele@alphadirect.co.bw', // Kutlo Keitumele
        'lbasotli@alphadirect.co.bw',   // Lefika Basotli
        'lmababa@alphadirect.co.bw',    // Lindani Mababa
        'rmokgware@alphadirect.co.bw',  // Rose Mokgware
        'tchimidza@alphadirect.co.bw',  // Tlamelo Chimidza
        'cbamusi@insurance.co.bw',      // Charmaine Bamusi
        'bmhusiwa@insurance.co.bw',     // Bakang Mhusiwa
    ];

    public function up(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles')) {
            return;
        }
        $this->flush();
        $now = now();

        // 1. Permission.
        $perm = DB::table('permissions')->where('name', self::PERM)->where('guard_name', self::GUARD)->first(['id', 'category']);
        if ($perm) {
            $permId = $perm->id;
            if (Schema::hasColumn('permissions', 'category') && empty($perm->category)) {
                DB::table('permissions')->where('id', $permId)->update(['category' => self::CATEGORY, 'updated_at' => $now]);
            }
        } else {
            $insert = ['name' => self::PERM, 'guard_name' => self::GUARD, 'created_at' => $now, 'updated_at' => $now];
            if (Schema::hasColumn('permissions', 'category')) {
                $insert['category'] = self::CATEGORY;
            }
            $permId = DB::table('permissions')->insertGetId($insert);
        }

        // 2. Role + attach the permission.
        $roleId = DB::table('roles')->where('name', self::ROLE)->where('guard_name', self::GUARD)->value('id');
        if (!$roleId) {
            $roleId = DB::table('roles')->insertGetId(['name' => self::ROLE, 'guard_name' => self::GUARD, 'created_at' => $now, 'updated_at' => $now]);
        }
        if (!DB::table('role_has_permissions')->where('permission_id', $permId)->where('role_id', $roleId)->exists()) {
            DB::table('role_has_permissions')->insert(['permission_id' => $permId, 'role_id' => $roleId]);
        }

        // 3. Revoke from everyone else (fail-closed). Keep-set = the new role
        //    only. Super Admin bypasses in code, so it is intentionally NOT
        //    granted the permission here.
        DB::table('role_has_permissions')->where('permission_id', $permId)->where('role_id', '!=', $roleId)->delete();
        if (Schema::hasTable('model_has_permissions')) {
            DB::table('model_has_permissions')->where('permission_id', $permId)->delete();
        }

        // 4. Assign the named users to the role by email.
        if (Schema::hasTable('model_has_roles') && Schema::hasTable('users')) {
            $modelType = DB::table('model_has_roles')->value('model_type') ?? 'AlphaDirect\\User';
            $granted = 0; $missing = [];
            foreach (self::USER_EMAILS as $email) {
                $uid = DB::table('users')->where('email', $email)->value('id');
                if (!$uid) { $missing[] = $email; continue; }
                $already = DB::table('model_has_roles')
                    ->where('role_id', $roleId)->where('model_type', $modelType)->where('model_id', $uid)->exists();
                if ($already) continue;
                DB::table('model_has_roles')->insert(['role_id' => $roleId, 'model_type' => $modelType, 'model_id' => $uid]);
                $granted++;
            }
            echo "[payment-reversal] assigned {$granted} user(s) to '" . self::ROLE . "'.\n";
            if (!empty($missing)) {
                echo "[payment-reversal] emails not found (skipped): " . implode(', ', $missing) . ".\n";
            }
        }

        $this->flush();
    }

    public function down(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles')) {
            return;
        }
        $roleId = DB::table('roles')->where('name', self::ROLE)->where('guard_name', self::GUARD)->value('id');
        if ($roleId) {
            DB::table('role_has_permissions')->where('role_id', $roleId)->delete();
            if (Schema::hasTable('model_has_roles')) {
                DB::table('model_has_roles')->where('role_id', $roleId)->delete();
            }
            DB::table('roles')->where('id', $roleId)->delete();
        }
        $permId = DB::table('permissions')->where('name', self::PERM)->where('guard_name', self::GUARD)->value('id');
        if ($permId) {
            DB::table('role_has_permissions')->where('permission_id', $permId)->delete();
            if (Schema::hasTable('model_has_permissions')) {
                DB::table('model_has_permissions')->where('permission_id', $permId)->delete();
            }
            DB::table('permissions')->where('id', $permId)->delete();
        }
        $this->flush();
    }

    private function flush(): void
    {
        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
