<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Application\Action;

/**
 * Implementations declare __invoke with their own Input and Output classes; PHP cannot narrow a
 * parameter of an interface method, so the signature lives in @method.
 *
 * @template-covariant TOutput of OutputInterface
 *
 * @method TOutput __invoke(InputInterface $input)
 */
interface ActionInterface
{
}
