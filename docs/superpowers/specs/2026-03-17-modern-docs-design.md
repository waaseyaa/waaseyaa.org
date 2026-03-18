# Modern Documentation for waaseyaa.org

**Date:** 2026-03-17
**Status:** Approved

## Overview

Add a comprehensive documentation section to waaseyaa.org, rendered by the Waaseyaa SSR app itself (dogfooding the framework). Documentation combines hand-written guides for core concepts with auto-fetched package READMEs from the framework GitHub repo, all processed through a unified markdown pipeline with frontmatter metadata.

## Content Architecture

### Two Content Sources, One Pipeline

- **Guides** — Authored as markdown files in the waaseyaa.org repo at `docs/guides/`. Deep, hand-written tutorials covering the core learning path.
- **Package references** — Fetched from each package's `README.md` in the framework GitHub repo at deploy time, cached to `storage/docs/packages/`.

### Frontmatter Format

Both sources use YAML frontmatter:

```yaml
---
title: Entity System
category: core
order: 3
description: Define content models with typed fields, revisions, and translations
---
```

For package READMEs without frontmatter, the fetch process injects it from a package manifest (`config/docs-manifest.php`).

### Categories

| Category | Contents |
|---|---|
| Getting Started | Installation, first app, concepts (guides only) |
| Core | foundation, core, config, state, routing, entity, field, entity-storage |
| Content | node, taxonomy, menu, media, cms |
| API | api, graphql, mcp |
| AI | ai-agent, ai-pipeline, ai-schema, ai-vector |
| Access & Users | access, user |
| Infrastructure | cache, queue, database-legacy, search, mail |
| Developer Tools | cli, testing, validation, telescope, typed-data |
| Frontend | ssr, admin-surface |
| Extensibility | plugin, workflows, i18n, path, note |

## Fetch & Cache Pipeline

### CLI Command: `docs:fetch`

Runs at deploy time:

1. Hits the GitHub API to list all directories under `waaseyaa/framework/packages/`
2. For each package, fetches `README.md` via the GitHub raw content API
3. Parses any existing frontmatter; if missing, injects it from the package manifest (`config/docs-manifest.php`) — README values take precedence when present
4. Writes each file to `storage/docs/packages/{package-name}.md`
5. Builds `storage/docs/index.json` — a full navigation tree with all guides + packages, grouped by category, ordered, with titles and descriptions

### Package Manifest (`config/docs-manifest.php`)

```php
return [
    'entity' => [
        'title' => 'Entity System',
        'category' => 'core',
        'order' => 3,
        'description' => 'Typed fields, revisions, and translations',
    ],
    // ... all 45 packages
];
```

Single source of truth for how packages map to docs navigation.

### Cache Strategy

- Deploy pipeline runs `docs:fetch` every deploy — no runtime cache expiry needed
- For local dev, run the command manually
- `GITHUB_TOKEN` env var is **required** in the deploy pipeline (authenticated API: 5,000 req/hr). Unauthenticated API (60 req/hr) is a local-dev fallback only — sufficient but tight, will warn if no token is set

### Failure Modes

- **GitHub API unreachable or auth failure:** Fail the deploy. Docs must be current.
- **Individual README missing:** Warn and skip — the package gets no reference page but the build continues. Log the missing packages.
- **Re-deploy after partial failure:** The fetch command writes to a temporary directory first, then atomically swaps into `storage/docs/`. Previous cache is preserved until a full successful fetch completes.

### Slug Collision Prevention

The `index.json` build step checks for collisions between guide slugs and package slugs within the same category. If a guide at `docs/guides/core/entity.md` and a package `entity` both map to category `core`, the build **fails with an error**. The manifest must place one of them in a different category or use a different slug. This is enforced at build time, not runtime.

## Rendering Pipeline

### Routes

- `GET /docs` — Landing page with category overview
- `GET /docs/{category}` — Category listing (all pages in that category)
- `GET /docs/{category}/{slug}` — Individual doc page (guide or package reference). Returns 404 via the framework's standard error handling if slug is not found in `index.json`.

### Markdown to HTML

- `league/commonmark` with GFM extensions (tables, task lists, autolinks) and `HeadingPermalinkExtension` for anchor links on headings (e.g., `#entity-fields`)
- Syntax highlighting via Prism.js (client-side, loaded from CDN or vendored)
- Frontmatter parsed with `symfony/yaml` before passing to CommonMark

### Rendering Flow

1. Controller receives request for `/docs/core/entity`
2. Reads `storage/docs/index.json` to build sidebar navigation
3. Locates the markdown file — guide in `docs/guides/core/entity.md` or cached package at `storage/docs/packages/entity.md`
4. Parses frontmatter + converts markdown to HTML
5. Passes HTML + nav tree + frontmatter metadata to `docs/page.html.twig`

