<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Application\Pipeline\Attribute;

use Attribute;

/**
 * Higher priority runs further out. Built-ins: logging 300, validation 200, lock 100.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsActionMiddleware
{
    public function __construct(public int $priority = 0)
    {
    }
}
