<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Framework\Validator;

use Msstc4Symfony\LogicBundle\Application\Action\ExpectedFailureInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException as SymfonyValidationFailedException;

final class ValidationFailedException extends SymfonyValidationFailedException implements ExpectedFailureInterface
{
}
