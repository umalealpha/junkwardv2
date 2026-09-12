<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class AiAssistantController extends Controller
{
    private AiAssistantService $service;

    public function __construct(AiAssistantService $service)
    {
        $this->service = $service;
    }

    /**
     * Authenticated user id, or fail closed with 401 (L1 fix). Never default to
     * a hardcoded id — the previous `Auth::id() ?? 1` attributed unauthenticated
     * actions to (and acted as) user 1.
     */
    private function currentUserId(): int
    {
        $userId = Auth::id();
        abort_if(! $userId, 401, 'Unauthenticated.');
        return (int) $userId;
    }

    // POST /ai/query
    public function query(Request $request): JsonResponse
    {
        set_time_limit(180); // AI calls can take 30-60s

        $request->validate([
            'message'         => 'required|string|max:2000',
            'conversation_id' => 'nullable|integer',
        ]);

        $userId = $this->currentUserId();
        $message = $request->input('message');
        $conversationId = $request->input('conversation_id');

        try {
            $result = $this->service->chat($userId, $message, $conversationId);
            return response()->json($result);
        } catch (\Exception $e) {
            // M6 fix: log the real upstream detail server-side, but never leak
            // provider name / model id / config to the API client. Return a
            // generic message with the 500 status.
            \Log::error('AI query failed', ['error' => $e->getMessage()]);
            return response()->json(
                ['error' => 'The AI assistant is temporarily unavailable. Please try again.'],
                500
            );
        }
    }

    // GET /ai/conversations
    public function conversations(Request $request): JsonResponse
    {
        $userId = $this->currentUserId();
        $conversations = DB::table('ai_conversations')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get(['id', 'title', 'updated_at']);

        return response()->json(['data' => $conversations]);
    }

    // GET /ai/conversations/{id}
    public function getConversation(int $id): JsonResponse
    {
        $userId = $this->currentUserId();

        // Ownership check (C1 IDOR fix): the conversation must belong to the
        // caller. 404 (not 403) so the endpoint does not confirm the existence
        // of other users' conversation ids.
        $owns = DB::table('ai_conversations')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->exists();
        if (!$owns) {
            return response()->json(['error' => 'Not found.'], 404);
        }

        $messages = DB::table('ai_messages')
            ->where('conversation_id', $id)
            ->orderBy('id')
            ->get(['id', 'role', 'content', 'result_data', 'created_at']);

        return response()->json(['data' => $messages]);
    }

    // DELETE /ai/conversations/{id}
    public function deleteConversation(int $id): JsonResponse
    {
        $userId = $this->currentUserId();

        // Scope the archive to the caller's own conversation (C1 IDOR fix) —
        // update matches on user_id so a caller cannot archive another user's row.
        $affected = DB::connection('mysql_write')->table('ai_conversations')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->update(['status' => 'archived']);

        if ($affected === 0) {
            return response()->json(['error' => 'Not found.'], 404);
        }
        return response()->json(['success' => true]);
    }

    // POST /ai/conversations (new blank conversation)
    public function newConversation(Request $request): JsonResponse
    {
        $userId = $this->currentUserId();
        $id = DB::connection('mysql_write')->table('ai_conversations')->insertGetId([
            'user_id'    => $userId,
            'title'      => 'New Conversation',
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json(['id' => $id]);
    }
}
