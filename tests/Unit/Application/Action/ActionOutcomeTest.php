<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Test\Unit\Application\Action;

use Msstc4Symfony\LogicBundle\Application\Action\ActionOutcome;
use Msstc4Symfony\LogicBundle\Test\Fixtures\ExpectedFailure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Throwable;

#[CoversClass(ActionOutcome::class)]
final class ActionOutcomeTest extends TestCase
{
    #[DataProvider('cases')]
    public function testClassifiesTheFailure(?Throwable $exception, ActionOutcome $expected): void
    {
        self::assertSame($expected, ActionOutcome::of($exception));
    }

    /**
     * @return iterable<string, array{?Throwable, ActionOutcome}>
     */
    public static function cases(): iterable
    {
        yield 'no exception' => [null, ActionOutcome::Success];
        yield 'expected failure marker' => [new ExpectedFailure(), ActionOutcome::ExpectedFailure];
        yield 'symfony validation' => [new ValidationFailedException('x', new ConstraintViolationList()), ActionOutcome::ExpectedFailure];
        yield 'anything else' => [new RuntimeException(), ActionOutcome::Error];
    }

    public function testValuesAreStableMetricLabels(): void
    {
        self::assertSame(['success', 'expected_failure', 'error'], array_map(static fn (ActionOutcome $o): string => $o->value, ActionOutcome::cases()));
    }
}
