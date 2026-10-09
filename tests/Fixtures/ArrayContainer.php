<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Test\Fixtures;

use Psr\Container\ContainerInterface;
use RuntimeException;

final readonly class ArrayContainer implements ContainerInterface
{
    /**
     * @param array<string, object> $services
     */
    public function __construct(private array $services)
    {
    }

    public function get(string $id): object
    {
        return $this->services[$id] ?? throw new RuntimeException(sprintf('No service "%s".', $id));
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }
}
