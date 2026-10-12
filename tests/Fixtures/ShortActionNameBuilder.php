<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Test\Fixtures;

use Msstc4Symfony\LogicBundle\Application\Logging\Builder\ActionNameBuilderInterface;

final class ShortActionNameBuilder implements ActionNameBuilderInterface
{
    public function build(string $actionClass): string
    {
        return 'echo';
    }

    public function supports(string $actionClass): bool
    {
        return $actionClass === EchoAction::class;
    }
}
