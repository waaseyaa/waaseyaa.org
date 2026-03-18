---
title: Access Control Guide
category: access
order: 100
description: Understanding the deny-unless-granted permission model
---

# Access Control Guide

The `waaseyaa/access` package provides a permission-based access control system built on a **deny-unless-granted** model. Every operation is denied by default unless an access policy explicitly grants it. This guide covers the access model, policies, field-level permissions, and how to check access in controllers and templates.

## The Access Model

Waaseyaa's access control operates at two levels:

1. **Route-level access** — Evaluated by `AccessChecker` based on route options (`_public`, `_permission`, `_role`, `_gate`)
2. **Entity-level access** — Evaluated by `AccessGate` using registered `AccessPolicyInterface` implementations

Both levels return `AccessResult` values that combine to produce a final access decision.

## AccessResult

Every access check returns an `AccessResult` — a value object with one of four states:

```php
use Waaseyaa\Access\AccessResult;

// Grant access
AccessResult::allowed('User has the required permission');

// No opinion (defer to other policies)
AccessResult::neutral('This policy does not apply');

// Deny access (403 Forbidden)
AccessResult::forbidden('User lacks the required permission');

// No valid identity (401 Unauthenticated)
AccessResult::unauthenticated('No valid session');
```

### Combining Results

Results can be combined with AND and OR logic:

```php
// AND: both must be allowed
$result = $checkA->andIf($checkB);
// If either is Forbidden or Unauthenticated, that wins.
// Both must be Allowed for the combined result to be Allowed.

// OR: either can be allowed
$result = $checkA->orIf($checkB);
// If either is Forbidden or Unauthenticated, that wins.
// Either being Allowed yields Allowed.
```

The key rule: **Forbidden and Unauthenticated always win**, regardless of AND or OR combination. This ensures that an explicit denial cannot be overridden by another policy granting access.

### Checking Results

```php
$result->isAllowed();          // true if Allowed
$result->isForbidden();        // true if Forbidden
$result->isNeutral();          // true if Neutral (no opinion)
$result->isUnauthenticated();  // true if Unauthenticated
```

## Access Policies

Access policies define who can perform what operations on entities. They implement `AccessPolicyInterface`:

```php
use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityInterface;

class ArticleAccessPolicy implements AccessPolicyInterface
{
    /**
     * Check access for an existing entity.
     */
    public function access(
        EntityInterface $entity,
        string $operation,
        AccountInterface $account,
    ): AccessResult {
        return match ($operation) {
            'view' => AccessResult::allowed(),
            'update' => $account->hasPermission('edit articles')
                ? AccessResult::allowed()
                : AccessResult::forbidden('Cannot edit articles'),
            'delete' => $account->hasPermission('delete articles')
                ? AccessResult::allowed()
                : AccessResult::forbidden('Cannot delete articles'),
            default => AccessResult::neutral(),
        };
    }

    /**
     * Check access for creating a new entity.
     */
    public function createAccess(
        string $entityTypeId,
        string $bundle,
        AccountInterface $account,
    ): AccessResult {
        return $account->hasPermission('create articles')
            ? AccessResult::allowed()
            : AccessResult::forbidden('Cannot create articles');
    }

    /**
     * Whether this policy applies to the given entity type.
     */
    public function appliesTo(string $entityTypeId): bool
    {
        return $entityTypeId === 'article';
    }
}
```

### Registering Policies

Policies are discovered automatically via the `#[PolicyAttribute]` PHP attribute. The `PackageManifestCompiler` scans your `src/Access/` directory for classes with this attribute. After adding a new policy, rebuild the manifest:

```bash
bin/waaseyaa optimize:manifest
```

### The Three Operations

Entity access policies handle three standard operations:

| Operation | Method | When Checked |
|---|---|---|
| `view` | `access()` | Loading/displaying an entity |
| `update` | `access()` | Saving changes to an entity |
| `delete` | `access()` | Deleting an entity |
| (create) | `createAccess()` | Creating a new entity of a type/bundle |

The `access()` method receives the full entity object, so you can make decisions based on entity state (e.g., only the author can edit their own articles).

## Field-Level Access

For fine-grained control, the `FieldAccessPolicyInterface` lets you control access to individual fields:

