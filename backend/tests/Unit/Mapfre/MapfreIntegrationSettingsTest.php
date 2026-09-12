<?php

namespace Tests\Unit\Mapfre;

use AlphaDirect\Http\Controllers\Api\V1\IntegrationSettingsController;
use AlphaDirect\Services\Mapfre\MapfreAuthClient;
use AlphaDirect\Services\Mapfre\MapfreTravelClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;
use ReflectionMethod;
use Tests\TestCase;

/**
 * The MAPFRE card on Admin > Integrations: its "configured" chip and its
 * Test connection button.
 *
 * Both were broken in the same way — the screen was written around Swiftly's
 * shape (one `api_key`, one testable list endpoint) and MAPFRE fits neither:
 *
 *   1. `configured` was `!empty(config("services.<slug>.api_key"))`. MAPFRE
 *      authenticates through Cognito + eMiA and has NO `api_key` key at all,
 *      so the card showed "Not configured" permanently, whatever was set.
 *   2. `testConnection()` early-returned "Connection test is not available for
 *      this integration" for everything except Swiftly — even though the admin
 *      UI's own capability map advertises a connection test for MAPFRE.
 *
 * Exercised through reflection on the private methods rather than over HTTP:
 * that keeps the test on the logic that was wrong, with no sanctum session,
 * no roles and no database. Outbound HTTP is mocked by binding a
 * MapfreAuthClient built on a Guzzle MockHandler into the container, which is
 * the seam testConnection() resolves through.
 */
class MapfreIntegrationSettingsTest extends TestCase
{
    /** FAKE, non-secret credentials. The .test TLD cannot resolve to a real host. */
    private const FAKE_CONFIG = [
        'enabled'               => true,
        'base_url'              => 'https://mapfre.test/maip_api_travel',
        'auth_url'              => 'https://mapfre.test/auth',
        'cognito_token_url'     => 'https://cognito.mapfre.test/oauth2/token',
        'cognito_client_id'     => 'test-cognito-id',
        'cognito_client_secret' => 'test-cognito-secret',
        'username'              => 'test-user',
        'password'              => 'test-password',
        'country'               => 'BW',
        'country_id'            => 'IT',
        'language'              => 'en',
        'timeout'               => 30,
        'connect_timeout'       => 10,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.mapfre', self::FAKE_CONFIG);

        Cache::forget('mapfre:cognito_token');
        Cache::forget('mapfre:emia_token');
    }

    /** Invoke a private/protected method on the controller. */
    private function invokePrivate(string $method, array $args = [])
    {
        $m = new ReflectionMethod(IntegrationSettingsController::class, $method);
        $m->setAccessible(true);

        return $m->invokeArgs(new IntegrationSettingsController(), $args);
    }

    /** Bind a MapfreAuthClient whose Guzzle client serves the queued responses. */
    private function bindAuthClient(array $responses): void
    {
        $stack = HandlerStack::create(new MockHandler($responses));

        $this->app->instance(
            MapfreAuthClient::class,
            new MapfreAuthClient(new Client(['handler' => $stack]))
        );
    }

    /** The happy-path Cognito + eMiA pair. */
    private function authChainResponses(): array
    {
        return [
            new Response(200, [], json_encode([
                'access_token' => 'COGNITO-TOKEN-123',
                'token_type'   => 'Bearer',
                'expires_in'   => 3600,
            ])),
            new Response(200, [], json_encode([
                'access_token' => 'EMIA-TOKEN-456',
                'token_type'   => 'Bearer',
                'expires_in'   => 1800,
            ])),
        ];
    }

    // ── the "Not configured" chip ────────────────────────────────────────

    /** THE BUG: fully-credentialled MAPFRE must not report itself unconfigured. */
    public function test_mapfre_counts_as_configured_when_its_credentials_are_set(): void
    {
        $this->assertTrue($this->invokePrivate('isConfigured', ['mapfre']),
            'MAPFRE has every Cognito/eMiA credential set, so the card must show Configured.');

        // Guard the specific cause: there is no api_key for MAPFRE, so the old
        // generic check could only ever have returned false.
        $this->assertEmpty(config('services.mapfre.api_key'),
            'MAPFRE genuinely has no api_key — that is why the generic check was wrong.');
    }

    /** Each required credential is load-bearing: drop any one and it is unconfigured. */
    public function test_mapfre_is_unconfigured_when_any_single_credential_is_missing(): void
    {
        foreach (MapfreAuthClient::REQUIRED_CONFIG_KEYS as $key) {
            config()->set('services.mapfre', self::FAKE_CONFIG);
            config()->set("services.mapfre.$key", null);

            $this->assertFalse($this->invokePrivate('isConfigured', ['mapfre']),
                "Missing services.mapfre.$key must report the integration as not configured.");
        }
    }

