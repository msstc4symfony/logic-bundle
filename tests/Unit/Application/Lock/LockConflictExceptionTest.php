<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Test\Unit\Application\Lock;

use Msstc4Symfony\LogicBundle\Application\Lock\LockConflictException;
use PHPUnit\Framework\TestCase;

final class LockConflictExceptionTest extends TestCase
{
    public function testMessageNamesTheKey(): void
    {
        self::assertSame('The lock "order-1" is held by another call.', (new LockConflictException('order-1'))->getMessage());
    }
}
