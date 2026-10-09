<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Test\Unit\Application\Action\Attribute;

use Msstc4Symfony\LogicBundle\Application\Action\Attribute\AsAction;
use PHPUnit\Framework\TestCase;

final class AsActionTest extends TestCase
{
    public function testEverythingIsOnByDefault(): void
    {
        $attribute = new AsAction();

        self::assertTrue($attribute->validation);
        self::assertTrue($attribute->logging);
    }
}
