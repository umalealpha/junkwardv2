<?php

namespace Tests\Feature\Api;

use AlphaDirect\Customer;
use AlphaDirect\CustomerProfile;
use AlphaDirect\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * PUT /customers/{id} — KYC staff / authorised users correcting customer
 * details from the Policy Details page's Customer tab (and Customer 360).
 *
 * Covers: route is permission-gated, an authorised user's edit persists and
 * round-trips through the same accessors the read side uses, and the change
 * leaves an audit trail.
 */
class CustomerUpdateTest extends TestCase
{
    private ?User $user = null;
    private bool $grantedPermission = false;
    private ?int $customerId = null;

    protected function tearDown(): void
    {
        if ($this->grantedPermission && $this->user) {
            $this->user->revokePermissionTo('customer-edit');
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
        if ($this->customerId) {
            DB::table('audits')->where('auditable_type', 'like', '%CustomerProfile')
                ->whereIn('auditable_id', function ($q) {
                    $q->select('id')->from('customer_profile')->where('customer_id', $this->customerId);
                })->delete();
            DB::table('audits')->where('auditable_type', 'like', '%Customer')
                ->where('auditable_id', $this->customerId)->delete();
            DB::table('activity_log')->where('subject_id', $this->customerId)->delete();
            DB::table('customer_profile')->where('customer_id', $this->customerId)->delete();
            DB::table('customer')->where('id', $this->customerId)->delete();
        }
        parent::tearDown();
    }

    public function test_update_route_is_gated_by_customer_edit_or_kyc_edit_permission(): void
    {
        $route = collect(Route::getRoutes())->first(fn ($r) => $r->getName() === 'api.v1.customers.update');

        $this->assertNotNull($route, 'customers.update route not registered');
        $this->assertContains('permission:customer-edit|customer-kyc-edit', $route->middleware());
    }

    public function test_authorised_user_can_correct_customer_and_profile_details(): void
    {
        $this->user = User::first();
        if (!$this->user) {
            $this->markTestSkipped('No users in database');
        }
        Sanctum::actingAs($this->user, ['*']);

        Permission::firstOrCreate(['name' => 'customer-edit', 'guard_name' => 'web']);
        if (!$this->user->hasPermissionTo('customer-edit')) {
            $this->user->givePermissionTo('customer-edit');
            $this->grantedPermission = true;
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        $customer = Customer::create([
            'firstName' => 'Wrong',
            'lastName'  => 'Name',
            'email'     => 'customer-edit-test+'.uniqid().'@example.com',
            'cellphone' => '71000000',
        ]);
        $this->customerId = $customer->id;
        CustomerProfile::create([
            'customer_id' => $customer->id,
            'entity_type' => 'Person',
            'omang'       => '111111111',
        ]);

        $resp = $this->putJson("/api/v1/customers/{$customer->id}", [
            'firstName'      => 'Corrected',
            'lastName'       => 'Person',
            'omang'          => '999999999',
            'gender'         => 'Female',
            'maritalstatus'  => 'Married',
            'sourceOfIncome' => 'employment',
            'riskCategory'       => 'high',
            'riskCategoryReason' => 'Multiple large claims within 12 months',
        ]);

        $resp->assertStatus(200);

        $customer->refresh();
        $this->assertSame('Corrected', $customer->firstName);
        $this->assertSame('Person', $customer->lastName);

        $profile = CustomerProfile::where('customer_id', $customer->id)->first();
        $this->assertSame('999999999', $profile->omang);
        // CustomerController maps the form's labels to the int codes
        // PolicyResource reads back out (gender: 1=Male else Female;
        // maritalstatus: 1=Single,2=Married,3=Divorced,4=Widowed).
        $this->assertSame(0, (int) $profile->getRawOriginal('gender'));
        $this->assertSame(2, (int) $profile->getRawOriginal('maritalstatus'));
        // sourceOfIncome is read everywhere via a json_decode accessor —
        // must round-trip back to the plain key, not get wiped to null.
        $this->assertSame('employment', $profile->sourceOfIncome);

        // Risk category (graphiteBWV8 customer_category parity): 'high' -> 2,
        // reason stored as a serialize()d array (legacy format), the same
        // shape CustomerController::show()'s decodeCategoryReason() reads back.
        $this->assertSame(2, (int) $customer->customer_category);
        $this->assertSame(['Multiple large claims within 12 months'], unserialize($customer->category_reason, ['allowed_classes' => false]));

        $hasCustomerAudit = DB::table('audits')
            ->where('auditable_type', 'like', '%Customer')
            ->where('auditable_id', $customer->id)
            ->exists();
        $hasProfileAudit = DB::table('audits')
            ->where('auditable_type', 'like', '%CustomerProfile')
            ->where('auditable_id', $profile->id)
            ->exists();
        $hasActivityLog = DB::table('activity_log')
            ->where('subject_id', $customer->id)
            ->exists();

        $this->assertTrue(
            $hasCustomerAudit || $hasProfileAudit || $hasActivityLog,
            'Expected an audit trail entry for the customer update.'
        );
    }
}
