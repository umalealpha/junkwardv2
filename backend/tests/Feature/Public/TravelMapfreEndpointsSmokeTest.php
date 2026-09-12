<?php

namespace Tests\Feature\Public;

use AlphaDirect\Services\Mapfre\MapfreNotConfiguredException;
use AlphaDirect\Services\Mapfre\MapfreResponse;
use AlphaDirect\Services\Mapfre\MapfreTravelClient;
use Tests\TestCase;

/**
 * Smoke test for the public Travel Insurance endpoints that front the MAPFRE /
 * MAWDY connector (PublicTravelController).
 *
 * No live DB and no live network: MapfreTravelClient is swapped for a stub
 * bound into the container, so every assertion here is about OUR contract with
 * the Start portal — the shape of a success body, and the way each upstream
 * failure mode is translated. MapfreTravelClient's own behaviour (auth, 401
 * retry, audit row) is covered by tests/Unit/Mapfre/MapfreTravelClientTest.
 *
 * The endpoints exercised are the ones the portal can reach WITHOUT a DB: the
 * config-driven package catalogue and the MAPFRE relay lookups. price / quote
 * / contract additionally resolve the agent (users row) + permission and are
 * covered by the agent-gate assertions in the portal e2e run.
 */
class TravelMapfreEndpointsSmokeTest extends TestCase
{
    /** Stand-in for the real client; each method returns a queued response. */
    private function fakeClient(MapfreResponse $response): MapfreTravelClient
    {
        return new class ($response) extends MapfreTravelClient {
            public function __construct(private MapfreResponse $canned)
            {
                // Deliberately skip parent::__construct — no config, no auth
                // client, no Guzzle. Every call is answered from $canned.
            }

            public function tripTypes(): MapfreResponse
            {
                return $this->canned;
            }

            public function destinations(string $tripType): MapfreResponse
            {
                return $this->canned;
            }

            public function fiscalIdTypes(): MapfreResponse
            {
                return $this->canned;
            }
        };
    }

    private function bindFake(MapfreResponse $response): void
    {
        $this->app->instance(MapfreTravelClient::class, $this->fakeClient($response));
    }

    /**
     * Stand-in whose every call THROWS — for the two outcomes that are not a
     * MapfreResponse at all.
     */
    private function bindThrowingFake(\Throwable $e): void
    {
        $client = new class ($e) extends MapfreTravelClient {
            public function __construct(private \Throwable $toThrow)
            {
                // As above: no parent::__construct, no config, no network.
            }

            public function tripTypes(): MapfreResponse
            {
                throw $this->toThrow;
            }

            public function destinations(string $tripType): MapfreResponse
            {
                throw $this->toThrow;
            }

            public function fiscalIdTypes(): MapfreResponse
            {
                throw $this->toThrow;
            }
        };

        $this->app->instance(MapfreTravelClient::class, $client);
    }

    // ─── Missing credentials vs failed authentication ────────────────────────

    /** A genuinely absent credential is a configuration problem: 503. */
    public function test_a_missing_credential_is_reported_as_not_configured(): void
    {
        $this->bindThrowingFake(new MapfreNotConfiguredException('cognito_client_secret'));

        $this->getJson('/api/v1/public/travel/trip-types')
            ->assertStatus(503)
            ->assertJson(['ok' => false, 'error' => 'travel_not_configured']);
    }

    /**
     * THE BUG: authentication failures are NOT configuration problems.
     *
     * Seven of the nine RuntimeExceptions the MAPFRE clients raise are auth or
     * connectivity failures ("Cognito authentication failed (HTTP 401)",
     * "eMiA token response missing access_token", timeouts). They all landed in
     * the same catch as assertConfigured() and were reported as
     * `travel_not_configured`, so a revoked client secret or a MAPFRE outage
     * looked like a missing env var.
     */
    public function test_an_authentication_failure_is_not_reported_as_not_configured(): void
    {
        $this->bindThrowingFake(new \RuntimeException('MAPFRE Cognito authentication failed (HTTP 401)'));

        $response = $this->getJson('/api/v1/public/travel/trip-types');

        $response->assertStatus(502)
            ->assertJson(['ok' => false, 'error' => 'travel_unavailable']);

        $this->assertNotSame('travel_not_configured', $response->json('error'),
            'An auth failure must not be reported as a configuration gap.');
    }

    /** A network/timeout failure is likewise an availability problem. */
    public function test_a_transport_failure_is_reported_as_unavailable(): void
    {
        $this->bindThrowingFake(new \RuntimeException('MAPFRE Cognito authentication error'));

        $this->getJson('/api/v1/public/travel/trip-types')
            ->assertStatus(502)
            ->assertJson(['ok' => false, 'error' => 'travel_unavailable']);
    }


