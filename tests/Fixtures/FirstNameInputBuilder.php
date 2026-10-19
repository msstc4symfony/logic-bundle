<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Test\Fixtures;

use Msstc4Symfony\LogicBundle\Application\Action\InputInterface;
use Msstc4Symfony\LogicBundle\Application\Logging\Builder\InputBuilderInterface;
use Override;

final readonly class FirstNameInputBuilder implements InputBuilderInterface
{
    #[Override]
    public function build(InputInterface $input): array
    {
        return ['first' => true];
    }

    #[Override]
    public function supports(InputInterface $input): bool
    {
        return $input instanceof FixtureInput && $input->name === 'first';
    }
}
