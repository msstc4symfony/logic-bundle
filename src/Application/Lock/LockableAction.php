<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Application\Lock;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class LockableAction
{
    /**
     * @param class-string<KeyBuilderInterface> $keyBuilder service id; the class name for autowired builders
     * @param non-empty-string|null $profile null takes msstc4symfony_logic.lock.default_profile
     */
    public function __construct(
        public string $keyBuilder = ActionNameKeyBuilder::class,
        public ?string $profile = null,
    ) {
    }
}
