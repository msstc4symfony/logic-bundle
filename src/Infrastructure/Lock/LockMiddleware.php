<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Infrastructure\Lock;

use Closure;
use LogicException;
use Msstc4Symfony\LogicBundle\Application\Action\InputInterface;
use Msstc4Symfony\LogicBundle\Application\Action\OutputInterface;
use Msstc4Symfony\LogicBundle\Application\Lock\KeyBuilderInterface;
use Msstc4Symfony\LogicBundle\Application\Lock\LockableAction;
use Msstc4Symfony\LogicBundle\Application\Lock\LockConflictException;
use Msstc4Symfony\LogicBundle\Application\Lock\LockProfile;
use Msstc4Symfony\LogicBundle\Application\Pipeline\ActionContext;
use Msstc4Symfony\LogicBundle\Application\Pipeline\ActionMiddlewareInterface;
use Msstc4Symfony\LogicBundle\Application\Pipeline\Attribute\AsActionMiddleware;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Throwable;

/**
 * @phpstan-import-type LockProfileSpec from LockProfile
 */
#[AsActionMiddleware(priority: self::PRIORITY)]
final readonly class LockMiddleware implements ActionMiddlewareInterface
{
    public const int PRIORITY = 100;

    /** @var array<string, LockProfile> */
    private array $profiles;

    /**
     * @param ContainerInterface $keyBuilders KeyBuilderInterface services by the ids named in #[LockableAction]
     * @param array<string, LockProfileSpec> $profiles
     * @param non-empty-string $defaultProfile
     */
    public function __construct(
        private LockFactory $factory,
        private ContainerInterface $keyBuilders,
        array $profiles,
        private string $defaultProfile,
        private LoggerInterface $logger,
    ) {
        $this->profiles = array_map(LockProfile::fromSpec(...), $profiles);
    }

    public function supports(ActionContext $context): bool
    {
        return $context->attribute(LockableAction::class) instanceof LockableAction;
    }

    public function handle(InputInterface $input, ActionContext $context, Closure $next): OutputInterface
    {
        $lockable = $context->attribute(LockableAction::class);
        if (!$lockable instanceof LockableAction) {
            return $next($input);
        }

        $profileName = $lockable->profile ?? $this->defaultProfile;
        $profile = $this->profiles[$profileName]
            ?? throw new LogicException(sprintf('%s names the unknown lock profile "%s".', $context->actionClass, $profileName));
        $keyBuilder = $this->keyBuilders->get($lockable->keyBuilder);
        if (!$keyBuilder instanceof KeyBuilderInterface) {
            throw new LogicException(sprintf('The lock key builder "%s" of %s does not implement %s.', $lockable->keyBuilder, $context->actionClass, KeyBuilderInterface::class));
        }

        $key = $keyBuilder->build($input, $context);
        // Released explicitly below; an auto-releasing lock would retry a failed release from __destruct(),
        // wherever its last reference happens to die (a buffered log record can hold it).
        $lock = $this->factory->createLock($key, $profile->ttl, false);
        if (!$this->acquire($lock, $profile)) {
            throw new LockConflictException($key);
        }

        try {
            return $next($input);
        } finally {
            $this->release($lock, $key);
        }
    }

    private function acquire(LockInterface $lock, LockProfile $profile): bool
    {
        for ($attempt = 0; $attempt <= $profile->retryCount; $attempt++) {
            try {
                if ($lock->acquire()) {
                    return true;
                }
            } catch (LockConflictedException) {
            }

            if ($attempt < $profile->retryCount && $profile->retryDelayMs > 0) {
                usleep($profile->retryDelayMs * 1000);
            }
        }

        return false;
    }

    private function release(LockInterface $lock, string $key): void
    {
        try {
            $lock->release();
        } catch (Throwable $exception) {
            // The lock expires with its TTL; a failed release (Lock::release() lets store errors of its
            // exists() check through unwrapped) must not replace the action's own result or exception.
            $this->logger->warning('Could not release the action lock', ['key' => $key, 'exception' => $exception]);
        }
    }
}
