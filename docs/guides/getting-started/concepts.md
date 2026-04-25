---
title: Core Concepts
category: getting-started
order: 4
description: Understanding entities, fields, service providers, and the kernel lifecycle
---

# Core Concepts

This guide covers the foundational concepts every Waaseyaa developer needs: the entity/field model, service providers, the kernel lifecycle, and the package system.

## The Entity/Field Model

Waaseyaa's content model is inspired by Drupal but rebuilt with modern PHP. Every piece of structured content is an **entity**. Articles, users, taxonomy terms, media. All entities.

### Entity Types

An entity type is a definition that describes a class of entities. You define it with the `EntityType` value object:

```php
use Waaseyaa\Entity\EntityType;

$articleType = new EntityType(
    id: 'article',
    label: 'Article',
    class: App\Entity\Article::class,
    keys: [
        'id' => 'id',
        'uuid' => 'uuid',
        'label' => 'title',
        'bundle' => 'bundle',
        'revision' => 'revision_id',
        'langcode' => 'langcode',
    ],
    revisionable: true,
    translatable: true,
    fieldDefinitions: [
        'title' => ['type' => 'string', 'label' => 'Title', 'required' => true],
        'body' => ['type' => 'text', 'label' => 'Body'],
    ],
);
```

This defines an article entity type with revision tracking, translation support, and two fields.

Key properties:

| Property | Purpose |
|---|---|
| `id` | Machine name used throughout the system |
| `class` | PHP class that extends `EntityBase` |
| `keys` | Maps logical keys (id, uuid, label) to field names |
| `revisionable` | Whether this type tracks revision history |
| `translatable` | Whether this type supports multiple languages |
| `fieldDefinitions` | Declares the typed fields on this entity |
| `bundleEntityType` | Optional: the config entity that provides bundles (e.g., `node_type` for `node`) |

### Content Entities vs Config Entities

Waaseyaa has two kinds of entities:

- **Content entities** (`ContentEntityBase`) store user-created content like articles, comments, and media. They are fieldable, potentially revisionable, and translatable.
- **Config entities** (`ConfigEntityBase`) store site configuration like content types, vocabularies, and workflows. They are exported to YAML and synced between environments.

```php
// Content entity: stores user content
class Article extends ContentEntityBase
{
    protected string $entityTypeId = 'article';
    protected array $entityKeys = ['id' => 'id', 'uuid' => 'uuid', 'label' => 'title'];
}

// Config entity: defines structure
class ArticleType extends ConfigEntityBase
{
    protected string $entityTypeId = 'article_type';
    protected array $entityKeys = ['id' => 'id', 'label' => 'label'];
}
```

Content entities use auto-increment integer IDs. Config entities use string IDs and are exported/imported through the config sync system.

### Fields and Typed Data

Every content entity implements `FieldableInterface`, giving it dynamic, typed fields:

```php
interface FieldableInterface
{
    public function hasField(string $name): bool;
    public function get(string $name): mixed;
    public function set(string $name, mixed $value): static;
    public function getFieldDefinitions(): array;
}
```

This interface is how you read and write field values on any content entity.

The `waaseyaa/field` package provides the field type system. Built-in field types:

| Field Type | Class | Description |
|---|---|---|
| `string` | `StringItem` | Short text values |
| `text` | `TextItem` | Long text with optional format |
| `integer` | `IntegerItem` | Integer values |
| `float` | `FloatItem` | Floating-point numbers |
| `boolean` | `BooleanItem` | True/false values |
| `entity_reference` | `EntityReferenceItem` | References to other entities |

Field types implement `FieldTypeInterface`:

```php
interface FieldTypeInterface
{
    public static function schema(): array;
    public static function defaultSettings(): array;
    public static function defaultValue(): mixed;
    public static function jsonSchema(): array;
}
```

The `jsonSchema()` method is what enables the AI packages to automatically generate JSON Schema definitions from your entity fields.

### Entity Lifecycle

You manage entities through storage handlers. Here is the typical lifecycle:

```php
// Get the storage handler for an entity type
$storage = $entityTypeManager->getStorage('article');

// Create a new entity
$article = new Article([
    'title' => 'My Article',
    'body' => 'Content here...',
]);
$article->enforceIsNew(); // Force INSERT (not UPDATE)
$storage->save($article);

// Load an entity by ID
$article = $storage->load(1);

// Update
$article->set('title', 'Updated Title');
$storage->save($article);

// Load multiple
$articles = $storage->loadMultiple([1, 2, 3]);
```

Call `enforceIsNew()` before saving entities with pre-set IDs to force an `INSERT` rather than an `UPDATE`.

