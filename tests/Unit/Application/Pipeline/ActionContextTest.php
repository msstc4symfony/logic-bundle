<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Test\Unit\Application\Pipeline;

use Msstc4Symfony\LogicBundle\Application\Action\Attribute\AsAction;
use Msstc4Symfony\LogicBundle\Application\Pipeline\ActionContext;
use Msstc4Symfony\LogicBundle\Test\Fixtures\EchoAction;
use Msstc4Symfony\LogicBundle\Test\Fixtures\FixtureMarker;
use Msstc4Symfony\LogicBundle\Test\Fixtures\QuietAction;
use PHPUnit\Framework\TestCase;

final class ActionContextTest extends TestCase
{
    public function testReadsClassAttributes(): void
    {
        $context = ActionContext::of(QuietAction::class);

        self::assertSame(QuietAction::class, $context->actionClass);
        self::assertFalse($context->attribute(AsAction::class)?->validation);
    }

    public function testRepeatedAttributeYieldsTheFirstInstance(): void
    {
        self::assertSame('first', ActionContext::of(QuietAction::class)->attribute(FixtureMarker::class)?->value);
    }

    public function testMissingAttributeIsNull(): void
    {
        self::assertNull(ActionContext::of(EchoAction::class)->attribute(AsAction::class));
    }
}
