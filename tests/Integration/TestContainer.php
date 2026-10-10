<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Test\Integration;

use Closure;
use LogicException;
use Msstc4Symfony\LogicBundle\LogicBundle;
use Msstc4Symfony\LogicBundle\Test\Fixtures\RecordingLogger;
use Msstc4Symfony\LogicBundle\Test\Fixtures\TestValidator;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class TestContainer
{
    /**
     * @param array<string, mixed> $config msstc4symfony_logic configuration
     * @param (Closure(ContainerBuilder): void)|null $register registers the test's own services
     */
    public static function compile(array $config = [], ?Closure $register = null, bool $withLockFactory = true): ContainerBuilder
    {
        $container = new ContainerBuilder(new ParameterBag([
            'kernel.environment' => 'test',
            'kernel.debug' => false,
            // Symfony 7.4's PhpFileLoader reads it; 8.x does not.
            'kernel.build_dir' => sys_get_temp_dir(),
        ]));
        $bundle = new LogicBundle();
        $extension = $bundle->getContainerExtension() ?? throw new LogicException('LogicBundle has no extension.');
        $container->registerExtension($extension);
        $container->loadFromExtension($extension->getAlias(), $config);

        $bundle->build($container);

        $container->register('logger', RecordingLogger::class)->setPublic(true);
        $container->register('validator', ValidatorInterface::class)->setFactory([TestValidator::class, 'create']);
        $container->register('lock.store', InMemoryStore::class);
        if ($withLockFactory) {
            $container->register('lock.factory', LockFactory::class)->setArguments([new Reference('lock.store')])->setPublic(true);
            $container->setAlias(LockFactory::class, 'lock.factory');
        }

        $register?->__invoke($container);

        // The bundle's own services are private; keep them inspectable after compilation.
        $container->addCompilerPass(new MakeBundleServicesPublicPass(), PassConfig::TYPE_BEFORE_REMOVING);
        $container->compile(true);

        return $container;
    }
}
