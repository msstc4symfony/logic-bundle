<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Application\Logging\Builder;

use Msstc4Symfony\LogicBundle\Application\Action\InputInterface;
use Override;
use Stringable;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * Logs a public "id" property (string or Stringable, e.g. a Uid). Lowest priority so any
 * application builder wins.
 */
#[AsTaggedItem(priority: -1000)]
final readonly class IdInputBuilder implements InputBuilderInterface
{
    #[Override]
    public function build(InputInterface $input): array
    {
        $id = $this->id($input);

        return $id === null ? [] : ['id' => $id];
    }

    #[Override]
    public function supports(InputInterface $input): bool
    {
        return $this->id($input) !== null;
    }

    private function id(InputInterface $input): ?string
    {
        $id = get_object_vars($input)['id'] ?? null;

        return is_string($id) || $id instanceof Stringable ? (string) $id : null;
    }
}
