# Convergence audit — waaseyaa.org

**Date:** 2026-03-30 (follow-up remediation applied same window)  
**Spec:** [per-site-convergence-audit.md](https://github.com/waaseyaa/framework/blob/main/docs/specs/per-site-convergence-audit.md) (Waaseyaa monorepo)

## Follow-up remediation (completed)

- **R1:** `composer.lock` refreshed and committed with `composer.json` (including new dev deps).
- **R2:** `.waaseyaa-golden-sha` added; CI sets `WAASEYAA_GOLDEN_SHA` to the checked-out `waaseyaa/framework` `HEAD` and runs `./bin/waaseyaa-version --strict`.
- **R3:** `require-dev` + `phpunit.xml.dist` + `tests/Unit/SmokeTest.php` + `composer test`.
- **O2 / O3:** `.env.example` added; `.github/workflows/ci.yml` runs validate, dry-run, PHPUnit, `waaseyaa-audit-site`, and strict provenance.

**Local:** After moving `../waaseyaa` to a new commit intentionally, update `.waaseyaa-golden-sha` to match (`git -C ../waaseyaa rev-parse HEAD`) or `./bin/waaseyaa-version --strict` will fail.

---

## 1. Provenance and version alignment

**Commands run:** `php bin/waaseyaa-version` / `./bin/waaseyaa-version --strict` (after `composer install`).

**Findings:**

- Golden SHA: **`.waaseyaa-golden-sha`** documents the pinned path monorepo revision; CI uses **`WAASEYAA_GOLDEN_SHA`** from the workflow’s framework checkout (always matches that clone).
- Path monorepo HEAD (from lockfile): aligns with golden when checkouts match.
- `waaseyaa/*` constraints: single pattern `dev-main` across all packages (pass).
- `minimum-stability: dev` + `prefer-stable: true` (pass).
- `composer validate --no-check-publish`: **pass** after lock refresh.

## 2. Skeleton conformance

| Check | Status |
|-------|--------|
| `autoload` PSR-4 `WaaseyaaOrg\` | Pass |
| `require-dev` (PHPUnit, PHPStan) | **PHPUnit** present; PHPStan not added (optional O1) |
| `phpunit.xml.dist` | **Present** |
| `extra.waaseyaa.providers` | Pass — `SiteServiceProvider` registered |
| `optimize-autoloader` | Pass |
| `bin/waaseyaa-version` | **Added** in this convergence pass |
| `bin/waaseyaa-audit-site` | **Added** in this convergence pass |

**Update (2026):** Project-local `bin/waaseyaa` was removed. Use `php vendor/bin/waaseyaa` from the project root; see [docs/CLI.md](../CLI.md).
| `post-create-project-cmd` | N/A — not created from skeleton installer |

## 3. Entity and provider audit

- **SiteServiceProvider** registers docs-related services and routes only; no `entityType()` calls.
- No duplicate entity type IDs from this app.
- **GraphQL** is a dependency but this site does not register app-specific entity types for GraphQL exposure; no `fieldDefinitions` gap for a custom schema.

## 4. API surface audit

- Public site: Twig-rendered pages and docs routes; not a GraphQL-primary app.
- **SchemaContractTest:** not applicable for app-owned entity GraphQL (none).
- No legacy JSON:API controllers identified in `src/`.

## 5. Framework boundary audit

- No vendored framework forks observed; standard `ServiceProvider` usage.

## 6. Test harness audit

- **PHPUnit 11** via `composer test`; smoke test asserts `HttpKernel` autoload.
- PHPStan not in CI (optional O1).

## 7. Operational invariants

- **`.env.example`:** present (`GITHUB_TOKEN` optional for docs fetch).
- **CI:** `.github/workflows/ci.yml` — validate, dry-run, PHPUnit, `waaseyaa-audit-site`, `waaseyaa-version --strict` (with golden from framework job checkout). **`deploy.yml`** remains production deploy with `composer install --no-dev`.

## 8. Drift and remediation plan

| ID | Area | Description | Status |
|----|------|-------------|--------|
| R1 | 1, 7 | Refresh `composer.lock` | Done |
| R2 | 1, 7 | Golden file + CI `WAASEYAA_GOLDEN_SHA` + `--strict` | Done |
| R3 | 6 | PHPUnit + smoke test | Done |
| O1 | 6 | PHPStan + CI | Open (optional) |
| O2 | 7 | `.env.example` | Done |
| O3 | 7 | Quality CI workflow | Done |

**Summary:** Required items **R1–R3** and optional **O2/O3** are closed. **O1** (PHPStan) remains optional if the org adopts a shared static-analysis bar for marketing sites.
