<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Application\Logging\Builder;

use Msstc4Symfony\LogicBundle\Application\Action\ActionInterface;
use Msstc4Symfony\LogicBundle\Application\Action\OutputInterface;

/**
 * Order builders with #[AsTaggedItem(priority: N)]; the first supporting one wins.
 */
interface ActionNameBuilderInterface
{
    /**
     * @param class-string<ActionInterface<OutputInterface>> $actionClass
     *
     * @return non-empty-string
     */
    public function build(string $actionClass): string;

    /**
     * @param class-string<ActionInterface<OutputInterface>> $actionClass
     */
    public function supports(string $actionClass): bool;
}
