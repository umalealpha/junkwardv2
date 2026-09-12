<?php

namespace Tests\Feature\Api;

use AlphaDirect\Http\Controllers\Api\V1\UserController;
use AlphaDirect\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Staff creation (POST /users) — the "saving fails with no field highlighted"
 * report.
 *
 * Drives UserController::store directly, the same pattern as UnionModuleTest,
 * on an in-memory SQLite schema. SAFE: never touches MySQL / RDS.
 *
 * Covers the defects found in the original store():
 *   - dob / omang / passport / address were passed to User::create() but are
 *     not in User::$fillable, so they were silently discarded instead of being
 *     written to user_profile,
 *   - `pin` was sent by the create form but never validated or persisted,
 *   - `active` used the `boolean` rule, rejecting Suspended (2) which the
 *     Status dropdown offers and update() accepts,
 *   - no transaction, so a failure after the users INSERT left a half-built
 *     account that made the retry fail on a duplicate email.
 */
class UserCreateTest extends TestCase
{
    private UserController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'audit.enabled' => false,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        DB::reconnect('sqlite');

        $this->buildSchema();
        $this->controller = new UserController();
    }

    private function buildSchema(): void
    {
        Schema::create('users', function ($t) {
            $t->id();
            $t->string('firstName', 100)->nullable();
            $t->string('lastName', 100)->nullable();
            $t->string('email')->unique();
            $t->string('password');
            $t->string('api_token')->nullable();
            $t->string('pin', 10)->nullable();
            $t->unsignedBigInteger('company_id')->nullable();
            $t->unsignedBigInteger('agency_id')->nullable();
            $t->tinyInteger('llmUserStatus')->nullable();
            $t->tinyInteger('commission')->nullable();
            $t->tinyInteger('is_graphite_login')->nullable();
            $t->tinyInteger('is_report_login')->nullable();
            $t->tinyInteger('bypass_500k')->nullable();
            $t->tinyInteger('is_first_login')->nullable();
            $t->tinyInteger('active')->default(1);
            $t->timestamp('password_changed_at')->nullable();
            $t->timestamp('last_login_at')->nullable();
            $t->timestamps();
        });

        Schema::create('user_profile', function ($t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('department_id')->nullable();
            $t->date('dob')->nullable();
            $t->string('gender')->nullable();
            $t->string('omang')->nullable();
            $t->string('passport')->nullable();
            $t->string('address')->nullable();
            $t->string('cellphone')->nullable();
            $t->string('work_position')->nullable();
            $t->timestamps();
        });

        Schema::create('agencies', function ($t) {
            $t->id();
            $t->string('name')->nullable();
            $t->timestamps();
        });

        Schema::create('roles', function ($t) {
            $t->id();
            $t->string('name');
            $t->string('guard_name')->default('web');
            $t->timestamps();
        });

        Schema::create('model_has_roles', function ($t) {
            $t->unsignedBigInteger('role_id');
            $t->string('model_type');
            $t->unsignedBigInteger('model_id');
        });
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'firstName' => 'Kefilwe',
            'lastName'  => 'Moloi',
            'email'     => 'k.moloi@alphadirect.co.bw',
            'password'  => 'Password123',
            'active'    => 1,
        ], $overrides);
    }

    private function store(array $payload)
    {
        return $this->controller->store(Request::create('/users', 'POST', $payload));
    }

    public function test_creates_a_staff_member_with_profile(): void
    {
        $resp = $this->store($this->payload([
            'cellphone'     => '72345678',
            'work_position' => 'Underwriter',
        ]));

        $this->assertSame(201, $resp->getStatusCode(), json_encode($resp->getData(true)));

        $user = User::where('email', 'k.moloi@alphadirect.co.bw')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('Password123', $user->password), 'password was not hashed');
        $this->assertNotNull($user->password_changed_at, 'password_changed_at not stamped');

        $profile = DB::table('user_profile')->where('user_id', $user->id)->first();
        $this->assertSame('72345678', $profile->cellphone);
        $this->assertSame('Underwriter', $profile->work_position);
    }

    /** dob/omang/passport/address used to be dropped by mass assignment. */
    public function test_profile_only_fields_are_persisted_not_discarded(): void
    {
        $resp = $this->store($this->payload([
            'dob'      => '1990-05-14',
            'omang'    => '419217634',
            'passport' => 'BW123456',
            'address'  => 'Plot 123, Gaborone',
        ]));
        $this->assertSame(201, $resp->getStatusCode(), json_encode($resp->getData(true)));

        $user    = User::where('email', 'k.moloi@alphadirect.co.bw')->first();
        $profile = DB::table('user_profile')->where('user_id', $user->id)->first();

        $this->assertSame('419217634', $profile->omang, 'omang was dropped');
        $this->assertSame('BW123456', $profile->passport, 'passport was dropped');
        $this->assertSame('Plot 123, Gaborone', $profile->address, 'address was dropped');
        $this->assertStringStartsWith('1990-05-14', (string) $profile->dob, 'dob was dropped');
    }

    /** The create form sends a PIN; store() used to ignore it. */
    public function test_pin_is_saved_when_supplied(): void
    {
        $resp = $this->store($this->payload(['pin' => '4821']));
        $this->assertSame(201, $resp->getStatusCode(), json_encode($resp->getData(true)));

        $this->assertSame('4821', User::where('email', 'k.moloi@alphadirect.co.bw')->value('pin'));
    }

    public function test_pin_must_be_four_digits(): void
    {
        $this->expectException(ValidationException::class);
        $this->store($this->payload(['pin' => '12']));
    }

    /** Status offers Suspended (2); the old `boolean` rule rejected it. */
    public function test_can_create_a_suspended_user(): void
    {
        $resp = $this->store($this->payload(['active' => 2]));

        $this->assertSame(201, $resp->getStatusCode(), json_encode($resp->getData(true)));
        $this->assertSame(2, (int) User::where('email', 'k.moloi@alphadirect.co.bw')->value('active'));
    }

    public function test_rejects_an_unknown_status(): void
    {
        $this->expectException(ValidationException::class);
        $this->store($this->payload(['active' => 7]));
    }

    public function test_attaches_the_selected_role(): void
    {
        $roleId = DB::table('roles')->insertGetId(['name' => 'Underwriter', 'guard_name' => 'web']);

        $resp = $this->store($this->payload(['role_id' => $roleId]));
        $this->assertSame(201, $resp->getStatusCode(), json_encode($resp->getData(true)));

        $user = User::where('email', 'k.moloi@alphadirect.co.bw')->first();
        $this->assertDatabaseHas('model_has_roles', [
            'role_id'    => $roleId,
            'model_id'   => $user->id,
            'model_type' => User::class,
        ]);
    }

    public function test_duplicate_email_is_a_field_error_not_a_500(): void
    {
        $this->store($this->payload());

        try {
            $this->store($this->payload());
            $this->fail('expected a validation error on the duplicate email');
        } catch (ValidationException $e) {
            // A 422 with an `errors.email` key is what lets the UI highlight
            // the field — the whole point of the original bug report.
            $this->assertArrayHasKey('email', $e->errors());
        }
    }

    /**
     * A failure after the users INSERT must not leave the account behind —
     * otherwise the operator retries and hits "email already taken" for a user
     * they were told was never created.
     */
    public function test_a_failure_midway_rolls_the_user_back(): void
    {
        // Break the second write: user_profile disappears after validation.
        Schema::drop('user_profile');

        try {
            $this->store($this->payload());
        } catch (\Throwable $e) {
            // Either a caught QueryException -> 500 JSON, or it bubbles; both
            // are acceptable. What matters is what is left in the database.
        }

        $this->assertSame(
            0,
            DB::table('users')->where('email', 'k.moloi@alphadirect.co.bw')->count(),
            'the half-created user was not rolled back'
        );
    }
}
