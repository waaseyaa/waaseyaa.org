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

The `routes()` method uses the `RouteBuilder` fluent API to define four routes. `allowAll()` marks them as public (no authentication required). `entityParameter()` tells the router to automatically load a `Todo` entity from the URL parameter, so your controller receives the entity object directly.

After creating the provider, rebuild the manifest so the kernel discovers it:

```bash
php vendor/bin/waaseyaa optimize:manifest
```

## 4. Create the Controller

Create `src/Controller/TodoController.php`. Controllers are thin orchestration layers that receive the request and return a response:

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Todo;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Entity\EntityTypeManagerInterface;

class TodoController
{
    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
    ) {}

    public function list(): Response
    {
        $storage = $this->entityTypeManager->getStorage('todo');
        $todos = $storage->loadMultiple();

        $html = $this->render($todos);

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

    private function render(array $todos): string
    {
        $pending = array_filter($todos, fn (Todo $t) => !$t->isCompleted());
        $completed = array_filter($todos, fn (Todo $t) => $t->isCompleted());

        $html = <<<'HTML'
        <!doctype html>
        <html lang="en">
        <head>
          <meta charset="utf-8">
          <meta name="viewport" content="width=device-width, initial-scale=1">
          <title>Todos</title>
          <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: system-ui, sans-serif; max-width: 600px; margin: 2rem auto; padding: 0 1rem; color: #1a1a1a; }
            h1 { margin-bottom: 1.5rem; }
            h2 { font-size: 1rem; color: #666; margin: 1.5rem 0 0.5rem; text-transform: uppercase; letter-spacing: 0.05em; }
            form.add { display: flex; gap: 0.5rem; margin-bottom: 1rem; }
            form.add input[type="text"] { flex: 1; padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px; font-size: 1rem; }
            form.add select { padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px; }
            form.add button { padding: 0.5rem 1rem; background: #0d4f4f; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 1rem; }
            form.add button:hover { background: #0f766e; }
            ul { list-style: none; }
            li { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0; border-bottom: 1px solid #eee; }
            li.done .title { text-decoration: line-through; color: #999; }
            .title { flex: 1; }
            .priority { font-size: 0.75rem; padding: 0.15rem 0.4rem; border-radius: 3px; background: #e5e7eb; color: #374151; }
            .priority.high { background: #fee2e2; color: #991b1b; }
            .priority.low { background: #dbeafe; color: #1e40af; }
            .btn { padding: 0.25rem 0.5rem; border: 1px solid #ccc; border-radius: 4px; background: white; cursor: pointer; font-size: 0.85rem; }
            .btn:hover { background: #f3f4f6; }
            .btn.delete { color: #dc2626; border-color: #fca5a5; }
            .btn.delete:hover { background: #fef2f2; }
            .empty { color: #999; padding: 1rem 0; }
            .count { color: #666; font-size: 0.9rem; margin-bottom: 1rem; }
          </style>
        </head>
        <body>
          <h1>Todos</h1>

          <form class="add" method="POST" action="/todos">
            <input type="text" name="title" placeholder="What needs to be done?" required>
            <select name="priority">
              <option value="normal">Normal</option>
              <option value="high">High</option>
              <option value="low">Low</option>
            </select>
            <button type="submit">Add</button>
          </form>
        HTML;

        $totalCount = count($todos);
        $pendingCount = count($pending);
        $html .= sprintf('<p class="count">%d item%s, %d remaining</p>', $totalCount, $totalCount === 1 ? '' : 's', $pendingCount);

        if ($pending !== []) {
            $html .= '<h2>Pending</h2><ul>';
            foreach ($pending as $todo) {
                $html .= $this->renderTodoItem($todo);
            }
            $html .= '</ul>';
        }

        if ($completed !== []) {
            $html .= '<h2>Completed</h2><ul>';
            foreach ($completed as $todo) {
                $html .= $this->renderTodoItem($todo);
            }
            $html .= '</ul>';
        }

        if ($todos === []) {
            $html .= '<p class="empty">No todos yet. Add one above.</p>';
        }

        $html .= '</body></html>';

        return $html;
    }

    private function renderTodoItem(Todo $todo): string
    {
        $id = $todo->id();
        $title = htmlspecialchars($todo->label());
        $doneClass = $todo->isCompleted() ? ' done' : '';
        $toggleLabel = $todo->isCompleted() ? 'Undo' : 'Done';
        $priority = htmlspecialchars($todo->get('priority') ?? 'normal');

        return <<<HTML
        <li class="todo{$doneClass}">
          <form method="POST" action="/todos/{$id}/toggle" style="display:inline">
            <button class="btn" type="submit">{$toggleLabel}</button>
          </form>
          <span class="title">{$title}</span>
          <span class="priority {$priority}">{$priority}</span>
          <form method="POST" action="/todos/{$id}/delete" style="display:inline">
            <button class="btn delete" type="submit">Delete</button>
          </form>
        </li>
        HTML;
    }
}
```

The controller has four actions. `list()` loads all todos and renders inline HTML. `create()` reads the form submission and persists a new `Todo` entity. `toggle()` flips the completed state using the entity's own method. `delete()` removes the entity from storage. All mutation actions redirect back to the list.

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

# Create a todo via the API
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

Refresh your browser. The todo you created via the API appears in the list. The HTML interface and the JSON:API share the same entity storage, so changes from either side are immediately visible.

## 7. Use Twig Templates (Optional)

The inline HTML works, but for a real application you would use Twig templates. Create `templates/todo/list.html.twig`:

```twig
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Todos</title>
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
      <li>
        <form method="POST" action="/todos/{{ todo.id }}/toggle" style="display:inline">
          <button type="submit">Done</button>
        </form>
        <span>{{ todo.label }}</span>
        <span>{{ todo.get('priority') ?? 'normal' }}</span>
        <form method="POST" action="/todos/{{ todo.id }}/delete" style="display:inline">
          <button type="submit">Delete</button>
        </form>
      </li>
    {% endfor %}
    </ul>
  {% endif %}

  {% if completed is not empty %}
    <h2>Completed</h2>
    <ul>
    {% for todo in completed %}
      <li>
        <form method="POST" action="/todos/{{ todo.id }}/toggle" style="display:inline">
          <button type="submit">Undo</button>
        </form>
        <span style="text-decoration: line-through; color: #999">{{ todo.label }}</span>
        <form method="POST" action="/todos/{{ todo.id }}/delete" style="display:inline">
          <button type="submit">Delete</button>
        </form>
      </li>
    {% endfor %}
    </ul>
  {% endif %}

  {% if todos is empty %}
    <p>No todos yet. Add one above.</p>
  {% endif %}
</body>
</html>
```

Then update `TodoController::list()` to render the template:

```php
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
```

## What You Built

You defined a `Todo` entity with typed fields. You registered it as an entity type with field definitions. You created a service provider with routes using the `RouteBuilder` fluent API, built a controller with full CRUD operations and automatic entity parameter upcasting, and got a JSON:API endpoint for free.

That is the pattern for every Waaseyaa application: define your entities, register routes, wire a controller.

## Next Steps

- **[Core Concepts](./concepts.md)** to understand the full entity/field model, service provider lifecycle, and kernel architecture
- **[Entity System](../core/entity-system.md)** for revisions, translations, and all field types
- **[Routing Guide](../core/routing.md)** for advanced routing with permissions, middleware, and URL generation
- **[Access Control](../access/access-control.md)** to add authentication and authorization to your routes
