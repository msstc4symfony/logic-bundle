# Logic Symfony bundle

![Build Status](https://github.com/msstc4symfony/logic-bundle/actions/workflows/checks.yml/badge.svg?branch=main)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

Use-case contracts for Symfony (`Action` / `Input` / `Output`) and a middleware pipeline wrapped
around every Action: logging, input validation and a distributed lock, without a line of that code
in the Action itself. Middleware is the extension point, so metrics and tracing can come later as
separate bridges that know nothing about the bundle's internals.

## Compatibility

| Bundle  | PHP  | Symfony  |
|---------|------|----------|
| 1.2+    | 8.2+ | 7.4, 8.x |
| 1.0–1.1 | 8.4+ | 7.4, 8.x |

Symfony 8.x needs PHP 8.4; on PHP 8.2 and 8.3 Composer installs Symfony 7.4.

## Installation

The package is not on Packagist yet, so register its GitHub repository first:

```sh
composer config repositories.msstc4symfony-logic vcs https://github.com/msstc4symfony/logic-bundle
composer require msstc4symfony/logic-bundle
```

Symfony Flex registers the bundle automatically; without Flex add it to `config/bundles.php`:

```php
Msstc4Symfony\LogicBundle\LogicBundle::class => ['all' => true],
```

The bundle needs a PSR-3 `logger` and the `validator` service; `framework.lock` only when an
Action is lockable.

## Writing an Action

One directory per use-case, e.g. `src/Application/Service/Order/Create/`:

```php
use Msstc4Symfony\LogicBundle\Application\Action\ActionInterface;

/**
 * @implements ActionInterface<Output>
 */
final readonly class Action implements ActionInterface
{
    public function __construct(private OrderRepository $orders)
    {
    }

    public function __invoke(Input $input): Output
    {
        // ...
    }
}
```

`Input` implements `InputInterface`, `Output` implements `OutputInterface`; both are plain
immutable classes. Validation constraints go on `Input`.

The Action service **must be autoconfigured** (the default for `App\` services): the bundle finds
Actions by the `ActionInterface` autoconfiguration tag. An Action registered with
`autoconfigure: false` is not wrapped and runs without logging, validation or locking.

## Calling an Action

The service registered under the Action's id is the pipeline, not the Action class, so inject
the interface:

```php
use Msstc4Symfony\LogicBundle\Application\Action\ActionInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class CreateOrderController
{
    /**
     * @param ActionInterface<Output> $createOrder
     */
    public function __construct(
        #[Autowire(service: Action::class)]
        private ActionInterface $createOrder,
    ) {
    }

    public function __invoke(Input $input): JsonResponse
    {
        $output = $this->createOrder->__invoke($input);
        // ...
    }
}
```

## Pipeline

Every Action runs through the middleware sorted by `#[AsActionMiddleware(priority: N)]`, highest
first (equal priorities keep registration order):

| Priority | Middleware | Applies to |
|---|---|---|
| 300 | logging | every Action unless `#[AsAction(logging: false)]` |
| 200 | validation | every Action unless `#[AsAction(validation: false)]` |
| 100 | lock | Actions with `#[LockableAction]` |

So a validation failure is logged, and an invalid Input never takes a lock.

```php
use Msstc4Symfony\LogicBundle\Application\Action\Attribute\AsAction;

#[AsAction(validation: false, logging: false)]
final readonly class Action implements ActionInterface
```

## Logging

- `info` `Start application action <name>` with `input` context, then `info`
  `Finish application action <name>` with `input` and `output`.
- On an exception: `Fail application action <name>` with the `exception`, then the exception is
  rethrown. The level is `warning` for exceptions implementing `ExpectedFailureInterface` (a refusal
  the caller can provoke: not found, invalid input, lock conflict) and for Symfony's
  `ValidationFailedException`; `error` for everything else.
- `<name>` is the Action class unless an `ActionNameBuilderInterface` supports it.
- `input` / `output` come from the first `InputBuilderInterface` / `OutputBuilderInterface` whose
  `supports()` returns `true`. Order builders with `#[AsTaggedItem(priority: N)]`; the built-in
  `IdInputBuilder` (a public `id` property, string or `Stringable` such as a `Uuid`) runs last at
  -1000. Builders are picked up by autoconfiguration. A builder that throws is logged as a warning
  and skipped; it never changes the outcome of the Action.

```yaml
msstc4symfony_logic:
    logging:
        logger: logger   # PSR-3 service id, e.g. monolog.logger.app
```

## Locking

```php
use Msstc4Symfony\LogicBundle\Application\Lock\LockableAction;

#[LockableAction]                                          // key = Action class, default profile
#[LockableAction(keyBuilder: OrderIdKeyBuilder::class)]    // one lock per order
#[LockableAction(profile: 'long')]
```

A key builder implements `KeyBuilderInterface::build(InputInterface $input, ActionContext $context): string`
and must be a service (autowired classes are; the id is the class name). Throw
`KeyBuildingFailedException` when the Input lacks what the key needs.

```yaml
msstc4symfony_logic:
    lock:
        factory: lock.factory          # Symfony\Component\Lock\LockFactory service id
        default_profile: short
        conflict_status_code: 409      # 400..599, or null to map nothing
        profiles:                      # merged with the built-ins; a built-in name overrides it
            short: { ttl: 5,   retry_count: 5, retry_delay_ms: 100 }
            long:  { ttl: 300, retry_count: 0, retry_delay_ms: 0 }
```

- The lock is taken without blocking, `retry_count + 1` attempts `retry_delay_ms` apart. If all
  fail, `LockConflictException` (an `ExpectedFailureInterface`) is thrown; on HTTP a
  `kernel.exception` listener turns it (also when wrapped) into an `HttpException` with
  `conflict_status_code` and a generic message (the key stays out of the response). Existing HTTP
  exceptions are left alone.
- The pauses between attempts block the worker (`usleep`): keep `retry_count × retry_delay_ms` short.
- Keys share one namespace across all Actions: a custom builder should prefix the key
  (`'order-cancel:' . $input->orderId`), not return a bare id.
- A store failure (Redis down) is not a conflict: it propagates as a service fault.
- The lock is released after the Action, also when it throws. A failed release is logged as a
  warning and never replaces the Action's result or exception; the lock then expires with its TTL.
- **The TTL is not refreshed.** Pick a profile whose `ttl` exceeds the longest run of the Action.
- Unknown profiles, missing key-builder services and a missing lock factory fail at container
  compilation. Without lockable Actions no lock service is needed at all.

## Writing a bridge

A bridge (metrics, tracing) is a middleware service; autoconfiguration registers it:

```php
use Closure;
use Msstc4Symfony\LogicBundle\Application\Action\ActionNameResolver;
use Msstc4Symfony\LogicBundle\Application\Action\ActionOutcome;
use Msstc4Symfony\LogicBundle\Application\Action\InputInterface;
use Msstc4Symfony\LogicBundle\Application\Action\OutputInterface;
use Msstc4Symfony\LogicBundle\Application\Pipeline\ActionContext;
use Msstc4Symfony\LogicBundle\Application\Pipeline\ActionMiddlewareInterface;
use Msstc4Symfony\LogicBundle\Application\Pipeline\Attribute\AsActionMiddleware;
use Override;
use Throwable;

#[AsActionMiddleware(priority: 400)]
final readonly class TimingMiddleware implements ActionMiddlewareInterface
{
    public function __construct(private ActionNameResolver $names)
    {
    }

    #[Override]
    public function supports(ActionContext $context): bool
    {
        return $context->attribute(NoTiming::class) === null;
    }

    #[Override]
    public function handle(InputInterface $input, ActionContext $context, Closure $next): OutputInterface
    {
        $start = hrtime(true);
        $failure = null;
        try {
            return $next($input);
        } catch (Throwable $exception) {
            $failure = $exception;

            throw $exception;
        } finally {
            // record (hrtime(true) - $start) / 1e9 under $this->names->resolve($context) and ActionOutcome::of($failure)->value
        }
    }
}
```

Inject `ActionNameResolver` for the same Action name the logs use, and classify failures with
`ActionOutcome::of($exception)` (`success`, `expected_failure`, `error`). Ready-made bridges:
`msstc4symfony/metrics-bridge-logic`, `msstc4symfony/profiling-bridge-logic`.

`supports()` is asked once per Action service. `ActionContext::attribute()` reads the Action's
class attributes; attributes of classes that are not installed are skipped, so an optional
bridge's attribute never breaks an Action.

To check the order in a test, read it from the Action service: it implements `MiddlewareChainInterface` (unless the
application decorates the service itself), and `middlewares()` lists the middlewares that support this Action,
outermost first. The interface is for calling only; the bundle implements it.

```php
use Msstc4Symfony\LogicBundle\Application\Pipeline\ActionMiddlewareInterface;
use Msstc4Symfony\LogicBundle\Application\Pipeline\MiddlewareChainInterface;

$action = static::getContainer()->get(CreateOrder::class);
self::assertInstanceOf(MiddlewareChainInterface::class, $action);
$classes = array_map(static fn (ActionMiddlewareInterface $middleware): string => $middleware::class, $action->middlewares());
self::assertSame(TimingMiddleware::class, $classes[0] ?? null); // priority 400: outside the built-ins
```

## Public API

Applications and bridges use or implement only these. Every other class is `@internal` (since 2.0) and may change in
any release.

| Namespace `Msstc4Symfony\LogicBundle\` | Classes |
|---|---|
| `Application\Action` | `ActionInterface`, `InputInterface`, `OutputInterface`, `ExpectedFailureInterface`, `ActionOutcome`, `ActionNameResolver`, `Attribute\AsAction` |
| `Application\Lock` | `LockableAction`, `KeyBuilderInterface`, `LockConflictException`, `KeyBuildingFailedException` |
| `Application\Logging\Builder` | `ActionNameBuilderInterface`, `InputBuilderInterface`, `OutputBuilderInterface`, `IdInputBuilder` |
| `Application\Pipeline` | `ActionMiddlewareInterface`, `ActionContext`, `MiddlewareChainInterface` (call only), `Attribute\AsActionMiddleware` |
| `Framework\Validator` | `ValidationFailedException` |
| (root) | `LogicBundle` |

Configuration keys under `msstc4symfony_logic` (see [Locking](#locking) and [Logging](#logging)) are public too.

## Migrating from tapfiliate/logic-bundle

- Namespace `Tapfiliate\LogicBundle\` → `Msstc4Symfony\LogicBundle\Application\…` (`Action\ActionInterface`, `Action\InputInterface`, `Action\OutputInterface`, `Lock\LockableAction`).
- `@method Output __invoke(Input $input)` → `@implements ActionInterface<Output>`.
- `KeyBuilderInterface::build($request, string $actionClass)` → `build(InputInterface $input, ActionContext $context)`; the class is `$context->actionClass`.
- `LockProfileEnum` → a profile name string (`'short'`, `'long'` or your own).
- Builders' static `getPriority()` → `#[AsTaggedItem(priority: N)]` on the builder class.
- `Request`/`Response` log builders → `InputBuilderInterface` / `OutputBuilderInterface`.
- Lock conflicts answered 422 before: set `conflict_status_code: 422`.
- `tapfiliate/lock-bundle` is not needed; configure `framework.lock`.

## Local development

```sh
COMPOSER=composer-ci.json composer install
COMPOSER=composer-ci.json make check
make test
make fix
```

## License

MIT, see [LICENSE](LICENSE).
