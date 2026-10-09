<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Application\Action\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsAction
{
    public function __construct(
        public bool $validation = true,
        public bool $logging = true,
    ) {
    }
}
