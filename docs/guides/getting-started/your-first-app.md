---
title: "Tutorial: Build a Todo App"
category: getting-started
order: 3
description: Build a working todo app from scratch with Waaseyaa
---

# Tutorial: Build a Todo App

In this tutorial you will build a working todo application from scratch. By the end you will have:

- A custom `Todo` entity type with typed fields
- A service provider that registers the entity and routes
- A controller that handles creating, completing, and deleting todos
- Twig templates that render the todo list
- A JSON:API endpoint for programmatic access

The full tutorial takes about 20 minutes. You should have a working Waaseyaa project before starting. If you do not, follow the [Installation](./installation.md) guide first.

## Current Defaults (Read First)

Waaseyaa uses a **deny-by-default** route model. Every route must opt into access (`allowAll()`, `requireAuthentication()`, `requirePermission()`, etc.), or it will be denied.

This tutorial keeps routes public so you can focus on entity and routing fundamentals first. In production, you should tighten access and avoid broad CSRF exemptions unless a route is intentionally machine-to-machine (for example, webhooks).

## 1. Define the Entity Class

Create `src/Entity/Todo.php`. Entity classes extend `ContentEntityBase` and **hardcode** `entityTypeId` and `entityKeys` as `protected` properties, then pass them to the parent constructor. That matches how production Waaseyaa apps (and `SqlEntityStorage`) expect subclasses to behave: the subclass constructor should only accept `$values` so storage can instantiate with `new Todo(values: $row)` — it must not declare a parameter named `entityTypeId` unless you intentionally use the generic storage injection path.

```php
<?php

declare(strict_types=1);

namespace App\Entity;

use Waaseyaa\Entity\ContentEntityBase;

class Todo extends ContentEntityBase
{
    protected string $entityTypeId = 'todo';

    /** @var array<string, string> */
    protected array $entityKeys = [
        'id' => 'id',
        'uuid' => 'uuid',
        'label' => 'title',
    ];

    public function __construct(array $values = [])
    {
        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }

    public function isCompleted(): bool
    {
        return (bool) $this->get('completed');
    }

    public function toggleCompleted(): static
    {
        return $this->set('completed', !$this->isCompleted());
    }
}
```

The `$entityKeys` array maps logical keys to field names. The `label` key tells Waaseyaa which field provides the human-readable title. The convenience methods `isCompleted()` and `toggleCompleted()` keep the completion logic on the entity where it belongs.

## 2. Register the Entity Type

Open `config/entity-types.php` and register the new entity type:

```php
<?php

declare(strict_types=1);

use Waaseyaa\Entity\EntityType;

return [
    new EntityType(
        id: 'todo',
        label: 'Todo',
        class: \App\Entity\Todo::class,
        keys: [
            'id' => 'id',
            'uuid' => 'uuid',
            'label' => 'title',
        ],
        fieldDefinitions: [
            'title' => [
                'type' => 'string',
                'label' => 'Title',
                'required' => true,
            ],
            'completed' => [
                'type' => 'boolean',
                'label' => 'Completed',
            ],
            'priority' => [
                'type' => 'string',
                'label' => 'Priority',
            ],
        ],
    ),
];
```

The `fieldDefinitions` array declares the fields on this entity type. Each field specifies a type (matching field types from the `waaseyaa/field` package) and a label.

## 3. Create the Service Provider

Service providers register routes, bindings, and entity types. Create `src/Provider/TodoServiceProvider.php`:

```php
<?php

declare(strict_types=1);

namespace App\Provider;

use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

class TodoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // No additional bindings needed for this tutorial.
    }

    public function routes(
        WaaseyaaRouter $router,
        ?\Waaseyaa\Entity\EntityTypeManager $entityTypeManager = null,
    ): void {
        // List all todos
        $router->addRoute('todo.list', RouteBuilder::create('/todos')
            ->controller('App\Controller\TodoController::list')
            ->methods('GET')
            ->allowAll()
            ->build());

        // Create a new todo (form submission)
        $router->addRoute('todo.create', RouteBuilder::create('/todos')
            ->controller('App\Controller\TodoController::create')
            ->methods('POST')
            ->allowAll()
            ->csrfExempt()
            ->build());

        // Toggle a todo's completed status
        $router->addRoute('todo.toggle', RouteBuilder::create('/todos/{todo}/toggle')
            ->controller('App\Controller\TodoController::toggle')
            ->entityParameter('todo', 'todo')
            ->methods('POST')
            ->allowAll()
            ->csrfExempt()
            ->build());

        // Delete a todo
        $router->addRoute('todo.delete', RouteBuilder::create('/todos/{todo}/delete')
            ->controller('App\Controller\TodoController::delete')
            ->entityParameter('todo', 'todo')
            ->methods('POST')
            ->allowAll()
            ->csrfExempt()
            ->build());
    }
}
```

