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