    /**
     * THE SECOND BUG: `base_url` was not checked at all.
     *
     * "configured" was computed from MapfreAuthClient::REQUIRED_CONFIG_KEYS
     * alone. The auth chain never calls base_url, so it is not in that list —
     * yet MapfreTravelClient asserts it on every request. An environment with
     * all six auth credentials and no base_url therefore showed a green
     * "Configured" chip while every /public/travel/* call answered 503
     * travel_not_configured, pointing the operator away from the one key at
     * fault.
     */
    public function test_mapfre_is_unconfigured_when_base_url_is_missing(): void
    {
        config()->set('services.mapfre.base_url', null);

        // The auth chain is fully credentialled and reports no gap at all —
        // which is exactly why checking only its list hid this.
        $this->assertSame([], MapfreAuthClient::missingConfigKeys(),
            'Every auth credential is present; the gap is base_url alone.');

        $this->assertSame(['base_url'], $this->invokePrivate('missingConfigKeys', ['mapfre']),
            'base_url is absent, so it must be listed as a missing config key.');

        $this->assertFalse($this->invokePrivate('isConfigured', ['mapfre']),
            'Travel cannot work without base_url, so the chip must not read Configured.');
    }

    /** The chip covers the union of BOTH clients' requirements, not just one. */
    public function test_mapfre_configured_check_covers_both_clients_key_sets(): void
    {
        $required = $this->invokePrivate('requiredConfigKeys', ['mapfre']);

        foreach (MapfreAuthClient::REQUIRED_CONFIG_KEYS as $key) {
            $this->assertContains($key, $required, "Auth chain needs $key.");
        }
        foreach (MapfreTravelClient::REQUIRED_CONFIG_KEYS as $key) {
            $this->assertContains($key, $required, "Travel requests need $key.");
        }

        // No duplicates: auth_url, cognito_token_url, username and password
        // appear in both lists.
        $this->assertSame(array_values(array_unique($required)), $required,
            'A key required by both clients must be checked once, not twice.');
    }

    /** Providers that really do use one API key keep the original behaviour. */
    public function test_api_key_providers_are_unchanged(): void
    {
        config()->set('services.swiftly.api_key', null);
        $this->assertFalse($this->invokePrivate('isConfigured', ['swiftly']));

        config()->set('services.swiftly.api_key', 'sk-test');
        $this->assertTrue($this->invokePrivate('isConfigured', ['swiftly']));
    }

    /**
     * Inbound-webhook integrations authenticate the other way round.
     *
     * `alpha_transit` has no outbound api_key — the ATC platform pushes events
     * to us and presents `services.alpha_transit.webhook_token`. Added when
     * main and this branch both fixed the same wrong `api_key` assumption
     * independently (main with a single-alternate-key map, here with the
     * multi-key CONFIG_KEYS list); the merge kept the multi-key form, so this
     * pins the behaviour main contributed.
     */
    public function test_inbound_webhook_integrations_use_their_shared_token(): void
    {
        config()->set('services.alpha_transit.webhook_token', null);
        $this->assertFalse($this->invokePrivate('isConfigured', ['alpha_transit']),
            'No webhook token means alpha_transit is not configured.');

        config()->set('services.alpha_transit.webhook_token', 'shared-token');
        $this->assertTrue($this->invokePrivate('isConfigured', ['alpha_transit']),
            'A shared webhook token is the alpha_transit credential — an api_key is never set for it.');

        // The old generic check looked for api_key, which this integration has
        // no concept of — the reason it read "Not configured" while working.
        $this->assertEmpty(config('services.alpha_transit.api_key'));
    }


    // ── the Test connection button ───────────────────────────────────────

    /** THE BUG: the button must actually test, not report itself unavailable. */
    public function test_mapfre_connection_test_authenticates_and_reports_ok(): void
    {
        $this->bindAuthClient($this->authChainResponses());

        $payload = $this->invokePrivate('testMapfreConnection')->getData(true);

        $this->assertTrue($payload['ok'], 'A working auth chain must report ok.');
        $this->assertSame('https://mapfre.test/maip_api_travel', $payload['base_url']);
        $this->assertSame('IT', $payload['country_id']);

        // The bearer token must never be handed to the browser.
        $this->assertStringNotContainsString('EMIA-TOKEN-456', json_encode($payload),
            'The connection test must not leak the MAPFRE token.');
    }

