# Соглашения

- Раскладка и правила семейства — спек этапа B (`docs/superpowers/specs/2026-10-17-stage-b-conventions-design.md`
  в корне msstc4symfony): канонические слои, `@internal` вне Public API, `#[\Override]` везде (PHPStan
  `method.missingOverride`; trailing `@phpstan-ignore` у метода с атрибутом ставится на строку атрибута — PHPStan
  сообщает ошибку метода по первой строке узла).
- Приоритеты встроенных middleware: logging 300 > validation 200 > lock 100. Внешний бридж
  выбирает своё место числом в `#[AsActionMiddleware]`; без атрибута — 0 (внутри lock).
- Middleware — `final readonly`, константа `PRIORITY` у встроенных; поведение включается/выключается
  через атрибуты на классе Action (`AsAction`, `LockableAction`), читаемые `ActionContext::attribute()`.
- Билдеры логов упорядочиваются `#[AsTaggedItem(priority: N)]`, первый `supports()` выигрывает.
- Тесты: `tests/Unit/<путь класса>`, `tests/Integration` — реальный `ContainerBuilder` через
  `TestContainer::compile($config, $register, $withLockFactory)`; `MakeBundleServicesPublicPass`
  открывает сервисы бандла для проверок. Фикстуры в `tests/Fixtures/` без суффикса Test:
  `EchoAction`, `QuietAction` (всё выключено + неизвестный атрибут), `LockableEchoAction`,
  `LongLockAction`, пробы `Outer/Middle/InnerProbe` (250/150/50) пишут в `Journal`,
  `RecordingLogger::lines()` = `"<level> <message>"`, `CountingStore` (счётчик save, `lastTtl`,
  режимы conflictOnSave/failOnDelete/failOnExists).
