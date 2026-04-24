---
title: Installation
category: getting-started
order: 2
description: Set up a new Waaseyaa project with Composer
---

# Installation

This guide walks you through creating a new Waaseyaa project from the official skeleton.

## Prerequisites

You need:

- **PHP 8.4+** with extensions: `pdo_sqlite`, `mbstring`, `json`, `openssl`
- **Composer 2.x** ([getcomposer.org](https://getcomposer.org))
- **SQLite 3** (the default database)

Verify your PHP version:

```bash
php -v
# PHP 8.4.x or higher required
```

This confirms PHP is installed and meets the minimum version requirement.

## Create a New Project

Use Composer to create a project from the Waaseyaa skeleton:

```bash
composer create-project waaseyaa/waaseyaa my-site --stability=dev
cd my-site
```

`waaseyaa/waaseyaa` is the published project skeleton package. The framework source itself lives in the separate `waaseyaa/framework` monorepo.

This installs the skeleton with all core packages: foundation, entity, field, routing, access control, node content types, taxonomy, media, and the CLI.

## Initialize the Database

Create the SQLite database and run all pending migrations:

```bash
php bin/waaseyaa db:init
```

This creates `storage/waaseyaa.sqlite` and initializes the framework tables and default entity storage tables.

## Project Directory Structure

After installation, your project looks like this:

```text
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
│   └── waaseyaa.sqlite   # SQLite database (after db:init)
├── templates/            # Twig templates for SSR
│   ├── home.html.twig
│   ├── page.html.twig
│   └── 404.html.twig
└── composer.json
```

### Key Directories

- **`src/Provider/`** - Your service providers register routes, entity types, bindings, and middleware
- **`src/Entity/`** - Custom entity classes extending `ContentEntityBase` or `ConfigEntityBase`
- **`src/Controller/`** - Thin HTTP controllers that orchestrate domain logic
- **`config/`** - Framework and application configuration files
- **`templates/`** - Twig templates rendered by the SSR package

## Configuration

The main configuration file is `config/waaseyaa.php`:

```php
<?php

return [
    // SQLite database path (defaults to {projectRoot}/storage/waaseyaa.sqlite)
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

This file sets database paths, file storage locations, CORS origins, SSR theme settings, and optional AI configuration.

Environment variables let you override settings per environment without changing the config file:

| Variable | Purpose | Default |
|---|---|---|
| `WAASEYAA_DB` | SQLite database path | `{projectRoot}/storage/waaseyaa.sqlite` |
| `WAASEYAA_CONFIG_DIR` | Config sync directory | `config/sync/` |
| `WAASEYAA_FILES_DIR` | Uploaded file storage | `storage/files/` |
| `WAASEYAA_JWT_SECRET` | JWT signing secret for API auth | (empty) |
| `WAASEYAA_SSR_THEME` | Active SSR theme package | (empty) |

## Run the Development Server

Start the built-in development server:

```bash
php bin/waaseyaa serve
```

This launches a PHP development server on port 8080. Visit [http://localhost:8080](http://localhost:8080) to see the default welcome page.

## Verify the Installation

### Check the Welcome Page

Open `http://localhost:8080` in your browser. You should see the Waaseyaa welcome page with links to the admin SPA, API endpoint, and CLI commands.

### Check the API

The JSON:API endpoint is available at `/api`:

```bash
curl http://localhost:8080/api/note \
  -H "Content-Type: application/vnd.api+json"
```

This returns a JSON:API response listing notes. The response will be empty until you create content.

### Create Entity Database Tables

Use the CLI to set up storage and verify the installation:

```bash
# List available CLI commands
php bin/waaseyaa list

# Create an entity interactively
php bin/waaseyaa entity:create node

# Export configuration
php bin/waaseyaa config:export
```

These commands confirm that the kernel boots, entity types are registered, and the config system works.

### Create a Note via the API

Waaseyaa ships with a built-in `core.note` content type that is always available:

```bash
curl -X POST http://localhost:8080/api/note \
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

This creates a note entity through the JSON:API endpoint and returns the created resource.

## Next Steps

Your Waaseyaa project is ready. Continue with:

- **[Build a Todo App](./your-first-app.md)** - Create a working app with entities, routes, and CRUD in 20 minutes
- **[Core Concepts](./concepts.md)** - Understand the entity model, service providers, and kernel lifecycle
