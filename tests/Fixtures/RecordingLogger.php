<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Test\Fixtures;

use Override;
use Psr\Log\AbstractLogger;
use Stringable;

final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<mixed>}> */
    public array $records = [];

    /**
     * @param array<mixed> $context
     */
    #[Override]
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => is_string($level) ? $level : 'unknown', 'message' => (string) $message, 'context' => $context];
    }

    /**
     * @return list<string> "level message" per record
     */
    public function lines(): array
    {
        return array_map(static fn (array $record): string => $record['level'] . ' ' . $record['message'], $this->records);
    }
}
