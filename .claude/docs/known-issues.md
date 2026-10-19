# Неочевидное

- **Приоритет билдеров через `#[AsTaggedItem]`, не `getPriority()`.** В исходном tapfiliate-бандле
  `defaultIndexMethod: 'getPriority'` делал индексом итератора значение приоритета, билдеры с
  равным приоритетом затирали друг друга, сортировки не было. `defaultPriorityMethod` в Symfony 8 —
  legacy. `#[AsTaggedItem]` читается только для autoconfigured-определений; ключ итератора — id сервиса.
  Регрессия: `WrapActionsPassTest::testInputBuildersWithTheSamePriorityAreAllKept`.
- **`#[AutoconfigureTag]` на интерфейсе бандла не работает.** `RegisterAutoconfigureAttributesPass`
  читает атрибуты только с класса определения; атрибуты интерфейсов попадают в автоконфигурацию,
  когда интерфейс найден `load()` в ресурсе приложения. Vendor-интерфейсы так не находятся, поэтому
  теги билдеров регистрирует `LogicBundle::loadExtension()` через `registerForAutoconfiguration()`.
- **`ActionInterface` шаблонизирован только `TOutput` (covariant).** Контравариантный `TInput`
  делает вызов `$inner->__invoke($input)` в пайплайне типом `never`. Сигнатура — `@method`, PHP не
  даёт сузить параметр метода интерфейса.
- **Блокировки создаются с `autoRelease: false`, ключа `auto_release` в профилях нет.** С
  `autoRelease: true` после неудачного `release()` блокировка остаётся dirty, и `Lock::__destruct()`
  повторяет release там, где умрёт последняя ссылка: при возврате из `handle()` (исключение подменяло
  результат Action) или позже — исключение в trace (`zend.exception_ignore_args=Off`, Xdebug develop)
  держит `Lock`, буферизующий Monolog-handler держит исключение. Middleware и так освобождает явно в
  `finally`, поэтому `auto_release` ничего не давал (в tapfiliate-оригинале тоже). Отклонение от спеки
  согласовано ревью.
- `Lock::release()` оборачивает в `LockReleasingException` только ошибки `delete()`; ошибка
  `exists()` после него вылетает как есть — поэтому `release()` ловит `Throwable`.
- Исключение из билдера логов логируется WARNING и пропускается: output-билдер работает уже после
  побочных эффектов Action, и его баг не должен превращать успех в ошибку (retry → двойное выполнение).
- `Key::getRemainingLifetime()` в момент `store->save()` ещё `null`: TTL ставит `Lock::refresh()` →
  `putOffExpiration()`. Тестовый store фиксирует TTL там.
- Временный `Lock` (`$factory->createLock(...)->acquire()` без переменной) с autoRelease сразу
  освобождается деструктором — в тестах держите блокировку в переменной.
- `loadExtension()` типизирован shape `LogicConfig` с inline-ignore `method.childParameterType`
  (дважды: два родителя объявляют `array`).
- Local `composer audit` без доступа к packagist падает — это песочница, CI проверяет.
- `ActionOutcome` импортирует `ValidationFailedException` Symfony, поэтому `symfony/validator` должен оставаться жёсткой зависимостью; если он станет опциональным, в `ActionOutcome` нужен guard через `class_exists`.
- **Код должен разбираться на PHP 8.2** (с 2026-10-14 UTC, bundle-standard v2.0.0, профиль `php82`): без
  типизированных констант (`const string X`), без `new readonly class` (оба — 8.3) и без `new X()->m()` /
  `new X()(...)` (8.4) — только `(new X())->m()`. PHPStan (`phpVersion.min: 80200`) ловит типизированные
  константы; остальное ловят только `php -l` в job «minimal» (PHP 8.2) и ячейки PHPUnit на 8.2. Шаблоны Rector
  (`withPhpVersion(PHP_82)`) и CS-Fixer (`new_expression_parentheses`) синтаксис обратно не поднимают.
  `#[Override]` (8.3) допустим: на 8.2 атрибут не инстанцируется. `array_any/all/find` (8.4) — только с
  `symfony/polyfill-php84` в `require`. Symfony 8 ставится только на PHP 8.4+.

## Потребители читают служебные классы в тестах

Kernel-тесты мостов (`metrics-`, `profiling-`, `tracing-bridge-logic`: `tests/Integration/KernelBridgeTest.php`) и
showcase (`apps/orders/tests/Integration/CreateOrderActionWiringTest.php`) проверяют порядок middleware
рефлексией по `ActionPipeline::$middlewares` и ссылаются на `LoggingMiddleware`. Оба вне Public API (в 2.0 станут
`@internal`): их переименование или смена приватного поля в миноре сломает эти тесты, а не код потребителей. Перед такой правкой —
grep по соседним репозиториям; публичного способа узнать порядок middleware пока нет.

## Roave BC check: новая пометка `@internal` — поломка

Roave сообщает `<Class> was marked "@internal"` для каждого ранее публичного класса, получившего `@internal`.
Помечать можно только в мажоре (спек этапа B, п. 4.3); в 1.3.0 пометка была откатана после красного CI 2026-10-19 UTC.

