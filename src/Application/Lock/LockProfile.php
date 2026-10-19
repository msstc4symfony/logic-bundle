<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Application\Lock;

use InvalidArgumentException;

/**
 * Lock profile as configured under msstc4symfony_logic.lock.profiles.
 *
 * @phpstan-type LockProfileSpec array{ttl: positive-int, retry_count: non-negative-int, retry_delay_ms: non-negative-int}
 *
 * @internal
 */
final readonly class LockProfile
{
    /** @var positive-int */
    public int $ttl;

    /** @var non-negative-int */
    public int $retryCount;

    /** @var non-negative-int */
    public int $retryDelayMs;

    public function __construct(int $ttl, int $retryCount, int $retryDelayMs)
    {
        if ($ttl < 1) {
            throw new InvalidArgumentException(sprintf('A lock profile ttl must be at least 1 second, got %d.', $ttl));
        }

        if ($retryCount < 0) {
            throw new InvalidArgumentException(sprintf('A lock profile retry_count must not be negative, got %d.', $retryCount));
        }

        if ($retryDelayMs < 0) {
            throw new InvalidArgumentException(sprintf('A lock profile retry_delay_ms must not be negative, got %d.', $retryDelayMs));
        }

        $this->ttl = $ttl;
        $this->retryCount = $retryCount;
        $this->retryDelayMs = $retryDelayMs;
    }

    /**
     * @param LockProfileSpec $spec
     */
    public static function fromSpec(array $spec): self
    {
        return new self($spec['ttl'], $spec['retry_count'], $spec['retry_delay_ms']);
    }
}
