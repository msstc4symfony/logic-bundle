<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Framework\Validator;

use Closure;
use Msstc4Symfony\LogicBundle\Application\Action\Attribute\AsAction;
use Msstc4Symfony\LogicBundle\Application\Action\InputInterface;
use Msstc4Symfony\LogicBundle\Application\Action\OutputInterface;
use Msstc4Symfony\LogicBundle\Application\Pipeline\ActionContext;
use Msstc4Symfony\LogicBundle\Application\Pipeline\ActionMiddlewareInterface;
use Msstc4Symfony\LogicBundle\Application\Pipeline\Attribute\AsActionMiddleware;
use Override;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsActionMiddleware(priority: self::PRIORITY)]
final readonly class ValidationMiddleware implements ActionMiddlewareInterface
{
    public const PRIORITY = 200;

    public function __construct(private ValidatorInterface $validator)
    {
    }

    #[Override]
    public function supports(ActionContext $context): bool
    {
        return $context->attribute(AsAction::class)->validation ?? true;
    }

    #[Override]
    public function handle(InputInterface $input, ActionContext $context, Closure $next): OutputInterface
    {
        $violations = $this->validator->validate($input);
        if ($violations->count() > 0) {
            throw new ValidationFailedException($input, $violations);
        }

        return $next($input);
    }
}
