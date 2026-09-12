<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Mail\HelpDeskTicketRaised;
use AlphaDirect\Models\HelpDeskAuditLog;
use AlphaDirect\Models\HelpDeskComment;
use AlphaDirect\Models\HelpDeskTicket;
use AlphaDirect\Services\HelpDeskNotifier;
use AlphaDirect\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Help Desk — issue intake + triage.
 *
 *   GET    help-desk-tickets                          list (filters: status, assignee_id, reporter_id=me, search)
 *   POST   help-desk-tickets                          create (multipart: description, screenshot_1..3, attachment, assignee_id?)
 *   GET    help-desk-tickets/{id}                     detail
 *   POST   help-desk-tickets/{id}/assign              assign to a user
 *   POST   help-desk-tickets/{id}/status              change status
 *   POST   help-desk-tickets/{id}/reopen              reopen a closed ticket (reporter-window / admin)
 *   POST   help-desk-tickets/{id}/clone               raise a new related ticket
 *   GET    help-desk-tickets/{id}/attachments/{slot}  stream an attachment
 *
 * The reporter is taken from the authenticated session — never trusted from
 * the request body — so "who raised it" is always accurate.
 */
class HelpDeskController extends Controller
{
    /**
     * How long after closure the original reporter may self-reopen a ticket.
     * Agents/admins (see canReopen) are not bound by this window. After it
     * lapses a reporter should raise a related ticket (clone) instead.
     */
    private const REOPEN_WINDOW_DAYS = 14;

