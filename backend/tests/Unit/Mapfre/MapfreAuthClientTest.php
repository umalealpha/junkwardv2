<?php

namespace Tests\Unit\Mapfre;

use AlphaDirect\Services\Mapfre\MapfreAuthClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Unit tests for the two-stage MAPFRE / MAWDY eMiA auth chain.
 *
 * No live DB and no live network: outbound HTTP is mocked by INJECTING a
 * Guzzle client built on a MockHandler into MapfreAuthClient's constructor
 * (the same seam the production code exposes for testability). Laravel is
 * booted (extends Tests\TestCase) so config() and Cache (array driver, per
 * phpunit.xml) work. No RefreshDatabase — these never touch the DB.
 */
class MapfreAuthClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // FAKE, non-secret test config. base_url uses the .test TLD so a leaked
        // request can never reach a real host.
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

        // Defensive: array cache should already be empty, but make the two-stage
        // expectations deterministic regardless of test order.
        Cache::forget('mapfre:cognito_token');
        Cache::forget('mapfre:emia_token');
    }

    /**
     * Build a MapfreAuthClient whose Guzzle client serves the queued responses
     * and records every outbound request into $history.
     */
    private function authClientWith(array $responses, array &$history): MapfreAuthClient
    {
        $mock  = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        return new MapfreAuthClient(new Client(['handler' => $stack]));
    }

    /** Standard happy-path Cognito + eMiA response pair. */
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

    /** token() runs Cognito then eMiA and returns the eMiA bearer. */
    public function test_token_performs_two_stage_flow_and_returns_emia_bearer(): void
    {
        $history = [];
        $auth = $this->authClientWith($this->authChainResponses(), $history);

        $token = $auth->token();

        $this->assertSame('EMIA-TOKEN-456', $token);

        // Both legs of the chain were called, in order.
        $this->assertCount(2, $history);

        /** @var Request $cognitoReq */
        $cognitoReq = $history[0]['request'];
        $this->assertSame('https://cognito.mapfre.test/oauth2/token', (string) $cognitoReq->getUri());
        $this->assertStringContainsString('grant_type=client_credentials', (string) $cognitoReq->getBody());
        // HTTP Basic client_id:client_secret on the Cognito leg.
        $this->assertSame(
            'Basic ' . base64_encode('test-cognito-id:test-cognito-secret'),
            $cognitoReq->getHeaderLine('Authorization'),
        );

        /** @var Request $emiaReq */
        $emiaReq = $history[1]['request'];
        $this->assertSame('https://mapfre.test/auth/oauth2/emia/token', (string) $emiaReq->getUri());
        // eMiA leg is authorized by the Cognito bearer.
        $this->assertSame('Bearer COGNITO-TOKEN-123', $emiaReq->getHeaderLine('Authorization'));
        // eMiA body carries the user credentials + country as JSON.
        $emiaBody = json_decode((string) $emiaReq->getBody(), true);
        $this->assertSame('test-user', $emiaBody['username']);
        $this->assertSame('test-password', $emiaBody['password']);
        $this->assertSame('BW', $emiaBody['country']);
    }

    /** A second token() call is served from cache — the network is NOT hit again. */
    public function test_token_is_cached_and_does_not_refetch(): void
    {
        $history = [];
        // Only the 2 auth-chain responses are queued. A second network round-trip
        // would exhaust the MockHandler and throw, so caching is asserted by the
        // absence of a third request.
        $auth = $this->authClientWith($this->authChainResponses(), $history);

        $first  = $auth->token();
        $second = $auth->token();

        $this->assertSame('EMIA-TOKEN-456', $first);
        $this->assertSame($first, $second);

        // Still exactly the 2 original calls — the cache short-circuited the rest.
        $this->assertCount(2, $history);
    }

    /** forgetTokens() drops the cache so the next token() re-runs the full chain. */
    public function test_forget_tokens_forces_a_refetch(): void
    {
        $history = [];
        // Two full auth chains queued: the first token() and the post-forget one.
        $auth = $this->authClientWith(
            array_merge($this->authChainResponses(), [
                new Response(200, [], json_encode([
                    'access_token' => 'COGNITO-TOKEN-NEW',
                    'expires_in'   => 3600,
                ])),
                new Response(200, [], json_encode([
                    'access_token' => 'EMIA-TOKEN-NEW',
                    'expires_in'   => 1800,
                ])),
            ]),
            $history,
        );

        $this->assertSame('EMIA-TOKEN-456', $auth->token());
        $this->assertCount(2, $history);

        $auth->forgetTokens();

        // Re-runs the chain → fresh eMiA token, two more network calls.
        $this->assertSame('EMIA-TOKEN-NEW', $auth->token());
        $this->assertCount(4, $history);
    }

    /** Missing required config (blank cognito_client_id) fails loud before any HTTP. */
    public function test_missing_config_throws_runtime_exception(): void
    {
        config()->set('services.mapfre.cognito_client_id', '');

        $history = [];
        // No responses queued: if any request fired, MockHandler would throw a
        // different error — so reaching the RuntimeException proves we short
        // circuited before the network.
        $auth = $this->authClientWith([], $history);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('services.mapfre.cognito_client_id');

        $auth->token();
    }
}
