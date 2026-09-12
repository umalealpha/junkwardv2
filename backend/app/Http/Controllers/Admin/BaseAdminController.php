<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Jobs\SendNotificationJob;
use Illuminate\Http\RedirectResponse;

/**
 * BaseAdminController — shared logic for all admin controllers.
 *
 * Extend this instead of Controller to get:
 *  - authorizeAdmin() — replaces duplicated hasPermissionTo checks
 *  - successRedirect() / errorRedirect() — consistent flash messages
 *  - notify() — queued notification dispatch via SendNotificationJob
 *
 * Migration: gradually extend admin controllers from this class.
 * The original Controller base remains for controllers not yet migrated.
 */
abstract class BaseAdminController extends Controller
{
    /**
     * Check permission and abort 403 if not granted.
     * Replaces the if/else hasPermissionTo pattern in every controller.
     */
    protected function authorizeAdmin(string $permission): void
    {
        if (!auth()->user()->can($permission)) {
            abort(403, 'You do not have permission to perform this action.');
        }
    }

    /**
     * Standard success redirect with flash message.
     */
    protected function successRedirect(string $routeName, string $message, array $params = []): RedirectResponse
    {
        return redirect()->route($routeName, $params)->with('success', $message);
    }

    /**
     * Standard error redirect with flash message.
     */
    protected function errorRedirect(string $routeName, string $message, array $params = []): RedirectResponse
    {
        return redirect()->route($routeName, $params)->with('error', $message);
    }

    /**
     * Dispatch a queued notification — replaces direct event(new SendMail/SendSms) calls.
     * QUEUE_CONNECTION=redis must be set in .env for true async delivery.
     *
     * @param string $hookSlug      The notification hook slug (e.g. 'policy_activated')
     * @param int    $customerId    Customer to notify
     * @param array  $channels      ['email', 'sms', 'whatsapp']
     * @param array  $data          Additional template data
     */
    protected function notify(string $hookSlug, int $customerId, array $channels = ['email'], array $data = []): void
    {
        SendNotificationJob::dispatch($hookSlug, $customerId, $channels, '', $data);
    }
}
