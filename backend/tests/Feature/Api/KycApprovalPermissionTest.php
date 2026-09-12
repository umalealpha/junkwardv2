<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * KYC approve / reject is restricted to holders of `customer-kyc-approve`
 * (business instruction 2026-09-10: three named approvers via the
 * `KYC Approver` role, plus Super Admin — see migration
 * 2026_09_10_000000_seed_customer_kyc_approve_permission).
 *
 * The four decision routes used to sit in the plain authenticated group with
 * no permission middleware, so any active account could approve KYC by URL.
 * These tests pin the gate to the route table so a future route edit cannot
 * silently drop it, and confirm the read routes stay open to queue viewers.
 */
class KycApprovalPermissionTest extends TestCase
{
    private const GATE = 'permission:customer-kyc-approve';

    /** Route name => human label. */
    private const DECISION_ROUTES = [
        'api.v1.kyc.updateStatus'       => 'MIS overall approve/reject',
        'api.v1.kyc.verifyDocument'     => 'MIS per-document verdict',
        'api.v1.domComKyc.updateStatus'   => 'DOM/COM overall approve/reject',
        'api.v1.domComKyc.verifyDocument' => 'DOM/COM per-document verdict',
    ];

    private const READ_ROUTES = [
        'api.v1.kyc.index',
        'api.v1.kyc.detail',
        'api.v1.domComKyc.index',
        'api.v1.domComKyc.detail',
    ];

    public function test_every_kyc_decision_route_requires_the_approver_permission(): void
    {
        $routes = collect(Route::getRoutes());

        foreach (self::DECISION_ROUTES as $name => $label) {
            $route = $routes->first(fn ($r) => $r->getName() === $name);

            $this->assertNotNull($route, "{$label} route ({$name}) is not registered");
            $this->assertContains(
                self::GATE,
                $route->middleware(),
                "{$label} route ({$name}) is not gated by " . self::GATE
            );
        }
    }

    /**
     * The approver list is only exclusive if ordinary staff cannot attach the
     * `KYC Approver` role to themselves. Pin the admin gate on the role
     * attach/detach routes.
     */
    public function test_role_attach_routes_are_restricted_to_access_administrators(): void
    {
        $routes = collect(Route::getRoutes());
        $gate = 'role_or_permission:Super Admin|Admin|Manager|user-edit';

        foreach (['api.v1.users.assignRole', 'api.v1.users.assignRoleLegacy', 'api.v1.users.removeRole'] as $name) {
            $route = $routes->first(fn ($r) => $r->getName() === $name);

            $this->assertNotNull($route, "{$name} route is not registered");
            $this->assertContains($gate, $route->middleware(), "{$name} is not gated by {$gate}");
        }
    }

    public function test_kyc_read_routes_do_not_require_the_approver_permission(): void
    {
        $routes = collect(Route::getRoutes());

        foreach (self::READ_ROUTES as $name) {
            $route = $routes->first(fn ($r) => $r->getName() === $name);

            $this->assertNotNull($route, "{$name} route is not registered");
            $this->assertNotContains(
                self::GATE,
                $route->middleware(),
                "{$name} must stay readable by queue viewers without the approver permission"
            );
        }
    }
}
