# CLAUDE.md

Guidance for Claude Code in this repository. Deep references live under `.claude/docs/`.

## What this is

Symfony bundle (`msstc4symfony/logic-bundle`, namespace `Msstc4Symfony\LogicBundle`) with
use-case contracts (`ActionInterface` / `InputInterface` / `OutputInterface`) and a middleware
pipeline wrapped around every Action service: logging, validation and locking built in,
custom middleware via `ActionMiddlewareInterface`. PHP >= 8.4, Symfony 7.4 / 8.x. Library code only.

## Common commands

Develop against the CI profile: `COMPOSER=composer-ci.json composer install`.

- `make check` — `php -l`, PHPStan level 10, PHP-CS-Fixer, `composer validate --strict`,
  `composer audit`, Rector dry-run, deptrac. Run as `COMPOSER=composer-ci.json make check`.
- `make test` — unit + integration suites; `make infection`, `make fix`, `make regenerate-baseline`.

## Architecture in 60 seconds

- `WrapActionsPass` decorates every autoconfigured `ActionInterface` service (`msstc4symfony_logic.action`
  tag, abstract definitions skipped) with `<id>.logic_pipeline` = `ActionPipeline`.
- `ActionPipeline` builds `ActionContext` (class attributes) once, keeps middleware whose `supports()`
  is true, runs them Messenger-style around the Action.
- Built-ins by `#[AsActionMiddleware(priority)]`: logging 300, validation 200, lock 100 (only
  `#[LockableAction]`). Without lockable Actions the pass removes the lock middleware and listener.
- Lock profiles come from `msstc4symfony_logic.lock.profiles` merged over `LogicBundle::BUILT_IN_PROFILES`;
  misconfiguration fails at compile time.

Details: `.claude/docs/architecture.md`. **Read `.claude/docs/known-issues.md` before chasing
a "weird" failure** (why locks are never auto-released, why builder tags are registered in code).

## Pointers

- `.claude/docs/architecture.md` — layers, wiring, service ids and tags.
- `.claude/docs/conventions.md` — middleware priorities, test fixtures.
- `.claude/docs/known-issues.md` — non-obvious decisions and Symfony/Xdebug traps.
