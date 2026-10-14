<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Test\Unit;

use Msstc4Symfony\LogicBundle\LogicBundle;
use PHPUnit\Framework\TestCase;

final class LogicBundleTest extends TestCase
{
    public function testExtensionAlias(): void
    {
        self::assertSame('msstc4symfony_logic', (new LogicBundle())->getContainerExtension()?->getAlias());
    }
}
