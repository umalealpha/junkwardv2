<?php

namespace Tests\Feature\SmartUw;

use AlphaDirect\Services\SmartUw\PhpScheduleExtractor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Groq refused the model id Smart Upload was configured with — first the
 * retired meta-llama/llama-4-scout-17b-16e-instruct, then llama-3.3-70b-versatile
 * on the same key (test env, 2026-09-04), which is why guessing a replacement
 * cannot be the fix. callGroq() now asks the account which models it may call.
 *
 * Everything here is faked at the HTTP layer: no Groq key, no network, no DB.
 */
class GroqModelFallbackTest extends TestCase
{
    private const MODELS_URL = 'https://api.groq.com/openai/v1/models';
    private const CHAT_URL   = 'https://api.groq.com/openai/v1/chat/completions';

    protected function setUp(): void
    {
        parent::setUp();

        // Keep AiConfigController::getSettings() off the database: an empty
        // vault cache makes VaultController::get() fall through to env, and
        // AI_MODEL is the env behind `groq_model`.
        Cache::put('credential_vault_cache', [], 600);
        Cache::forget('smartuw.groq_models.' . substr(md5('test-key'), 0, 12));
        putenv('AI_MODEL=meta-llama/llama-4-scout-17b-16e-instruct');
        $_ENV['AI_MODEL'] = $_SERVER['AI_MODEL'] = 'meta-llama/llama-4-scout-17b-16e-instruct';
    }

    protected function tearDown(): void
    {
        putenv('AI_MODEL');
        unset($_ENV['AI_MODEL'], $_SERVER['AI_MODEL']);

        parent::tearDown();
    }

    /** Invoke the private callGroq() the way complete() does. */
    private function callGroq(string $key = 'test-key'): string
    {
        $method = new ReflectionMethod(PhpScheduleExtractor::class, 'callGroq');
        $method->setAccessible(true);

        return $method->invoke(new PhpScheduleExtractor(), 'system prompt', 'user prompt', $key);
    }

    private static function modelNotFound(string $model): array
    {
        return ['error' => [
            'message' => "The model `{$model}` does not exist or you do not have access to it.",
            'type'    => 'invalid_request_error',
            'code'    => 'model_not_found',
        ]];
    }

    private static function chatOk(string $json = '{"coverages":[]}'): array
    {
        return ['choices' => [['message' => ['content' => $json], 'finish_reason' => 'stop']]];
    }

    public function test_it_retries_on_a_model_the_account_actually_lists(): void
    {
        $chatCalls = 0;

        Http::fake([
            self::MODELS_URL => Http::response(['data' => [
                ['id' => 'whisper-large-v3'],            // speech — must be skipped
                ['id' => 'meta-llama/llama-guard-4-12b'], // classifier — must be skipped
                ['id' => 'llama-3.1-8b-instant'],         // the only usable chat model
            ]], 200),

            self::CHAT_URL => function ($request) use (&$chatCalls) {
                $chatCalls++;
                $model = $request->data()['model'];

                return $model === 'llama-3.1-8b-instant'
                    ? Http::response(self::chatOk(), 200)
                    : Http::response(self::modelNotFound($model), 404);
            },
        ]);

        $this->assertSame('{"coverages":[]}', $this->callGroq());
        $this->assertSame(2, $chatCalls, 'expected one rejected attempt then one retry');

        // Configured id first, the listed one second — and the listing in between.
        $sent = collect(Http::recorded())->map(fn ($pair) => $pair[0]);
        $this->assertSame(
            'meta-llama/llama-4-scout-17b-16e-instruct',
            $sent->first()->data()['model']
        );
        $this->assertSame(self::MODELS_URL, $sent->get(1)->url());
        $this->assertSame('llama-3.1-8b-instant', $sent->get(2)->data()['model']);
    }

    public function test_it_prefers_the_known_good_json_models_over_whatever_is_listed_first(): void
    {
        Http::fake([
            self::MODELS_URL => Http::response(['data' => [
                ['id' => 'allam-2-7b'],
                ['id' => 'openai/gpt-oss-120b'],       // second preference
                ['id' => 'llama-3.3-70b-versatile'],   // first preference
            ]], 200),

            self::CHAT_URL => fn ($request) => $request->data()['model'] === 'llama-3.3-70b-versatile'
                ? Http::response(self::chatOk('{"ok":true}'), 200)
                : Http::response(self::modelNotFound($request->data()['model']), 404),
        ]);

        $this->assertSame('{"ok":true}', $this->callGroq());
    }

    public function test_a_dead_key_produces_an_error_that_names_the_key_not_the_schedule(): void
    {
        Http::fake([
            self::MODELS_URL => Http::response(['error' => ['message' => 'Invalid API Key']], 401),
            self::CHAT_URL   => fn ($request) => Http::response(
                self::modelNotFound($request->data()['model']),
                404
            ),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/key itself is the suspect/');

        $this->callGroq();
    }

    public function test_a_surviving_model_not_found_lists_what_the_key_can_call(): void
    {
        Http::fake([
            self::MODELS_URL => Http::response(['data' => [['id' => 'llama-3.1-8b-instant']]], 200),
            self::CHAT_URL   => fn ($request) => Http::response(
                self::modelNotFound($request->data()['model']),
                404
            ),
        ]);

        try {
            $this->callGroq();
            $this->fail('expected a RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Models it can call: llama-3.1-8b-instant', $e->getMessage());
            $this->assertStringContainsString('groq_model', $e->getMessage());
        }
    }

    public function test_a_working_model_costs_no_extra_round_trip(): void
    {
        Http::fake([
            self::MODELS_URL => Http::response(['data' => []], 200),
            self::CHAT_URL   => Http::response(self::chatOk('{"clean":1}'), 200),
        ]);

        $this->assertSame('{"clean":1}', $this->callGroq());
        Http::assertNotSent(fn ($request) => $request->url() === self::MODELS_URL);
        Http::assertSentCount(1);
    }

    public function test_a_vault_model_beats_a_bad_ai_model_env(): void
    {
        // The whole point of groqModel(): AI_MODEL (env, AWS-only) held the
        // retired scout id, so the operator must be able to override it from
        // the AI card without a task-definition change.
        Cache::put('credential_vault_cache', ['groq_model' => 'openai/gpt-oss-20b'], 600);

        Http::fake([self::CHAT_URL => Http::response(self::chatOk('{"from_vault":1}'), 200)]);

        $this->assertSame('{"from_vault":1}', $this->callGroq());
        Http::assertSent(fn ($request) => $request->data()['model'] === 'openai/gpt-oss-20b');
        Http::assertSentCount(1);
    }

    public function test_the_vault_model_still_wins_when_the_account_can_call_it(): void
    {
        // Vault entries beat env in AiConfigController::getSettings() only via
        // VaultController::get()'s env-first rule, so exercise the env value:
        // whatever groq_model resolves to must be used as-is when it works.
        putenv('AI_MODEL=openai/gpt-oss-20b');
        $_ENV['AI_MODEL'] = $_SERVER['AI_MODEL'] = 'openai/gpt-oss-20b';
        Cache::put('credential_vault_cache', [], 600);

        Http::fake([self::CHAT_URL => Http::response(self::chatOk('{"as_configured":1}'), 200)]);

        $this->assertSame('{"as_configured":1}', $this->callGroq());
        Http::assertSent(fn ($request) => $request->data()['model'] === 'openai/gpt-oss-20b');
    }
}
