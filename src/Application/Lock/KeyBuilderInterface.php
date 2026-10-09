<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Application\Lock;

use Msstc4Symfony\LogicBundle\Application\Action\InputInterface;
use Msstc4Symfony\LogicBundle\Application\Pipeline\ActionContext;

interface KeyBuilderInterface
{
    /**
     * @return non-empty-string
     *
     * @throws KeyBuildingFailedException the input does not carry what the key needs
     */
    public function build(InputInterface $input, ActionContext $context): string;
}
