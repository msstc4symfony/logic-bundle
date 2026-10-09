<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Test\Fixtures;

use Msstc4Symfony\LogicBundle\Application\Action\ActionInterface;
use Msstc4Symfony\LogicBundle\Application\Lock\LockableAction;

/**
 * @implements ActionInterface<FixtureOutput>
 */
#[LockableAction(keyBuilder: UnregisteredKeyBuilder::class)]
final class UnregisteredKeyBuilderAction implements ActionInterface
{
    public function __invoke(FixtureInput $input): FixtureOutput
    {
        return new FixtureOutput($input->name);
    }
}
