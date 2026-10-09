<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Test\Unit\Application\Lock;

use Msstc4Symfony\LogicBundle\Application\Lock\ActionNameKeyBuilder;
use Msstc4Symfony\LogicBundle\Application\Pipeline\ActionContext;
use Msstc4Symfony\LogicBundle\Test\Fixtures\EchoAction;
use Msstc4Symfony\LogicBundle\Test\Fixtures\FixtureInput;
use PHPUnit\Framework\TestCase;

final class ActionNameKeyBuilderTest extends TestCase
{
    public function testKeyIsTheActionClass(): void
    {
        self::assertSame(EchoAction::class, new ActionNameKeyBuilder()->build(new FixtureInput(), ActionContext::of(EchoAction::class)));
    }
}
