<?php

namespace AlphaDirect\Exceptions;

/** Caller-correctable failure from HcbCoapplicantService — message is a stable error code, not prose. */
class HcbCoapplicantException extends \RuntimeException
{
    public function __construct(string $errorCode, public readonly int $status = 422)
    {
        parent::__construct($errorCode);
    }
}
