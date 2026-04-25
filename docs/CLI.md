# Waaseyaa CLI (this project)

## Canonical entrypoint

Use **Composer’s project binary** (same pattern as PHPUnit, PHPStan, Laravel Pint):

```bash
php vendor/bin/waaseyaa
```

Run this **from the project root** (the directory that contains `composer.json`). The `waaseyaa/cli` package resolves the project root from the current working directory; do not rely on a repo-local `bin/waaseyaa` wrapper.

## Common commands

```bash
php vendor/bin/waaseyaa list
php vendor/bin/waaseyaa serve
composer dev   # composer.json script: dev server with workers
```

## Why not `bin/waaseyaa`?

A second copy under `bin/` duplicates the framework binary, can drift from `vendor/bin/waaseyaa`, and is not how Composer documents first-party CLIs in 2025–2026 PHP ecosystems. This repository intentionally does **not** ship `bin/waaseyaa`.

## Deploy / CI

- Production deploy hooks use `php vendor/bin/waaseyaa …` (see `deploy.php`).
- `bin/waaseyaa-audit-site` checks that `vendor/bin/waaseyaa` exists after `composer install`.
