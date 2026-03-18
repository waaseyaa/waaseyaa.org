---
title: Entity System
category: core
order: 100
description: Deep dive into entities, typed fields, revisions, and translations
---

# Entity System

The entity system is the heart of Waaseyaa. It provides a structured, typed content model with revisions, translations, and dynamic fields. This guide covers the `waaseyaa/entity` and `waaseyaa/field` packages in depth.

## Entity Type Definitions

Every entity in Waaseyaa is described by an `EntityType`. This is a readonly value object that declares the entity's structure and capabilities:

```php
use Waaseyaa\Entity\EntityType;

new EntityType(
    id: 'article',
    label: 'Article',
    class: App\Entity\Article::class,
    storageClass: '', // Uses default SQL storage
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
    bundleEntityType: 'article_type',
    constraints: [],
    fieldDefinitions: [
        'title' => ['type' => 'string', 'label' => 'Title', 'required' => true],
        'body' => ['type' => 'text', 'label' => 'Body'],
        'category' => ['type' => 'entity_reference', 'label' => 'Category'],
    ],
);
```

This defines an article entity type with revision tracking, translations, bundles, and three fields.

### Entity Keys

The `keys` array maps logical roles to column names:

| Key | Purpose | Example |
|---|---|---|
| `id` | Primary key | `'id'` |
| `uuid` | Universally unique identifier (auto-generated) | `'uuid'` |
| `label` | Human-readable title | `'title'` |
| `bundle` | Bundle/subtype discriminator | `'bundle'` |
| `revision` | Revision tracking ID | `'revision_id'` |
| `langcode` | Language code for translations | `'langcode'` |

Not all keys are required. A simple entity may only need `id`, `uuid`, and `label`.

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

### ContentEntityBase

Extends `EntityBase` with fieldable capabilities. Use this for user-created content:

```php
abstract class ContentEntityBase extends EntityBase
    implements ContentEntityInterface
{
    protected array $fieldDefinitions = [];

    public function hasField(string $name): bool;
    public function get(string $name): mixed;
    public function set(string $name, mixed $value): static;
    public function getFieldDefinitions(): array;
}
```

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

| Type | Item Class | Schema Type | Description |
|---|---|---|---|
| `string` | `StringItem` | `VARCHAR` | Short text (titles, names) |
| `text` | `TextItem` | `TEXT` | Long text with optional format |
| `integer` | `IntegerItem` | `INTEGER` | Whole numbers |
| `float` | `FloatItem` | `FLOAT` | Decimal numbers |
| `boolean` | `BooleanItem` | `BOOLEAN` | True/false flags |
| `entity_reference` | `EntityReferenceItem` | `INTEGER` | Reference to another entity |

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

### Define Fields on Entity Types

You declare fields in the `fieldDefinitions` parameter of `EntityType`:

```php
'fieldDefinitions' => [
    'title' => [
        'type' => 'string',
        'label' => 'Title',
        'required' => true,
    ],
    'body' => [
        'type' => 'text',
        'label' => 'Body',
    ],
    'views_count' => [
        'type' => 'integer',
        'label' => 'View Count',
    ],
    'is_featured' => [
        'type' => 'boolean',
        'label' => 'Featured',
    ],
    'author' => [
        'type' => 'entity_reference',
        'label' => 'Author',
    ],
],
```

Each field definition specifies a type, a label, and optional constraints like `required`.

## Entity Storage

The `waaseyaa/entity-storage` package provides SQL-backed storage for entities. You access storage handlers through the `EntityTypeManager`:

```php
// Get the storage handler
$storage = $entityTypeManager->getStorage('article');

// CRUD operations
$article = $storage->load(42);
$articles = $storage->loadMultiple([1, 2, 3]);
$storage->save($article);
$storage->delete($article);
```

These four operations cover the full entity lifecycle: load, load multiple, save, and delete.

### Save New vs Existing Entities

When saving an entity, the storage handler checks whether it already has an ID:

- If the entity has no ID, it performs an `INSERT`
- If the entity has an ID, it performs an `UPDATE`

To force an `INSERT` on an entity with a pre-set ID (e.g., imported data), call `enforceIsNew()`:

```php
$article = new Article(['id' => 100, 'title' => 'Imported Article']);
$article->enforceIsNew();
$storage->save($article); // Forces INSERT
```

This is useful when importing data with known IDs from another system.

### Schema Management

The entity storage package manages database schema automatically. When you add a new entity type or modify field definitions, run the CLI to create or update tables:

```bash
bin/waaseyaa entity:create
```

This reads all registered entity type definitions and creates the corresponding database tables.

## Revisions

Entity types with `revisionable: true` track a full history of changes. Each save creates a new revision:

```php
new EntityType(
    id: 'article',
    label: 'Article',
    class: App\Entity\Article::class,
    keys: [
        'id' => 'id',
        'uuid' => 'uuid',
        'label' => 'title',
        'revision' => 'revision_id',
    ],
    revisionable: true,
);
```

Revisionable entities implement `RevisionableInterface`, providing access to revision metadata and history. Combined with the `waaseyaa/workflows` package, revisions power editorial workflows with draft, review, and published states.

## Translations

Entity types with `translatable: true` support multiple languages. Each translation is a separate entity object:

```php
new EntityType(
    id: 'article',
    label: 'Article',
    class: App\Entity\Article::class,
    keys: [
        'id' => 'id',
        'uuid' => 'uuid',
        'label' => 'title',
        'langcode' => 'langcode',
    ],
    translatable: true,
);
```

Translatable entities implement `TranslatableInterface`. The language is negotiated at the routing layer via `UrlPrefixNegotiator` or `AcceptHeaderNegotiator` from the routing package.

## The EntityTypeManager

The `EntityTypeManager` is the central registry for all entity types:

```php
interface EntityTypeManagerInterface
{
    /** Get the definition for an entity type */
    public function getDefinition(string $entityTypeId): EntityType;

    /** Get the storage handler for an entity type */
    public function getStorage(string $entityTypeId): EntityStorageInterface;

    /** Get all registered entity type definitions */
    public function getDefinitions(): array;
}
```

You use this interface to look up entity type definitions, get storage handlers, and list all registered types.

Service providers register entity types using the `entityType()` helper method:

```php
public function register(): void
{
    $this->entityType(new EntityType(
        id: 'article',
        label: 'Article',
        class: Article::class,
        keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'title'],
    ));
}
```

This registers the entity type during the provider's `register()` phase, making it available to the rest of the application.

## AI Integration

The `jsonSchema()` method on field types is what enables Waaseyaa's AI-native capabilities. The `waaseyaa/ai-schema` package reads entity type definitions and field definitions to automatically generate JSON Schema and MCP tool definitions. AI agents can interact with your custom entity types without any additional configuration.

See the [AI Overview](../ai/ai-overview.md) for details on how the AI packages work together.

## Next Steps

- **[Routing Guide](./routing.md)** — Route to your entities with controllers and middleware
- **[Access Control](../access/access-control.md)** — Protect entity operations with policies
- **[Your First App](../getting-started/your-first-app.md)** — A hands-on walkthrough
