<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle;

use Msstc4Symfony\LogicBundle\Application\Action\ActionInterface;
use Msstc4Symfony\LogicBundle\Application\Lock\LockProfile;
use Msstc4Symfony\LogicBundle\Application\Logging\Builder\ActionNameBuilderInterface;
use Msstc4Symfony\LogicBundle\Application\Logging\Builder\InputBuilderInterface;
use Msstc4Symfony\LogicBundle\Application\Logging\Builder\OutputBuilderInterface;
use Msstc4Symfony\LogicBundle\Application\Pipeline\ActionMiddlewareInterface;
use Msstc4Symfony\LogicBundle\DependencyInjection\CompilerPass\WrapActionsPass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * @phpstan-import-type LockProfileSpec from LockProfile
 *
 * @phpstan-type LogicConfig array{
 *     logging: array{logger: non-empty-string},
 *     lock: array{factory: non-empty-string, default_profile: non-empty-string, conflict_status_code: int|null, profiles: array<string, LockProfileSpec>},
 * }
 */
final class LogicBundle extends AbstractBundle
{
    /** @var array<string, LockProfileSpec> */
    public const BUILT_IN_PROFILES = [
        'short' => ['ttl' => 5, 'retry_count' => 5, 'retry_delay_ms' => 100],
        'long' => ['ttl' => 300, 'retry_count' => 0, 'retry_delay_ms' => 0],
    ];

    protected string $extensionAlias = 'msstc4symfony_logic';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('logging')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('logger')->cannotBeEmpty()->defaultValue('logger')->end()
                    ->end()
                ->end()
                ->arrayNode('lock')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('factory')->cannotBeEmpty()->defaultValue('lock.factory')->end()
                        ->scalarNode('default_profile')->cannotBeEmpty()->defaultValue('short')->end()
                        ->scalarNode('conflict_status_code')
                            ->defaultValue(409)
                            ->validate()
                                ->ifTrue(static fn (mixed $code): bool => $code !== null && (!is_int($code) || $code < 400 || $code > 599))
                                ->thenInvalid('conflict_status_code must be null or an HTTP error status (400-599), got %s.')
                            ->end()
                        ->end()
                        ->arrayNode('profiles')
                            ->info('Merged with the built-in "short" and "long" profiles; a built-in name overrides it.')
                            ->useAttributeAsKey('name')
                            ->arrayPrototype()
                                ->children()
                                    ->integerNode('ttl')->isRequired()->min(1)->end()
                                    ->integerNode('retry_count')->defaultValue(0)->min(0)->end()
                                    ->integerNode('retry_delay_ms')->defaultValue(0)->min(0)->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    /**
     * Reason for the ignore: Symfony declares $config as a plain array; configure() guarantees the shape.
     *
     * @param LogicConfig $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void // @phpstan-ignore method.childParameterType, method.childParameterType
    {
        $lock = $config['lock'];
        $profiles = [...self::BUILT_IN_PROFILES, ...$lock['profiles']];
        if (!isset($profiles[$lock['default_profile']])) {
            throw new InvalidConfigurationException(sprintf('msstc4symfony_logic.lock.default_profile "%s" is not a defined profile (%s).', $lock['default_profile'], implode(', ', array_keys($profiles))));
        }

        $builder->setParameter(WrapActionsPass::PROFILES_PARAMETER, $profiles);
        $builder->setParameter(WrapActionsPass::DEFAULT_PROFILE_PARAMETER, $lock['default_profile']);
        $builder->setParameter(WrapActionsPass::LOCK_FACTORY_PARAMETER, $lock['factory']);
        $builder->setParameter(WrapActionsPass::CONFLICT_STATUS_PARAMETER, $lock['conflict_status_code']);

        $container->import(__DIR__ . '/Resources/config/services.php');

        $logger = new Reference($config['logging']['logger']);
        $builder->getDefinition(WrapActionsPass::LOGGING_MIDDLEWARE_ID)->setArgument('$logger', $logger);
        $builder->getDefinition(WrapActionsPass::LOCK_MIDDLEWARE_ID)
            ->setArgument('$factory', new Reference($lock['factory']))
            ->setArgument('$logger', $logger)
        ;

        $builder->registerForAutoconfiguration(ActionInterface::class)->addTag(WrapActionsPass::ACTION_TAG);
        $builder->registerForAutoconfiguration(ActionMiddlewareInterface::class)->addTag(WrapActionsPass::MIDDLEWARE_TAG);
        // Tag names match the #[AutowireIterator] arguments of LoggingMiddleware.
        foreach ([ActionNameBuilderInterface::class, InputBuilderInterface::class, OutputBuilderInterface::class] as $builderInterface) {
            $builder->registerForAutoconfiguration($builderInterface)->addTag($builderInterface);
        }
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new WrapActionsPass());
    }
}
