<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Test\Fixtures;

use RuntimeException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;

/**
 * Records save attempts and the TTL Lock::refresh() sets; can refuse every save or fail every delete or exists check.
 */
final class CountingStore implements PersistingStoreInterface
{
    public int $saves = 0;

    public ?float $lastTtl = null;

    /** @var array<string, true> */
    private array $held = [];

    public function __construct(
        private readonly bool $conflictOnSave = false,
        private readonly bool $failOnDelete = false,
        private readonly bool $failOnExists = false,
    ) {
    }

    public function save(Key $key): void
    {
        $this->saves++;
        if ($this->conflictOnSave) {
            throw new LockConflictedException();
        }

        $this->held[(string) $key] = true;
    }

    public function delete(Key $key): void
    {
        if ($this->failOnDelete) {
            throw new RuntimeException('store down');
        }

        unset($this->held[(string) $key]);
    }

    public function exists(Key $key): bool
    {
        if ($this->failOnExists) {
            throw new RuntimeException('store down');
        }

        return isset($this->held[(string) $key]);
    }

    public function putOffExpiration(Key $key, float $ttl): void
    {
        $this->lastTtl = $ttl;
    }
}
