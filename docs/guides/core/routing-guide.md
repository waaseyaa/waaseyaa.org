---
title: Routing Guide
category: core
order: 101
description: Route definitions, controllers, middleware, and URL generation
---

The `waaseyaa/routing` package wraps Symfony Routing with a fluent `RouteBuilder` API. It adds route-level access control, entity parameter upcasting, and language negotiation middleware.

## RouteBuilder Fluent API

You define routes using the `RouteBuilder` class. It provides a chainable interface for building Symfony `Route` objects:

```php
use Waaseyaa\Routing\RouteBuilder;

$route = RouteBuilder::create('/articles/{article}')
    ->controller('App\Controller\ArticleController::view')
    ->entityParameter('article', 'article')
    ->requirePermission('access content')
    ->methods('GET')
    ->build();
```

This creates a route that loads an article entity from the URL, checks the `access content` permission, and calls the `view` method on `ArticleController`.

### Create Routes

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

Each example shows a different pattern: a static page, an entity view page, and a multi-method API endpoint.

### Available Builder Methods

| Method | Purpose |
|---|---|
| `create(string $path)` | Start building a route for the given path |
| `controller(string\|callable $controller)` | Set the controller (class::method or callable) |
| `methods(string ...$methods)` | Set allowed HTTP methods (GET, POST, etc.) |
| `entityParameter(string $name, string $entityType)` | Mark a path placeholder as `entity:{type}` for typed SSR binding |
| `bind(string $name, string $class)` | Require loaded entity for `{name}` to satisfy `class-string` |
| `requirePermission(string $permission)` | Require a specific permission |
| `requireRole(string $role)` | Require a specific role |
| `requireAuthentication()` | Require an authenticated user |
| `allowAll()` | Mark route as publicly accessible (no access check) |
| `render(bool $enabled)` | Enable SSR rendering for this route |
| `csrfExempt()` | Exempt from CSRF token validation |
| `jsonApi()` | Mark as a JSON:API route |
| `build()` | Build and return the Symfony Route object |

## Register Routes in Service Providers

