<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Test\Unit\Application\Logging\Builder;

use Msstc4Symfony\LogicBundle\Application\Action\InputInterface;
use Msstc4Symfony\LogicBundle\Application\Logging\Builder\IdInputBuilder;
use Msstc4Symfony\LogicBundle\Test\Fixtures\FixtureInput;
use Override;
use PHPUnit\Framework\TestCase;
use Stringable;

final class IdInputBuilderTest extends TestCase
{
    public function testStringId(): void
    {
        $builder = new IdInputBuilder();

        self::assertTrue($builder->supports(new FixtureInput('a-1')));
        self::assertSame(['id' => 'a-1'], $builder->build(new FixtureInput('a-1')));
    }

    public function testStringableId(): void
    {
        $input = new class implements InputInterface {
            public Stringable $id;

            public function __construct()
            {
                $this->id = new class implements Stringable {
                    #[Override]
                    public function __toString(): string
                    {
                        return 'uid-1';
                    }
                };
            }
        };

        self::assertSame(['id' => 'uid-1'], (new IdInputBuilder())->build($input));
    }

    public function testInputWithoutPublicIdIsNotSupported(): void
    {
        $input = new class implements InputInterface {
            public function __construct(private readonly string $id = 'hidden')
            {
            }

            public function id(): string
            {
                return $this->id;
            }
        };

        self::assertFalse((new IdInputBuilder())->supports($input));
        self::assertSame([], (new IdInputBuilder())->build($input));
    }
}
