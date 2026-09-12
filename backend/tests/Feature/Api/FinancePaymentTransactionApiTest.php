<?php

namespace Tests\Feature\Api;

use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Feature tests for the Finance ERP read-only API at
 * /api/v1/finance/payment-transactions.
 *
 * These tests exercise the public contract: auth gate, ability gate,
 * validation, the cursor-pagination shape, and case-insensitive status
 * filtering. They do NOT touch the database write path — by design the
 * controller is read-only.
 *
 * The DB-dependent tests skip themselves when the database is empty so
 * the suite stays green on a fresh dev box; the auth / validation tests
 * never need rows and always run.
 */
class FinancePaymentTransactionApiTest extends TestCase
{
    private const ENDPOINT      = '/api/v1/finance/payment-transactions';
    private const FUTURE_DATE   = '2099-01-01'; // outside any reasonable data window
    private const FUTURE_DATE_2 = '2099-01-31';

    /** Memoised: is the mysql connection reachable from this test runner? */
    private static ?bool $dbAvailable = null;

    /**
     * Skip the calling test when the mysql connection isn't reachable from
     * this environment. Lets the suite go green on a dev box that doesn't
     * have access to the live read-replica, while still running fully on
     * CI / staging where the DB IS reachable.
     */
    private function requireDb(): void
    {
        if (self::$dbAvailable === null) {
            try {
                \DB::connection('mysql')->getPdo();
                self::$dbAvailable = true;
            } catch (\Throwable $e) {
                self::$dbAvailable = false;
            }
        }
        if (!self::$dbAvailable) {
            $this->markTestSkipped('mysql connection unreachable from this environment');
        }
    }

    /**
     * Helper: act as a Sanctum-authenticated user with a specified ability
     * set. The ERP token in production will carry only ['finance:read'];
     * tests can override to verify the gate.
     *
     * Uses a non-persisted User so the auth gate doesn't require a database
     * round-trip. This keeps validation / auth tests independent of dev-env
     * DB connectivity. DB-dependent tests (row shape, status filter, cursor)
     * still self-skip when the DB is unreachable.
     *
     * @param  array<int,string>  $abilities
     */
    private function authAs(array $abilities)
    {
        $user = new \AlphaDirect\User();
        $user->id        = 1;
        $user->firstName = 'Test';
        $user->lastName  = 'ERP';
        $user->email     = 'erp-test@alphadirect.co.bw';
        Sanctum::actingAs($user, $abilities);
        return $user;
    }

    public function test_endpoint_requires_authentication()
    {
        $response = $this->getJson(self::ENDPOINT.'?date_from=2099-01-01&date_to=2099-01-31');
        $response->assertStatus(401);
    }

    public function test_endpoint_rejects_token_without_finance_read_ability()
    {
        // Token with a different ability — must NOT pass the ability gate.
        $this->authAs(['policies:read']);

        $response = $this->getJson(self::ENDPOINT.'?date_from='.self::FUTURE_DATE.'&date_to='.self::FUTURE_DATE_2);
        $response->assertStatus(403);
    }