## Service Providers

Service providers are the central mechanism for bootstrapping your application. They register services, entity types, routes, and middleware.

### The ServiceProvider Class

Every provider extends `Waaseyaa\Foundation\ServiceProvider\ServiceProvider`:

```php
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

class ArticleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register bindings in the container
        $this->singleton(
            ArticleRepository::class,
            fn () => new ArticleRepository($this->resolve(EntityTypeManagerInterface::class)),
        );
    }

    public function boot(): void
    {
        // Runs after all providers are registered.
        // Use for setup that depends on other services.
    }

    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
        // Register HTTP routes
    }

    public function middleware(EntityTypeManager $entityTypeManager): array
    {
        // Return HTTP middleware instances
        return [];
    }

    public function commands(EntityTypeManager $entityTypeManager, PdoDatabase $database): array
    {
        // Return CLI commands
        return [];
    }
}
```

Each method has a specific role in the boot sequence. The `register()` method binds services. The `boot()` method runs after all providers are registered.

### Lifecycle Methods

| Method | When It Runs | Purpose |
|---|---|---|
| `register()` | First, for all providers | Bind services into the container |
| `boot()` | After all providers registered | Cross-provider setup |
| `routes()` | During HTTP kernel boot | Register route definitions |
| `middleware()` | During HTTP kernel boot | Register middleware classes |
| `commands()` | During console kernel boot | Register CLI commands |

### Container Bindings

Providers offer `singleton()`, `bind()`, `tag()`, and `resolve()` for dependency management:

```php
public function register(): void
{
    // Singleton: same instance every time
    $this->singleton(CacheInterface::class, fn () => new MemoryCache());

    // Bind: new instance every time
    $this->bind(LoggerInterface::class, fn () => new FileLogger('/tmp/app.log'));

    // Tags: group services for bulk retrieval
    $this->tag(ArticlePolicy::class, 'access_policy');
}
```

Singletons are resolved once and reused. Bindings create a fresh instance on every call. Tags group related services so you can retrieve them all at once.

## The Kernel Lifecycle

The kernel is the entry point for every request. Waaseyaa provides two kernels:

- **`HttpKernel`** handles web requests (`public/index.php`)
- **`ConsoleKernel`** handles CLI commands (`php vendor/bin/waaseyaa`)

Both extend `AbstractKernel` from the `waaseyaa/foundation` package.

### HTTP Request Lifecycle

```
1. public/index.php creates HttpKernel
2. Kernel discovers and registers all ServiceProviders
3. PackageManifestCompiler scans for providers, policies, middleware
4. register() called on every provider
5. boot() called on every provider
6. Router matches the request to a route
7. Middleware pipeline executes (authentication, CSRF, etc.)
8. AccessChecker evaluates route access options
9. Parameter upcasters convert route params to entities
10. Controller method executes and returns Response
11. Response sent to the client
```

This sequence runs on every HTTP request. Steps 2-5 are cached after the first boot when you run `optimize:manifest`.

### Boot Optimization

The `PackageManifestCompiler` scans your application for providers, access policies, and middleware using PHP attributes. After adding new providers or policies, rebuild the manifest:

```bash
php vendor/bin/waaseyaa optimize:manifest
```

This compiles discovery results into a cached manifest, avoiding runtime reflection.

## The Package System

Waaseyaa's architecture is defined by how packages compose together.

### Layer Rules

Each package declares its architectural layer (0-6). A package may only import from packages at its own layer or below. This is enforced by convention and tested in CI:

```
Layer 0 (Foundation) depends on: nothing
Layer 1 (Core Data)  depends on: Layer 0
Layer 2 (Services)   depends on: Layers 0-1
...and so on
```

This strict layering prevents circular dependencies and keeps the architecture clean.

### Composability

You install only the packages you need. If you only need entities and routing without AI or SSR, install `waaseyaa/core`. If you need everything, install `waaseyaa/full`.

### Package Interfaces

Every package exposes its public API through interfaces:

- `EntityTypeManagerInterface` — not `EntityTypeManager`
- `ConfigFactoryInterface` — not `ConfigFactory`
- `AccessPolicyInterface` — not a concrete policy class

You can swap implementations for testing (using in-memory backends) or extend behavior without touching framework internals.

## Next Steps

- **[Entity System](../core/entity-system.md)** — Deep dive into entity types, revisions, and translations
- **[Routing Guide](../core/routing-guide.md)** — The RouteBuilder API, middleware, and access control
- **[Access Control](../access/access-control.md)** — The deny-unless-granted permission model