The `routes()` method uses the `RouteBuilder` fluent API to define four routes. `allowAll()` marks them as publicly accessible (no authentication required). `entityParameter()` tells the router to automatically load a `Todo` entity from the URL parameter, so your controller receives the entity object directly.

The POST routes are `csrfExempt()` in this tutorial for simplicity. For production forms, keep CSRF protection enabled and submit a token from your form or frontend client.

After creating the provider, rebuild the manifest so the kernel discovers it:

```bash
php vendor/bin/waaseyaa optimize:manifest
```

## 4. Create the Controller

Create `src/Controller/TodoController.php`. Controllers are thin orchestration layers that receive the request and return a response. This tutorial renders with Twig from the start (not inline HTML in controllers):

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Todo;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Waaseyaa\Entity\EntityTypeManagerInterface;

class TodoController
{
    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
        private readonly Environment $twig,
    ) {}

    public function list(): Response
    {
        $storage = $this->entityTypeManager->getStorage('todo');
        $todos = $storage->loadMultiple();

        $pending = array_filter($todos, fn (Todo $t) => !$t->isCompleted());
        $completed = array_filter($todos, fn (Todo $t) => $t->isCompleted());

        $html = $this->twig->render('todo/list.html.twig', [
            'todos' => $todos,
            'pending' => array_values($pending),
            'completed' => array_values($completed),
        ]);

        return new Response($html);
    }

    public function create(Request $request): Response
    {
        $title = trim($request->request->get('title', ''));
        if ($title === '') {
            return new RedirectResponse('/todos');
        }

        $storage = $this->entityTypeManager->getStorage('todo');
        $todo = new Todo([
            'title' => $title,
            'completed' => false,
            'priority' => $request->request->get('priority', 'normal'),
        ]);
        $todo->enforceIsNew();
        $storage->save($todo);

        return new RedirectResponse('/todos');
    }

    public function toggle(Todo $todo): Response
    {
        $todo->toggleCompleted();
        $storage = $this->entityTypeManager->getStorage('todo');
        $storage->save($todo);

        return new RedirectResponse('/todos');
    }

    public function delete(Todo $todo): Response
    {
        $storage = $this->entityTypeManager->getStorage('todo');
        $storage->delete([$todo]);

        return new RedirectResponse('/todos');
    }
}
```

The controller should not concatenate or heredoc HTML. All markup lives in Twig templates; PHP only loads data, persists entities, and returns `Response` objects (including the result of `$this->twig->render(...)`).

The controller has four actions. `list()` loads all todos and renders a Twig template. `create()` reads the form submission and persists a new `Todo` entity. `toggle()` flips the completed state using the entity's own method. `delete()` removes the entity from storage. All mutation actions redirect back to the list.

Notice how `toggle()` and `delete()` receive a fully loaded `Todo` entity as a parameter. The routing system's parameter upcaster handled the database lookup. If the entity does not exist, the framework returns a 404 automatically.

**Why `enforceIsNew()`?** When creating a `Todo` with pre-set values, Waaseyaa needs to know this is an INSERT, not an UPDATE. `enforceIsNew()` makes that explicit.

## 5. Run It

Start the development server:

```bash
php vendor/bin/waaseyaa serve
```

Visit [http://localhost:8080/todos](http://localhost:8080/todos). You should see an empty todo list with a form to add new items. Try it out:

1. Type "Learn Waaseyaa entities" and click **Add**
2. Add a few more todos with different priorities
3. Click **Done** to mark a todo as completed
4. Click **Undo** to revert it
5. Click **Delete** to remove one

You just built a working CRUD application with Waaseyaa's entity system, routing, and parameter upcasting.

## 6. Add the JSON:API Endpoint

Waaseyaa's API package automatically generates JSON:API endpoints for registered entity types. Your `todo` entity type already has a REST API at `/api/todo`.

Try it from the command line:

```bash
# List all todos
curl http://localhost:8080/api/todo \
  -H "Content-Type: application/vnd.api+json"

