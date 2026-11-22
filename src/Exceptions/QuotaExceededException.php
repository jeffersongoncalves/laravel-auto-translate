<?php

declare(strict_types=1);

namespace JeffersonGoncalves\AutoTranslate\Exceptions;

use RuntimeException;

/**
 * The provider's quota is spent; retrying before $retryAfter seconds is useless.
 */
final class QuotaExceededException extends RuntimeException
{
    public function __construct(string $message, public readonly int $retryAfter)
    {
        parent::__construct($message);
    }
}