You register routes in service providers through the `routes()` method:

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
            ->allowAll()
            ->build());

        $router->addRoute('blog.view', RouteBuilder::create('/blog/{node}')
            ->controller('App\Controller\BlogController::view')
            ->entityParameter('node', 'node')
            ->methods('GET')
            ->allowAll()
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

## Entity route segments (`entityParameter`)

`RouteBuilder::entityParameter($name, $entityTypeId)` stores metadata on the Symfony `Route` so the path placeholder `{name}` is treated as **`entity:{entityTypeId}`**. Extraction still happens during routing, but **loading** happens later when Waaseyaa dispatches an SSR **app controller** (`Waaseyaa\SSR\SsrPageHandler::dispatchAppController`):

1. The matched route supplies raw attribute values (e.g. `{ "article" => "42" }`).
2. `AppControllerMethodInvoker` sees an action parameter typed as `Article` (implements `EntityInterface`) and loads via `EntityTypeManagerInterface::getStorage($entityTypeId)->load($rawId)`.
3. A successful load passes the entity instance into your method; failures become HTTP errors (see **Typed app controllers** below).

Optional **`->bind('article', Article::class)`** records `_waaseyaa_app_bindings['article'] = Article::class` so, after load, Waaseyaa verifies `is_a($entity, Article::class)`—handy when multiple PHP classes could back the same storage row.

```php
$router->addRoute('article.view', RouteBuilder::create('/articles/{article}')
    ->controller('App\Controller\ArticleController::view')
    ->entityParameter('article', 'article')
    ->bind('article', \App\Entity\Article::class)
    ->methods('GET')
    ->allowAll()
    ->build());
```

The `{article}` placeholder name, the `entityParameter` name, the optional `bind` name, and the **`$article` type-hint** must line up (or use `#[FromRoute('custom')]` on the parameter—see framework spec).

## Route-Level Access Control

Waaseyaa integrates access control directly into route definitions.

### Public Routes

Routes marked as `allowAll()` bypass all access checks:

```php
RouteBuilder::create('/about')
    ->controller('App\Controller\PageController::about')
    ->allowAll()
    ->build();
```

Use this for pages that anyone can view without authentication.

### Permission-Based Access

Require a specific permission string:

```php
RouteBuilder::create('/admin/articles')
    ->controller('App\Controller\Admin\ArticleController::list')
    ->requirePermission('administer content')
    ->build();
```

The framework checks whether the current user has the `administer content` permission before executing the controller.

### Role-Based Access

Require a specific user role:

```php
RouteBuilder::create('/admin/settings')
    ->controller('App\Controller\Admin\SettingsController::index')
    ->requireRole('administrator')
    ->build();
```

Only users with the `administrator` role can access this route.

### Authentication Required

Require any authenticated user without checking specific permissions:

```php
RouteBuilder::create('/dashboard')
    ->controller('App\Controller\DashboardController::index')
    ->requireAuthentication()
    ->build();
```

Any logged-in user can access this route. Anonymous users get a 401 response.

### How Access Is Evaluated

The `AccessChecker` evaluates route access options in order:

1. If `_public` is set, access is granted immediately
2. If `_permission` is set, the user must have that permission
3. If `_role` is set, the user must have that role
4. If `_gate` is set, a custom gate callback is invoked
5. If none of the above are set, access is denied by default

This is a **deny-by-default** model. If you forget to add access options to a route, it will be inaccessible. This is intentional.

## Typed app controllers (SSR `Class::method`)

SSR routes that point at **`App\Controller\…::method`** strings are **not** Symfony controller services. Waaseyaa builds the controller instance (constructor injection via reflection + optional service resolver), then invokes the action through **`Waaseyaa\SSR\Http\AppController\AppControllerMethodInvoker`**.

### What the invoker supports

| Parameter kind | How it resolves |
|----------------|-----------------|
| **Framework services** | `Symfony\Component\HttpFoundation\Request`, `Waaseyaa\Access\AccountInterface`, `Waaseyaa\Entity\EntityTypeManagerInterface` / `EntityTypeManager`, `Twig\Environment`, `Waaseyaa\Access\Gate\GateInterface` (when configured), plus exact types returned by your HTTP service resolver |
| **Content entities** | Type-hint `Article`, `Todo`, … **implements `EntityInterface`** + matching `entityParameter()` on the route + `#[ContentEntityType]` on the class (strict mode) |
| **Scalars & backed enums** | From the route attribute named after the parameter (camelCase → snake_case fallback) or an explicit **`#[FromRoute('segment')]`** attribute on the parameter |
| **Whole bags** | `#[MapRoute]` on `array $params`, `#[MapQuery]` on `array $query` — opt-in; strict mode avoids implicit “everything” signatures |

Duplicate identical service types in one method signature are invalid in **strict** mode. Strict mode defaults **on** (`config['app_controller']['strict']` or `WAASEYAA_APP_CONTROLLER_STRICT`).

### Error semantics (dispatch)

| Situation | Exception | Typical HTTP |
|-----------|-----------|----------------|
| Entity id missing / not found | `Symfony\Component\Routing\Exception\ResourceNotFoundException` | **404** |
| Scalar / enum cannot be cast | `InvalidAppControllerArgumentException` | **400** |
| Binding / reflection programmer error | `InvalidAppControllerBindingException`, `AppControllerTypeMismatchException` | **500** |

Exact response shapes (JSON:API vs HTML) follow existing middleware conventions for `_render` routes—see the framework spec [app-controller-invocation](https://github.com/waaseyaa/framework/blob/main/docs/specs/app-controller-invocation.md).

### Example controller

```php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ArticleController
{
    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
    ) {}

    public function view(Article $article): Response
    {
        return new Response($article->label());
    }

    public function create(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            // read $request->request / $request->query
        }

        return new Response('Create form');
    }
}
```

Constructor parameters are resolved from Waaseyaa’s controller rules (same allowlist style as action services). Action parameters use **typed injection only**: `Request`, entity types bound from the route, scalars and backed enums from route attributes, and `#[MapRoute]` / `#[MapQuery]` when you need the full params or query bag.

## Language Negotiation Middleware

The routing package includes two language negotiation strategies.

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

This exempts the webhook endpoint from CSRF checks because it uses its own authentication.

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
