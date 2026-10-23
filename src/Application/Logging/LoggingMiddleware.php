<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Application\Logging;

use Closure;
use Msstc4Symfony\LogicBundle\Application\Action\ActionNameResolver;
use Msstc4Symfony\LogicBundle\Application\Action\ActionOutcome;
use Msstc4Symfony\LogicBundle\Application\Action\Attribute\AsAction;
use Msstc4Symfony\LogicBundle\Application\Action\InputInterface;
use Msstc4Symfony\LogicBundle\Application\Action\OutputInterface;
use Msstc4Symfony\LogicBundle\Application\Logging\Builder\ActionNameBuilderInterface;
use Msstc4Symfony\LogicBundle\Application\Logging\Builder\InputBuilderInterface;
use Msstc4Symfony\LogicBundle\Application\Logging\Builder\OutputBuilderInterface;
use Msstc4Symfony\LogicBundle\Application\Pipeline\ActionContext;
use Msstc4Symfony\LogicBundle\Application\Pipeline\ActionMiddlewareInterface;
use Msstc4Symfony\LogicBundle\Application\Pipeline\Attribute\AsActionMiddleware;
use Override;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Throwable;

/**
 * @internal
 */
#[AsActionMiddleware(priority: self::PRIORITY)]
final readonly class LoggingMiddleware implements ActionMiddlewareInterface
{
    public const PRIORITY = 300;

    private ActionNameResolver $names;

    /**
     * @param iterable<array-key, ActionNameBuilderInterface> $actionNameBuilders
     * @param iterable<array-key, InputBuilderInterface> $inputBuilders
     * @param iterable<array-key, OutputBuilderInterface> $outputBuilders
     */
    public function __construct(
        private LoggerInterface $logger,
        #[AutowireIterator(ActionNameBuilderInterface::class)]
        iterable $actionNameBuilders = [],
        #[AutowireIterator(InputBuilderInterface::class)]
        private iterable $inputBuilders = [],
        #[AutowireIterator(OutputBuilderInterface::class)]
        private iterable $outputBuilders = [],
    ) {
        $this->names = new ActionNameResolver($actionNameBuilders);
    }

    #[Override]
    public function supports(ActionContext $context): bool
    {
        return $context->attribute(AsAction::class)->logging ?? true;
    }

    #[Override]
    public function handle(InputInterface $input, ActionContext $context, Closure $next): OutputInterface
    {
        $name = $this->names->resolve($context);
        $logContext = [];
        $inputContext = $this->inputContext($input, $name);
        if ($inputContext !== null) {
            $logContext['input'] = $inputContext;
        }

        $this->logger->info('Start application action ' . $name, $logContext);

        try {
            $output = $next($input);
        } catch (Throwable $exception) {
            $outcome = ActionOutcome::of($exception);
            $level = $outcome === ActionOutcome::ExpectedFailure ? LogLevel::WARNING : LogLevel::ERROR;
            $this->logger->log($level, 'Fail application action ' . $name, [...$logContext, 'exception' => $exception]);

            throw $exception;
        }

        $outputContext = $this->outputContext($output, $name);
        if ($outputContext !== null) {
            $logContext['output'] = $outputContext;
        }

        $this->logger->info('Finish application action ' . $name, $logContext);

        return $output;
    }

    /**
     * @return array<string, scalar|null>|null
     */
    private function inputContext(InputInterface $input, string $name): ?array
    {
        foreach ($this->inputBuilders as $builder) {
            try {
                if ($builder->supports($input)) {
                    return $builder->build($input);
                }
            } catch (Throwable $exception) {
                $this->builderFailed($name, $builder, $exception);
            }
        }

        return null;
    }

    /**
     * Runs after the Action committed its effects: a builder bug must not turn success into a failure.
     *
     * @return array<string, scalar|null>|null
     */
    private function outputContext(OutputInterface $output, string $name): ?array
    {
        foreach ($this->outputBuilders as $builder) {
            try {
                if ($builder->supports($output)) {
                    return $builder->build($output);
                }
            } catch (Throwable $exception) {
                $this->builderFailed($name, $builder, $exception);
            }
        }

        return null;
    }

    private function builderFailed(string $name, object $builder, Throwable $exception): void
    {
        $this->logger->warning('Could not build the action log context', ['action' => $name, 'builder' => $builder::class, 'exception' => $exception]);
    }
}