    // ─── Package catalogue (config-driven, no upstream call) ─────────────────

    /** A tier with a configured MAPFRE product id is available; one without isn't. */
    public function test_packages_reports_availability_per_configured_product_id(): void
    {
        config()->set('services.mapfre.travel_products', [
            ['code' => 'essential', 'name' => 'Essential', 'blurb' => 'Medical only', 'id' => '77'],
            ['code' => 'premium',   'name' => 'Premium',   'blurb' => 'Highest limits', 'id' => null],
        ]);

        $response = $this->getJson('/api/v1/public/travel/packages');

        $response->assertOk()
            ->assertJson([
                'ok' => true,
                'packages' => [
                    ['code' => 'essential', 'product_id' => '77',  'available' => true],
                    ['code' => 'premium',   'product_id' => null,  'available' => false],
                ],
            ]);
    }

    // ─── Relay success ──────────────────────────────────────────────────────

    /** A MAPFRE list comes back flattened under the endpoint's own key. */
    public function test_trip_types_relays_the_upstream_list(): void
    {
        $this->bindFake(MapfreResponse::success(200, [
            ['id' => 1, 'name' => 'Single trip'],
            ['id' => 2, 'name' => 'Annual multi-trip'],
        ]));

        $this->getJson('/api/v1/public/travel/trip-types')
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'trip_types' => [
                    ['id' => 1, 'name' => 'Single trip'],
                    ['id' => 2, 'name' => 'Annual multi-trip'],
                ],
            ]);
    }

    /** Envs that wrap the list in { data: [...] } are normalised to a flat list. */
    public function test_trip_types_unwraps_a_data_wrapped_list(): void
    {
        $this->bindFake(MapfreResponse::success(200, [
            'data' => [['id' => 1, 'name' => 'Single trip']],
        ]));

        $this->getJson('/api/v1/public/travel/trip-types')
            ->assertOk()
            ->assertJson(['ok' => true, 'trip_types' => [['id' => 1, 'name' => 'Single trip']]]);
    }

    // ─── Failure mapping ────────────────────────────────────────────────────

    /** Integration toggled off → 503 with a stable code, not a 500. */
    public function test_disabled_integration_returns_503(): void
    {
        $this->bindFake(MapfreResponse::failure(null, 'MAPFRE integration is disabled'));

        $this->getJson('/api/v1/public/travel/trip-types')
            ->assertStatus(503)
            ->assertJson(['ok' => false, 'error' => 'travel_integration_disabled']);
    }

    /** Upstream 5xx → 502 mapfre_unavailable (retry, don't blame the input). */
    public function test_upstream_server_error_returns_502(): void
    {
        $this->bindFake(MapfreResponse::failure(500, 'Server error: `GET https://api20-pre.mia-assistance.com/...`'));

        $this->getJson('/api/v1/public/travel/trip-types')
            ->assertStatus(502)
            ->assertJson(['ok' => false, 'error' => 'mapfre_unavailable', 'upstream_http' => 500]);
    }

    /**
     * Upstream 4xx → 422 carrying MAPFRE's own business message, and NOT the
     * Guzzle exception text (which embeds our MAPFRE base URL).
     */
    public function test_upstream_client_error_surfaces_business_message_without_leaking_base_url(): void
    {
        $this->bindFake(MapfreResponse::failure(
            400,
            'Client error: `GET https://api20-pre.mia-assistance.com/maip_api_travel/api/v1/dealer/destinations`',
            json_encode(['message' => 'tripType 9 is not available for this dealer']),
        ));

        $response = $this->getJson('/api/v1/public/travel/destinations?trip_type=9');

        $response->assertStatus(422)
            ->assertJson([
                'ok'      => false,
                'error'   => 'mapfre_rejected',
                'message' => 'tripType 9 is not available for this dealer',
            ]);
        $this->assertStringNotContainsString('mia-assistance.com', $response->getContent());
    }

    /** An upstream 401/403 is OUR credential problem → 502, never a 422. */
    public function test_upstream_auth_error_is_reported_as_unavailable(): void
    {
        $this->bindFake(MapfreResponse::failure(401, 'Client error: 401 Unauthorized'));

        $this->getJson('/api/v1/public/travel/trip-types')
            ->assertStatus(502)
            ->assertJson(['ok' => false, 'error' => 'mapfre_unavailable']);
    }

    /** Missing trip_type is rejected before any upstream call. */
    public function test_destinations_requires_a_trip_type(): void
    {
        $this->bindFake(MapfreResponse::success(200, []));

        $this->getJson('/api/v1/public/travel/destinations')
            ->assertStatus(422)
            ->assertJsonValidationErrors('trip_type');
    }
}
