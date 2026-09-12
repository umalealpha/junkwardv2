<?php

namespace AlphaDirect\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;

class AiConfigController extends Controller
{
    /**
     * Redirect to the Credentials Vault filtered to the AI category.
     * The vault is now the single place to manage all API credentials.
     */
    public function index()
    {
        return redirect(route('admin.vault.index') . '?category=ai');
    }

    /**
     * Save AI settings via the Vault.
     * Vault must be unlocked; values are encrypted at rest.
     */
    public function update(Request $request)
    {
        $request->validate([
            'ai_provider'       => 'required|in:anthropic,groq,gemini',
            'ai_enabled'        => 'nullable',
            'groq_api_key'      => 'nullable|string',
            'groq_model'        => 'nullable|string',
            'anthropic_api_key' => 'nullable|string',
            'anthropic_model'   => 'nullable|string',
            'gemini_api_key'    => 'nullable|string',
            'gemini_model'      => 'nullable|string',
        ]);

        $credentials = [
            'ai_provider'     => $request->input('ai_provider'),
            'ai_enabled'      => $request->has('ai_enabled') ? '1' : '0',
            'groq_model'      => $request->input('groq_model', 'llama-3.3-70b-versatile'),
            'anthropic_model' => $request->input('anthropic_model', 'claude-opus-4-6'),
            'gemini_model'    => $request->input('gemini_model', 'gemini-2.5-flash'),
        ];

        $groqKey      = $request->input('groq_api_key', '');
        $anthropicKey = $request->input('anthropic_api_key', '');
        $geminiKey    = $request->input('gemini_api_key', '');

        if (!empty($groqKey))      $credentials['groq_api_key']      = $groqKey;
        if (!empty($anthropicKey)) $credentials['anthropic_api_key'] = $anthropicKey;
        if (!empty($geminiKey))    $credentials['gemini_api_key']    = $geminiKey;

        // Delegate persistence to VaultController (encrypts, busts cache)
        $vaultRequest = new Request(['credentials' => $credentials]);
        $vaultRequest->setMethod('POST');

        $result = app(VaultController::class)->save($vaultRequest);
        $json   = json_decode($result->getContent(), true);

        if ($json['success'] ?? false) {
            return redirect()->back()->with('success', 'AI configuration saved to vault.');
        }

        return redirect()->back()->withErrors(['error' => $json['message'] ?? 'Vault save failed. Ensure the vault is unlocked.']);
    }

    /**
     * Test AI provider connection using credentials from the Vault.
     */
    public function testConnection(Request $request)
    {
        $provider = $request->input('provider', 'groq');
        $settings = static::getSettings();

        try {
            if ($provider === 'groq') {
                $key = $settings['groq_api_key'] ?? '';
                if (empty($key)) throw new \Exception('Groq API key is not set in the vault');

                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'Authorization' => 'Bearer ' . $key,
                    'Content-Type'  => 'application/json',
                ])->timeout(15)->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model'      => $settings['groq_model'] ?? 'llama-3.3-70b-versatile',
                    'max_tokens' => 10,
                    'messages'   => [['role' => 'user', 'content' => 'Reply with OK only']],
                ]);

                if ($response->failed()) {
                    throw new \Exception('HTTP ' . $response->status() . ': ' . $response->json('error.message', $response->body()));
                }

                return response()->json(['success' => true, 'message' => 'Groq connection successful ✓', 'model' => $settings['groq_model']]);

            } elseif ($provider === 'gemini') {
                $key = $settings['gemini_api_key'] ?? '';
                if (empty($key)) throw new \Exception('Gemini API key is not set in the vault');

                $model = $settings['gemini_model'] ?? 'gemini-2.5-flash';
                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'x-goog-api-key' => $key,
                    'Content-Type'   => 'application/json',
                ])->timeout(15)->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                    'contents'         => [['role' => 'user', 'parts' => [['text' => 'Reply with OK only']]]],
                    'generationConfig' => ['maxOutputTokens' => 10],
                ]);

                if ($response->failed()) {
                    throw new \Exception('HTTP ' . $response->status() . ': ' . $response->json('error.message', $response->body()));
                }

                return response()->json(['success' => true, 'message' => 'Gemini connection successful ✓', 'model' => $model]);

            } else {
                $key = $settings['anthropic_api_key'] ?? '';
                if (empty($key)) throw new \Exception('Anthropic API key is not set in the vault');

                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'x-api-key'         => $key,
                    'anthropic-version' => '2023-06-01',
                    'Content-Type'      => 'application/json',
                ])->timeout(15)->post('https://api.anthropic.com/v1/messages', [
                    'model'      => $settings['anthropic_model'] ?? 'claude-opus-4-6',
                    'max_tokens' => 10,
                    'messages'   => [['role' => 'user', 'content' => 'Reply with OK only']],
                ]);

                if ($response->failed()) {
                    throw new \Exception('HTTP ' . $response->status() . ': ' . $response->json('error.message', $response->body()));
                }

                return response()->json(['success' => true, 'message' => 'Anthropic connection successful ✓', 'model' => $settings['anthropic_model']]);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // ── Static helper used by AiAssistantService ──────────────────────────────

    public static function getSettings(): array
    {
        return [
            'ai_provider'       => VaultController::get('ai_provider',       env('AI_PROVIDER', 'groq')),
            'ai_enabled'        => VaultController::get('ai_enabled',        '1'),
            'groq_api_key'      => VaultController::get('groq_api_key',      env('GROQ_API_KEY', '')),
            'groq_model'        => VaultController::get('groq_model',        env('AI_MODEL', 'llama-3.3-70b-versatile')),
            'anthropic_api_key' => VaultController::get('anthropic_api_key', env('ANTHROPIC_API_KEY', '')),
            'anthropic_model'   => VaultController::get('anthropic_model',   env('AI_MODEL', 'claude-opus-4-6')),
            'gemini_api_key'    => VaultController::get('gemini_api_key',    env('GEMINI_API_KEY', '')),
            'gemini_model'      => VaultController::get('gemini_model',      env('GEMINI_MODEL', 'gemini-2.5-flash')),
        ];
    }
}
