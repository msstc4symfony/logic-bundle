<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\DependencyInjection\CompilerPass;

use InvalidArgumentException;
use Msstc4Symfony\LogicBundle\Application\Action\ActionInterface;
use Msstc4Symfony\LogicBundle\Application\Action\OutputInterface;
use Msstc4Symfony\LogicBundle\Application\Lock\KeyBuilderInterface;
use Msstc4Symfony\LogicBundle\Application\Lock\LockableAction;
use Msstc4Symfony\LogicBundle\Application\Pipeline\ActionPipeline;
use Msstc4Symfony\LogicBundle\Application\Pipeline\Attribute\AsActionMiddleware;
use Override;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 */
final class WrapActionsPass implements CompilerPassInterface
{
    public const ACTION_TAG = 'msstc4symfony_logic.action';

    public const MIDDLEWARE_TAG = 'msstc4symfony_logic.middleware';

    public const LOGGING_MIDDLEWARE_ID = 'msstc4symfony_logic.middleware.logging';

    public const VALIDATION_MIDDLEWARE_ID = 'msstc4symfony_logic.middleware.validation';

    public const LOCK_MIDDLEWARE_ID = 'msstc4symfony_logic.middleware.lock';

    public const LOCK_LISTENER_ID = 'msstc4symfony_logic.lock_conflict_listener';

    public const PROFILES_PARAMETER = 'msstc4symfony_logic.lock.profiles';

    public const DEFAULT_PROFILE_PARAMETER = 'msstc4symfony_logic.lock.default_profile';

    public const LOCK_FACTORY_PARAMETER = 'msstc4symfony_logic.lock.factory';

    public const CONFLICT_STATUS_PARAMETER = 'msstc4symfony_logic.lock.conflict_status_code';

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        $actions = $this->actions($container);

        $keyBuilders = [];
        foreach ($actions as [, $class, $lockable]) {
            if ($lockable !== null) {
                $this->assertLockable($container, $class, $lockable);
                $keyBuilders[$lockable->keyBuilder] = new Reference($lockable->keyBuilder);
            }
        }

        if ($keyBuilders === []) {
            // Without lockable actions a host needs no lock store at all.
            $container->removeDefinition(self::LOCK_MIDDLEWARE_ID);
            $container->removeDefinition(self::LOCK_LISTENER_ID);
        } else {
            $container->getDefinition(self::LOCK_MIDDLEWARE_ID)->setArgument('$keyBuilders', ServiceLocatorTagPass::register($container, $keyBuilders));
            if ($container->getParameter(self::CONFLICT_STATUS_PARAMETER) === null) {
                $container->removeDefinition(self::LOCK_LISTENER_ID);
            }
        }

        $middlewares = $this->middlewares($container);
        foreach ($actions as [$id, $class]) {
            $container->register($id . '.logic_pipeline', ActionPipeline::class)
                ->setDecoratedService($id)
                ->setArguments([new Reference('.inner'), $class, new IteratorArgument($middlewares)])
            ;
        }
    }

    /**
     * @return list<array{string, class-string<ActionInterface<OutputInterface>>, LockableAction|null}>
     */
    private function actions(ContainerBuilder $container): array
    {
        $actions = [];
        foreach (array_keys($container->findTaggedServiceIds(self::ACTION_TAG)) as $id) {
            $definition = $container->getDefinition($id);
            if ($definition->isAbstract() || $definition->getDecoratedService() !== null) {
                continue;
            }

            $class = $container->getParameterBag()->resolveValue($definition->getClass() ?? $id);
            if (!is_string($class) || !is_subclass_of($class, ActionInterface::class)) {
                continue;
            }

            // getReflectionClass() also tracks the file, so editing #[LockableAction] rebuilds the container.
            $attribute = $container->getReflectionClass($class, false)?->getAttributes(LockableAction::class)[0] ?? null;
            $actions[] = [$id, $class, $attribute?->newInstance()];
        }

        return $actions;
    }

    /**
     * @param class-string $class
     */
    private function assertLockable(ContainerBuilder $container, string $class, LockableAction $lockable): void
    {
        $profiles = $container->getParameter(self::PROFILES_PARAMETER);
        $profile = $lockable->profile ?? $container->getParameter(self::DEFAULT_PROFILE_PARAMETER);
        if (!is_array($profiles) || !is_string($profile) || !isset($profiles[$profile])) {
            throw new InvalidArgumentException(sprintf('%s names the unknown lock profile "%s"; define it under msstc4symfony_logic.lock.profiles.', $class, is_string($profile) ? $profile : ''));
        }

        if (!$container->has($lockable->keyBuilder)) {
            throw new InvalidArgumentException(sprintf('The lock key builder "%s" of %s is not a service.', $lockable->keyBuilder, $class));
        }

        $builderClass = $container->getParameterBag()->resolveValue($container->findDefinition($lockable->keyBuilder)->getClass() ?? $lockable->keyBuilder);
        if (!is_string($builderClass) || !is_a($builderClass, KeyBuilderInterface::class, true)) {
            throw new InvalidArgumentException(sprintf('The lock key builder "%s" of %s does not implement %s.', $lockable->keyBuilder, $class, KeyBuilderInterface::class));
        }

        $factory = $container->getParameter(self::LOCK_FACTORY_PARAMETER);
        if (!is_string($factory) || !$container->has($factory)) {
            throw new InvalidArgumentException(sprintf('%s is lockable but the lock factory service "%s" does not exist; enable framework.lock or set msstc4symfony_logic.lock.factory.', $class, is_string($factory) ? $factory : ''));
        }
    }

    /**
     * Highest #[AsActionMiddleware] priority first; equal priorities keep registration order.
     *
     * @return list<Reference>
     */
    private function middlewares(ContainerBuilder $container): array
    {
        $ranked = [];
        foreach (array_keys($container->findTaggedServiceIds(self::MIDDLEWARE_TAG)) as $position => $id) {
            $class = $container->getParameterBag()->resolveValue($container->getDefinition($id)->getClass() ?? $id);
            $reflection = is_string($class) ? $container->getReflectionClass($class, false) : null;
            $attribute = $reflection?->getAttributes(AsActionMiddleware::class)[0] ?? null;
            $ranked[] = [$attribute?->newInstance()->priority ?? 0, $position, new Reference($id)];
        }

        usort($ranked, static fn (array $a, array $b): int => [$b[0], $a[1]] <=> [$a[0], $b[1]]);

        return array_column($ranked, 2);
    }
}
