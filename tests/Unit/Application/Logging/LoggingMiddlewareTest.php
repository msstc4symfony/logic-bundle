<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Test\Unit\Application\Logging;

use Closure;
use Msstc4Symfony\LogicBundle\Application\Action\InputInterface;
use Msstc4Symfony\LogicBundle\Application\Action\OutputInterface;
use Msstc4Symfony\LogicBundle\Application\Logging\Builder\ActionNameBuilderInterface;
use Msstc4Symfony\LogicBundle\Application\Logging\Builder\IdInputBuilder;
use Msstc4Symfony\LogicBundle\Application\Logging\Builder\InputBuilderInterface;
use Msstc4Symfony\LogicBundle\Application\Logging\Builder\OutputBuilderInterface;
use Msstc4Symfony\LogicBundle\Application\Logging\LoggingMiddleware;
use Msstc4Symfony\LogicBundle\Application\Pipeline\ActionContext;
use Msstc4Symfony\LogicBundle\Test\Fixtures\EchoAction;
use Msstc4Symfony\LogicBundle\Test\Fixtures\ExpectedFailure;
use Msstc4Symfony\LogicBundle\Test\Fixtures\FixtureInput;
use Msstc4Symfony\LogicBundle\Test\Fixtures\FixtureOutput;
use Msstc4Symfony\LogicBundle\Test\Fixtures\QuietAction;
use Msstc4Symfony\LogicBundle\Test\Fixtures\RecordingLogger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Throwable;

final class LoggingMiddlewareTest extends TestCase
{
    public function testLogsStartAndFinishWithInputAndOutput(): void
    {
        $logger = new RecordingLogger();
        $outputBuilder = new class implements OutputBuilderInterface {
            public function build(OutputInterface $output): array
            {
                return ['echo' => $output instanceof FixtureOutput ? $output->echo : null];
            }

            public function supports(OutputInterface $output): bool
            {
                return true;
            }
        };
        $middleware = new LoggingMiddleware($logger, [], [new IdInputBuilder()], [$outputBuilder]);

        $middleware->handle(new FixtureInput('order-7', 'hi'), ActionContext::of(EchoAction::class), $this->echo());

        self::assertSame([
            'info Start application action ' . EchoAction::class,
            'info Finish application action ' . EchoAction::class,
        ], $logger->lines());
        self::assertSame(['input' => ['id' => 'order-7']], $logger->records[0]['context']);
        self::assertSame(['input' => ['id' => 'order-7'], 'output' => ['echo' => 'hi']], $logger->records[1]['context']);
    }

    public function testFirstSupportingInputAndOutputBuildersWin(): void
    {
        $logger = new RecordingLogger();
        $middleware = new LoggingMiddleware(
            $logger,
            [],
            [$this->inputBuilder('first'), $this->inputBuilder('second')],
            [$this->outputBuilder('first'), $this->outputBuilder('second')],
        );

        $middleware->handle(new FixtureInput(), ActionContext::of(EchoAction::class), $this->echo());

        self::assertSame(['input' => ['builder' => 'first'], 'output' => ['builder' => 'first']], $logger->records[1]['context']);
    }

    public function testFailingBuildersDoNotChangeTheOutcome(): void
    {
        $logger = new RecordingLogger();
        $broken = new class implements InputBuilderInterface, OutputBuilderInterface {
            public function build(InputInterface|OutputInterface $value): array
            {
                throw new RuntimeException('builder bug');
            }

            public function supports(InputInterface|OutputInterface $value): bool
            {
                return true;
            }
        };

        $output = (new LoggingMiddleware($logger, [], [$broken], [$broken]))->handle(new FixtureInput(name: 'x'), ActionContext::of(EchoAction::class), $this->echo());

        self::assertEquals(new FixtureOutput('x'), $output);
        self::assertSame([
            'warning Could not build the action log context',
            'info Start application action ' . EchoAction::class,
            'warning Could not build the action log context',
            'info Finish application action ' . EchoAction::class,
        ], $logger->lines());
        self::assertInstanceOf(RuntimeException::class, $logger->records[0]['context']['exception'] ?? null);
    }

