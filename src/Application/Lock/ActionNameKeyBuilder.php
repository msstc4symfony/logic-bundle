<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Application\Lock;

use Msstc4Symfony\LogicBundle\Application\Action\InputInterface;
use Msstc4Symfony\LogicBundle\Application\Pipeline\ActionContext;
use Override;

/**
 * One key per Action class: every call of that Action runs one at a time across the system.
 *
 * @internal
 */
final readonly class ActionNameKeyBuilder implements KeyBuilderInterface
{
    #[Override]
    public function build(InputInterface $input, ActionContext $context): string
    {
        return $context->actionClass;
    }
}
