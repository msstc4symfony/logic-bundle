<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Application\Logging\Builder;

use Msstc4Symfony\LogicBundle\Application\Action\InputInterface;

/**
 * Order builders with #[AsTaggedItem(priority: N)]; the first supporting one wins.
 */
interface InputBuilderInterface
{
    /**
     * @return array<string, scalar|null>
     */
    public function build(InputInterface $input): array;

    public function supports(InputInterface $input): bool;
}
