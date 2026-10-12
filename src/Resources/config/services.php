<?php

declare(strict_types=1);

use Msstc4Symfony\LogicBundle\Application\Action\ActionNameResolver;
use Msstc4Symfony\LogicBundle\Application\Lock\ActionNameKeyBuilder;
use Msstc4Symfony\LogicBundle\Application\Logging\Builder\IdInputBuilder;
use Msstc4Symfony\LogicBundle\Application\Logging\LoggingMiddleware;
use Msstc4Symfony\LogicBundle\DependencyInjection\CompilerPass\WrapActionsPass;
use Msstc4Symfony\LogicBundle\Framework\Validator\ValidationMiddleware;
use Msstc4Symfony\LogicBundle\Infrastructure\Lock\LockMiddleware;
use Msstc4Symfony\LogicBundle\Presentation\EventListener\LockConflictExceptionListener;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\abstract_arg;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();
    $services->defaults()->autowire()->autoconfigure();

    // Loggers and the lock factory are set by LogicBundle::loadExtension() from the bundle config.
    $services->set(WrapActionsPass::LOGGING_MIDDLEWARE_ID, LoggingMiddleware::class)
        ->arg('$logger', abstract_arg('configured logger'))
    ;

    $services->set(WrapActionsPass::VALIDATION_MIDDLEWARE_ID, ValidationMiddleware::class)
        ->arg('$validator', service('validator'))
    ;

    $services->set(WrapActionsPass::LOCK_MIDDLEWARE_ID, LockMiddleware::class)
        ->arg('$factory', abstract_arg('configured lock factory'))
        ->arg('$keyBuilders', abstract_arg('key builder locator, set by WrapActionsPass'))
        ->arg('$profiles', param(WrapActionsPass::PROFILES_PARAMETER))
        ->arg('$defaultProfile', param(WrapActionsPass::DEFAULT_PROFILE_PARAMETER))
        ->arg('$logger', abstract_arg('configured logger'))
    ;

    $services->set(WrapActionsPass::LOCK_LISTENER_ID, LockConflictExceptionListener::class)
        ->arg('$statusCode', param(WrapActionsPass::CONFLICT_STATUS_PARAMETER))
        ->tag('kernel.event_listener', ['event' => 'kernel.exception', 'priority' => 16])
    ;

    $services->set(ActionNameKeyBuilder::class);
    $services->set(IdInputBuilder::class);
    $services->set(ActionNameResolver::class);
};
