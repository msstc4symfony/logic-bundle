<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Test\Fixtures;

use Closure;
use Msstc4Symfony\LogicBundle\Application\Action\InputInterface;
use Msstc4Symfony\LogicBundle\Application\Action\OutputInterface;
use Msstc4Symfony\LogicBundle\Application\Pipeline\ActionContext;
use Msstc4Symfony\LogicBundle\Application\Pipeline\ActionMiddlewareInterface;
use Msstc4Symfony\LogicBundle\Application\Pipeline\Attribute\AsActionMiddleware;
use Symfony\Component\Lock\LockFactory;

/**
 * Runs inside the lock middleware: records whether the action's lock is already held.
 */
#[AsActionMiddleware(priority: 50)]
final readonly class InnerProbe implements ActionMiddlewareInterface
{
    public function __construct(private Journal $journal, private LockFactory $locks)
    {
    }

    public function supports(ActionContext $context): bool
    {
        return true;
    }

    public function handle(InputInterface $input, ActionContext $context, Closure $next): OutputInterface
    {
        $this->journal->add($this->locks->createLock($context->actionClass)->acquire() ? 'inner lock-free' : 'inner lock-held');

        return $next($input);
    }
}
