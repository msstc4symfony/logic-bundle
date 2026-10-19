<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Test\Fixtures;

use Closure;
use Msstc4Symfony\LogicBundle\Application\Action\InputInterface;
use Msstc4Symfony\LogicBundle\Application\Action\OutputInterface;
use Msstc4Symfony\LogicBundle\Application\Pipeline\ActionContext;
use Msstc4Symfony\LogicBundle\Application\Pipeline\ActionMiddlewareInterface;
use Msstc4Symfony\LogicBundle\Application\Pipeline\Attribute\AsActionMiddleware;
use Override;

#[AsActionMiddleware(priority: 250)]
final readonly class OuterProbe implements ActionMiddlewareInterface
{
    public function __construct(private Journal $journal)
    {
    }

    #[Override]
    public function supports(ActionContext $context): bool
    {
        return true;
    }

    #[Override]
    public function handle(InputInterface $input, ActionContext $context, Closure $next): OutputInterface
    {
        $this->journal->add('outer');

        return $next($input);
    }
}
