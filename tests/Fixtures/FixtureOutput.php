<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Test\Fixtures;

use Msstc4Symfony\LogicBundle\Application\Action\OutputInterface;

final readonly class FixtureOutput implements OutputInterface
{
    public function __construct(public string $echo)
    {
    }
}
