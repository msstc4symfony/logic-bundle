# Changelog

## 1.3.0 - 2026-10-19

- Added: README "Public API" lists the classes applications and bridges may use; the rest becomes `@internal` in 2.0
  (marking it now would break BC in a minor).
- Changed: every overriding or implementing method carries `#[\Override]`.
- Changed: adopts bundle-standard v3 — canonical `deptrac.yaml` layers (anchored `BundleRoot`, no `exclude_files`).

## 1.2.0 - 2026-10-14

- Changed: requires PHP 8.2 instead of 8.4 (Symfony 8 still needs PHP 8.4).
- Changed: class constants no longer declare a native type (PHP 8.2 has none); values are unchanged.

## 1.1.0 - 2026-10-12

- `ActionNameResolver` service: the Action name used in logs, for bridges (metrics, spans).
- `ActionOutcome::of()`: `success` / `expected_failure` / `error` classification shared by logging and bridges.

## 1.0.0 - 2026-10-10

- First release: Action/Input/Output contracts, middleware pipeline, logging, validation and lock middleware.
