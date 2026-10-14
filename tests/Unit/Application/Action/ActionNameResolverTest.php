<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Test\Unit\Application\Action;

use Msstc4Symfony\LogicBundle\Application\Action\ActionNameResolver;
use Msstc4Symfony\LogicBundle\Application\Logging\Builder\ActionNameBuilderInterface;
use Msstc4Symfony\LogicBundle\Application\Pipeline\ActionContext;
use Msstc4Symfony\LogicBundle\Test\Fixtures\EchoAction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ActionNameResolver::class)]
#[UsesClass(ActionContext::class)]
final class ActionNameResolverTest extends TestCase
{
    public function testFallsBackToTheActionClass(): void
    {
        self::assertSame(EchoAction::class, (new ActionNameResolver())->resolve(ActionContext::of(EchoAction::class)));
    }

    public function testTheFirstSupportingBuilderWins(): void
    {
        $resolver = new ActionNameResolver([$this->builder(false, 'skipped'), $this->builder(true, 'echo'), $this->builder(true, 'late')]);

        self::assertSame('echo', $resolver->resolve(ActionContext::of(EchoAction::class)));
    }

    /**
     * @param non-empty-string $name
     */
    private function builder(bool $supports, string $name): ActionNameBuilderInterface
    {
        return new class($supports, $name) implements ActionNameBuilderInterface {
            /**
             * @param non-empty-string $name
             */
            public function __construct(private readonly bool $supports, private readonly string $name)
            {
            }

            public function build(string $actionClass): string
            {
                return $this->name;
            }

            public function supports(string $actionClass): bool
            {
                return $this->supports;
            }
        };
    }
}
