<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Application\Pipeline;

use Closure;
use Msstc4Symfony\LogicBundle\Application\Action\InputInterface;
use Msstc4Symfony\LogicBundle\Application\Action\OutputInterface;

interface ActionMiddlewareInterface
{
    /**
     * Asked once per Action service and cached for the life of the process: decide from the context only.
     */
    public function supports(ActionContext $context): bool;

    /**
     * @param Closure(InputInterface): OutputInterface $next
     */
    public function handle(InputInterface $input, ActionContext $context, Closure $next): OutputInterface;
}
