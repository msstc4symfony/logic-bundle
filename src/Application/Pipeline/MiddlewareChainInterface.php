<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Application\Pipeline;

/**
 * An Action service wrapped in the logic pipeline: $container->get(MyAction::class) implements it, unless the
 * application decorates that service itself. The bundle implements it; applications and bridges only call it.
 */
interface MiddlewareChainInterface
{
    /**
     * @return list<ActionMiddlewareInterface> the middlewares that support this Action, outermost first
     */
    public function middlewares(): array;
}
