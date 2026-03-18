---
title: Routing Guide
category: core
order: 101
description: Route definitions, controllers, middleware, and URL generation
---

# Routing Guide

The `waaseyaa/routing` package wraps Symfony Routing with a fluent `RouteBuilder` API and adds Waaseyaa-specific features: route-level access control, entity parameter upcasting, and language negotiation middleware.

## RouteBuilder Fluent API

Routes are defined using the `RouteBuilder` class. It provides a clean, chainable interface for building Symfony `Route` objects:

```php
use Waaseyaa\Routing\RouteBuilder;

$route = RouteBuilder::create('/articles/{article}')
    ->controller('App\Controller\ArticleController::view')
    ->entityParameter('article', 'article')
    ->requirePermission('access content')
    ->methods('GET')
    ->build();
```

### Creating Routes

Every route starts with `RouteBuilder::create()` and ends with `->build()`:

```php
// Simple page route
$home = RouteBuilder::create('/')
    ->controller('App\Controller\HomeController::index')
    ->methods('GET')
    ->build();

// Route with parameters
$view = RouteBuilder::create('/node/{node}')
    ->controller('App\Controller\NodeController::view')
    ->entityParameter('node', 'node')
    ->methods('GET')
    ->build();

// API route with multiple methods
$api = RouteBuilder::create('/api/articles/{article}')
    ->controller('App\Controller\Api\ArticleController::handle')
    ->entityParameter('article', 'article')
    ->methods('GET', 'PATCH', 'DELETE')
    ->build();
```

### Available Builder Methods

| Method | Purpose |
|---|---|
| `create(string $path)` | Start building a route for the given path |
| `controller(string\|callable $controller)` | Set the controller (class::method or callable) |
| `methods(string ...$methods)` | Set allowed HTTP methods (GET, POST, etc.) |
| `entityParameter(string $name, string $entityType)` | Upcast a URL parameter to an entity |
| `requirePermission(string $permission)` | Require a specific permission |
| `requireRole(string $role)` | Require a specific role |
| `requireAuth()` | Require an authenticated user |
| `public()` | Mark route as publicly accessible (no access check) |
| `render(bool $enabled)` | Enable SSR rendering for this route |
| `csrfExempt()` | Exempt from CSRF token validation |
| `jsonApi()` | Mark as a JSON:API route |
| `build()` | Build and return the Symfony Route object |

## Registering Routes

Routes are registered in service providers through the `routes()` method:

```php
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

class BlogServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function routes(
        WaaseyaaRouter $router,
        ?\Waaseyaa\Entity\EntityTypeManager $entityTypeManager = null,
    ): void {
        $router->addRoute('blog.list', RouteBuilder::create('/blog')
            ->controller('App\Controller\BlogController::list')
            ->methods('GET')
            ->public()
            ->build());

        $router->addRoute('blog.view', RouteBuilder::create('/blog/{node}')
            ->controller('App\Controller\BlogController::view')
            ->entityParameter('node', 'node')
            ->methods('GET')
            ->public()
            ->build());

        $router->addRoute('blog.create', RouteBuilder::create('/blog/new')
            ->controller('App\Controller\BlogController::create')
            ->methods('GET', 'POST')
            ->requirePermission('create node')
            ->build());
    }
}
```

The first argument to `addRoute()` is a unique route name used for URL generation and debugging.

## Entity Parameter Upcasting

One of the most powerful routing features is automatic entity loading. When you call `entityParameter()`, the router automatically:

1. Extracts the parameter value from the URL
2. Loads the entity from storage
3. Injects the fully loaded entity object into the controller

```php
// Route definition
$route = RouteBuilder::create('/articles/{article}')
    ->controller('App\Controller\ArticleController::view')
    ->entityParameter('article', 'article')
    ->build();

// Controller receives a loaded entity, not a raw ID
class ArticleController
{
    public function view(Article $article): Response
    {
        // $article is already loaded from the database.
        // If the entity doesn't exist, a 404 is returned automatically.
        return new Response($article->label());
    }
}
```

