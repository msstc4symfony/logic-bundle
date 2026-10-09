<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Test\Unit\Application\Lock;

use InvalidArgumentException;
use Msstc4Symfony\LogicBundle\Application\Lock\LockProfile;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class LockProfileTest extends TestCase
{
    public function testFromSpec(): void
    {
        $profile = LockProfile::fromSpec(['ttl' => 5, 'retry_count' => 2, 'retry_delay_ms' => 10]);

        self::assertSame([5, 2, 10], [$profile->ttl, $profile->retryCount, $profile->retryDelayMs]);
    }

    #[TestWith([0, 0, 0, 'ttl'])]
    #[TestWith([1, -1, 0, 'retry_count'])]
    #[TestWith([1, 0, -1, 'retry_delay_ms'])]
    public function testRejectsInvalidValues(int $ttl, int $retryCount, int $retryDelayMs, string $field): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($field);

        new LockProfile($ttl, $retryCount, $retryDelayMs);
    }

    public function testAcceptsTheBoundaries(): void
    {
        $profile = new LockProfile(1, 0, 0);

        self::assertSame(1, $profile->ttl);
    }
}
