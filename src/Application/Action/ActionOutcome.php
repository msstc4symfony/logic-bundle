<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Application\Action;

use Symfony\Component\Validator\Exception\ValidationFailedException;
use Throwable;

/**
 * How an Action run ended; the values are stable metric labels.
 */
enum ActionOutcome: string
{
    case Success = 'success';
    case ExpectedFailure = 'expected_failure';
    case Error = 'error';

    public static function of(?Throwable $exception): self
    {
        return match (true) {
            !$exception instanceof Throwable => self::Success,
            // Symfony's own exception is listed too: it reaches the pipeline from validation inside an Action.
            $exception instanceof ExpectedFailureInterface, $exception instanceof ValidationFailedException => self::ExpectedFailure,
            default => self::Error,
        };
    }
}
