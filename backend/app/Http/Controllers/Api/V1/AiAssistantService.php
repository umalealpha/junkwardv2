<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use AlphaDirect\Http\Controllers\Admin\AiConfigController;
use AlphaDirect\Helpers\AiEgressPiiScrubber;

class AiAssistantService
{
    private string $provider;
    private string $apiKey;
    private string $model;
    private int $maxTokens     = 4096;
    private int $maxToolRounds = 5;

    public function __construct()
    {
        // Load from DB config (with .env fallback via AiConfigController::getSettings)
        $cfg = AiConfigController::getSettings();

        $this->provider = strtolower($cfg['ai_provider'] ?? 'groq');

        // The assistant's agentic tool-loop supports Groq (OpenAI-style) and
        // Anthropic only — NOT Gemini's function-calling format yet. When the
        // global provider is 'gemini' (set for the OCR document reader), this
        // class would otherwise fall into the Anthropic branch with no key and
        // throw on every query. Route the assistant to Groq instead so normal
        // staff queries keep working. (Routing complex queries to Gemini is a
        // separate enhancement that needs a Gemini agentic adapter.)
        if ($this->provider === 'gemini') {
            $this->provider = 'groq';
        }

        if ($this->provider === 'groq') {
            $this->apiKey = $cfg['groq_api_key'] ?? '';
            $this->model  = $cfg['groq_model']   ?? 'meta-llama/llama-4-scout-17b-16e-instruct';
        } else {
            $this->apiKey = $cfg['anthropic_api_key'] ?? '';
            $this->model  = $cfg['anthropic_model']   ?? 'claude-opus-4-6';
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Public: main entry point
    // ─────────────────────────────────────────────────────────────────────────

    public function chat(int $userId, string $userMessage, ?int $conversationId): array
    {
        $write = DB::connection('mysql_write');

        // Create or resume conversation
        if (!$conversationId) {
            $conversationId = $write->table('ai_conversations')->insertGetId([
                'user_id'    => $userId,
                'title'      => $this->generateTitle($userMessage),
                'status'     => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $conv = DB::table('ai_conversations')->find($conversationId);
            if ($conv && $conv->title === 'New Conversation') {
                $write->table('ai_conversations')->where('id', $conversationId)
                    ->update(['title' => $this->generateTitle($userMessage), 'updated_at' => now()]);
            }
        }

        // Persist user message
        $write->table('ai_messages')->insert([
            'conversation_id' => $conversationId,
            'role'            => 'user',
            'content'         => $userMessage,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        // Build history (last 20 messages) — read from replica is fine
        $history = DB::table('ai_messages')
            ->where('conversation_id', $conversationId)
            ->orderByDesc('id')->limit(20)->get()
            ->reverse()->values();

        // Convert to provider-appropriate message format
        $messages = $this->provider === 'groq'
            ? $this->buildGroqMessages($history->toArray())
            : $this->buildAnthropicMessages($history->toArray());

        // Agentic tool loop
        $resultData    = null;
        $toolsService  = new AiToolsService();
        $rounds        = 0;
        $totalTokens   = 0;

        do {
            $rounds++;
            $raw = $this->provider === 'groq'
                ? $this->callGroq($messages, $rounds)
                : $this->callAnthropic($messages);

            // Normalise response into provider-agnostic structure
            $normalised  = $this->normaliseResponse($raw);
            $totalTokens += $normalised['tokens'];
            $textParts   = $normalised['text_parts'];
            $toolCalls   = $normalised['tool_calls'];
            $hasTools    = !empty($toolCalls);

            if ($hasTools) {
                // Append assistant turn to message history
                $messages[] = $this->provider === 'groq'
                    ? $this->groqAssistantTurn($raw)
                    : ['role' => 'assistant', 'content' => $raw['content']];

                // Execute tools and collect results
                $toolResults = [];
                foreach ($toolCalls as $call) {
                    try {
                        $result = $toolsService->execute($call['name'], $call['input']);
                        if ($resultData === null && isset($result['rows'])) {
                            $resultData = $result;
                        }
                    } catch (\Exception $e) {
                        $result = ['error' => $e->getMessage()];
                    }

                    // C3 (pentest): scrub PII before the result leaves to the LLM.
                    // $resultData (the raw in-app UI copy) was captured above and is
                    // a separate array, so it stays unmasked for the operator.
                    $result = AiEgressPiiScrubber::scrub($result);

                    if ($this->provider === 'groq') {
                        $toolResults[] = [
                            'role'         => 'tool',
                            'tool_call_id' => $call['id'],
                            'content'      => json_encode($result),
                        ];
                    } else {
                        $toolResults[] = [
                            'type'        => 'tool_result',
                            'tool_use_id' => $call['id'],
                            'content'     => json_encode($result),
                        ];
                    }
                }

                // Append tool results to history
                if ($this->provider === 'groq') {
                    foreach ($toolResults as $tr) {
                        $messages[] = $tr;
                    }
                } else {
                    $messages[] = ['role' => 'user', 'content' => $toolResults];
                }

            } else {
                // Final text response
                $assistantText = implode("\n\n", $textParts) ?: 'I was unable to generate a response.';

                $write->table('ai_messages')->insert([
                    'conversation_id' => $conversationId,
                    'role'            => 'assistant',
                    'content'         => $assistantText,
                    'result_data'     => $resultData ? json_encode($resultData) : null,
                    'tokens_used'     => $totalTokens,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);

                $write->table('ai_conversations')->where('id', $conversationId)
                    ->update(['updated_at' => now()]);

                return [
                    'conversation_id' => $conversationId,
                    'message'         => $assistantText,
                    'result_data'     => $resultData,
                    'tokens_used'     => $totalTokens,
                    'provider'        => $this->provider,
                    'model'           => $this->model,
                ];
            }
        } while ($rounds < $this->maxToolRounds);

        throw new \Exception('Max tool rounds reached without a final response.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Public: WhatsApp bot entry — custom tools + custom system prompt
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Chat with custom tools and tool executor (used by WhatsApp AI bot).
     * Doesn't persist to ai_messages — caller handles that.
     *
     * @param array    $messages     Pre-built message history [{role, content}, ...]
     * @param array    $tools        Tool definitions (Anthropic format)
     * @param callable $toolExecutor fn(string $toolName, array $input): array
     * @return array   ['content' => string, 'tokens_used' => int]
     */
    public function chatWithCustomTools(array $messages, array $tools, callable $toolExecutor): array
    {
        // Convert messages to provider format
        $providerMessages = $this->provider === 'groq'
            ? $this->convertToGroqFormat($messages)
            : $this->convertToAnthropicFormat($messages);

        $rounds     = 0;
        $totalTokens = 0;

        do {
            $rounds++;

            if ($this->provider === 'groq') {
                $raw = $this->callGroqCustom($providerMessages, $tools, $rounds);
            } else {
                $raw = $this->callAnthropicCustom($providerMessages, $tools);
            }

            $normalised  = $this->normaliseResponse($raw);
            $totalTokens += $normalised['tokens'];
            $toolCalls   = $normalised['tool_calls'];

            if (!empty($toolCalls)) {
                $providerMessages[] = $this->provider === 'groq'
                    ? $this->groqAssistantTurn($raw)
                    : ['role' => 'assistant', 'content' => $raw['content']];

                foreach ($toolCalls as $call) {
                    try { $result = $toolExecutor($call['name'], $call['input']); }
                    catch (\Exception $e) { $result = ['error' => $e->getMessage()]; }
                    $result = AiEgressPiiScrubber::scrub($result); // C3: scrub PII before LLM egress

                    if ($this->provider === 'groq') {
                        $providerMessages[] = ['role' => 'tool', 'tool_call_id' => $call['id'], 'content' => json_encode($result)];
                    } else {
                        $providerMessages[] = ['role' => 'user', 'content' => [['type' => 'tool_result', 'tool_use_id' => $call['id'], 'content' => json_encode($result)]]];
                    }
                }
            } else {
                return [
                    'content'     => implode("\n\n", $normalised['text_parts']) ?: 'I could not process your request.',
                    'tokens_used' => $totalTokens,
                ];
            }
        } while ($rounds < $this->maxToolRounds);

        return ['content' => 'Processing took too long. Please simplify your question.', 'tokens_used' => $totalTokens];
    }

    private function callGroqCustom(array $messages, array $tools, int $round): array
    {
        // Llama 4 Scout uses a discriminated union for message roles — 'system' is not a valid value.
        // Extract any system message and prepend its content to the first user message instead.
        $systemContent    = null;
        $filteredMessages = [];
        foreach ($messages as $m) {
            if (($m['role'] ?? '') === 'system') {
                $systemContent = $m['content'];
            } else {
                $filteredMessages[] = $m;
            }
        }

        if ($systemContent && !empty($filteredMessages)) {
            foreach ($filteredMessages as $i => $msg) {
                if (($msg['role'] ?? '') === 'user') {
                    $filteredMessages[$i]['content'] =
                        "<system_context>\n{$systemContent}\n</system_context>\n\n"
                        . $msg['content'];
                    break;
                }
            }
        }

        $groqTools = array_map(fn($t) => [
            'type' => 'function',
            'function' => ['name' => $t['name'], 'description' => $t['description'], 'parameters' => $t['input_schema']],
        ], $tools);

        $body = [
            'model'       => $this->model,
            'messages'    => $filteredMessages,
            'tools'       => $groqTools,
            'max_tokens'  => $this->maxTokens,
            'temperature' => 0.3,
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type'  => 'application/json',
        ])->timeout(60)->post('https://api.groq.com/openai/v1/chat/completions', $body);

        if ($response->failed()) {
            $err = $response->json();
            // Same tool_use_failed graceful-fallback as callGroq above — keep
            // both call sites consistent so conversational replies don't 500.
            if (($err['error']['code'] ?? null) === 'tool_use_failed'
                && !empty($err['error']['failed_generation'])) {
                // Do NOT log failed_generation content — it is model output that can
                // echo customer PII. Log the event only.
                Log::info('Groq tool_use_failed (custom) -> returning failed_generation as text');
                return [
                    'choices' => [[
                        'finish_reason' => 'stop',
                        'message' => [
                            'role'    => 'assistant',
                            'content' => $err['error']['failed_generation'],
                        ],
                    ]],
                ];
            }
            // M6 fix: log the raw upstream body server-side only; throw generic.
            Log::error('Groq API error (custom)', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \Exception('AI provider request failed.');
        }
        return $response->json();
    }

    private function callAnthropicCustom(array $messages, array $tools): array
    {
        $systemMsg = '';
        $filteredMessages = [];
        foreach ($messages as $m) {
            if ($m['role'] === 'system') { $systemMsg = $m['content']; continue; }
            $filteredMessages[] = $m;
        }

        $response = Http::withHeaders([
            'x-api-key'         => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])->timeout(90)->post('https://api.anthropic.com/v1/messages', [
            'model'      => $this->model,
            'max_tokens' => $this->maxTokens,
            'system'     => $systemMsg,
            'tools'      => $tools,
            'messages'   => $filteredMessages,
        ]);

        if ($response->failed()) {
            // M6 fix: log upstream detail server-side; surface a generic message.
            Log::error('Anthropic API error (custom)', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \Exception('AI provider request failed.');
        }
        return $response->json();
    }

    private function convertToGroqFormat(array $messages): array
    {
        return array_map(fn($m) => ['role' => $m['role'], 'content' => $m['content']], $messages);
    }

    private function convertToAnthropicFormat(array $messages): array
    {
        $out = [];
        foreach ($messages as $m) {
            if ($m['role'] === 'system') continue; // system handled separately
            $out[] = ['role' => $m['role'], 'content' => $m['content']];
        }
        return $out;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Anthropic call
    // ─────────────────────────────────────────────────────────────────────────

    private function callAnthropic(array $messages): array
    {
        if (empty($this->apiKey)) {
            throw new \Exception('ANTHROPIC_API_KEY is not set in .env');
        }

        $response = Http::withHeaders([
            'x-api-key'         => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])->timeout(90)->post('https://api.anthropic.com/v1/messages', [
            'model'      => $this->model,
            'max_tokens' => $this->maxTokens,
            'system'     => $this->buildSystemPrompt(),
            'tools'      => $this->getAnthropicTools(),
            'messages'   => $messages,
        ]);

        if ($response->failed()) {
            // M6 fix: log upstream detail server-side; surface a generic message.
            Log::error('Anthropic API error', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \Exception('AI provider request failed.');
        }

        return $response->json();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Groq call (OpenAI-compatible)
    // ─────────────────────────────────────────────────────────────────────────

    private function callGroq(array $messages, int $round = 1): array
    {
        if (empty($this->apiKey)) {
            throw new \Exception('GROQ_API_KEY is not set in .env');
        }

        $today = now()->format('Y-m-d');
        $systemMsg = "You are an AI assistant for Alpha Direct Insurance (Botswana). Today: {$today}. Currency: BWP (P). "
            . "For legitimate business data questions, use the available tools to fetch real data and pick the best tool for the request. "
            . "You may decline requests that fall outside your safe, intended business scope — for example unsafe, out-of-scope, or data-exfiltration requests. "
            . "Policy status: 1=Active, 2=Cancelled, 0=Pending, 3=Expired. "
            . "Claim statuses: Pending, Approved, Rejected, Closed, Reopen. For 'open claims' use status='open'. "
            . "Products: Motor Comprehensive, Domestic, Commercial, Third Party Car, Legal, Mobile Device, Hospital Cashback, Health. "
            . "For projections/forecasts: use get_payment_summary with group_by=month to get historical data, then calculate projection. "
            . "For sales analysis: use get_payment_summary grouped by product. For claims: use get_claims. For policy stats: use get_policy_stats. "
            . "For complex queries: use execute_query with a SELECT SQL statement. "
            . "After results, summarise clearly. Format money as P X,XXX.";

        $fullMessages = array_merge(
            [['role' => 'system', 'content' => $systemMsg]],
            $messages
        );

        // L4 fix: use 'auto' so the model may choose not to call a tool — e.g.
        // to decline an unsafe / out-of-scope request. Previously the first
        // round forced 'required', which prevented the model from refusing.
        $toolChoice = 'auto';

        // C3 fix: TLS certificate verification must stay ON for cross-border
        // egress to Groq. (Was Http::withOptions(['verify' => false]), which
        // disabled cert validation and exposed the transfer to MITM.)
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type'  => 'application/json',
        ])->timeout(90)->post('https://api.groq.com/openai/v1/chat/completions', [
            'model'       => $this->model,
            'max_tokens'  => $this->maxTokens,
            'tools'       => $this->getOpenAiTools(),
            'tool_choice' => $toolChoice,
            'messages'    => $fullMessages,
        ]);

        if ($response->failed()) {
            $err = $response->json();
            // Groq's strict tool_choice=required mode raises tool_use_failed when
            // the model produces a plain-text reply (typical for greetings / small
            // talk where no data tool is needed). The model's actual response is
            // stuffed into error.failed_generation — surface it as a normal
            // assistant message instead of throwing a 500 to the user.
            if (($err['error']['code'] ?? null) === 'tool_use_failed'
                && !empty($err['error']['failed_generation'])) {
                // Do NOT log failed_generation content — it is model output that can
                // echo customer PII. Log the event only.
                Log::info('Groq tool_use_failed -> returning failed_generation as text');
                return [
                    'choices' => [[
                        'finish_reason' => 'stop',
                        'message' => [
                            'role'    => 'assistant',
                            'content' => $err['error']['failed_generation'],
                        ],
                    ]],
                ];
            }
            // M6 fix: keep the raw upstream detail in the server log only; the
            // thrown message must not carry provider name / model id / body to
            // the caller.
            Log::error('Groq API error', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \Exception('AI provider request failed.');
        }

        $json = $response->json();
        // Log response metadata only — never the model content (can echo customer PII).
        Log::info('Groq raw response', ['finish' => $json['choices'][0]['finish_reason'] ?? '?', 'has_tool_calls' => !empty($json['choices'][0]['message']['tool_calls'])]);
        return $json;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Normalise provider responses into a common structure
    // ─────────────────────────────────────────────────────────────────────────

    private function normaliseResponse(array $raw): array
    {
        if ($this->provider === 'groq') {
            $choice  = $raw['choices'][0] ?? [];
            $message = $choice['message'] ?? [];
            $finish  = $choice['finish_reason'] ?? 'stop';

            $textParts = [];
            $toolCalls = [];

            if (!empty($message['content'])) {
                $textParts[] = $message['content'];
            }

            if (!empty($message['tool_calls'])) {
                foreach ($message['tool_calls'] as $tc) {
                    $toolCalls[] = [
                        'id'    => $tc['id'],
                        'name'  => $tc['function']['name'],
                        'input' => json_decode($tc['function']['arguments'], true) ?? [],
                    ];
                }
            }

            return [
                'text_parts' => $textParts,
                'tool_calls' => $toolCalls,
                'tokens'     => ($raw['usage']['total_tokens'] ?? 0),
            ];

        } else {
            // Anthropic
            $content   = $raw['content'] ?? [];
            $stopReason = $raw['stop_reason'] ?? 'end_turn';
            $textParts = [];
            $toolCalls = [];

            foreach ($content as $block) {
                if ($block['type'] === 'text') {
                    $textParts[] = $block['text'];
                } elseif ($block['type'] === 'tool_use') {
                    $toolCalls[] = [
                        'id'    => $block['id'],
                        'name'  => $block['name'],
                        'input' => $block['input'],
                    ];
                }
            }

            return [
                'text_parts' => $textParts,
                'tool_calls' => ($stopReason === 'tool_use') ? $toolCalls : [],
                'tokens'     => ($raw['usage']['input_tokens'] ?? 0) + ($raw['usage']['output_tokens'] ?? 0),
            ];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Message format builders
    // ─────────────────────────────────────────────────────────────────────────

    private function buildAnthropicMessages(array $history): array
    {
        return array_map(fn($m) => [
            'role'    => $m->role,
            // C3: pattern-scrub PII (email/phone/Omang) from user free-text before egress.
            'content' => $m->role === 'user'
                ? AiEgressPiiScrubber::scrubText((string) $m->content)
                : $m->content,
        ], $history);
    }

    private function buildGroqMessages(array $history): array
    {
        // Groq uses flat role+content format (no nested blocks)
        return array_map(fn($m) => [
            'role'    => $m->role,
            // C3: pattern-scrub PII (email/phone/Omang) from user free-text before egress.
            'content' => $m->role === 'user'
                ? AiEgressPiiScrubber::scrubText((string) $m->content)
                : $m->content,
        ], $history);
    }

    private function groqAssistantTurn(array $raw): array
    {
        $choice  = $raw['choices'][0] ?? [];
        $message = $choice['message'] ?? [];
        // Return the raw message object so tool_calls IDs are preserved
        return $message;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Tool definitions — two formats
    // ─────────────────────────────────────────────────────────────────────────

    private function getAnthropicTools(): array
    {
        return $this->toolSchemas('anthropic');
    }

    private function getOpenAiTools(): array
    {
        return array_map(fn($t) => [
            'type'     => 'function',
            'function' => [
                'name'        => $t['name'],
                'description' => $t['description'],
                'parameters'  => $t['input_schema'],
            ],
        ], $this->toolSchemas('anthropic')); // same schema shape, just wrapped
    }

    private function toolSchemas(string $format): array
    {
        return [
            [
                'name'         => 'search_policies',
                'description'  => 'Search and filter policies by status, product, date range, customer name, or policy number.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'status'        => ['type' => 'string', 'description' => 'active, cancelled, pending, expired, draft'],
                        'product'       => ['type' => 'string', 'description' => 'Product keyword: motor, domestic, commercial, legal, mobile, tyre, hospital, health, cashback, third party, engineering, specialist'],
                        'customer_name' => ['type' => 'string', 'description' => 'Customer full or partial name'],
                        'policy_number' => ['type' => 'string', 'description' => 'Policy number e.g. MIS2021020969'],
                        'date_from'     => ['type' => 'string', 'description' => 'Start date YYYY-MM-DD'],
                        'date_to'       => ['type' => 'string', 'description' => 'End date YYYY-MM-DD'],
                        'limit'         => ['type' => 'integer', 'description' => 'Max results, default 50'],
                    ],
                ],
            ],
            [
                'name'         => 'get_payment_summary',
                'description'  => 'Get payment collection totals and stats. Use for "how much collected in March", "show failed payments", "collection rate".',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'date_from'      => ['type' => 'string', 'description' => 'Start date YYYY-MM-DD'],
                        'date_to'        => ['type' => 'string', 'description' => 'End date YYYY-MM-DD'],
                        'product'        => ['type' => 'string', 'description' => 'Filter by product (optional)'],
                        'payment_method' => ['type' => 'string', 'description' => 'realpay, dpo, orange_money, vcs'],
                        'group_by'       => ['type' => 'string', 'description' => 'day, week, month, product, payment_method'],
                    ],
                ],
            ],
            [
                'name'         => 'get_customer_info',
                'description'  => 'Look up a customer and all their policies, payments, and claims.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'search' => ['type' => 'string', 'description' => 'Customer name, ID number, phone, or email'],
                    ],
                    'required'   => ['search'],
                ],
            ],
            [
                'name'         => 'get_claims',
                'description'  => 'Search insurance claims by status, date range, or policy number.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'status'        => ['type' => 'string', 'description' => 'open (=Pending+Reopen), pending, approved, rejected, closed, reopen'],
                        'date_from'     => ['type' => 'string', 'description' => 'Start date YYYY-MM-DD'],
                        'date_to'       => ['type' => 'string', 'description' => 'End date YYYY-MM-DD'],
                        'policy_number' => ['type' => 'string', 'description' => 'Filter by policy number'],
                        'limit'         => ['type' => 'integer', 'description' => 'Max results, default 50'],
                    ],
                ],
            ],
            [
                'name'         => 'get_anomalies',
                'description'  => 'Get payment reconciliation anomalies: cancelled policies still collecting, premium mismatches, unpaid invoices, payment gaps.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'type'     => ['type' => 'string', 'description' => 'cancelled_but_collecting, premium_mismatch, partial_payment, unpaid_invoice, balance_accumulating, payment_gap'],
                        'severity' => ['type' => 'string', 'description' => 'critical, high, medium, low'],
                        'limit'    => ['type' => 'integer', 'description' => 'Max results, default 50'],
                    ],
                ],
            ],
            [
                'name'         => 'get_policy_stats',
                'description'  => 'Get aggregate policy statistics: total active by product, new vs cancelled, premium totals.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'period' => ['type' => 'string', 'description' => 'today, this_week, this_month, last_month, this_year, or YYYY-MM'],
                    ],
                ],
            ],
            // C2 (pentest) — the free-form `execute_query` tool let the model run
            // model-chosen SQL against any table (incl. Omang / bank / health),
            // gated only by a leaky blocklist. Removed from the toolset so the
            // model can no longer call it (the executeQuery() handler is also
            // hard-disabled server-side as defence in depth). If AI data Q&A is
            // wanted, reintroduce it as a FIXED set of parameterised,
            // column-allow-listed queries — never free-form SQL.
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // System prompt
    // ─────────────────────────────────────────────────────────────────────────

    private function buildSystemPrompt(): string
    {
        $today        = now()->format('Y-m-d');
        $currentMonth = now()->format('F Y');

        return <<<PROMPT
You are an intelligent AI assistant for Alpha Direct Insurance, a leading insurance provider in Botswana. Help staff query data, analyse trends, and surface insights about policies, payments, claims, and customers.

## Company Context
- Company: Alpha Direct Insurance (Botswana)
- Currency: Botswana Pula (BWP, symbol P)
- Today: {$today} | Current month: {$currentMonth}

## Products (actual DB names and IDs)
1=Accidental Death, 2=Third Party Car, 3=Motor Comprehensive, 4=Legal, 5=Mobile Device, 6=Tyre and Rim, 7=Commercial, 8=Domestic, 9=Hospital Cashback, 10=Health In A Box, 12=Grouped AD, 16=Commercial Engineering, 17=Commercial Specialist, 18=Domestic Engineering, 19=Domestic Specialist

## Policy Statuses
status=1 Active | status=2 Cancelled | status=0 Pending | status=3 Expired | is_draft=1 Draft

## Claim Statuses (enum)
Pending, Approved, Rejected, Closed, Reopen. "Open claims" = Pending + Reopen (use status='open' in get_claims tool)

## Payment Methods
RealPay (debit orders, primary), DPO (card), Orange Money (mobile), VCS (card), N-Genius, Flutterwave

## Key Rules
- premium = monthly amount; billingStartDate = when debit order starts
- RealPay contract status=1 means actively collecting
- Payment status SUCCESS or Paid = successful; is_reverse=1 = refund
- Always use tools to get actual counts — do not use hardcoded numbers

## Behaviour
1. Always use tools to fetch real data — never make up numbers
2. After fetching, add analysis: highlight anomalies, trends, suggest actions
3. Format money as "P X,XXX". Use commas for large numbers
4. If no results, explain why and suggest alternatives
5. For complex reports, use execute_query with a SELECT statement
PROMPT;
    }

    private function generateTitle(string $message): string
    {
        return mb_strlen($message) > 60
            ? mb_substr($message, 0, 57) . '...'
            : $message;
    }
}
