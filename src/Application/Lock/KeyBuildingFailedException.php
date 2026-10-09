<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Application\Lock;

use LogicException;

final class KeyBuildingFailedException extends LogicException
{
    /**
     * @param class-string $actionClass
     */
    public static function create(string $actionClass, string $reason): self
    {
        return new self(sprintf('Cannot build the lock key for %s: %s', $actionClass, $reason));
    }
}
