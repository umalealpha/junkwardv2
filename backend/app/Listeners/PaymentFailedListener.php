<?php

namespace AlphaDirect\Listeners;

use AlphaDirect\Services\CustomerNotificationService;

class PaymentFailedListener
{
    /**
     * Can be called directly from payment controllers:
     *   (new PaymentFailedListener)->handle($policyId, $policyNumber, $amount, 'RealPay', 'Insufficient funds');
     */
    public function handle(int $policyId, string $policyNumber, float $amount, string $paymentMethod, ?string $reason = null): void
    {
        $service = new CustomerNotificationService();
        $service->notifyPaymentFailed($policyId, $policyNumber, $amount, $paymentMethod, $reason);
    }
}