The parameter name in the route path (`{article}`) must match the parameter name in `entityParameter('article', ...)`. The second argument is the entity type ID.

## Route-Level Access Control

Waaseyaa integrates access control directly into route definitions using four access options:

### Public Routes

Routes marked as `public()` bypass all access checks:

```php
RouteBuilder::create('/about')
    ->controller('App\Controller\PageController::about')
    ->public()
    ->build();
```

### Permission-Based Access

Require a specific permission string:

```php
RouteBuilder::create('/admin/articles')
    ->controller('App\Controller\Admin\ArticleController::list')
    ->requirePermission('administer content')
    ->build();
```

### Role-Based Access

Require a specific user role:

```php
RouteBuilder::create('/admin/settings')
    ->controller('App\Controller\Admin\SettingsController::index')
    ->requireRole('administrator')
    ->build();
```

### Authentication Required

Require any authenticated user without checking specific permissions:

```php
RouteBuilder::create('/dashboard')
    ->controller('App\Controller\DashboardController::index')
    ->requireAuth()
    ->build();
```

### How Access Is Evaluated

The `AccessChecker` evaluates route access options in order:

1. If `_public` is set, access is granted immediately
2. If `_permission` is set, the user must have that permission
3. If `_role` is set, the user must have that role
4. If `_gate` is set, a custom gate callback is invoked
5. If none of the above are set, access is denied by default (deny-unless-granted)

This is a **deny-by-default** model. If you forget to add access options to a route, it will be inaccessible. This is a safety feature.

## Controller Method Signatures

Controllers are plain PHP classes. Waaseyaa resolves method parameters from the route match and the service container:

```php
class ArticleController
{
    // Constructor injection from the container
    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
    ) {}

    // Route parameters are injected by name
    public function view(Article $article): Response
    {
        return new Response($article->label());
    }

    // Request object is always available
    public function create(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            // Handle form submission
        }
        return new Response('Create form');
    }
}
```

## Language Negotiation Middleware

The routing package includes two language negotiation strategies:

### URL Prefix Negotiation

Detects language from URL prefixes like `/fr/articles` or `/en/about`:

```php
// Configured via waaseyaa.php
'i18n' => [
    'languages' => [
        ['id' => 'en', 'label' => 'English', 'is_default' => true],
        ['id' => 'fr', 'label' => 'French', 'is_default' => false],
    ],
],
```

The `UrlPrefixNegotiator` strips the language prefix from the URL before routing and makes the negotiated language available to the rest of the system.

### Accept Header Negotiation

The `AcceptHeaderNegotiator` reads the `Accept-Language` HTTP header for API clients that prefer header-based negotiation.

## CSRF Protection

By default, state-changing routes (POST, PUT, PATCH, DELETE) are protected by CSRF token validation. Routes that use their own authentication model (like API keys or JWT) can exempt themselves:

```php
RouteBuilder::create('/api/webhook')
    ->controller('App\Controller\WebhookController::receive')
    ->methods('POST')
    ->csrfExempt()
    ->build();
```

The `csrf_token()` Twig function is available in templates when the User middleware is active.

## SSR Rendering

Routes that serve HTML pages through the SSR system use the `render()` option:

```php
RouteBuilder::create('/articles/{article}')
    ->controller('App\Controller\ArticleController::view')
    ->entityParameter('article', 'article')
    ->render()
    ->build();
```

This enables the `SsrPageHandler` to handle path alias resolution, editorial visibility checks, language negotiation, and cache headers automatically.

## Next Steps

- **[Entity System](./entity-system.md)** — The entities that routes serve
- **[Access Control](../access/access-control.md)** — Deep dive into the permission model
- **[Core Concepts](../getting-started/concepts.md)** — Overview of the kernel lifecycle
