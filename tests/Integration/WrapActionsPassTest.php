<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Test\Integration;

use Closure;
use InvalidArgumentException;
use Msstc4Symfony\LogicBundle\Application\Lock\LockConflictException;
use Msstc4Symfony\LogicBundle\Application\Pipeline\ActionPipeline;
use Msstc4Symfony\LogicBundle\DependencyInjection\CompilerPass\WrapActionsPass;
use Msstc4Symfony\LogicBundle\Framework\Validator\ValidationFailedException;
use Msstc4Symfony\LogicBundle\Test\Fixtures\EchoAction;
use Msstc4Symfony\LogicBundle\Test\Fixtures\FirstNameInputBuilder;
use Msstc4Symfony\LogicBundle\Test\Fixtures\FixtureInput;
use Msstc4Symfony\LogicBundle\Test\Fixtures\FixtureOutput;
use Msstc4Symfony\LogicBundle\Test\Fixtures\InnerProbe;
use Msstc4Symfony\LogicBundle\Test\Fixtures\Journal;
use Msstc4Symfony\LogicBundle\Test\Fixtures\JournalMiddleware;
use Msstc4Symfony\LogicBundle\Test\Fixtures\LockableEchoAction;
use Msstc4Symfony\LogicBundle\Test\Fixtures\MiddleProbe;
use Msstc4Symfony\LogicBundle\Test\Fixtures\OuterProbe;
use Msstc4Symfony\LogicBundle\Test\Fixtures\QuietAction;
use Msstc4Symfony\LogicBundle\Test\Fixtures\RecordingLogger;
use Msstc4Symfony\LogicBundle\Test\Fixtures\SecondNameInputBuilder;
use Msstc4Symfony\LogicBundle\Test\Fixtures\UnknownProfileAction;
use Msstc4Symfony\LogicBundle\Test\Fixtures\UnregisteredKeyBuilderAction;
use Msstc4Symfony\LogicBundle\Test\Fixtures\WrongKeyBuilderAction;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Lock\LockFactory;

final class WrapActionsPassTest extends TestCase
{
    private const NO_RETRY = ['lock' => ['profiles' => ['short' => ['ttl' => 5, 'retry_count' => 0]]]];

    public function testWrapsAnAutoconfiguredActionWithoutConfiguration(): void
    {
        $container = TestContainer::compile(register: self::actions(EchoAction::class));

        $action = $container->get(EchoAction::class);
        self::assertInstanceOf(ActionPipeline::class, $action);
        self::assertEquals(new FixtureOutput('hi'), $action(new FixtureInput(name: 'hi')));
        self::assertSame(['info Start application action ' . EchoAction::class, 'info Finish application action ' . EchoAction::class], $this->logger($container)->lines());
    }

    public function testOrderIsLoggingValidationLockAction(): void
    {
        $container = TestContainer::compile(self::NO_RETRY, static function (ContainerBuilder $container): void {
            self::actions(LockableEchoAction::class)($container);
            $container->register(Journal::class)->setPublic(true);
            foreach ([OuterProbe::class, MiddleProbe::class, InnerProbe::class] as $probe) {
                $container->register($probe)->setAutowired(true)->setAutoconfigured(true);
            }
        });
        $journal = $container->get(Journal::class);
        self::assertInstanceOf(Journal::class, $journal);
        $action = $container->get(LockableEchoAction::class);
        self::assertInstanceOf(ActionPipeline::class, $action);

        $action(new FixtureInput());
        self::assertSame(['outer', 'middle', 'inner lock-held'], $journal->entries, 'logging 300 > outer 250 > validation 200 > middle 150 > lock 100 > inner 50');

        $journal->entries = [];
        try {
            $action(new FixtureInput(name: ''));
            self::fail('Invalid input must be rejected.');
        } catch (ValidationFailedException) {
        }

        self::assertSame(['outer'], $journal->entries, 'validation stops before the lock is taken');
        self::assertSame('warning Fail application action ' . LockableEchoAction::class, $this->logger($container)->lines()[3] ?? null, 'logging wraps validation');
    }

    public function testAsActionSwitchesValidationAndLoggingOff(): void
    {
        $container = TestContainer::compile(register: self::actions(QuietAction::class));
        $action = $container->get(QuietAction::class);
        self::assertInstanceOf(ActionPipeline::class, $action);

        self::assertEquals(new FixtureOutput(''), $action(new FixtureInput(name: '')));
        self::assertSame([], $this->logger($container)->records);
    }

    public function testHeldLockRejectsTheCall(): void
    {
        $container = TestContainer::compile(self::NO_RETRY, self::actions(LockableEchoAction::class));
        $factory = $container->get('lock.factory');
        self::assertInstanceOf(LockFactory::class, $factory);
        $held = $factory->createLock(LockableEchoAction::class);
        self::assertTrue($held->acquire());
        $action = $container->get(LockableEchoAction::class);
        self::assertInstanceOf(ActionPipeline::class, $action);

        $this->expectException(LockConflictException::class);
        $action(new FixtureInput());
    }

    public function testInputBuildersWithTheSamePriorityAreAllKept(): void
    {
        $container = TestContainer::compile(register: static function (ContainerBuilder $container): void {
            self::actions(EchoAction::class)($container);
            $container->register(FirstNameInputBuilder::class)->setAutoconfigured(true);
            $container->register(SecondNameInputBuilder::class)->setAutoconfigured(true);
        });
        $action = $container->get(EchoAction::class);
        self::assertInstanceOf(ActionPipeline::class, $action);

        $action(new FixtureInput(name: 'second'));

        self::assertSame(['input' => ['second' => true]], $this->logger($container)->records[0]['context']);
    }

