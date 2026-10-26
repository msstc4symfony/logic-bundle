<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Application\Pipeline;

use Msstc4Symfony\LogicBundle\Application\Action\ActionInterface;
use Msstc4Symfony\LogicBundle\Application\Action\InputInterface;
use Msstc4Symfony\LogicBundle\Application\Action\OutputInterface;
use Override;

/**
 * @implements ActionInterface<OutputInterface>
 *
 * @internal
 */
final class ActionPipeline implements ActionInterface, MiddlewareChainInterface
{
    // Both are derived from the class alone, so long-running workers may keep them.
    private ?ActionContext $context = null;

    /** @var list<ActionMiddlewareInterface>|null */
    private ?array $chain = null;

    /**
     * @param ActionInterface<OutputInterface> $inner
     * @param class-string<ActionInterface<OutputInterface>> $actionClass
     * @param iterable<array-key, ActionMiddlewareInterface> $middlewares outermost first
     */
    public function __construct(
        private readonly ActionInterface $inner,
        private readonly string $actionClass,
        private readonly iterable $middlewares,
    ) {
    }

    public function __invoke(InputInterface $input): OutputInterface
    {
        return $this->next($this->middlewares(), 0, $input, $this->context());
    }

    /**
     * @return list<ActionMiddlewareInterface>
     */
    #[Override]
    public function middlewares(): array
    {
        $context = $this->context();

        return $this->chain ??= array_values(array_filter(
            iterator_to_array($this->middlewares, false),
            static fn (ActionMiddlewareInterface $middleware): bool => $middleware->supports($context),
        ));
    }

    private function context(): ActionContext
    {
        return $this->context ??= ActionContext::of($this->actionClass);
    }

    /**
     * @param list<ActionMiddlewareInterface> $chain
     */
    private function next(array $chain, int $position, InputInterface $input, ActionContext $context): OutputInterface
    {
        $middleware = $chain[$position] ?? null;
        if ($middleware === null) {
            return $this->inner->__invoke($input);
        }

        return $middleware->handle(
            $input,
            $context,
            fn (InputInterface $input): OutputInterface => $this->next($chain, $position + 1, $input, $context),
        );
    }
}
