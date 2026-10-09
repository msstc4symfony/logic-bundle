<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Test\Integration;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class MakeBundleServicesPublicPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            if (str_starts_with($id, 'msstc4symfony_logic.') || str_ends_with($id, '.logic_pipeline')) {
                $definition->setPublic(true);
            }
        }
    }
}
