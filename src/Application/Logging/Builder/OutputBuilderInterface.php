<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Application\Logging\Builder;

use Msstc4Symfony\LogicBundle\Application\Action\OutputInterface;

/**
 * Order builders with #[AsTaggedItem(priority: N)]; the first supporting one wins.
 */
interface OutputBuilderInterface
{
    /**
     * @return array<string, scalar|null>
     */
    public function build(OutputInterface $output): array;

    public function supports(OutputInterface $output): bool;
}