    public function testUnknownProfileFailsCompilation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/UnknownProfileAction.*"missing"/');

        TestContainer::compile(register: self::actions(UnknownProfileAction::class));
    }

    public function testKeyBuilderMustBeAService(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not a service');

        TestContainer::compile(register: self::actions(UnregisteredKeyBuilderAction::class));
    }

    public function testKeyBuilderMustImplementTheInterface(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not implement');

        TestContainer::compile(register: static function (ContainerBuilder $container): void {
            self::actions(WrongKeyBuilderAction::class)($container);
            $container->register(Journal::class);
        });
    }

    public function testLockableActionNeedsALockFactory(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('enable framework.lock');

        TestContainer::compile(register: self::actions(LockableEchoAction::class), withLockFactory: false);
    }

    public function testWithoutLockableActionsNoLockServicesAreNeeded(): void
    {
        $container = TestContainer::compile(register: self::actions(EchoAction::class), withLockFactory: false);

        self::assertFalse($container->has(WrapActionsPass::LOCK_MIDDLEWARE_ID));
        self::assertFalse($container->has(WrapActionsPass::LOCK_LISTENER_ID));
        self::assertTrue($container->has(WrapActionsPass::LOGGING_MIDDLEWARE_ID));
    }

    public function testNullConflictStatusRemovesOnlyTheListener(): void
    {
        $container = TestContainer::compile(['lock' => ['conflict_status_code' => null]], self::actions(LockableEchoAction::class));

        self::assertFalse($container->has(WrapActionsPass::LOCK_LISTENER_ID));
        self::assertTrue($container->has(WrapActionsPass::LOCK_MIDDLEWARE_ID));
    }

    public function testActionWithoutAutoconfigureIsNotWrapped(): void
    {
        $container = TestContainer::compile(register: static function (ContainerBuilder $container): void {
            $container->register(EchoAction::class)->setPublic(true);
        });

        self::assertInstanceOf(EchoAction::class, $container->get(EchoAction::class));
    }

    public function testSameClassUnderTwoIdsGetsTwoPipelines(): void
    {
        $container = TestContainer::compile(register: static function (ContainerBuilder $container): void {
            $container->register('first_echo', EchoAction::class)->setAutoconfigured(true)->setPublic(true);
            $container->register('second_echo', EchoAction::class)->setAutoconfigured(true)->setPublic(true);
        });

        $first = $container->get('first_echo');
        $second = $container->get('second_echo');
        self::assertInstanceOf(ActionPipeline::class, $first);
        self::assertInstanceOf(ActionPipeline::class, $second);
        self::assertNotSame($first, $second);
        self::assertEquals(new FixtureOutput('a'), $first(new FixtureInput(name: 'a')));
    }

    public function testAbstractActionDefinitionIsSkipped(): void
    {
        $container = TestContainer::compile(register: static function (ContainerBuilder $container): void {
            $container->register('abstract_echo', EchoAction::class)->setAutoconfigured(true)->setAbstract(true);
            self::actions(EchoAction::class)($container);
        });

        self::assertFalse($container->has('abstract_echo.logic_pipeline'));
        self::assertInstanceOf(ActionPipeline::class, $container->get(EchoAction::class));
    }

    public function testDecoratorOfAnActionIsNotWrappedAgain(): void
    {
        $container = TestContainer::compile(register: static function (ContainerBuilder $container): void {
            self::actions(EchoAction::class)($container);
            $container->register('echo_decorator', QuietAction::class)->setAutoconfigured(true)->setDecoratedService(EchoAction::class, 'echo_decorator.inner', 10);
        });

        self::assertFalse($container->has('echo_decorator.logic_pipeline'));
        self::assertTrue($container->has(EchoAction::class . '.logic_pipeline'));
    }

    public function testCustomMiddlewareWithoutPriorityRunsInsideTheBuiltInsInRegistrationOrder(): void
    {
        $container = TestContainer::compile(register: static function (ContainerBuilder $container): void {
            self::actions(EchoAction::class)($container);
            $container->register(Journal::class)->setPublic(true);
            foreach (['first', 'second'] as $name) {
                $container->register('journal_middleware.' . $name, JournalMiddleware::class)
                    ->setArguments([$name, new Reference(Journal::class)])
                    ->setAutoconfigured(true)
                ;
            }
        });
        $action = $container->get(EchoAction::class);
        self::assertInstanceOf(ActionPipeline::class, $action);
        $journal = $container->get(Journal::class);
        self::assertInstanceOf(Journal::class, $journal);

        $action(new FixtureInput());

        self::assertSame(['>first', '>second', '<second', '<first'], $journal->entries);
    }

    /**
     * @param class-string ...$classes
     *
     * @return Closure(ContainerBuilder): void
     */
    private static function actions(string ...$classes): Closure
    {
        return static function (ContainerBuilder $container) use ($classes): void {
            foreach ($classes as $class) {
                $container->register($class)->setAutoconfigured(true)->setPublic(true);
            }
        };
    }

    private function logger(ContainerBuilder $container): RecordingLogger
    {
        $logger = $container->get('logger');
        self::assertInstanceOf(RecordingLogger::class, $logger);

        return $logger;
    }
}
