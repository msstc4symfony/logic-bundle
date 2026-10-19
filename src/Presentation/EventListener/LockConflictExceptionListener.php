<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Presentation\EventListener;

use Msstc4Symfony\LogicBundle\Application\Lock\LockConflictException;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final readonly class LockConflictExceptionListener
{
    // Lock keys may carry business identifiers; error renderers can show this message to the client.
    private const MESSAGE = 'The resource is being changed by another request.';

    public function __construct(private int $statusCode)
    {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if ($exception instanceof HttpExceptionInterface) {
            return;
        }

        for ($cursor = $exception; $cursor instanceof Throwable; $cursor = $cursor->getPrevious()) {
            if ($cursor instanceof LockConflictException) {
                $event->setThrowable(new HttpException($this->statusCode, self::MESSAGE, $exception));

                return;
            }
        }
    }
}
