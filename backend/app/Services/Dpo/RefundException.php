<?php

namespace AlphaDirect\Services\Dpo;

/**
 * Domain-specific validation/business-rule exception thrown by RefundService.
 * Distinct from generic Exception so controllers can turn it into a 422.
 */
class RefundException extends \RuntimeException
{
}
