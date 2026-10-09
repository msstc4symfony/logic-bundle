<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Application\Pipeline;

use Msstc4Symfony\LogicBundle\Application\Action\ActionInterface;
use Msstc4Symfony\LogicBundle\Application\Action\InputInterface;
use Msstc4Symfony\LogicBundle\Application\Action\OutputInterface;

/**
 * @implements ActionInterface<OutputInterface>
 */
final class ActionPipeline implements ActionInterface
{
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
        // Both are derived from the class alone, so long-running workers may keep them.
        $context = $this->context ??= ActionContext::of($this->actionClass);
        $this->chain ??= array_values(array_filter(
            iterator_to_array($this->middlewares, false),
            static fn (ActionMiddlewareInterface $middleware): bool => $middleware->supports($context),
        ));

        return $this->next(0, $input, $context);
    }

    private function next(int $position, InputInterface $input, ActionContext $context): OutputInterface
    {
        $middleware = $this->chain[$position] ?? null;
        if ($middleware === null) {
            return $this->inner->__invoke($input);
        }

        return $middleware->handle(
            $input,
            $context,
            fn (InputInterface $input): OutputInterface => $this->next($position + 1, $input, $context),
        );
    }
}