### Twig Templates

- `templates/docs/layout.html.twig` — Extends `base.html.twig`, two-column layout: sidebar nav + content area
- `templates/docs/page.html.twig` — Extends docs layout, renders markdown HTML with prev/next navigation
- `templates/docs/landing.html.twig` — The `/docs` index page with category cards
- `templates/docs/category.html.twig` — Lists all pages in a category

## Layout & Styling

### Brand Continuity

Extends the existing waaseyaa.org dark theme — same CSS variables, no new design system:

- Dark background (`--color-bg: #0f1117`), surface colors, amber accent (`--color-accent: #f59e0b`)
- Same font stack, border radius, transition speeds

### Two-Column Docs Layout

- **Sidebar:** 260px fixed width, sticky below nav, scrolls independently. Background `var(--color-surface)`, right border `var(--color-border)`. Category headings in amber, page links in muted text, active page highlighted with amber left border + white text. Collapsible category groups.
- **Content area:** Max-width 780px, centered in remaining space. Generous padding for readability.

### Markdown Content Styling

- **Headings:** `h1` not rendered (title from frontmatter in template). `h2` gets subtle amber left border. `h3`/`h4` normal weight hierarchy.
- **Code blocks:** Existing `--color-code-bg` with Prism.js syntax highlighting. Language label badge in top-right corner.
- **Tables:** Surface background, border, rounded corners — consistent with card aesthetic.
- **Inline code:** Existing `p code` styles carry over.
- **Blockquotes:** Left border in amber, muted background — used for callouts/notes.

### Responsive Behavior

- Below 1024px: Sidebar collapses into hamburger/drawer
- Below 768px: Full-width content, nav drawer overlays
- Prev/next navigation stacks vertically on mobile

All docs styles added to `public/css/site.css` in a `/* --- Docs --- */` section.

## Initial Content Scope

### Deep Guides (8 total, hand-written)

| Guide | Category | Purpose |
|---|---|---|
| Introduction | Getting Started | What Waaseyaa is, who it's for, philosophy |
| Installation | Getting Started | Composer setup, skeleton project, first boot |
| Your First App | Getting Started | Build a simple content type end-to-end |
| Concepts | Getting Started | Entity/field model, service providers, kernel lifecycle |
| Entity System | Core | Deep dive into entities, fields, revisions, translations |
| Routing | Core | Route definitions, controllers, middleware |
| Access Control | Access & Users | Deny-unless-granted model, field permissions |
| AI Overview | AI | How the AI packages work together |

### Package Reference Pages (all 45)

Each package gets a page rendered from its GitHub README. No additional writing needed.

### Docs Landing Page (`/docs`)

- Brief intro paragraph
- Category cards (reusing existing `.card` component)
- "Start here" callout pointing to Getting Started guides

### Not In Scope (future work)

- Search (client-side index like Pagefind or Fuse.js)
- Versioning
- API-generated reference docs (PHPDoc extraction)
- Dark/light theme toggle

## File Structure

### New Files

```
config/docs-manifest.php              # Package → category/order mapping
src/Controller/DocsController.php      # All /docs routes
src/Service/DocsRenderer.php           # Markdown parsing + frontmatter extraction
src/Service/DocsFetcher.php            # GitHub fetch + cache logic
src/Command/DocsFetchCommand.php       # CLI command for deploy-time fetch
templates/docs/layout.html.twig        # Two-column docs layout
templates/docs/page.html.twig          # Individual doc page
templates/docs/landing.html.twig       # /docs index
templates/docs/category.html.twig      # Category listing
docs/guides/getting-started/introduction.md
docs/guides/getting-started/installation.md
docs/guides/getting-started/your-first-app.md
docs/guides/getting-started/concepts.md
docs/guides/core/entity-system.md
docs/guides/core/routing.md
docs/guides/access/access-control.md
docs/guides/ai/ai-overview.md
storage/docs/                          # Git-ignored, populated by docs:fetch
```

### New Composer Dependencies

- `league/commonmark` — Markdown to HTML
- `symfony/yaml` — Frontmatter parsing (verify if already pulled in by framework packages before adding)

No other new dependencies. Prism.js loaded client-side.

**Note:** The package count referenced throughout this spec should be reconciled with the actual packages in the framework repo during implementation. The manifest will be the authoritative list — only packages present in the manifest get doc pages.

### Modified Files

- `src/Provider/SiteServiceProvider.php` — Register DocsController routes and services
- `public/css/site.css` — Add docs layout styles
- `templates/base.html.twig` — Add "Docs" to nav links
- `.gitignore` — Add `storage/docs/`
- `deploy.php` — Add `docs:fetch` step
- `.github/workflows/deploy.yml` — Run fetch command during deploy
