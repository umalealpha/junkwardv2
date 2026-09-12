<?php

namespace AlphaDirect\Services\Refunds;

/**
 * Thrown on any illegal Customer Refund Engine action — bad transition,
 * missing SOP document, separation-of-duties breach, area mismatch.
 * Controllers map it to a 422 (or 403 for access variants) with the message.
 */
class RefundWorkflowException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 422)
    {
        parent::__construct($message);
    }
}
