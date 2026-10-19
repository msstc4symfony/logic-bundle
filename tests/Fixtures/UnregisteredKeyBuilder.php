<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Test\Fixtures;

use Msstc4Symfony\LogicBundle\Application\Action\InputInterface;
use Msstc4Symfony\LogicBundle\Application\Lock\KeyBuilderInterface;
use Msstc4Symfony\LogicBundle\Application\Pipeline\ActionContext;
use Override;

final readonly class UnregisteredKeyBuilder implements KeyBuilderInterface
{
    #[Override]
    public function build(InputInterface $input, ActionContext $context): string
    {
        return 'k';
    }
}