    public function test_endpoint_accepts_token_with_finance_read_ability()
    {
        $this->requireDb();
        $this->authAs(['finance:read']);

        $response = $this->getJson(self::ENDPOINT.'?date_from='.self::FUTURE_DATE.'&date_to='.self::FUTURE_DATE_2);
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['count', 'per_page', 'next_cursor', 'window' => ['from', 'to'], 'has_more'],
            ]);
    }

    public function test_endpoint_accepts_wildcard_token_for_admin_user()
    {
        $this->requireDb();
        // Admin users with full-scope tokens (Sanctum's '*' wildcard) should
        // still pass — the wildcard explicitly covers every ability.
        $this->authAs(['*']);

        $response = $this->getJson(self::ENDPOINT.'?date_from='.self::FUTURE_DATE.'&date_to='.self::FUTURE_DATE_2);
        $response->assertStatus(200);
    }

    public function test_validation_rejects_missing_date_window()
    {
        $this->authAs(['finance:read']);

        $response = $this->getJson(self::ENDPOINT);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date_from', 'date_to']);
    }

    public function test_validation_rejects_inverted_date_window()
    {
        $this->authAs(['finance:read']);

        $response = $this->getJson(self::ENDPOINT.'?date_from=2099-01-31&date_to=2099-01-01');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date_to']);
    }

    public function test_validation_rejects_window_exceeding_31_days()
    {
        $this->authAs(['finance:read']);

        // 32 days — must trip the explicit window cap. Picked dates that
        // sit firmly inside reasonable range so no other rule fires first.
        $response = $this->getJson(self::ENDPOINT.'?date_from=2099-01-01&date_to=2099-02-02');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date_to']);
    }

    public function test_validation_rejects_limit_above_cap()
    {
        $this->authAs(['finance:read']);

        $response = $this->getJson(self::ENDPOINT.'?date_from='.self::FUTURE_DATE.'&date_to='.self::FUTURE_DATE_2.'&limit=5000');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['limit']);
    }

    public function test_validation_rejects_malformed_date()
    {
        $this->authAs(['finance:read']);

        $response = $this->getJson(self::ENDPOINT.'?date_from=01-01-2099&date_to=31-01-2099');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date_from', 'date_to']);
    }

    public function test_empty_window_returns_zero_rows_with_valid_envelope()
    {
        $this->requireDb();
        $this->authAs(['finance:read']);

        $response = $this->getJson(self::ENDPOINT.'?date_from='.self::FUTURE_DATE.'&date_to='.self::FUTURE_DATE_2);
        $response->assertStatus(200)
            ->assertJson([
                'data' => [],
                'meta' => [
                    'count'    => 0,
                    'has_more' => false,
                    'window'   => ['from' => self::FUTURE_DATE, 'to' => self::FUTURE_DATE_2],
                ],
            ]);
    }

    public function test_show_endpoint_returns_404_for_unknown_id()
    {
        $this->requireDb();
        $this->authAs(['finance:read']);

        $response = $this->getJson(self::ENDPOINT.'/999999999');
        $response->assertStatus(404)
            ->assertJson(['error' => 'not_found']);
    }

    public function test_show_endpoint_requires_finance_read_ability()
    {
        $this->authAs(['policies:read']);

        $response = $this->getJson(self::ENDPOINT.'/1');
        $response->assertStatus(403);
    }

    public function test_row_shape_when_data_exists()
    {
        $this->requireDb();
        $this->authAs(['finance:read']);

        $first = \DB::connection('mysql')->table('payment_transactions')->orderBy('id')->first();
        if (!$first) {
            $this->markTestSkipped('No payment_transactions rows in database');
        }

        $dateFrom = date('Y-m-d', strtotime($first->created_at) - 86400);
        $dateTo   = date('Y-m-d', strtotime($first->created_at) + 86400);

        $response = $this->getJson(self::ENDPOINT.'?date_from='.$dateFrom.'&date_to='.$dateTo.'&limit=1');
        $response->assertStatus(200);

        $data = $response->json('data');
        if (count($data) === 0) {
            $this->markTestSkipped('No rows landed in window; skipping shape assertion');
        }

        $this->assertArrayHasKey('id',                $data[0]);
        $this->assertArrayHasKey('policy_number',     $data[0]);
        $this->assertArrayHasKey('reference_number',  $data[0]);
        $this->assertArrayHasKey('amount',            $data[0]);
        $this->assertArrayHasKey('payment_method',    $data[0]);
        $this->assertArrayHasKey('status',            $data[0]);
        $this->assertArrayHasKey('is_refund',         $data[0]);
        $this->assertArrayHasKey('is_reverse',        $data[0]);
        $this->assertArrayHasKey('paid_at',           $data[0]);
        $this->assertArrayHasKey('recorded_at',       $data[0]);
        $this->assertArrayHasKey('policy',            $data[0]);
        $this->assertArrayHasKey('dpo',               $data[0]);

        // Type assertions — guard against accidental schema drift.
        $this->assertIsInt($data[0]['id']);
        $this->assertIsBool($data[0]['is_refund']);
        $this->assertIsBool($data[0]['is_reverse']);
        $this->assertIsFloat($data[0]['amount']);
    }

    public function test_case_insensitive_status_filter()
    {
        $this->requireDb();
        $this->authAs(['finance:read']);

        $rowWithStatus = \DB::connection('mysql')
            ->table('payment_transactions')
            ->whereNotNull('status')
            ->where('status', '<>', '')
            ->orderBy('id', 'desc')
            ->first();
        if (!$rowWithStatus) {
            $this->markTestSkipped('No payment_transactions row with status to test against');
        }

        $dateFrom = date('Y-m-d', strtotime($rowWithStatus->created_at) - 86400);
        $dateTo   = date('Y-m-d', strtotime($rowWithStatus->created_at) + 86400);

        $upper = $this->getJson(self::ENDPOINT
            .'?date_from='.$dateFrom
            .'&date_to='.$dateTo
            .'&status='.strtoupper($rowWithStatus->status)
            .'&policy_number='.urlencode($rowWithStatus->policyNumber ?? ''));
        $lower = $this->getJson(self::ENDPOINT
            .'?date_from='.$dateFrom
            .'&date_to='.$dateTo
            .'&status='.strtolower($rowWithStatus->status)
            .'&policy_number='.urlencode($rowWithStatus->policyNumber ?? ''));

        $upper->assertStatus(200);
        $lower->assertStatus(200);
        // Both casings must return the same count — the controller normalises.
        $this->assertSame($upper->json('meta.count'), $lower->json('meta.count'));
    }

    public function test_cursor_returned_when_more_pages_exist()
    {
        $this->requireDb();
        $this->authAs(['finance:read']);

        // Pick the most recent row's date as a 1-day window with limit=1.
        // If there are 2+ rows that day, has_more must be true.
        $latest = \DB::connection('mysql')->table('payment_transactions')->orderBy('id', 'desc')->first();
        if (!$latest) {
            $this->markTestSkipped('No payment_transactions rows');
        }

        $day = date('Y-m-d', strtotime($latest->created_at));
        $sameDayCount = \DB::connection('mysql')
            ->table('payment_transactions')
            ->whereBetween('created_at', [$day.' 00:00:00', $day.' 23:59:59'])
            ->count();

        if ($sameDayCount < 2) {
            $this->markTestSkipped('Need >=2 rows in the same day to assert next_cursor');
        }

        $response = $this->getJson(self::ENDPOINT.'?date_from='.$day.'&date_to='.$day.'&limit=1');
        $response->assertStatus(200);
        $this->assertTrue($response->json('meta.has_more'), 'has_more should be true when more pages exist');
        $this->assertNotNull($response->json('meta.next_cursor'), 'next_cursor should not be null when more pages exist');
    }
}
