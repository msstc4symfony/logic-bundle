<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Test\Fixtures;

use Msstc4Symfony\LogicBundle\Application\Action\ActionInterface;

/**
 * @implements ActionInterface<FixtureOutput>
 */
final class EchoAction implements ActionInterface
{
    /** @var list<FixtureInput> */
    public array $calls = [];

    public function __invoke(FixtureInput $input): FixtureOutput
    {
        $this->calls[] = $input;

        return new FixtureOutput($input->name);
    }
}
