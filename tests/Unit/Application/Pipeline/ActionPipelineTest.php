<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Test\Unit\Application\Pipeline;

use Msstc4Symfony\LogicBundle\Application\Action\InputInterface;
use Msstc4Symfony\LogicBundle\Application\Pipeline\ActionPipeline;
use Msstc4Symfony\LogicBundle\Test\Fixtures\EchoAction;
use Msstc4Symfony\LogicBundle\Test\Fixtures\FixtureInput;
use Msstc4Symfony\LogicBundle\Test\Fixtures\FixtureOutput;
use Msstc4Symfony\LogicBundle\Test\Fixtures\Journal;
use Msstc4Symfony\LogicBundle\Test\Fixtures\JournalMiddleware;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ActionPipelineTest extends TestCase
{
    public function testWithoutMiddlewareCallsTheAction(): void
    {
        $action = new EchoAction();

        $output = (new ActionPipeline($action, EchoAction::class, []))->__invoke(new FixtureInput(name: 'x'));

        self::assertEquals(new FixtureOutput('x'), $output);
        self::assertCount(1, $action->calls);
    }

    public function testRunsMiddlewareInGivenOrderAroundTheAction(): void
    {
        $journal = new Journal();
        $pipeline = new ActionPipeline(new EchoAction(), EchoAction::class, [
            new JournalMiddleware('outer', $journal),
            new JournalMiddleware('inner', $journal),
        ]);

        $pipeline(new FixtureInput());

        self::assertSame(['>outer', '>inner', '<inner', '<outer'], $journal->entries);
    }

    public function testSkipsUnsupportedMiddlewareAndAsksSupportsOnce(): void
    {
        $journal = new Journal();
        $skipped = new JournalMiddleware('skipped', $journal, supported: false);
        $pipeline = new ActionPipeline(new EchoAction(), EchoAction::class, [$skipped, new JournalMiddleware('kept', $journal)]);

        $pipeline(new FixtureInput());
        $pipeline(new FixtureInput());

        self::assertSame(['>kept', '<kept', '>kept', '<kept'], $journal->entries);
        self::assertSame(1, $skipped->supportsCalls);
    }

    public function testListsTheSupportedMiddlewaresOutermostFirst(): void
    {
        $journal = new Journal();
        $outer = new JournalMiddleware('outer', $journal);
        $skipped = new JournalMiddleware('skipped', $journal, supported: false);
        $inner = new JournalMiddleware('inner', $journal);
        $pipeline = new ActionPipeline(new EchoAction(), EchoAction::class, [$outer, $skipped, $inner]);

        $chain = $pipeline->middlewares();
        self::assertSame([$outer, $inner], $chain);
        self::assertSame($chain, $pipeline->middlewares());
        $pipeline(new FixtureInput());
        self::assertSame(1, $skipped->supportsCalls);
        self::assertSame(['>outer', '>inner', '<inner', '<outer'], $journal->entries);
    }

    public function testEveryMiddlewareGetsOneContextPerService(): void
    {
        $middleware = new JournalMiddleware('m', new Journal());
        $pipeline = new ActionPipeline(new EchoAction(), EchoAction::class, [$middleware]);

        $pipeline(new FixtureInput());
        $pipeline(new FixtureInput());

        self::assertCount(3, $middleware->contexts);
        self::assertSame($middleware->contexts[0], $middleware->contexts[1]);
        self::assertSame($middleware->contexts[0], $middleware->contexts[2]);
    }

    public function testActionReceivesTheInputAMiddlewarePassedOn(): void
    {
        $action = new EchoAction();
        $replace = static fn (InputInterface $input): InputInterface => new FixtureInput(name: 'replaced');
        $pipeline = new ActionPipeline($action, EchoAction::class, [new JournalMiddleware('m', new Journal(), replaceInput: $replace)]);

        $output = $pipeline(new FixtureInput(name: 'original'));

        self::assertEquals(new FixtureOutput('replaced'), $output);
        self::assertSame('replaced', $action->calls[0]->name);
    }

    public function testActionExceptionPropagatesThroughMiddleware(): void
    {
        $journal = new Journal();
        $failing = new JournalMiddleware('m', $journal, replaceInput: static fn (InputInterface $input): never => throw new RuntimeException('boom'));
        $pipeline = new ActionPipeline(new EchoAction(), EchoAction::class, [$failing]);

        $this->expectExceptionObject(new RuntimeException('boom'));
        $pipeline(new FixtureInput());
    }
}