    public function testFailingBuilderIsSkippedForTheNextOneAndNamedInTheWarning(): void
    {
        $logger = new RecordingLogger();
        $broken = new class implements InputBuilderInterface {
            public function build(InputInterface $input): array
            {
                throw new RuntimeException('builder bug');
            }

            public function supports(InputInterface $input): bool
            {
                return true;
            }
        };

        (new LoggingMiddleware($logger, [], [$broken, $this->inputBuilder('next')]))->handle(new FixtureInput(), ActionContext::of(EchoAction::class), $this->echo());

        self::assertSame(['action' => EchoAction::class, 'builder' => $broken::class], array_intersect_key($logger->records[0]['context'], ['action' => 0, 'builder' => 0]));
        self::assertSame(['input' => ['builder' => 'next']], $logger->records[1]['context']);
    }

    public function testFailureContextKeepsTheInput(): void
    {
        $logger = new RecordingLogger();
        $failure = new RuntimeException('down');

        try {
            (new LoggingMiddleware($logger, [], [new IdInputBuilder()]))->handle(new FixtureInput('order-9'), ActionContext::of(EchoAction::class), static fn (InputInterface $input): never => throw $failure);
        } catch (RuntimeException) {
        }

        self::assertSame(['input' => ['id' => 'order-9'], 'exception' => $failure], $logger->records[1]['context']);
    }

    public function testUsesTheFirstSupportingNameBuilder(): void
    {
        $logger = new RecordingLogger();
        $short = new class implements ActionNameBuilderInterface {
            public function build(string $actionClass): string
            {
                return 'echo';
            }

            public function supports(string $actionClass): bool
            {
                return true;
            }
        };

        (new LoggingMiddleware($logger, [$short]))->handle(new FixtureInput(), ActionContext::of(EchoAction::class), $this->echo());

        self::assertSame('Start application action echo', $logger->records[0]['message']);
    }

    /**
     * @return iterable<string, array{Throwable, string}>
     */
    public static function failures(): iterable
    {
        yield 'expected failure' => [new ExpectedFailure('declined'), 'warning'];
        yield 'symfony validation' => [new ValidationFailedException(null, new ConstraintViolationList()), 'warning'];
        yield 'service fault' => [new RuntimeException('down'), 'error'];
    }

    #[DataProvider('failures')]
    public function testLogsFailureAtItsLevelAndRethrows(Throwable $failure, string $level): void
    {
        $logger = new RecordingLogger();
        $middleware = new LoggingMiddleware($logger);

        try {
            $middleware->handle(new FixtureInput(), ActionContext::of(EchoAction::class), static fn (InputInterface $input): never => throw $failure);
            self::fail('The failure must be rethrown.');
        } catch (Throwable $caught) {
            self::assertSame($failure, $caught);
        }

        self::assertSame($level . ' Fail application action ' . EchoAction::class, $logger->lines()[1]);
        self::assertSame($failure, $logger->records[1]['context']['exception']);
    }

    public function testDisabledByAsAction(): void
    {
        $middleware = new LoggingMiddleware(new RecordingLogger());

        self::assertFalse($middleware->supports(ActionContext::of(QuietAction::class)));
        self::assertTrue($middleware->supports(ActionContext::of(EchoAction::class)));
    }

    private function inputBuilder(string $name): InputBuilderInterface
    {
        return new class($name) implements InputBuilderInterface {
            public function __construct(private readonly string $name)
            {
            }

            public function build(InputInterface $input): array
            {
                return ['builder' => $this->name];
            }

            public function supports(InputInterface $input): bool
            {
                return true;
            }
        };
    }

    private function outputBuilder(string $name): OutputBuilderInterface
    {
        return new class($name) implements OutputBuilderInterface {
            public function __construct(private readonly string $name)
            {
            }

            public function build(OutputInterface $output): array
            {
                return ['builder' => $this->name];
            }

            public function supports(OutputInterface $output): bool
            {
                return true;
            }
        };
    }

    /**
     * @return Closure(InputInterface): OutputInterface
     */
    private function echo(): Closure
    {
        return static fn (InputInterface $input): OutputInterface => new FixtureOutput($input instanceof FixtureInput ? $input->name : '');
    }
}
