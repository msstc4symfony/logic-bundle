<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Test\Unit\Presentation\EventListener;

use Msstc4Symfony\LogicBundle\Application\Lock\LockConflictException;
use Msstc4Symfony\LogicBundle\Presentation\EventListener\LockConflictExceptionListener;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Throwable;

final class LockConflictExceptionListenerTest extends TestCase
{
    #[TestWith([409])]
    #[TestWith([422])]
    public function testMapsTheConflictToTheConfiguredStatus(int $status): void
    {
        $conflict = new LockConflictException('k');
        $event = $this->event($conflict);

        new LockConflictExceptionListener($status)($event);

        $mapped = $event->getThrowable();
        self::assertInstanceOf(HttpException::class, $mapped);
        self::assertSame($status, $mapped->getStatusCode());
        self::assertSame($conflict, $mapped->getPrevious());
    }

    public function testResponseMessageDoesNotRevealTheLockKey(): void
    {
        $event = $this->event(new LockConflictException('customer@example.com'));

        new LockConflictExceptionListener(409)($event);

        self::assertStringNotContainsString('customer@example.com', $event->getThrowable()->getMessage());
    }

    public function testFindsAWrappedConflict(): void
    {
        $wrapper = new RuntimeException('wrapped', previous: new LockConflictException('k'));
        $event = $this->event($wrapper);

        new LockConflictExceptionListener(409)($event);

        $mapped = $event->getThrowable();
        self::assertInstanceOf(HttpException::class, $mapped);
        self::assertSame($wrapper, $mapped->getPrevious());
    }

    public function testKeepsAnHttpException(): void
    {
        $http = new NotFoundHttpException('gone', new LockConflictException('k'));
        $event = $this->event($http);

        new LockConflictExceptionListener(409)($event);

        self::assertSame($http, $event->getThrowable());
    }

    public function testIgnoresOtherExceptions(): void
    {
        $other = new RuntimeException('other');
        $event = $this->event($other);

        new LockConflictExceptionListener(409)($event);

        self::assertSame($other, $event->getThrowable());
    }

    private function event(Throwable $exception): ExceptionEvent
    {
        return new ExceptionEvent(self::createStub(HttpKernelInterface::class), Request::create('/'), HttpKernelInterface::MAIN_REQUEST, $exception);
    }
}
