<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Services\Claims\ClaimFormDispatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Staff side of the claim-form send button. Two endpoints, both authenticated
 * and role-gated in the route file:
 *
 *   GET  claims-v2/{id}/claim-form-options  what to show in the dialog
 *   POST claims-v2/{id}/send-claim-form     do it
 *
 * Deliberately thin — all behaviour lives in ClaimFormDispatchService so the
 * same logic can later be called by a scheduled job without going through HTTP.
 */
class ClaimFormDispatchController extends Controller
{
    public function __construct(private ClaimFormDispatchService $service)
    {
    }

    public function options(int $id): JsonResponse
    {
        if (!ClaimFormDispatchService::enabled()) {
            return response()->json(['message' => 'Claim form sending is not switched on.'], 404);
        }

        $result = $this->service->options($id);
        if (empty($result['ok'])) {
            return response()->json(['message' => 'Claim not found.'], 404);
        }

        return response()->json(['data' => $result]);
    }

    public function send(Request $request, int $id): JsonResponse
    {
        if (!ClaimFormDispatchService::enabled()) {
            return response()->json(['message' => 'Claim form sending is not switched on.'], 404);
        }

        $validated = $request->validate([
            'form_id'      => 'required|integer|exists:claim_type_forms,id',
            'to_email'     => 'required|email|max:190',
            'include_pdf'  => 'nullable|boolean',
            'include_link' => 'nullable|boolean',
        ]);

        $actor = optional($request->user())->email
            ?: (string) optional($request->user())->id
            ?: 'staff';

        $result = $this->service->dispatch(
            $id,
            (int) $validated['form_id'],
            (string) $validated['to_email'],
            (bool) ($validated['include_pdf'] ?? true),
            (bool) ($validated['include_link'] ?? true),
            $actor
        );

        if (empty($result['ok'])) {
            // Plain messages: this text is shown to a claims handler, not a developer.
            $messages = [
                'feature_off'      => 'Claim form sending is not switched on.',
                'nothing_to_send'  => 'Choose the form, the link, or both.',
                'invalid_email'    => 'That email address does not look right.',
                'claim_not_found'  => 'Claim not found.',
                'form_not_found'   => 'That form is not available.',
                'pdf_failed'       => 'We could not produce the form. Nothing was sent — please try again, or send just the link.',
                'send_failed'      => 'We could not send it. Nothing was sent — please try again.',
            ];
            $reason = $result['reason'] ?? 'send_failed';

            return response()->json(
                ['message' => $messages[$reason] ?? $messages['send_failed']],
                $reason === 'claim_not_found' ? 404 : 422
            );
        }

        return response()->json(['data' => $result]);
    }
}
