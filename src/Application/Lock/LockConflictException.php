<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Application\Lock;

use Msstc4Symfony\LogicBundle\Application\Action\ExpectedFailureInterface;
use RuntimeException;

final class LockConflictException extends RuntimeException implements ExpectedFailureInterface
{
    /**
     * @param non-empty-string $key
     */
    public function __construct(public readonly string $key)
    {
        parent::__construct(sprintf('The lock "%s" is held by another call.', $key));
    }
}