    /** Roles that may reopen any closed ticket, any time. Mirrors AuthGate. */
    private const REOPEN_ROLES = ['Super Admin', 'Manager', 'Admin'];

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status'        => 'nullable|string|in:' . implode(',', HelpDeskTicket::STATUSES),
            'priority'      => 'nullable|string|in:' . implode(',', HelpDeskTicket::PRIORITIES),
            'assignee_id'   => 'nullable|integer',
            'assignee_name' => 'nullable|string|max:150',
            'reporter_name' => 'nullable|string|max:150',
            'mine'          => 'nullable|boolean',
            'search'        => 'nullable|string|max:100',
            'per_page'      => 'nullable|integer|min:5|max:100',
        ]);

        $query = $this->buildFilteredQuery($validated);
        $results = $query->paginate($validated['per_page'] ?? 10);

        return response()->json([
            'data' => collect($results->items())->map(fn ($t) => $this->summaryRow($t)),
            'meta' => [
                'total'        => $results->total(),
                'per_page'     => $results->perPage(),
                'current_page' => $results->currentPage(),
                'last_page'    => $results->lastPage(),
                'from'         => $results->firstItem(),
                'to'           => $results->lastItem(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Authentication required.'], 401);
        }

        $validated = $request->validate([
            'title'        => 'required|string|max:150',
            'description'  => 'required|string|min:100|max:5000',
            'priority'     => 'nullable|string|in:' . implode(',', HelpDeskTicket::PRIORITIES),
            'type'         => 'nullable|string|in:' . implode(',', HelpDeskTicket::TYPES),
            'assignee_email' => 'nullable|email|max:150',
            // Two screenshots are mandatory; the third is optional. The
            // generic attachment can be any file type.
            'screenshot_1' => 'required|image|max:5120',  // 5MB
            'screenshot_2' => 'required|image|max:5120',
            'screenshot_3' => 'nullable|image|max:5120',
            'attachment'   => 'nullable|file|max:10240',   // 10MB
        ], [
            'description.min'      => 'Please describe the issue in at least 100 characters.',
            'screenshot_1.required'=> 'Please attach the first screenshot.',
            'screenshot_2.required'=> 'Please attach the second screenshot.',
            'screenshot_1.image'   => 'Screenshot one must be an image.',
            'screenshot_2.image'   => 'Screenshot two must be an image.',
        ]);

        $ref = HelpDeskTicket::generateRef();

        $attachments = [];
        foreach (['screenshot_1', 'screenshot_2', 'screenshot_3', 'attachment'] as $slot) {
            if ($request->hasFile($slot)) {
                $attachments[] = $this->storeFile($request->file($slot), $ref, $slot);
            }
        }

        $assignee = $this->resolveAssignee($validated['assignee_email'] ?? null);

        $ticket = HelpDeskTicket::create([
            'ticket_ref'     => $ref,
            'title'          => $validated['title'],
            'description'    => $validated['description'],
            'priority'       => $validated['priority'] ?? 'medium',
            'type'           => $validated['type'] ?? 'bug',
            'source'         => 'web',
            'reporter_id'    => $user->id,
            'reporter_name'  => $this->displayName($user),
            'reporter_email' => $user->email,
            'assignee_id'    => $assignee['id'],
            'assignee_name'  => $assignee['name'],
            'assignee_email' => $assignee['email'],
            'status'         => 'new',
            'attachments'    => $attachments,
        ]);

        HelpDeskAuditLog::record(
            $ticket->id, 'created', 'Ticket created',
            $user->id, $this->displayName($user),
            ['priority' => $ticket->priority, 'type' => $ticket->type, 'assignee' => $ticket->assignee_name],
        );

        // Notify the dev team that a new issue was raised. Best-effort: a mail
        // failure (SMTP down, bad config) must never fail ticket submission.
        try {
            $recipient = env('HELP_DESK_NOTIFY_EMAIL', 'developers@theriskco.com');
            Mail::to($recipient)->send(new HelpDeskTicketRaised($ticket));
        } catch (\Throwable $e) {
            Log::error('help_desk.notify_failed', ['ticket' => $ref, 'msg' => $e->getMessage()]);
        }

        // Reporter (always) + assignee (if assigned at creation) notifications.
        (new HelpDeskNotifier())->ticketCreated($ticket);

        return response()->json([
            'message' => "Help Desk ticket {$ref} submitted.",
            'data'    => $this->detail($ticket),
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $ticket = HelpDeskTicket::findOrFail($id);
        return response()->json(['data' => $this->detail($ticket)]);
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'assignee_email' => 'nullable|email|max:150',
        ]);

        $ticket = HelpDeskTicket::findOrFail($id);
        if ($r = $this->rejectIfClosed($ticket)) {
            return $r;
        }
        $previousAssignee = $ticket->assignee_name;

        $previousAssigneeEmail = $ticket->assignee_email;
        $newEmail = $validated['assignee_email'] ?? null;

        if (empty($newEmail)) {
            $ticket->assignee_id    = null;
            $ticket->assignee_name  = null;
            $ticket->assignee_email = null;
        } else {
            $assignee = $this->resolveAssignee($newEmail);
            $ticket->assignee_id    = $assignee['id'];
            $ticket->assignee_name  = $assignee['name'];
            $ticket->assignee_email = $assignee['email'];
            // Assignment never changes the status. A ticket stays in its current
            // state (e.g. New) until someone explicitly moves it via updateStatus().
        }
        $ticket->save();

        // Notify on (re)assignment. Reassigned = it already had a different
        // assignee. Best-effort; never blocks the response.
        if (!empty($newEmail)) {
            $reassigned = !empty($previousAssigneeEmail) && $previousAssigneeEmail !== $newEmail;
            (new HelpDeskNotifier())->ticketAssigned($ticket, $reassigned);
        }

        [$actorId, $actorName] = $this->actor();
        if (empty($ticket->assignee_name)) {
            HelpDeskAuditLog::record($ticket->id, 'unassigned', 'Ticket unassigned', $actorId, $actorName, ['from' => $previousAssignee]);
        } elseif (!empty($previousAssignee) && $previousAssignee !== $ticket->assignee_name) {
            HelpDeskAuditLog::record($ticket->id, 'reassigned', "Reassigned from {$previousAssignee} to {$ticket->assignee_name}", $actorId, $actorName, ['from' => $previousAssignee, 'to' => $ticket->assignee_name]);
        } else {
            HelpDeskAuditLog::record($ticket->id, 'assigned', "Assigned to {$ticket->assignee_name}", $actorId, $actorName, ['to' => $ticket->assignee_name]);
        }

        return response()->json([
            'message' => $ticket->assignee_name ? "Assigned to {$ticket->assignee_name}." : 'Ticket unassigned.',
            'data'    => $this->detail($ticket),
        ]);
    }

    /**
     * A closed ticket is a frozen record. Edits, assignment, attachment changes
     * and plain status flips are all rejected — the only forward path is the
     * governed reopen() (reason + window/role) or clone() into a related ticket.
     * Enforced here, server-side, so it can't be bypassed by calling the API.
     */
    private function rejectIfClosed(HelpDeskTicket $ticket): ?JsonResponse
    {
        if ($ticket->status === 'closed') {
            return response()->json([
                'message' => 'This ticket is closed and read-only. Reopen it (or raise a related ticket) to make changes.',
            ], 422);
        }
        return null;
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:' . implode(',', HelpDeskTicket::STATUSES),
        ]);

        // Closing requires a summary + proof screenshot — route through close().
        if ($validated['status'] === 'closed') {
            return response()->json([
                'message' => 'Closing a ticket requires a closing summary and a screenshot. Use the close action.',
            ], 422);
        }
        // Reopening is a dedicated, reporter-only action — route through reopen().
        if ($validated['status'] === 'reopened') {
            return response()->json([
                'message' => 'Use the reopen action to reopen a ticket.',
            ], 422);
        }

        $ticket = HelpDeskTicket::findOrFail($id);
        // Frozen once closed — a flip back to open/in_progress/resolved must go
        // through the governed reopen() flow, not this endpoint.
        if ($r = $this->rejectIfClosed($ticket)) {
            return $r;
        }
        $previousStatus = $ticket->status;
        $ticket->status = $validated['status'];
        // Reopening a previously-closed ticket clears the closed stamp.
        if ($ticket->closed_at) {
            $ticket->closed_at = null;
        }
        $ticket->save();

        [$actorId, $actorName] = $this->actor();
        HelpDeskAuditLog::record(
            $ticket->id, 'status_changed',
            'Status changed from ' . $this->statusLabel($previousStatus) . ' to ' . $this->statusLabel($ticket->status),
            $actorId, $actorName, ['from' => $previousStatus, 'to' => $ticket->status],
        );

        return response()->json([
            'message' => "Status updated to {$validated['status']}.",
            'data'    => $this->detail($ticket),
        ]);
    }

    /**
     * Close a ticket. Mandatory: a ≤50-char closing summary and a screenshot
     * proving the issue is resolved. The screenshot is appended to the
     * ticket's attachments under the "closing_screenshot" slot.
     */
    public function close(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'closing_summary'    => 'required|string|min:50',
            'closing_screenshot' => 'required|image|max:5120',
        ], [
            'closing_summary.required'    => 'Please add a closing summary (at least 50 characters).',
            'closing_summary.min'         => 'The closing summary must be at least 50 characters.',
            'closing_screenshot.required' => 'Please attach a screenshot showing the issue is resolved.',
            'closing_screenshot.image'    => 'The closing screenshot must be an image.',
        ]);

        $ticket = HelpDeskTicket::findOrFail($id);

        $attachments = $ticket->attachments ?? [];
        $attachments[] = $this->storeFile($request->file('closing_screenshot'), $ticket->ticket_ref, 'closing_screenshot');

        $ticket->attachments     = $attachments;
        $ticket->closing_summary = $validated['closing_summary'];
        $ticket->status          = 'closed';
        $ticket->closed_at       = now();
        $ticket->save();

        // Closure notification to the reporter. Best-effort.
        (new HelpDeskNotifier())->ticketClosed($ticket);

        [$actorId, $actorName] = $this->actor();
        HelpDeskAuditLog::record(
            $ticket->id, 'closed', 'Ticket closed',
            $actorId, $actorName, ['summary' => $ticket->closing_summary],
        );

        return response()->json([
            'message' => "Ticket {$ticket->ticket_ref} closed.",
            'data'    => $this->detail($ticket),
        ]);
    }

    /**
     * Edit an existing ticket's core fields (title / description / priority /
     * type). Title and the 100-char description minimum are kept as invariants,
     * matching ticket creation. Status, assignment and attachments are managed
     * by their own endpoints.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:150',
            'description' => 'required|string|min:100|max:5000',
            'priority'    => 'nullable|string|in:' . implode(',', HelpDeskTicket::PRIORITIES),
            'type'        => 'nullable|string|in:' . implode(',', HelpDeskTicket::TYPES),
        ], [
            'description.min' => 'Please describe the issue in at least 100 characters.',
        ]);

        $ticket = HelpDeskTicket::findOrFail($id);
        if ($r = $this->rejectIfClosed($ticket)) {
            return $r;
        }
        $ticket->title       = $validated['title'];
        $ticket->description = $validated['description'];
        if (!empty($validated['priority'])) {
            $ticket->priority = $validated['priority'];
        }
        if (!empty($validated['type'])) {
            $ticket->type = $validated['type'];
        }
        $ticket->save();

        return response()->json([
            'message' => "Ticket {$ticket->ticket_ref} updated.",
            'data'    => $this->detail($ticket),
        ]);
    }

    /**
     * Add a screenshot / file to an existing ticket. `slot` is a display label
     * (e.g. "screenshot" / "attachment"); files append to the attachments list.
     */
    public function addAttachment(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:10240',  // 10MB
            'slot' => 'nullable|string|max:30',
        ]);

        $ticket = HelpDeskTicket::findOrFail($id);
        if ($r = $this->rejectIfClosed($ticket)) {
            return $r;
        }
        $slot   = preg_replace('/[^a-z0-9_]/i', '_', (string) ($request->input('slot') ?: 'attachment'));

        $attachments   = $ticket->attachments ?? [];
        $attachments[] = $this->storeFile($request->file('file'), $ticket->ticket_ref, $slot);
        $ticket->attachments = array_values($attachments);
        $ticket->save();

        return response()->json([
            'message' => 'Attachment added.',
            'data'    => $this->detail($ticket),
        ]);
    }

    /**
     * Delete one attachment by its index. Removes the stored file (best-effort)
     * and re-indexes the list so remaining attachment indices stay contiguous.
     */
    public function deleteAttachment(int $id, int $slot): JsonResponse
    {
        $ticket      = HelpDeskTicket::findOrFail($id);
        if ($r = $this->rejectIfClosed($ticket)) {
            return $r;
        }
        $attachments = $ticket->attachments ?? [];

        abort_unless(isset($attachments[$slot]), 404, 'Attachment not found.');
        $removed = $attachments[$slot];

        try {
            if (!empty($removed['path'])) {
                Storage::disk('s3')->delete($removed['path']);
            }
        } catch (\Throwable $e) {
            Log::warning('help_desk.attachment_delete_failed', [
                'ticket' => $ticket->ticket_ref, 'path' => $removed['path'] ?? null, 'msg' => $e->getMessage(),
            ]);
        }

        array_splice($attachments, $slot, 1);
        $ticket->attachments = array_values($attachments);
        $ticket->save();

        return response()->json([
            'message' => 'Attachment removed.',
            'data'    => $this->detail($ticket),
        ]);
    }

    /**
     * Reopen a closed ticket.
     *
     * Who may reopen (see canReopen()):
     *   - the reporter, within REOPEN_WINDOW_DAYS of closure, OR
     *   - an agent/admin (REOPEN_ROLES), any time.
     * A short reason is required and recorded on the audit trail. Once the
     * reporter's window lapses, the clone() flow ("raise a related ticket") is
     * the path instead. The developer can then close it again via close().
     */
    public function reopen(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|min:10|max:500',
        ], [
            'reason.required' => 'Please add a short reason for reopening.',
            'reason.min'      => 'The reason must be at least 10 characters.',
        ]);

        $ticket = HelpDeskTicket::findOrFail($id);
        $user   = Auth::user();

        if ($ticket->status !== 'closed') {
            return response()->json(['message' => 'Only a closed ticket can be reopened.'], 422);
        }
        if (!$this->canReopen($ticket, $user)) {
            $isReporter = $user && $user->id === $ticket->reporter_id;
            $msg = $isReporter
                ? 'The reopen window for this ticket has passed (' . self::REOPEN_WINDOW_DAYS . ' days). Please raise a related ticket instead.'
                : 'You are not allowed to reopen this ticket. Only the reporter (within ' . self::REOPEN_WINDOW_DAYS . ' days) or an admin can.';
            return response()->json(['message' => $msg], 403);
        }

        $reason = trim($validated['reason']);
        $ticket->status    = 'reopened';
        $ticket->closed_at = null;
        $ticket->save();

        [$actorId, $actorName] = $this->actor();
        HelpDeskAuditLog::record(
            $ticket->id, 'reopened', "Ticket reopened by {$actorName}: {$reason}",
            $actorId, $actorName, ['reason' => $reason],
        );

        // Notify reporter + assignee the ticket is active again. Best-effort.
        (new HelpDeskNotifier())->ticketReopened($ticket);

        return response()->json([
            'message' => "Ticket {$ticket->ticket_ref} reopened.",
            'data'    => $this->detail($ticket),
        ]);
    }

    /**
     * Clone a ticket into a NEW, linked ticket ("Raise a related ticket").
     *
     * For when an issue relates to a closed/older ticket but is really a fresh
     * occurrence: keeps the original closed (metrics stay clean) while
     * preserving a two-way reference. Any authenticated user may do this; no
     * screenshots are required because the evidence lives on the original.
     */
    public function clone(int $id): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Authentication required.'], 401);
        }

        $orig = HelpDeskTicket::findOrFail($id);
        $ref  = HelpDeskTicket::generateRef();

        // Prepend a reference line so the link is obvious even outside the UI
        // (Bridge / emails), capped to the same 5000-char ceiling as create.
        $description = "[Related to {$orig->ticket_ref}]\n\n" . (string) $orig->description;
        $description = mb_substr($description, 0, 5000);

        $clone = HelpDeskTicket::create([
            'ticket_ref'        => $ref,
            'title'             => $orig->title,
            'description'       => $description,
            'priority'          => $orig->priority,
            'type'              => $orig->type,
            'source'            => 'web',
            'reporter_id'       => $user->id,
            'reporter_name'     => $this->displayName($user),
            'reporter_email'    => $user->email,
            'assignee_id'       => $orig->assignee_id,
            'assignee_name'     => $orig->assignee_name,
            'assignee_email'    => $orig->assignee_email,
            'status'            => 'new',
            'related_ticket_id' => $orig->id,
            'attachments'       => [],
        ]);

        [$actorId, $actorName] = $this->actor();
        HelpDeskAuditLog::record(
            $clone->id, 'created', "Ticket created as related to {$orig->ticket_ref}",
            $actorId, $actorName, ['related_to' => $orig->ticket_ref],
        );
        HelpDeskAuditLog::record(
            $orig->id, 'related', "Related ticket {$clone->ticket_ref} raised by {$actorName}",
            $actorId, $actorName, ['related_ticket' => $clone->ticket_ref],
        );

        // Notify reporter (+ carried-over assignee) of the new ticket. Best-effort.
        (new HelpDeskNotifier())->ticketCreated($clone);

        return response()->json([
            'message' => "Related ticket {$ref} created from {$orig->ticket_ref}.",
            'data'    => $this->detail($clone),
        ], 201);
    }

    /** Audit trail for a ticket, newest first. */
    public function audit(int $id): JsonResponse
    {
        HelpDeskTicket::findOrFail($id); // 404 if the ticket doesn't exist
        $rows = HelpDeskAuditLog::where('ticket_id', $id)->orderBy('id', 'desc')->get();

        return response()->json([
            'data' => $rows->map(fn ($r) => [
                'id'          => $r->id,
                'event'       => $r->event,
                'description' => $r->description,
                'actor'       => $r->actor,
                'createdAt'   => optional($r->created_at)->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Discussion comments for a ticket, in chronological order (oldest first)
     * so the conversation reads top-to-bottom. Any authenticated user who can
     * view the ticket can read the discussion — same access as show().
     */
    public function comments(int $id): JsonResponse
    {
        HelpDeskTicket::findOrFail($id); // 404 if the ticket doesn't exist
        $rows = HelpDeskComment::where('ticket_id', $id)->orderBy('id', 'asc')->get();

        return response()->json([
            'data' => $rows->map(fn ($c) => $this->commentRow($c)),
        ]);
    }

    /**
     * Add a discussion comment. The user supplies only the comment text; the
     * author (id + display name) and timestamp are captured from the session,
     * never trusted from the body. Append-only — comments are never edited or
     * overwritten. Mirrors the "ungated, any authenticated user" access of the
     * other ticket actions.
     */
    public function addComment(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Authentication required.'], 401);
        }

        $validated = $request->validate([
            'comment' => 'required|string|min:1|max:5000',
        ], [
            'comment.required' => 'Please enter a comment.',
        ]);

        $comment = HelpDeskComment::create([
            'ticket_id' => HelpDeskTicket::findOrFail($id)->id,
            'user_id'   => $user->id,
            'user_name' => $this->displayName($user),
            'comment'   => trim($validated['comment']),
        ]);

        return response()->json([
            'message' => 'Comment added.',
            'data'    => $this->commentRow($comment),
        ], 201);
    }

    public function downloadAttachment(int $id, int $slot): StreamedResponse
    {
        $ticket = HelpDeskTicket::findOrFail($id);
        $attachments = $ticket->attachments ?? [];

        abort_unless(isset($attachments[$slot]), 404, 'Attachment not found.');
        $file = $attachments[$slot];
        abort_unless(Storage::disk('s3')->exists($file['path']), 404, 'File missing from storage.');

        return Storage::disk('s3')->download($file['path'], $file['original_name'] ?? 'attachment');
    }

    /**
     * Aggregated counts for the dashboard widget at the top of the
     * Help Desk list page. Honours the same filter inputs as index()
     * so the chips reflect the user's current scope (e.g. "mine=1"
     * narrows counts to tickets I raised).
     *
     * Returns:
     *   by_status   — one key per HelpDeskTicket::STATUSES (new, open, in_progress, resolved, closed, reopened)
     *   by_priority — fixed 4-key map (low, medium, high, critical)
     *   by_reporter — top 8 reporters by count (name + count)
     *   by_assignee — top 8 assignees (Unassigned bucket included if any)
     */
    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status'        => 'nullable|string|in:' . implode(',', HelpDeskTicket::STATUSES),
            'priority'      => 'nullable|string|in:' . implode(',', HelpDeskTicket::PRIORITIES),
            'assignee_id'   => 'nullable|integer',
            'assignee_name' => 'nullable|string|max:150',
            'reporter_name' => 'nullable|string|max:150',
            'mine'          => 'nullable|boolean',
            'search'        => 'nullable|string|max:100',
        ]);

        // Counts are pulled in 4 small grouped queries against indexed
        // columns (status, priority, assignee_id are all indexed) — no
        // need to load any ticket rows into memory.
        $statusRows = $this->buildFilteredQuery($validated)
            ->select('status', DB::raw('COUNT(*) as c'))
            ->groupBy('status')->pluck('c', 'status');

        $priorityRows = $this->buildFilteredQuery($validated)
            ->select('priority', DB::raw('COUNT(*) as c'))
            ->groupBy('priority')->pluck('c', 'priority');

        // Secondary sort by name keeps ties stable + alphabetical instead of
        // MySQL's storage-order non-determinism.
        //
        // ->reorder() clears the orderBy('id','desc') that buildFilteredQuery()
        // appends for the LIST endpoint. Without reorder(), the inherited
        // ORDER BY id DESC sits in front of our count-based sort and dominates
        // (with GROUP BY, MySQL picks an indeterminate row's id per group, so
        // the final order is essentially random — which is what live PROD
        // showed before this fix: counts of 9 landing below counts of 1).
        $reporterRows = $this->buildFilteredQuery($validated)
            ->select('reporter_name', DB::raw('COUNT(*) as c'))
            ->whereNotNull('reporter_name')
            ->groupBy('reporter_name')
            ->reorder('c', 'desc')
            ->orderBy('reporter_name')
            ->limit(8)
            ->get();

        $assigneeRows = $this->buildFilteredQuery($validated)
            ->select('assignee_name', DB::raw('COUNT(*) as c'))
            ->groupBy('assignee_name')
            ->reorder('c', 'desc')
            ->orderBy('assignee_name')
            ->limit(8)
            ->get();

        $byStatus = [];
        foreach (HelpDeskTicket::STATUSES as $s) { $byStatus[$s] = (int) ($statusRows[$s] ?? 0); }
        $byPriority = [];
        foreach (HelpDeskTicket::PRIORITIES as $p) { $byPriority[$p] = (int) ($priorityRows[$p] ?? 0); }

        $total = array_sum($byStatus);

        return response()->json([
            'data' => [
                'total'       => $total,
                'by_status'   => $byStatus,
                'by_priority' => $byPriority,
                'by_reporter' => $reporterRows->map(fn ($r) => [
                    'name'  => $r->reporter_name,
                    'count' => (int) $r->c,
                ])->values(),
                'by_assignee' => $assigneeRows->map(fn ($r) => [
                    'name'  => $r->assignee_name,   // null => Unassigned bucket
                    'count' => (int) $r->c,
                ])->values(),
            ],
        ]);
    }

    // ── helpers ────────────────────────────────────────────────────────

    /**
     * Shared filter builder for index() and summary() so the dashboard
     * widget always reflects exactly the same scope the table is
     * showing. Search hits ticket_ref, title, description, reporter_name,
     * and assignee_name.
     */
    private function buildFilteredQuery(array $validated)
    {
        return HelpDeskTicket::query()
            ->when($validated['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($validated['priority'] ?? null, fn ($q, $v) => $q->where('priority', $v))
            ->when($validated['assignee_id'] ?? null, fn ($q, $v) => $q->where('assignee_id', $v))
            ->when($validated['assignee_name'] ?? null, function ($q, $v) {
                $v === '__unassigned__'
                    ? $q->whereNull('assignee_name')
                    : $q->where('assignee_name', $v);
            })
            ->when($validated['reporter_name'] ?? null, fn ($q, $v) => $q->where('reporter_name', $v))
            ->when(($validated['mine'] ?? false) && Auth::id(), fn ($q) => $q->where('reporter_id', Auth::id()))
            ->when($validated['search'] ?? null, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('ticket_ref', 'like', "%{$search}%")
                      ->orWhere('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('reporter_name', 'like', "%{$search}%")
                      ->orWhere('assignee_name', 'like', "%{$search}%");
                });
            })
            ->orderBy('id', 'desc');
    }

    private function storeFile($file, string $ref, string $slot): array
    {
        $safe = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'file';
        $ext  = $file->getClientOriginalExtension();
        $name = $safe . ($ext ? ".{$ext}" : '');
        $path = "help-desk/{$ref}/{$slot}_{$name}";

        Storage::disk('s3')->putFileAs(
            dirname($path),
            $file,
            basename($path),
            ['visibility' => 'private'],
        );

        return [
            'slot'          => $slot,
            'original_name' => $file->getClientOriginalName(),
            'path'          => $path,
            'mime'          => $file->getClientMimeType(),
            'size'          => $file->getSize(),
        ];
    }

    private function displayName(User $user): string
    {
        $name = trim(($user->firstName ?? '') . ' ' . ($user->lastName ?? ''));
        return $name !== '' ? $name : (string) $user->email;
    }

    /**
     * Resolve an assignee email to { id, name, email }. If the email matches a
     * Graphite user we use their id + full name; otherwise the email is kept
     * as the display name (external addresses / typos still route somewhere).
     * Returns nulls when no email is given.
     *
     * @return array{id: int|null, name: string|null, email: string|null}
     */
    private function resolveAssignee(?string $email): array
    {
        $email = $email ? trim($email) : null;
        if (empty($email)) {
            return ['id' => null, 'name' => null, 'email' => null];
        }
        $user = User::where('email', $email)->first();
        if ($user) {
            return ['id' => $user->id, 'name' => $this->displayName($user), 'email' => $email];
        }
        return ['id' => null, 'name' => $email, 'email' => $email];
    }

    /** The acting user for audit entries: [id, displayName]. */
    private function actor(): array
    {
        $u = Auth::user();
        return $u ? [$u->id, $this->displayName($u)] : [null, 'System'];
    }

    /**
     * May $user reopen $ticket right now? Reporter within the window, or an
     * agent/admin any time. Used by reopen() (enforcement) and detail() (so
     * the UI shows the button to exactly who can use it — single source of
     * truth, no duplicated client-side role logic).
     */
    private function canReopen(HelpDeskTicket $ticket, ?User $user): bool
    {
        if (!$user || $ticket->status !== 'closed') {
            return false;
        }
        if ($user->hasAnyRole(self::REOPEN_ROLES)) {
            return true;
        }
        if ($user->id === $ticket->reporter_id) {
            // Reporter only within the window; need a closure stamp to judge.
            return $ticket->closed_at !== null
                && $ticket->closed_at->gt(now()->subDays(self::REOPEN_WINDOW_DAYS));
        }
        return false;
    }

    private function statusLabel(?string $status): string
    {
        return [
            'new' => 'New', 'open' => 'Open', 'in_progress' => 'In Progress',
            'resolved' => 'Resolved', 'closed' => 'Closed', 'reopened' => 'Reopened',
        ][$status] ?? ucfirst((string) $status);
    }

    /** Shape a discussion comment for the API. */
    private function commentRow(HelpDeskComment $c): array
    {
        return [
            'id'        => $c->id,
            'userId'    => $c->user_id,
            'userName'  => $c->user_name,
            'comment'   => $c->comment,
            'createdAt' => optional($c->created_at)->toIso8601String(),
        ];
    }

    private function summaryRow(HelpDeskTicket $t): array
    {
        $desc = (string) $t->description;
        return [
            'id'             => $t->id,
            'ticketRef'      => $t->ticket_ref,
            'title'          => $t->title,
            'excerpt'        => Str::limit($desc, 120),
            'priority'       => $t->priority,
            'type'           => $t->type,
            'reporterName'   => $t->reporter_name,
            'assigneeId'     => $t->assignee_id,
            'assigneeName'   => $t->assignee_name,
            'status'         => $t->status,
            'attachmentCount'=> count($t->attachments ?? []),
            'createdAt'      => optional($t->created_at)->toIso8601String(),
            'closedAt'       => optional($t->closed_at)->toIso8601String(),
        ];
    }

    private function detail(HelpDeskTicket $t): array
    {
        $relatedRef = $t->related_ticket_id
            ? HelpDeskTicket::where('id', $t->related_ticket_id)->value('ticket_ref')
            : null;

        $children = HelpDeskTicket::where('related_ticket_id', $t->id)
            ->orderBy('id', 'desc')
            ->get(['id', 'ticket_ref', 'status'])
            ->map(fn ($c) => [
                'id'        => $c->id,
                'ticketRef' => $c->ticket_ref,
                'status'    => $c->status,
            ]);

        return [
            'id'             => $t->id,
            'ticketRef'      => $t->ticket_ref,
            'title'          => $t->title,
            'description'    => $t->description,
            'priority'       => $t->priority,
            'type'           => $t->type,
            'source'         => $t->source,
            'externalRef'    => $t->external_ref,
            'reporterId'     => $t->reporter_id,
            'reporterName'   => $t->reporter_name,
            'reporterEmail'  => $t->reporter_email,
            'assigneeId'     => $t->assignee_id,
            'assigneeName'   => $t->assignee_name,
            'status'         => $t->status,
            'relatedTicketId'  => $t->related_ticket_id,
            'relatedTicketRef' => $relatedRef,
            'relatedChildren'  => $children,
            'canReopen'        => $this->canReopen($t, Auth::user()),
            'closingSummary' => $t->closing_summary,
            'openedAt'       => optional($t->created_at)->toIso8601String(),
            'closedAt'       => optional($t->closed_at)->toIso8601String(),
            'attachments'    => collect($t->attachments ?? [])->values()->map(fn ($a, $i) => [
                'slot'         => $a['slot'] ?? "file_{$i}",
                'index'        => $i,
                'originalName' => $a['original_name'] ?? 'attachment',
                'mime'         => $a['mime'] ?? null,
                'size'         => $a['size'] ?? null,
            ]),
            'createdAt'      => optional($t->created_at)->toIso8601String(),
            'updatedAt'      => optional($t->updated_at)->toIso8601String(),
        ];
    }
}
