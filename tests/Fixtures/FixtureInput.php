<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Test\Fixtures;

use Msstc4Symfony\LogicBundle\Application\Action\InputInterface;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class FixtureInput implements InputInterface
{
    public function __construct(
        public string $id = 'order-1',
        #[Assert\NotBlank]
        public string $name = 'ok',
    ) {
    }
}
