---
title: Installation
category: getting-started
order: 2
description: Set up a new Waaseyaa project with Composer
---

# Installation

This guide walks you through creating a new Waaseyaa project from the official skeleton.

## Prerequisites

Before you begin, make sure you have:

- **PHP 8.3+** with the following extensions: `pdo_sqlite`, `mbstring`, `json`, `openssl`
- **Composer 2.x** ([getcomposer.org](https://getcomposer.org))
- **SQLite 3** (used as the default database)

Verify your PHP version:

```bash
php -v
# PHP 8.3.x or higher required
```

## Creating a New Project

Use Composer to create a project from the Waaseyaa skeleton:

```bash
composer create-project waaseyaa/waaseyaa my-site
cd my-site
```

This installs the skeleton with all core packages including foundation, entity, field, routing, access control, node content types, taxonomy, media, and the CLI.

## Project Directory Structure

After installation, your project looks like this:

```
my-site/
├── bin/
│   └── waaseyaa          # CLI entry point (console kernel)
├── config/
│   ├── waaseyaa.php      # Framework configuration
│   ├── entity-types.php  # Custom entity type definitions
│   ├── services.php      # Service overrides
│   └── sync/             # Configuration sync directory
├── files/                # Uploaded file storage
├── public/
│   └── index.php         # Web entry point (HTTP kernel)
├── src/
│   ├── Access/           # Authorization policies
│   ├── Controller/       # HTTP controllers
│   ├── Domain/           # Domain logic by bounded context
│   ├── Entity/           # Custom entity classes
│   ├── Ingestion/        # Inbound data pipelines
│   ├── Provider/         # Service providers
│   ├── Search/           # Search providers and indexing
│   ├── Seed/             # Dev/local data seeders
│   └── Support/          # Cross-cutting utilities
├── storage/              # Application storage (SQLite DB, caches)
├── templates/            # Twig templates for SSR
│   ├── home.html.twig
│   ├── page.html.twig
│   └── 404.html.twig
└── composer.json
```

### Key Directories

- **`src/Provider/`** — Your service providers register routes, entity types, bindings, and middleware
- **`src/Entity/`** — Custom entity classes extending `ContentEntityBase` or `ConfigEntityBase`
- **`src/Controller/`** — Thin HTTP controllers that orchestrate domain logic
- **`config/`** — Framework and application configuration files
- **`templates/`** — Twig templates rendered by the SSR package

## Configuration

The main configuration file is `config/waaseyaa.php`. Key settings include:

```php
<?php

return [
    // SQLite database path (defaults to {projectRoot}/waaseyaa.sqlite)
    'database' => null,

    // Config sync directory
    'config_dir' => getenv('WAASEYAA_CONFIG_DIR') ?: __DIR__ . '/sync',

    // File storage root
    'files_dir' => getenv('WAASEYAA_FILES_DIR') ?: __DIR__ . '/../storage/files',

    // CORS origins for the admin SPA
    'cors_origins' => ['http://localhost:3000'],

    // SSR theme and cache settings
    'ssr' => [
        'theme' => getenv('WAASEYAA_SSR_THEME') ?: '',
        'cache_max_age' => 300,
    ],

    // AI embedding pipeline (optional)
    'ai' => [
        'embedding_provider' => getenv('WAASEYAA_EMBEDDING_PROVIDER') ?: '',
    ],
];
```

Environment variables allow you to override settings per environment without changing the config file:

| Variable | Purpose | Default |
|---|---|---|
| `WAASEYAA_DB` | SQLite database path | `{projectRoot}/waaseyaa.sqlite` |
| `WAASEYAA_CONFIG_DIR` | Config sync directory | `config/sync/` |
| `WAASEYAA_FILES_DIR` | Uploaded file storage | `storage/files/` |
| `WAASEYAA_JWT_SECRET` | JWT signing secret for API auth | (empty) |
| `WAASEYAA_SSR_THEME` | Active SSR theme package | (empty) |

## Running the Development Server

Start the built-in development server:

```bash
bin/waaseyaa serve
```

This launches a PHP development server. Visit [http://localhost:8000](http://localhost:8000) to see the default welcome page.

## Verifying the Installation

### Check the welcome page

Open `http://localhost:8000` in your browser. You should see the Waaseyaa welcome page with links to the admin SPA, API endpoint, and CLI commands.

### Check the API

The JSON:API endpoint is available at `/api`:

```bash
curl http://localhost:8000/api/note \
  -H "Content-Type: application/vnd.api+json"
```

### Create your first content

Use the CLI to interact with your application:

```bash
# List available CLI commands
bin/waaseyaa list

# Create entity database tables
bin/waaseyaa entity:create

# Export configuration
bin/waaseyaa config:export
```

### Create a note via the API

Waaseyaa ships with a built-in `core.note` content type that is always available:

```bash
curl -X POST http://localhost:8000/api/note \
  -H "Content-Type: application/vnd.api+json" \
  -d '{
    "data": {
      "type": "note",
      "attributes": {
        "title": "Hello, Waaseyaa",
        "body": "My first note."
      }
    }
  }'
```

## Next Steps

Your Waaseyaa project is ready. Continue with:

- **[Your First App](./your-first-app.md)** — Define a custom entity type and build a page
- **[Core Concepts](./concepts.md)** — Understand the entity model, service providers, and kernel lifecycle
