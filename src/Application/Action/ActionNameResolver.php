<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Application\Action;

use Msstc4Symfony\LogicBundle\Application\Logging\Builder\ActionNameBuilderInterface;
use Msstc4Symfony\LogicBundle\Application\Pipeline\ActionContext;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * One Action name for logs, metrics and spans: the first supporting builder, else the Action class.
 */
final readonly class ActionNameResolver
{
    /**
     * @param iterable<array-key, ActionNameBuilderInterface> $builders
     */
    public function __construct(
        #[AutowireIterator(ActionNameBuilderInterface::class)]
        private iterable $builders = [],
    ) {
    }

    /**
     * @return non-empty-string
     */
    public function resolve(ActionContext $context): string
    {
        foreach ($this->builders as $builder) {
            if ($builder->supports($context->actionClass)) {
                return $builder->build($context->actionClass);
            }
        }

        return $context->actionClass;
    }
}
