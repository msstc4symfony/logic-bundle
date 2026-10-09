<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Test\Fixtures;

use Msstc4Symfony\LogicBundle\Application\Action\ActionInterface;
use Msstc4Symfony\LogicBundle\Application\Lock\LockableAction;

/**
 * @implements ActionInterface<FixtureOutput>
 */
// Reason: the fixture deliberately names a class that is not a key builder to test the compile-time check.
// @phpstan-ignore argument.type
#[LockableAction(keyBuilder: Journal::class)]
final class WrongKeyBuilderAction implements ActionInterface
{
    public function __invoke(FixtureInput $input): FixtureOutput
    {
        return new FixtureOutput($input->name);
    }
}
