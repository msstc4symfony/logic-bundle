<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Test\Unit\Infrastructure\Lock;

use LogicException;
use Msstc4Symfony\LogicBundle\Application\Action\InputInterface;
use Msstc4Symfony\LogicBundle\Application\Action\OutputInterface;
use Msstc4Symfony\LogicBundle\Application\Lock\ActionNameKeyBuilder;
use Msstc4Symfony\LogicBundle\Application\Lock\LockConflictException;
use Msstc4Symfony\LogicBundle\Application\Pipeline\ActionContext;
use Msstc4Symfony\LogicBundle\Infrastructure\Lock\LockMiddleware;
use Msstc4Symfony\LogicBundle\Test\Fixtures\ArrayContainer;
use Msstc4Symfony\LogicBundle\Test\Fixtures\CountingStore;
use Msstc4Symfony\LogicBundle\Test\Fixtures\EchoAction;
use Msstc4Symfony\LogicBundle\Test\Fixtures\FixtureInput;
use Msstc4Symfony\LogicBundle\Test\Fixtures\FixtureOutput;
use Msstc4Symfony\LogicBundle\Test\Fixtures\LockableEchoAction;
use Msstc4Symfony\LogicBundle\Test\Fixtures\LongLockAction;
use Msstc4Symfony\LogicBundle\Test\Fixtures\RecordingLogger;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\InMemoryStore;

final class LockMiddlewareTest extends TestCase
{
    private const array PROFILES = [
        'short' => ['ttl' => 5, 'retry_count' => 2, 'retry_delay_ms' => 0],
        'long' => ['ttl' => 300, 'retry_count' => 0, 'retry_delay_ms' => 0],
    ];

    public function testSupportsOnlyLockableActions(): void
    {
        $middleware = $this->middleware(new InMemoryStore());

        self::assertTrue($middleware->supports(ActionContext::of(LockableEchoAction::class)));
        self::assertFalse($middleware->supports(ActionContext::of(EchoAction::class)));
    }

    public function testHoldsTheLockWhileTheActionRunsAndReleasesIt(): void
    {
        $factory = new LockFactory(new InMemoryStore());
        $middleware = $this->middleware($factory);
        $heldInside = null;

        $output = $middleware->handle(new FixtureInput(name: 'x'), ActionContext::of(LockableEchoAction::class), static function (InputInterface $input) use ($factory, &$heldInside): OutputInterface {
            $heldInside = !$factory->createLock(LockableEchoAction::class)->acquire();

            return new FixtureOutput('done');
        });

        self::assertEquals(new FixtureOutput('done'), $output);
        self::assertTrue($heldInside);
        self::assertTrue($factory->createLock(LockableEchoAction::class)->acquire(), 'released after the call');
    }

    public function testConflictAfterAllRetries(): void
    {
        $store = new CountingStore(conflictOnSave: true);

        try {
            $this->middleware($store)->handle(new FixtureInput(), ActionContext::of(LockableEchoAction::class), static fn (InputInterface $input): never => self::fail('The action must not run.'));
            self::fail('LockConflictException expected.');
        } catch (LockConflictException $exception) {
            self::assertSame(LockableEchoAction::class, $exception->key);
        }

        self::assertSame(3, $store->saves, 'retry_count 2 = 3 attempts');
    }

    public function testWaitsTheRetryDelayBetweenAttempts(): void
    {
        $profiles = ['short' => ['ttl' => 5, 'retry_count' => 2, 'retry_delay_ms' => 20]];
        $middleware = new LockMiddleware(new LockFactory(new CountingStore(conflictOnSave: true)), new ArrayContainer([ActionNameKeyBuilder::class => new ActionNameKeyBuilder()]), $profiles, 'short', new RecordingLogger());
        $start = hrtime(true);

        try {
            $middleware->handle(new FixtureInput(), ActionContext::of(LockableEchoAction::class), static fn (InputInterface $input): never => self::fail('The action must not run.'));
        } catch (LockConflictException) {
        }

        self::assertGreaterThanOrEqual(40_000_000, hrtime(true) - $start, 'two pauses of 20 ms between three attempts');
    }

    public function testUsesTheProfileNamedOnTheAttribute(): void
    {
        $store = new CountingStore();

        $this->middleware($store)->handle(new FixtureInput(), ActionContext::of(LongLockAction::class), static fn (InputInterface $input): OutputInterface => new FixtureOutput(''));

        self::assertSame(300.0, $store->lastTtl);
    }

    public function testReleasesWhenTheActionThrows(): void
    {
        $factory = new LockFactory(new InMemoryStore());

        try {
            $this->middleware($factory)->handle(new FixtureInput(), ActionContext::of(LockableEchoAction::class), static fn (InputInterface $input): never => throw new RuntimeException('boom'));
        } catch (RuntimeException) {
        }

        self::assertTrue($factory->createLock(LockableEchoAction::class)->acquire());
    }

    public function testFailedReleaseKeepsTheActionResultAndIsLogged(): void
    {
        $logger = new RecordingLogger();

        $output = $this->middleware(new CountingStore(failOnDelete: true), $logger)->handle(new FixtureInput(), ActionContext::of(LockableEchoAction::class), static fn (InputInterface $input): OutputInterface => new FixtureOutput('kept'));

        self::assertEquals(new FixtureOutput('kept'), $output);
        self::assertSame(['warning Could not release the action lock'], $logger->lines());
        self::assertSame(LockableEchoAction::class, $logger->records[0]['context']['key'] ?? null);
    }

    public function testFailingStoreCheckAfterReleaseKeepsTheActionResult(): void
    {
        $logger = new RecordingLogger();

        $output = $this->middleware(new CountingStore(failOnExists: true), $logger)->handle(new FixtureInput(), ActionContext::of(LockableEchoAction::class), static fn (InputInterface $input): OutputInterface => new FixtureOutput('kept'));

        self::assertEquals(new FixtureOutput('kept'), $output);
        self::assertSame(['warning Could not release the action lock'], $logger->lines());
        self::assertSame(LockableEchoAction::class, $logger->records[0]['context']['key'] ?? null);
    }

    public function testFailedReleaseKeepsTheActionException(): void
    {
        $this->expectExceptionObject(new RuntimeException('action failed'));

        $this->middleware(new CountingStore(failOnDelete: true))->handle(new FixtureInput(), ActionContext::of(LockableEchoAction::class), static fn (InputInterface $input): never => throw new RuntimeException('action failed'));
    }

    public function testUnknownProfileIsALogicError(): void
    {
        $middleware = new LockMiddleware(new LockFactory(new InMemoryStore()), new ArrayContainer([ActionNameKeyBuilder::class => new ActionNameKeyBuilder()]), [], 'short', new RecordingLogger());

        $this->expectException(LogicException::class);
        $middleware->handle(new FixtureInput(), ActionContext::of(LockableEchoAction::class), static fn (InputInterface $input): OutputInterface => new FixtureOutput(''));
    }

    private function middleware(PersistingStoreInterface|LockFactory $store, ?RecordingLogger $logger = null): LockMiddleware
    {
        return new LockMiddleware(
            $store instanceof LockFactory ? $store : new LockFactory($store),
            new ArrayContainer([ActionNameKeyBuilder::class => new ActionNameKeyBuilder()]),
            self::PROFILES,
            'short',
            $logger ?? new RecordingLogger(),
        );
    }
}
