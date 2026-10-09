<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Application\Lock;

use Msstc4Symfony\LogicBundle\Application\Action\InputInterface;
use Msstc4Symfony\LogicBundle\Application\Pipeline\ActionContext;

/**
 * One key per Action class: every call of that Action runs one at a time across the system.
 */
final readonly class ActionNameKeyBuilder implements KeyBuilderInterface
{
    public function build(InputInterface $input, ActionContext $context): string
    {
        return $context->actionClass;
    }
}