# Create a todo via the API (requires an authenticated account/session)
curl -X POST http://localhost:8080/api/todo \
  -H "Content-Type: application/vnd.api+json" \
  -d '{
    "data": {
      "type": "todo",
      "attributes": {
        "title": "Call the plumber",
        "completed": false,
        "priority": "high"
      }
    }
  }'
```

If you are not authenticated, the POST endpoint returns an authentication error by default. You can still use `GET /api/todo` anonymously.

After authenticating, refresh your browser. The todo you created via the API appears in the list. The HTML interface and the JSON:API share the same entity storage, so changes from either side are immediately visible.

You can also test an invalid payload to see error behavior:

```bash
# Missing required title (authenticated request) -> validation error response
curl -X POST http://localhost:8080/api/todo \
  -H "Content-Type: application/vnd.api+json" \
  -d '{
    "data": {
      "type": "todo",
      "attributes": {
        "completed": false
      }
    }
  }'
```

## 7. Create the Twig Templates

Put all HTML in Twig. A small partial keeps each todo row defined once (no duplicated markup between pending and completed lists).

Create `templates/todo/_todo_row.html.twig`:

```twig
<li class="{{ completed ? 'todo done' : 'todo' }}">
  <form method="POST" action="/todos/{{ todo.id }}/toggle" style="display:inline">
    <button type="submit">{{ completed ? 'Undo' : 'Done' }}</button>
  </form>
  <span class="title">{{ todo.label }}</span>
  <span class="priority">{{ todo.get('priority') ?? 'normal' }}</span>
  <form method="POST" action="/todos/{{ todo.id }}/delete" style="display:inline">
    <button type="submit">Delete</button>
  </form>
</li>
```

Create `templates/todo/list.html.twig`:

```twig
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Todos</title>
  <style>
    li.done .title { text-decoration: line-through; color: #999; }
  </style>
</head>
<body>
  <h1>Todos</h1>

  <form method="POST" action="/todos">
    <input type="text" name="title" placeholder="What needs to be done?" required>
    <select name="priority">
      <option value="normal">Normal</option>
      <option value="high">High</option>
      <option value="low">Low</option>
    </select>
    <button type="submit">Add</button>
  </form>

  <p>{{ todos|length }} items, {{ pending|length }} remaining</p>

  {% if pending is not empty %}
    <h2>Pending</h2>
    <ul>
      {% for todo in pending %}
        {% include 'todo/_todo_row.html.twig' with { todo: todo, completed: false } %}
      {% endfor %}
    </ul>
  {% endif %}

  {% if completed is not empty %}
    <h2>Completed</h2>
    <ul>
      {% for todo in completed %}
        {% include 'todo/_todo_row.html.twig' with { todo: todo, completed: true } %}
      {% endfor %}
    </ul>
  {% endif %}

  {% if todos is empty %}
    <p>No todos yet. Add one above.</p>
  {% endif %}
</body>
</html>
```

These templates are what `TodoController::list()` renders in step 4.

## What You Built

You defined a `Todo` entity with typed fields. You registered it as an entity type with field definitions. You created a service provider with routes using the `RouteBuilder` fluent API, built a controller with full CRUD operations and automatic entity parameter upcasting, and got a JSON:API endpoint for free.

That is the pattern for every Waaseyaa application: define your entities, register routes, wire a controller.

## Optional: Production-Grade Next Steps

These are not required for the tutorial, but they reflect current framework capabilities.

### Add Access Policies

For real apps, move beyond `allowAll()` routes and enforce entity access with `#[PolicyAttribute]` policies. After adding policy classes, rebuild the package manifest:

```bash
php vendor/bin/waaseyaa optimize:manifest
```

See the [Access Control](../access/access-control.md) guide for full policy patterns.

### Add Revisions and Translations

When your content model needs editorial history or multilingual content, extend the entity type with revision and language keys and enable:

- `revisionable: true`
- `translatable: true`

The [Entity System](../core/entity-system.md) guide shows the full key map (`bundle`, `revision`, `langcode`) and storage implications.

## Next Steps

- **[Core Concepts](./concepts.md)** to understand the full entity/field model, service provider lifecycle, and kernel architecture
- **[Entity System](../core/entity-system.md)** for revisions, translations, and all field types
- **[Routing Guide](../core/routing-guide.md)** for advanced routing with permissions, middleware, and URL generation
- **[Access Control](../access/access-control.md)** to add authentication and authorization to your routes
