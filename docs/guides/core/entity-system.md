---
title: Entity System
category: core
order: 100
description: Deep dive into entities, typed fields, revisions, and translations
---

The entity system is the heart of Waaseyaa. It provides a structured, typed content model with revisions, translations, and dynamic fields. This guide covers the `waaseyaa/entity` and `waaseyaa/field` packages in depth.

## Entity Type Definitions

Every entity in Waaseyaa is described by an `EntityType`, a readonly value object that declares the entity's structure and capabilities. For content entities you do not construct it by hand — the entity class declares its own metadata with attributes, and `EntityType::fromClass()` reflects them into the definition:

```php
use Waaseyaa\Entity\Attribute\ContentEntityKeys;
use Waaseyaa\Entity\Attribute\ContentEntityType;
use Waaseyaa\Entity\Attribute\Field;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\FieldReadLevel;

#[ContentEntityType(id: 'article', label: 'Article')]
#[ContentEntityKeys(label: 'title', bundle: 'bundle', revision: 'revision_id', langcode: 'langcode')]
class Article extends ContentEntityBase
{
    #[Field(label: 'Title', required: true, read: FieldReadLevel::Public)]
    public string $title = '';

    #[Field(type: 'text', label: 'Body', read: FieldReadLevel::Public)]
    public ?string $body = null;

    #[Field(type: 'entity_reference', label: 'Category', settings: ['target_type' => 'taxonomy_term'], read: FieldReadLevel::Public)]
    public ?int $category = null;
}

$articleType = EntityType::fromClass(
    Article::class,
    revisionable: true,
    translatable: true,
    bundleEntityType: 'article_type',
);
```

This defines an article entity type with revision tracking, translations, bundles, and three fields. The `EntityType` constructor still exists for definitions that have no field-attribute metadata — config entity types like `node_type` are registered that way — but its field-definitions slot is `@internal`: application code declares fields with `#[Field]` attributes, never by passing arrays to the constructor.

### Entity Keys

`#[ContentEntityKeys]` maps logical roles to field names:

| Key | Purpose | Example |
|---|---|---|
| `id` | Primary key | `'id'` |
| `uuid` | Universally unique identifier (auto-generated) | `'uuid'` |
| `label` | Human-readable title | `'title'` |
| `bundle` | Bundle/subtype discriminator | `'bundle'` |
| `revision` | Revision tracking ID | `'revision_id'` |
| `langcode` | Language code for translations | `'langcode'` |

Not all keys are required. A simple entity may only need `id`, `uuid`, and `label`.

## Class-level metadata (content entities)

Content entity classes can declare **`#[ContentEntityType(id: 'machine_name')]`** and **`#[ContentEntityKeys(...)]`** (`waaseyaa/entity`). At construction time, `ContentEntityBase`:

1. Reads merged metadata for the concrete class (child attributes override parents).
2. Fills omitted logical keys (`id`, `uuid`, `label`) with identity defaults when not present on the attribute.
3. Throws **`EntityMetadataException`** if a concrete subclass still has no resolvable type id (every public content entity class must declare `#[ContentEntityType]`).

**Strict registration:** when you register an `EntityType` whose PHP `class` extends `ContentEntityBase`, `EntityTypeManager` asserts:

- The class carries `#[ContentEntityType]` and its `id` matches the registered `EntityType::id()`.
- The sorted **`keys`** array on the `EntityType` matches the sorted keys resolved from class attributes.

Keep `config/entity-types.php` (or provider-registered types) aligned with the attributes on your entity class.

## Entity Base Classes

Waaseyaa provides three base classes for entities.

### EntityBase

The abstract root class implementing `EntityInterface`. All entities extend this:

```php
abstract class EntityBase implements EntityInterface
{
    protected string $entityTypeId = '';
    protected array $values = [];
    protected bool $enforceIsNew = false;
    protected array $entityKeys = [];

    public function id(): int|string|null;
    public function uuid(): string;
    public function label(): string;
    public function getEntityTypeId(): string;
    public function enforceIsNew(): void;
}
```

When an entity type declares a `uuid` key, the UUID is auto-generated on construction using `Symfony\Component\Uid\Uuid::v4()`.

For **content** subclasses, prefer **`#[ContentEntityType]`** / **`#[ContentEntityKeys]`** on the class instead of assigning `$entityTypeId` / `$entityKeys` in PHP properties—`ContentEntityBase` hydrates those values from metadata.

### ContentEntityBase

Extends `EntityBase` with fieldable capabilities. Use this for user-created content:

