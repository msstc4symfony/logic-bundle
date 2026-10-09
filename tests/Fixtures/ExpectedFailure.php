<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Test\Fixtures;

use DomainException;
use Msstc4Symfony\LogicBundle\Application\Action\ExpectedFailureInterface;

final class ExpectedFailure extends DomainException implements ExpectedFailureInterface
{
}
