<?php

namespace Tests\Unit\Mapfre;

use AlphaDirect\Services\Mapfre\MapfreAuthClient;
use AlphaDirect\Services\Mapfre\MapfreResponse;
use AlphaDirect\Services\Mapfre\MapfreTravelClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Unit tests for the MAPFRE / MAWDY "maip-travel" travel client.
 *
 * No live DB and no live network. Outbound HTTP is mocked by INJECTING a
 * Guzzle client (MockHandler + history middleware) into MapfreTravelClient.
 * The auth chain is made deterministic by pre-seeding the eMiA token in the
 * (array-driver) Cache so every test's MockHandler queue only needs to serve
 * the actual /api/v1/* response — not the two-stage auth round-trips.
 *
 * contract() writes a best-effort audit row to mapfre_quote_submissions on
 * mysql_system; with no DB that write throws internally and is swallowed by
 * the service's try/catch, so the contract call still succeeds here — which is
 * exactly the guarantee we want to pin down.
 */
class MapfreTravelClientTest extends TestCase
{
    /** The pre-seeded eMiA bearer used to authorize /api/v1/* calls in these tests. */
    private const EMIA_TOKEN = 'EMIA-TOKEN-SEEDED';

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * "NO LIVE DB" WAS NOT TRUE, AND IT COST THIS SUITE ITS GREEN.
         *
         * The enabled-flag guards call IntegrationSettings::isEnabled(), which
         * reads integration_settings on the mysql_system connection and only
         * falls back to config when there is NO ROW. That makes
         * services.mapfre.enabled a DEFAULT, not a switch — and the server
         * carries a row saying enabled=1, so a live toggle silently outranked
         * every config() call in this file. test_disabled_flag_short_circuits_
         * every_method set the config false, isEnabled() answered true from the
         * database, tripTypes() went out to the network and exhausted an empty
         * MockHandler.
         *
         * The production behaviour is correct and is not changed: an operator
         * toggling an integration from the admin UI is meant to outrank config
         * without a redeploy. What was wrong is a UNIT test reaching that
         * database at all.
         */
        config(['database.connections.mysql_system.driver' => 'sqlite']);
        config(['database.connections.mysql_system.database' => ':memory:']);
        config(['database.connections.mysql_system.prefix' => '']);
        config(['database.connections.mysql_system.foreign_key_constraints' => false]);
        DB::purge('mysql_system');

        // An EMPTY table rather than no table. isEnabled() also falls back when
        // the read throws, but by way of a logged warning on every call; a table
        // with no rows reaches the same answer quietly, and says out loud that
        // "no row" is the state being tested.
        //
        // GUARDED, because the :memory: database outlives the test that made it
        // — purging the connection drops Laravel's handle on it, not sqlite's —
        // so the second test in the class would otherwise die on "table already
        // exists". Nothing here ever inserts a row, so an inherited empty table
        // is the same state as a fresh one.
        if (! Schema::connection('mysql_system')->hasTable('integration_settings')) {
            Schema::connection('mysql_system')->create('integration_settings', function (Blueprint $t) {
                $t->increments('id');
                $t->string('integration')->index();
                $t->boolean('enabled')->default(false);
            });
        }

        // mapfre_quote_submissions is deliberately NOT created. contract()'s
        // audit write is best-effort and swallows its own failure, and this
        // class exists partly to pin that guarantee.

        config()->set('services.mapfre', [
            'enabled'               => true,
            'base_url'              => 'https://mapfre.test/maip_api_travel',
            'auth_url'              => 'https://mapfre.test/auth',
            'cognito_token_url'     => 'https://cognito.mapfre.test/oauth2/token',
            'cognito_client_id'     => 'test-cognito-id',
            'cognito_client_secret' => 'test-cognito-secret',
            'username'              => 'test-user',
            'password'              => 'test-password',
            'country'               => 'BW',
            'language'              => 'en',
            'timeout'               => 30,
            'connect_timeout'       => 10,
        ]);