```php
use Waaseyaa\Access\FieldAccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityInterface;

class ArticleFieldAccessPolicy implements FieldAccessPolicyInterface
{
    public function fieldAccess(
        EntityInterface $entity,
        string $fieldName,
        string $operation,
        AccountInterface $account,
    ): AccessResult {
        // Only admins can view the internal_notes field
        if ($fieldName === 'internal_notes' && $operation === 'view') {
            return $account->hasRole('administrator')
                ? AccessResult::allowed()
                : AccessResult::forbidden('Internal notes are restricted');
        }

        return AccessResult::neutral();
    }
}
```

Field-level access is evaluated per-field during entity serialization (API responses) and rendering (templates), ensuring sensitive fields are never leaked.

## Route-Level Access

The `AccessChecker` enforces access at the route level using options set through `RouteBuilder`:

```php
// Public: no access check
RouteBuilder::create('/about')
    ->public()
    ->build();

// Permission-based
RouteBuilder::create('/admin/content')
    ->requirePermission('administer content')
    ->build();

// Role-based
RouteBuilder::create('/admin/settings')
    ->requireRole('administrator')
    ->build();

// Authentication required
RouteBuilder::create('/dashboard')
    ->requireAuth()
    ->build();
```

### Evaluation Order

The `AccessChecker` evaluates route options in this order:

1. `_public: true` — Immediately grants access
2. `_permission` — Checks the account for a specific permission string
3. `_role` — Checks the account for a specific role
4. `_gate` — Invokes a custom gate callback
5. **Default: denied** — If no option matches, the route is inaccessible

This is the deny-unless-granted principle in action. Routes without explicit access options are locked down by default.

## The AccountInterface

All access checks receive an `AccountInterface` representing the current user:

```php
interface AccountInterface
{
    public function id(): int|string;
    public function hasPermission(string $permission): bool;
    public function hasRole(string $role): bool;
    public function isAuthenticated(): bool;
}
```

The `waaseyaa/user` package provides the concrete `User` entity and session management that populates the account on each request.

## Checking Access in Controllers

In controllers, you can check access programmatically through the `AccessGate`:

```php
class ArticleController
{
    public function __construct(
        private readonly AccessGate $accessGate,
        private readonly EntityTypeManagerInterface $entityTypeManager,
    ) {}

    public function edit(Article $article, AccountInterface $account): Response
    {
        $result = $this->accessGate->check($article, 'update', $account);

        if (!$result->isAllowed()) {
            return new Response('Access denied', 403);
        }

        // Proceed with edit form...
    }
}
```

For most cases, route-level access options are sufficient. Use programmatic checks when you need conditional logic that depends on the specific entity being accessed.

## Checking Access in Templates

The SSR package makes access checking available in Twig templates. You can conditionally render UI elements based on permissions:

```twig
{% if account.hasPermission('edit articles') %}
  <a href="/articles/{{ article.id }}/edit">Edit</a>
{% endif %}

{% if account.hasRole('administrator') %}
  <a href="/admin/settings">Settings</a>
{% endif %}
```

## Best Practices

### Use Neutral for Non-Applicable Policies

When a policy does not apply to a given operation, return `AccessResult::neutral()` rather than `allowed()` or `forbidden()`. This lets other policies make the decision:

```php
public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
{
    if ($operation !== 'view') {
        return AccessResult::neutral(); // Let other policies decide
    }
    // Check view access...
}
```

### Prefer Route-Level Access Over Controller Checks

Route-level access options are evaluated before the controller executes, which means:

- The controller never runs for unauthorized requests
- No risk of forgetting to check access in the controller body
- Access rules are visible in route definitions, not buried in controller logic

### Author-Based Access

Use the entity object in `access()` to implement ownership-based rules:

```php
public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
{
    if ($operation === 'update' && $entity->get('author_id') === $account->id()) {
        return AccessResult::allowed('Author can edit own content');
    }
    return AccessResult::neutral();
}
```

## Next Steps

- **[Routing Guide](../core/routing.md)** — Route-level access options in detail
- **[Entity System](../core/entity-system.md)** — The entities that policies protect
- **[AI Overview](../ai/ai-overview.md)** — How AI agents respect access control
