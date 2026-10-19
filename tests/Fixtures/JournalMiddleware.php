<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Test\Fixtures;

use Closure;
use Msstc4Symfony\LogicBundle\Application\Action\InputInterface;
use Msstc4Symfony\LogicBundle\Application\Action\OutputInterface;
use Msstc4Symfony\LogicBundle\Application\Pipeline\ActionContext;
use Msstc4Symfony\LogicBundle\Application\Pipeline\ActionMiddlewareInterface;
use Override;

final class JournalMiddleware implements ActionMiddlewareInterface
{
    public int $supportsCalls = 0;

    /**
     * @param (Closure(InputInterface): InputInterface)|null $replaceInput
     */
    public function __construct(
        private readonly string $name,
        private readonly Journal $journal,
        private readonly bool $supported = true,
        private readonly ?Closure $replaceInput = null,
    ) {
    }

    #[Override]
    public function supports(ActionContext $context): bool
    {
        $this->supportsCalls++;

        return $this->supported;
    }

    #[Override]
    public function handle(InputInterface $input, ActionContext $context, Closure $next): OutputInterface
    {
        $this->journal->add('>' . $this->name);
        $output = $next($this->replaceInput instanceof Closure ? ($this->replaceInput)($input) : $input);
        $this->journal->add('<' . $this->name);

        return $output;
    }
}
