<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Test\Fixtures;

use Msstc4Symfony\LogicBundle\Application\Logging\Builder\ActionNameBuilderInterface;
use Override;

final class ShortActionNameBuilder implements ActionNameBuilderInterface
{
    #[Override]
    public function build(string $actionClass): string
    {
        return 'echo';
    }

    #[Override]
    public function supports(string $actionClass): bool
    {
        return $actionClass === EchoAction::class;
    }
}
