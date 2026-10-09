<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Test\Unit\Framework\Validator;

use Msstc4Symfony\LogicBundle\Application\Action\ExpectedFailureInterface;
use Msstc4Symfony\LogicBundle\Application\Action\InputInterface;
use Msstc4Symfony\LogicBundle\Application\Action\OutputInterface;
use Msstc4Symfony\LogicBundle\Application\Pipeline\ActionContext;
use Msstc4Symfony\LogicBundle\Framework\Validator\ValidationFailedException;
use Msstc4Symfony\LogicBundle\Framework\Validator\ValidationMiddleware;
use Msstc4Symfony\LogicBundle\Test\Fixtures\EchoAction;
use Msstc4Symfony\LogicBundle\Test\Fixtures\FixtureInput;
use Msstc4Symfony\LogicBundle\Test\Fixtures\FixtureOutput;
use Msstc4Symfony\LogicBundle\Test\Fixtures\QuietAction;
use Msstc4Symfony\LogicBundle\Test\Fixtures\TestValidator;
use PHPUnit\Framework\TestCase;

final class ValidationMiddlewareTest extends TestCase
{
    public function testValidInputReachesTheAction(): void
    {
        $output = new ValidationMiddleware(TestValidator::create())->handle(
            new FixtureInput(name: 'ok'),
            ActionContext::of(EchoAction::class),
            static fn (InputInterface $input): OutputInterface => new FixtureOutput('reached'),
        );

        self::assertEquals(new FixtureOutput('reached'), $output);
    }

    public function testInvalidInputStopsBeforeTheAction(): void
    {
        $input = new FixtureInput(name: '');

        try {
            new ValidationMiddleware(TestValidator::create())->handle(
                $input,
                ActionContext::of(EchoAction::class),
                static fn (InputInterface $input): never => self::fail('The action must not run.'),
            );
            self::fail('ValidationFailedException expected.');
        } catch (ValidationFailedException $exception) {
            self::assertInstanceOf(ExpectedFailureInterface::class, $exception);
            self::assertSame($input, $exception->getValue());
            self::assertSame('name', $exception->getViolations()->get(0)->getPropertyPath());
        }
    }

    public function testDisabledByAsAction(): void
    {
        $middleware = new ValidationMiddleware(TestValidator::create());

        self::assertFalse($middleware->supports(ActionContext::of(QuietAction::class)));
        self::assertTrue($middleware->supports(ActionContext::of(EchoAction::class)));
    }
}
