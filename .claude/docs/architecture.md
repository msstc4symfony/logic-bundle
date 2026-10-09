# Архитектура

## Слои (deptrac.yaml)

| Слой | Что | Зависит от |
|---|---|---|
| `Application/Action` | контракты `ActionInterface<TOutput>`, `InputInterface`, `OutputInterface`, `ExpectedFailureInterface`, `#[AsAction]` | — |
| `Application/Pipeline` | `ActionPipeline`, `ActionContext`, `ActionMiddlewareInterface`, `#[AsActionMiddleware]` | — |
| `Application/Logging` | `LoggingMiddleware` (300), билдеры имени/input/output, `IdInputBuilder` (-1000) | — |
| `Application/Lock` | `#[LockableAction]`, `KeyBuilderInterface`, `ActionNameKeyBuilder`, `LockProfile`, исключения | — |
| `Framework/Validator` | `ValidationMiddleware` (200), `ValidationFailedException` | Application |
| `Infrastructure/Lock` | `LockMiddleware` (100) поверх `symfony/lock` | Application |
| `Presentation/EventListener` | `LockConflictExceptionListener` (`kernel.exception`, priority 16) | Application |
| `DependencyInjection/CompilerPass` | `WrapActionsPass` | Application |
| `LogicBundle` | конфиг-дерево, параметры, автоконфигурация | Application, DependencyInjection |

Application-слой бандла импортирует атрибуты Symfony DI (`AutowireIterator`, `AsTaggedItem`) и
`Symfony\...\ValidationFailedException` (уровень лога): осознанно, deptrac проверяет только
внутренние слои, бандл и так зависит от Symfony.

## Подключение

- `LogicBundle::loadExtension()` кладёт параметры `msstc4symfony_logic.lock.{profiles,default_profile,factory,conflict_status_code}`,
  импортирует `Resources/config/services.php`, проставляет logger/lock factory в middleware и
  регистрирует автоконфигурацию: `ActionInterface` → тег `msstc4symfony_logic.action`,
  `ActionMiddlewareInterface` → `msstc4symfony_logic.middleware`, три интерфейса билдеров логов →
  тег = FQCN интерфейса (его читает `#[AutowireIterator]` в `LoggingMiddleware`).
- Id встроенных сервисов: `msstc4symfony_logic.middleware.{logging,validation,lock}`,
  `msstc4symfony_logic.lock_conflict_listener`.
- `WrapActionsPass`:
  1. Собирает сервисы с тегом action, пропуская abstract и декораторы; класс обязан реализовывать `ActionInterface`.
  2. Для каждого `#[LockableAction]` на этапе компиляции проверяет профиль, сервис key builder'а (и что он
     `KeyBuilderInterface`), наличие сервиса lock factory. Key builders уходят в service locator
     аргумента `$keyBuilders` lock middleware.
  3. Нет lockable Action → удаляет lock middleware и listener (хосту не нужен lock store).
     `conflict_status_code: null` → удаляет только listener.
  4. Сортирует middleware по `#[AsActionMiddleware(priority)]` desc, при равенстве — порядок регистрации.
  5. Регистрирует `<id>.logic_pipeline` (`ActionPipeline`) декоратором каждого Action.

## Путь вызова

`ActionPipeline::__invoke` при первом вызове строит `ActionContext::of(class)` (атрибуты класса,
первый экземпляр каждого; классы неустановленных атрибутов пропускаются) и отфильтрованную по
`supports()` цепочку; оба кэшируются в экземпляре (зависят только от класса). Дальше Messenger-style:
`handle($input, $context, $next)`; middleware может передать в `$next` другой Input.
