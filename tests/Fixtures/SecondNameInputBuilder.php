<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Test\Fixtures;

use Msstc4Symfony\LogicBundle\Application\Action\InputInterface;
use Msstc4Symfony\LogicBundle\Application\Logging\Builder\InputBuilderInterface;

final readonly class SecondNameInputBuilder implements InputBuilderInterface
{
    public function build(InputInterface $input): array
    {
        return ['second' => true];
    }

    public function supports(InputInterface $input): bool
    {
        return $input instanceof FixtureInput && $input->name === 'second';
    }
}