```php
abstract class ContentEntityBase extends EntityBase
    implements ContentEntityInterface
{
    public function hasField(string $name): bool;
    public function get(string $name): mixed;
    public function set(string $name, mixed $value): static;
    public function getFieldDefinitions(): array;
}
```

Declare **`#[ContentEntityType]`** / **`#[ContentEntityKeys]`** on the concrete class instead of duplicating `$entityTypeId` / `$entityKeys` properties—`ContentEntityBase` merges attributes into the constructor path automatically.

A content entity object represents one language at a time. Calling `getTranslation()` returns a separate entity object for the requested language rather than embedding all translations in a single object.

### ConfigEntityBase

Extends `EntityBase` for configuration entities. Config entities are exported to YAML and synced between environments:

```php
abstract class ConfigEntityBase extends EntityBase
    implements ConfigEntityInterface
{
    // Config entities use string IDs, not auto-increment integers.
    // They are exported and imported via the config sync system.
}
```

Config entities use string IDs because they need to be referenced by name across environments.

## Field Types

The `waaseyaa/field` package provides the typed field system. Each field type implements `FieldTypeInterface`:

```php
interface FieldTypeInterface
{
    /** Column schema for database storage */
    public static function schema(): array;

    /** Default settings for this field type */
    public static function defaultSettings(): array;

    /** Default value for new entities */
    public static function defaultValue(): mixed;

    /** JSON Schema for API and AI integration */
    public static function jsonSchema(): array;
}
```

Each method serves a specific purpose: `schema()` defines database columns, `defaultSettings()` provides field configuration defaults, `defaultValue()` sets initial values, and `jsonSchema()` enables API and AI integration.

### Built-in Field Types

| Type | Item Class | Description |
|---|---|---|
| `string` | `StringItem` | Short text (titles, names) |
| `text` | `TextItem` | Long text with optional format |
| `integer` | `IntegerItem` | Whole numbers |
| `float` | `FloatItem` | Floating-point numbers |
| `decimal` | `DecimalItem` | Exact decimal numbers (money, quantities) |
| `boolean` | `BooleanItem` | True/false flags |
| `date` / `datetime` | `DateItem` / `DateTimeItem` | Calendar dates and timestamps |
| `email` | `EmailItem` | Validated email addresses |
| `link` | `LinkItem` | URLs with optional link text |
| `enum` | `EnumItem` | Backed-enum values (inferred from enum-typed properties) |
| `list` | `ListItem` | Multi-value lists |
| `json` | `JsonItem` | Arbitrary structured data |
| `file` / `image` | `FileItem` / `ImageItem` | File and image references |
| `entity_reference` | `EntityReferenceItem` | Reference to another entity |

### Field Items and Field Lists

Fields are accessed through a layered API:

- **`FieldItemInterface`** is a single field value (e.g., one string value)
- **`FieldItemList`** is a list of field items (supports multi-value fields)
- **`FieldDefinition`** is metadata about the field (type, label, required, settings)

```php
// Access a field value directly
$title = $article->get('title');

// Set a field value
$article->set('title', 'Updated Title');

// Check if a field exists
if ($article->hasField('category')) {
    $category = $article->get('category');
}
```

These methods work on any content entity. The field system validates types at the storage layer.

### Define Fields with Attributes

You declare fields with `#[Field]` attributes on public typed properties of the entity class:

```php
#[Field(label: 'Title', required: true, read: FieldReadLevel::Public)]
public string $title = '';

#[Field(type: 'text', label: 'Body', read: FieldReadLevel::Public)]
public ?string $body = null;

#[Field(label: 'View Count', read: FieldReadLevel::Public)]
public int $viewsCount = 0;

#[Field(label: 'Featured', read: FieldReadLevel::Public)]
public bool $isFeatured = false;

#[Field(type: 'entity_reference', label: 'Author', settings: ['target_type' => 'user'], read: FieldReadLevel::Protected)]
public ?int $author = null;
```

When `type:` is omitted, it is inferred from the PHP property type (`string` → `string`, `int` → `integer`, `bool` → `boolean`, a backed enum → `enum`). Beyond type and label, an attribute can declare:

- **`read:`** — the field's read level (`FieldReadLevel::Public`, `Protected`, or `Internal`). Waaseyaa is fail-closed: fields without a read level are internal and cannot be read by application code. `Protected` reads require an account read context.
- **`required:`, `default:`, `settings:`** — validation and configuration.
- **`stored:`** — how the field persists: `FieldStorage::Column` materializes a dedicated SQL column; `FieldStorage::Data` keeps the value in the entity's JSON data blob.
- **`translatable:`, `revisionable:`** — per-field translation and revision participation.

## Entity Storage

