<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Models\ReleaseNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * "What's New" release notes.
 *   GET  release-notes          full changelog (published, newest first)
 *   GET  release-notes/unseen   notes newer than the user's last-seen marker (+count)
 *   POST release-notes/seen     mark all current notes as seen for this user
 *   POST release-notes          create a note (admin) — one per UI-changing deploy
 */
class ReleaseNoteController extends Controller
{
    public function index(): JsonResponse
    {
        $notes = ReleaseNote::published()->orderByDesc('id')->limit(50)->get();
        return response()->json(['data' => $notes->map(fn ($n) => $this->row($n))]);
    }

    public function unseen(): JsonResponse
    {
        $lastSeen = (int) (optional(Auth::user())->last_seen_release_note_id ?? 0);
        $notes = ReleaseNote::published()->where('id', '>', $lastSeen)->orderByDesc('id')->get();
        return response()->json([
            'count' => $notes->count(),
            'data'  => $notes->map(fn ($n) => $this->row($n)),
        ]);
    }

    public function markSeen(): JsonResponse
    {
        $user  = Auth::user();
        $maxId = (int) ReleaseNote::published()->max('id');
        if ($user) {
            DB::table('users')->where('id', $user->id)->update(['last_seen_release_note_id' => $maxId]);
        }
        return response()->json(['ok' => true, 'last_seen_release_note_id' => $maxId]);
    }

    public function store(Request $request): JsonResponse
    {
        $v = $request->validate([
            'title'            => 'required|string|max:150',
            'version'          => 'nullable|string|max:40',
            'highlights'       => 'nullable|array',
            'highlights.*.tag' => 'nullable|string|in:feature,fix,improvement',
            'highlights.*.text'=> 'required_with:highlights|string|max:500',
            'is_published'     => 'nullable|boolean',
        ]);

        $note = ReleaseNote::create([
            'title'        => $v['title'],
            'version'      => $v['version'] ?? null,
            'highlights'   => $v['highlights'] ?? [],
            'is_published' => $v['is_published'] ?? true,
            'published_at' => now(),
        ]);

        return response()->json(['data' => $this->row($note)], 201);
    }

    private function row(ReleaseNote $n): array
    {
        return [
            'id'          => $n->id,
            'version'     => $n->version,
            'title'       => $n->title,
            'highlights'  => $n->highlights ?? [],
            'publishedAt' => optional($n->published_at)->toIso8601String(),
        ];
    }
}
