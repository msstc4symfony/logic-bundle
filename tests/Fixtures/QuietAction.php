<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Test\Fixtures;

use Msstc4Symfony\LogicBundle\Application\Action\ActionInterface;
use Msstc4Symfony\LogicBundle\Application\Action\Attribute\AsAction;

/**
 * @implements ActionInterface<FixtureOutput>
 */
#[AsAction(validation: false, logging: false)]
// Reason: an attribute of an uninstalled class must be skipped by ActionContext::of(); PHPStan cannot know that is the point.
// @phpstan-ignore attribute.notFound
#[AttributeThatDoesNotExist]
#[FixtureMarker('first')]
#[FixtureMarker('second')]
final class QuietAction implements ActionInterface
{
    public function __invoke(FixtureInput $input): FixtureOutput
    {
        return new FixtureOutput($input->name);
    }
}
