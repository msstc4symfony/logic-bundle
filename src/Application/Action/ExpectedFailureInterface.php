<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Application\Action;

use Throwable;

/**
 * A refusal the caller can provoke (not found, invalid input, business rule declined). Logged as
 * WARNING so ERROR stays reserved for service faults.
 */
interface ExpectedFailureInterface extends Throwable
{
}
