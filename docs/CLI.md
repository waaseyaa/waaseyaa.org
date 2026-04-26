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

## Scaffolding: `make:entity-type`

Generate a starter entity class into stdout (redirect to a file under `src/Entity/`):

```bash
# Content entity (extends ContentEntityBase; emits #[ContentEntityType] + #[ContentEntityKeys])
php vendor/bin/waaseyaa make:entity-type event --content > src/Entity/Event.php

# Config entity (extends ConfigEntityBase)
php vendor/bin/waaseyaa make:entity-type ticket_type > src/Entity/TicketType.php
```

Content output includes **`#[ContentEntityType(id: '…')]`** and **`#[ContentEntityKeys]`** so class metadata matches what `EntityTypeManager` expects. The template may still include optional hydration helpers (`fromStorage`, `duplicateInstance`, …); **minimal apps** often keep only attributes + domain methods and rely on the default `ContentEntityBase` constructor—trim generated noise once you understand hydration.

After adding a file, register the `EntityType` (for example in `config/entity-types.php`), run `php vendor/bin/waaseyaa optimize:manifest` if policies/providers changed, and `php vendor/bin/waaseyaa migrate` when the database needs updating.

## Why not `bin/waaseyaa`?

A second copy under `bin/` duplicates the framework binary, can drift from `vendor/bin/waaseyaa`, and is not how Composer documents first-party CLIs in 2025–2026 PHP ecosystems. This repository intentionally does **not** ship `bin/waaseyaa`.

## Deploy / CI

- **Manifest:** `php vendor/bin/waaseyaa optimize:manifest` (minimal console; no DB).
- **Docs cache:** `php scripts/docs-fetch-deploy.php` with optional `GITHUB_TOKEN` — used in production instead of `docs:fetch` so deploy does not require a booted kernel with content types in SQLite.
- `bin/waaseyaa-audit-site` checks that `vendor/bin/waaseyaa` exists after `composer install`.