The `waaseyaa/entity-storage` package provides SQL-backed persistence for entities. The public API is the **entity repository**, which you get from the `EntityTypeManager`:

```php
// Get the repository
$repository = $entityTypeManager->getRepository('article');

// CRUD operations
$article = $repository->create(['title' => 'My Article']); // new, unsaved, defaults applied
$repository->save($article);

$article  = $repository->find('42');
$articles = $repository->findMany([1, 2, 3]);
$latest   = $repository->findBy([], orderBy: ['id' => 'DESC'], limit: 10);

$repository->delete($article);
```

The repository handles entity hydration, language fallback, validation, and pre/post save and delete domain events, and delegates raw I/O to a storage driver. For queries `findBy()` cannot express, `getQuery()` returns an access-checked entity query builder — bind the acting account with `setAccount()` before `execute()`, or opt out explicitly with `accessCheck(false)` in trusted system contexts.

### Save New vs Existing Entities

Entities built with `$repository->create()` are marked new and `save()` performs an `INSERT`; entities loaded from storage are not, and `save()` performs an `UPDATE`.

To force an `INSERT` on a hand-constructed entity with a pre-set ID (e.g., imported data), call `enforceIsNew()`:

```php
$article = new Article(['id' => 100, 'title' => 'Imported Article']);
$article->enforceIsNew();
$repository->save($article); // Forces INSERT
```

This is useful when importing data with known IDs from another system.

### Schema Management

The entity storage package materializes SQL tables from registered `EntityType` definitions during kernel boot (and migrations cover framework-owned tables). After you change `config/entity-types.php` or add migrations, run:

```bash
php vendor/bin/waaseyaa migrate
```

Use `php vendor/bin/waaseyaa schema:check` when you need to detect drift between definitions and the live database.

## Revisions

Entity types with `revisionable: true` track a full history of changes. Each save creates a new revision. Declare the revision key on the class and pass the flag to `fromClass()`:

```php
#[ContentEntityType(id: 'article', label: 'Article')]
#[ContentEntityKeys(label: 'title', revision: 'revision_id')]
class Article extends ContentEntityBase { /* ... */ }

EntityType::fromClass(Article::class, revisionable: true);
```

A revisionable entity type must declare a non-empty `revision` key — the definition is rejected otherwise. The repository exposes revision history via `loadRevision()` and copy-forward rollback via `rollback()`.

Revisionable entities implement `RevisionableInterface`, providing access to revision metadata and history. Combined with the `waaseyaa/workflows` package, revisions power editorial workflows with draft, review, and published states.

## Translations

Entity types with `translatable: true` support multiple languages. Each translation is a separate entity object:

```php
#[ContentEntityType(id: 'article', label: 'Article')]
#[ContentEntityKeys(label: 'title', langcode: 'langcode')]
class Article extends ContentEntityBase { /* ... */ }

EntityType::fromClass(Article::class, translatable: true);
```

Translatable entities implement `TranslatableInterface`. The language is negotiated at the routing layer via `UrlPrefixNegotiator` or `AcceptHeaderNegotiator` from the routing package.

## The EntityTypeManager

The `EntityTypeManager` is the central registry for all entity types:

```php
interface EntityTypeManagerInterface
{
    /** Get the definition for an entity type */
    public function getDefinition(string $entityTypeId): EntityType;

    /** Get the repository for an entity type */
    public function getRepository(string $entityTypeId): EntityRepositoryInterface;

    /** Get all registered entity type definitions */
    public function getDefinitions(): array;
}
```

You use this interface to look up entity type definitions, get repositories, and list all registered types. (A `getStorage()` seam also exists for entity types that bring their own `EntityStorageInterface` implementation via `storageClass`; first-party persistence goes through repositories.)

Service providers register entity types using the `entityType()` helper method:

```php
public function register(): void
{
    $this->entityType(EntityType::fromClass(Article::class));
}
```

This registers the entity type during the provider's `register()` phase, making it available to the rest of the application.

## AI Integration

The `jsonSchema()` method on field types is what enables Waaseyaa's AI-native capabilities. The `waaseyaa/ai-schema` package reads entity type definitions and field definitions to automatically generate JSON Schema and MCP tool definitions. AI agents can interact with your custom entity types without any additional configuration.

See the [AI Overview](../ai/ai-overview.md) for details on how the AI packages work together.

## Next Steps

- **[Routing Guide](./routing-guide.md)** — Route to your entities with controllers and middleware
- **[Access Control](../access/access-control.md)** — Protect entity operations with policies
- **[Your First App](../getting-started/your-first-app.md)** — A hands-on walkthrough
