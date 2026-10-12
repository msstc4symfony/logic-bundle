<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Test\Integration;

use Msstc4Symfony\LogicBundle\Application\Action\ActionNameResolver;
use Msstc4Symfony\LogicBundle\Application\Pipeline\ActionContext;
use Msstc4Symfony\LogicBundle\Test\Fixtures\EchoAction;
use Msstc4Symfony\LogicBundle\Test\Fixtures\ShortActionNameBuilder;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversNothing]
final class ActionNameResolverWiringTest extends TestCase
{
    public function testTheResolverServiceSeesAutoconfiguredBuilders(): void
    {
        $container = TestContainer::compile(register: static function (ContainerBuilder $container): void {
            $container->register(ShortActionNameBuilder::class)->setAutoconfigured(true);
            $container->setAlias('test.resolver', ActionNameResolver::class)->setPublic(true);
        });

        $resolver = $container->get('test.resolver');
        self::assertInstanceOf(ActionNameResolver::class, $resolver);
        self::assertSame('echo', $resolver->resolve(ActionContext::of(EchoAction::class)));
    }
}
