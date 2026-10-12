<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Test\Integration;

use Msstc4Symfony\LogicBundle\Application\Action\ActionNameResolver;
use Msstc4Symfony\LogicBundle\Application\Pipeline\ActionContext;
use Msstc4Symfony\LogicBundle\Application\Pipeline\ActionPipeline;
use Msstc4Symfony\LogicBundle\Test\Fixtures\EchoAction;
use Msstc4Symfony\LogicBundle\Test\Fixtures\FixtureInput;
use Msstc4Symfony\LogicBundle\Test\Fixtures\RecordingLogger;
use Msstc4Symfony\LogicBundle\Test\Fixtures\ShortActionNameBuilder;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversNothing]
final class LoggingActionNameTest extends TestCase
{
    public function testLoggingMiddlewareUsesTheNameTheResolverReturns(): void
    {
        $container = TestContainer::compile(register: static function (ContainerBuilder $container): void {
            $container->register(EchoAction::class)->setAutoconfigured(true)->setPublic(true);
            $container->register(ShortActionNameBuilder::class)->setAutoconfigured(true);
            $container->setAlias('test.resolver', ActionNameResolver::class)->setPublic(true);
        });

        $action = $container->get(EchoAction::class);
        self::assertInstanceOf(ActionPipeline::class, $action);
        $action(new FixtureInput());

        $resolver = $container->get('test.resolver');
        self::assertInstanceOf(ActionNameResolver::class, $resolver);
        $name = $resolver->resolve(ActionContext::of(EchoAction::class));
        $logger = $container->get('logger');
        self::assertInstanceOf(RecordingLogger::class, $logger);

        self::assertSame('echo', $name);
        self::assertSame(['info Start application action ' . $name, 'info Finish application action ' . $name], $logger->lines());
    }
}
