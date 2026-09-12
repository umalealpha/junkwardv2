<?php

namespace AlphaDirect\Jobs;

use AlphaDirect\Services\GenericNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queued notification job — replaces direct event(new SendMail/SendSms) calls
 * throughout controllers. Set QUEUE_CONNECTION=redis in .env for async delivery.
 *
 * Usage:
 *   SendNotificationJob::dispatch('policy_activated', $customerId, ['email', 'sms']);
 */
class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        private string $hookSlug,
        private int    $customerId,
        private array  $channels = ['email'],
        private string $customMessage = '',
        private array  $additionalData = []
    ) {}

    public function handle(GenericNotificationService $notificationService): void
    {
        $notificationService->sendMultiChannel(
            $this->hookSlug,
            $this->customerId,
            $this->channels,
            $this->customMessage,
            $this->additionalData
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendNotificationJob failed', [
            'hook_slug'   => $this->hookSlug,
            'customer_id' => $this->customerId,
            'channels'    => $this->channels,
            'error'       => $exception->getMessage(),
        ]);
    }
}