        // Pre-seed the eMiA token so MapfreAuthClient::token() returns from cache
        // and never tries to reach Cognito/eMiA during these travel tests.
        Cache::forget('mapfre:cognito_token');
        Cache::put('mapfre:emia_token', self::EMIA_TOKEN, 3600);
    }

    /**
     * Drop the redirected connection rather than leaving it resolved. Config is
     * rebuilt per test, but the DatabaseManager caches what it has already
     * resolved — and a later test finding an in-memory sqlite behind the name
     * mysql_system would be the same class of fault as the one above, pointed
     * the other way.
     */
    protected function tearDown(): void
    {
        DB::purge('mysql_system');

        parent::tearDown();
    }

    /**
     * Build a MapfreTravelClient backed by a MockHandler serving $responses.
     * A pre-seeded MapfreAuthClient sharing the SAME mock client is injected so
     * the auth chain is satisfied from cache and the queue only serves API hits.
     */
    private function travelClientWith(array $responses, array &$history): MapfreTravelClient
    {
        $mock  = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $http  = new Client(['handler' => $stack]);

        return new MapfreTravelClient($http, new MapfreAuthClient($http));
    }

    /** @return Request the last outbound request captured by the history middleware */
    private function lastRequest(array $history): Request
    {
        $this->assertNotEmpty($history, 'expected at least one outbound request');
        return $history[array_key_last($history)]['request'];
    }

    // ─── GET endpoints ───────────────────────────────────────────────────────

    /** tripTypes() success → isSuccess() true + parsed list. */
    public function test_trip_types_success(): void
    {
        $history = [];
        $client = $this->travelClientWith([
            new Response(200, [], json_encode([
                ['id' => 1, 'name' => 'Single trip'],
                ['id' => 2, 'name' => 'Annual multi-trip'],
            ])),
        ], $history);

        $res = $client->tripTypes();

        $this->assertTrue($res->isSuccess());
        $this->assertSame(200, $res->httpStatus);
        $this->assertSame('Single trip', $res->data()[0]['name']);
        $this->assertSame(
            'https://mapfre.test/maip_api_travel/api/v1/dealer/tripTypes',
            (string) $this->lastRequest($history)->getUri(),
        );
    }

    /** destinations() success → isSuccess() true, tripType is sent as a query param. */
    public function test_destinations_success_passes_trip_type_query(): void
    {
        $history = [];
        $client = $this->travelClientWith([
            new Response(200, [], json_encode([
                ['code' => 'ES', 'name' => 'Spain'],
            ])),
        ], $history);

        $res = $client->destinations('2');

        $this->assertTrue($res->isSuccess());
        $this->assertSame('Spain', $res->get('0')['name'] ?? $res->data()[0]['name']);
        $this->assertSame(
            'https://mapfre.test/maip_api_travel/api/v1/dealer/destinations?tripType=2',
            (string) $this->lastRequest($history)->getUri(),
        );
    }

    /** fiscalIdTypes() success → isSuccess() true + parsed catalog. */
    public function test_fiscal_id_types_success(): void
    {
        $history = [];
        $client = $this->travelClientWith([
            new Response(200, [], json_encode([
                ['id' => 'PASSPORT', 'name' => 'Passport'],
                ['id' => 'NIF', 'name' => 'NIF'],
            ])),
        ], $history);

        $res = $client->fiscalIdTypes();

        $this->assertTrue($res->isSuccess());
        $this->assertSame('Passport', $res->data()[0]['name']);
        $this->assertSame(
            'https://mapfre.test/maip_api_travel/api/v1/catalog/fiscalIdTypes',
            (string) $this->lastRequest($history)->getUri(),
        );
    }

    // ─── POST endpoints ──────────────────────────────────────────────────────

    /** price() success → isSuccess() true, posts the request body, parses the premium. */
    public function test_price_success_posts_body_and_parses_premium(): void
    {
        $history = [];
        $client = $this->travelClientWith([
            new Response(200, [], json_encode([
                'productCode'  => 'TRV-STD',
                'totalPremium' => 350.5,
                'currency'     => 'BWP',
            ])),
        ], $history);

        $req = [
            'startDate'   => '2026-07-01',
            'endDate'     => '2026-07-15',
            'destination' => 'ES',
            'travellers'  => [['age' => 34]],
        ];
        $res = $client->price('77', $req);

        $this->assertTrue($res->isSuccess());
        $this->assertSame(350.5, $res->get('totalPremium'));

        $sent = $this->lastRequest($history);
        $this->assertSame('POST', $sent->getMethod());
        $this->assertSame(
            'https://mapfre.test/maip_api_travel/api/v1/product/77/price',
            (string) $sent->getUri(),
        );
        $this->assertSame($req, json_decode((string) $sent->getBody(), true));
    }

    /** quote() success → returns quoteId + token (the shape contract() consumes next). */
    public function test_quote_success_returns_quote_id_and_token(): void
    {
        $history = [];
        $client = $this->travelClientWith([
            new Response(201, [], json_encode([
                'quoteId'     => 'Q-2026-0001',
                'productCode' => 'TRV-STD',
                'token'       => 'QUOTE-TOKEN-XYZ',
                'totalPremium' => 350.00,
            ])),
        ], $history);

        $res = $client->quote('77', [
            'startDate'   => '2026-07-01',
            'endDate'     => '2026-07-15',
            'destination' => 'ES',
            'travellers'  => [['age' => 34, 'fiscalIdType' => 'PASSPORT', 'fiscalId' => 'BW123456']],
        ]);

        $this->assertTrue($res->isSuccess());
        $this->assertSame(201, $res->httpStatus);
        $this->assertSame('Q-2026-0001', $res->get('quoteId'));
        $this->assertSame('QUOTE-TOKEN-XYZ', $res->get('token'));
        $this->assertSame(
            'https://mapfre.test/maip_api_travel/api/v1/product/77/quotes',
            (string) $this->lastRequest($history)->getUri(),
        );
    }

    /** contract() success → returns contractNumber + productName; audit write is best-effort. */
    public function test_contract_success_returns_contract_number(): void
    {
        $history = [];
        $client = $this->travelClientWith([
            new Response(201, [], json_encode([
                'contractNumber' => 'C-2026-9001',
                'productName'    => 'MAWDY Travel Standard',
            ])),
        ], $history);

        $res = $client->contract('77', [
            'reference' => 'AD-REF-001',
            'quoteId'   => 'Q-2026-0001',
            'token'     => 'QUOTE-TOKEN-XYZ',
        ]);

        $this->assertTrue($res->isSuccess());
        $this->assertSame('C-2026-9001', $res->get('contractNumber'));
        $this->assertSame('MAWDY Travel Standard', $res->get('productName'));
        $this->assertSame(
            'https://mapfre.test/maip_api_travel/api/v1/product/77/contract',
            (string) $this->lastRequest($history)->getUri(),
        );
    }

    // ─── Error handling ──────────────────────────────────────────────────────

    /** A 4xx → isSuccess() false, error populated, NO exception bubbles to the caller. */
    public function test_client_error_returns_failure_without_throwing(): void
    {
        $history = [];
        $client = $this->travelClientWith([
            new Response(422, [], json_encode([
                'error'   => 'validation_failed',
                'message' => 'destination is required',
            ])),
        ], $history);

        $res = $client->price('77', ['startDate' => '2026-07-01']);

        $this->assertInstanceOf(MapfreResponse::class, $res);
        $this->assertFalse($res->isSuccess());
        $this->assertSame(422, $res->httpStatus);
        $this->assertNotNull($res->error);
        $this->assertNotEmpty($res->error);
    }

    // ─── Feature flag ────────────────────────────────────────────────────────

    /** With the integration disabled, every method returns failure WITHOUT any HTTP call. */
    public function test_disabled_flag_short_circuits_every_method(): void
    {
        config()->set('services.mapfre.enabled', false);

        $history = [];
        // No responses queued: any outbound call would exhaust the empty
        // MockHandler and surface as an error, so an untouched history proves
        // the short-circuit.
        $client = $this->travelClientWith([], $history);

        $calls = [
            fn () => $client->tripTypes(),
            fn () => $client->destinations('2'),
            fn () => $client->fiscalIdTypes(),
            fn () => $client->price('77', []),
            fn () => $client->quote('77', []),
            fn () => $client->contract('77', ['reference' => 'AD-REF-001']),
        ];

        foreach ($calls as $call) {
            $res = $call();
            $this->assertFalse($res->isSuccess());
            $this->assertSame('MAPFRE integration is disabled', $res->error);
            $this->assertNull($res->httpStatus);
        }

        // Not a single outbound request was made.
        $this->assertCount(0, $history);
    }

    // ─── Security ────────────────────────────────────────────────────────────

    /**
     * The outbound request must carry the eMiA bearer, AND no secret
     * (password / client_secret / the bearer token) may appear anywhere in the
     * logged output. Every log message + context is captured via Log::listen
     * and scanned for secret values.
     */
    public function test_request_carries_bearer_and_never_logs_secrets(): void
    {
        // Capture every log record (level, message, recursively-flattened context).
        $logged = [];
        Log::listen(function ($log) use (&$logged) {
            $flat = $log->message;
            array_walk_recursive($log->context, function ($v) use (&$flat) {
                if (is_scalar($v)) {
                    $flat .= '|' . $v;
                }
            });
            $logged[] = $flat;
        });

        $history = [];
        // 200 success path then a 500 failure path so the error-logging branch
        // also runs and is checked for secret leakage.
        $client = $this->travelClientWith([
            new Response(200, [], json_encode([['id' => 1, 'name' => 'Single trip']])),
            new Response(500, [], json_encode(['error' => 'upstream', 'message' => 'boom'])),
        ], $history);

        $ok = $client->tripTypes();
        $this->assertTrue($ok->isSuccess());

        // Authorization header carries the seeded eMiA bearer.
        $this->assertSame('Bearer ' . self::EMIA_TOKEN, $this->lastRequest($history)->getHeaderLine('Authorization'));

        // Trigger the error-logging path too (drives Log::error in request()).
        $fail = $client->fiscalIdTypes();
        $this->assertFalse($fail->isSuccess());
        $this->assertSame(500, $fail->httpStatus);

        // No secret may appear anywhere in any captured log line.
        $secrets = [
            'test-password',          // services.mapfre.password
            'test-cognito-secret',    // services.mapfre.cognito_client_secret
            self::EMIA_TOKEN,         // the live eMiA bearer
        ];
        $haystack = implode("\n", $logged);
        foreach ($secrets as $secret) {
            $this->assertStringNotContainsString($secret, $haystack, "secret leaked into a log call: {$secret}");
        }
    }
}