    /** A provider-side failure is a clean ok:false, not an exception or a 500. */
    public function test_mapfre_connection_test_reports_a_provider_failure_cleanly(): void
    {
        $this->bindAuthClient([new Response(401, [], json_encode(['message' => 'Unauthorized']))]);

        $response = $this->invokePrivate('testMapfreConnection');
        $payload  = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode(),
            'The endpoint always answers 200 so the UI can render pass/fail itself.');
        $this->assertFalse($payload['ok']);
        $this->assertNotEmpty($payload['error']);
    }

    /** Missing credentials produce a specific, actionable message. */
    public function test_mapfre_connection_test_names_the_missing_credential(): void
    {
        config()->set('services.mapfre.cognito_client_secret', null);
        $this->bindAuthClient([]);

        $payload = $this->invokePrivate('testMapfreConnection')->getData(true);

        $this->assertFalse($payload['ok']);
        $this->assertStringContainsString('cognito_client_secret', $payload['error'],
            'The operator should be told exactly which credential is absent.');
    }

    /**
     * Every missing credential is reported at once, not one per attempt.
     *
     * assertConfigured() throws on the first absent key and cognito_token_url
     * is first of six, so an environment missing several used to answer only
     * "missing services.mapfre.cognito_token_url" — the next gap surfaced only
     * after that one had been supplied and redeployed.
     */
    public function test_mapfre_connection_test_lists_every_missing_credential(): void
    {
        config()->set('services.mapfre.cognito_token_url', null);
        config()->set('services.mapfre.username', null);
        config()->set('services.mapfre.password', null);
        $this->bindAuthClient([]); // must not need a round trip to answer

        $payload = $this->invokePrivate('testMapfreConnection')->getData(true);

        $this->assertFalse($payload['ok']);
        $this->assertSame(
            ['cognito_token_url', 'username', 'password'],
            $payload['missing'],
            'All three gaps must be reported together, in declaration order.'
        );

        foreach (['cognito_token_url', 'username', 'password'] as $key) {
            $this->assertStringContainsString("services.mapfre.{$key}", $payload['error']);
        }

        // The credentials that ARE present must not be named as missing.
        $this->assertStringNotContainsString('cognito_client_secret', $payload['error']);
    }

    /** The helper is the single source of truth for what "configured" means. */
    public function test_missing_config_keys_is_empty_when_fully_configured(): void
    {
        $this->assertSame([], MapfreAuthClient::missingConfigKeys());
    }

    /**
     * A missing base_url must fail the connection test, not pass it.
     *
     * The auth chain would authenticate happily — base_url is not one of its
     * credentials — so the test used to answer "Authenticated with MAPFRE" and
     * echo `base_url: null`, declaring healthy an environment on which no
     * travel call could succeed.
     */
    public function test_mapfre_connection_test_reports_a_missing_base_url(): void
    {
        config()->set('services.mapfre.base_url', null);
        $this->bindAuthClient($this->authChainResponses()); // would have succeeded

        $payload = $this->invokePrivate('testMapfreConnection')->getData(true);

        $this->assertFalse($payload['ok'],
            'No base_url means no travel endpoint to call — that is not a pass.');
        $this->assertSame(['base_url'], $payload['missing']);
        $this->assertStringContainsString('services.mapfre.base_url', $payload['error']);
    }


    /**
     * A warm cached token must not be mistaken for connectivity.
     *
     * testMapfreConnection() calls forgetTokens() first for this reason. With a
     * stale token in the cache and an empty response queue, a cached-token path
     * would report ok; the real behaviour is a failed live call.
     */
    public function test_mapfre_connection_test_does_not_pass_on_a_cached_token(): void
    {
        Cache::put('mapfre:emia_token', 'STALE-TOKEN', 1800);
        $this->bindAuthClient([]); // no responses queued — any live call fails

        $payload = $this->invokePrivate('testMapfreConnection')->getData(true);

        $this->assertFalse($payload['ok'],
            'The test must hit MAPFRE, not short-circuit on a cached token.');
    }

    /**
     * And on success the cache is left REFRESHED, not empty — clearing it
     * without repopulating would leave in-flight travel requests to re-auth.
     */
    public function test_a_successful_connection_test_leaves_a_fresh_token_cached(): void
    {
        Cache::put('mapfre:emia_token', 'STALE-TOKEN', 1800);
        $this->bindAuthClient($this->authChainResponses());

        $this->assertTrue($this->invokePrivate('testMapfreConnection')->getData(true)['ok']);

        $this->assertSame('EMIA-TOKEN-456', Cache::get('mapfre:emia_token'),
            'The token cache should hold the newly fetched token.');
    }
}
