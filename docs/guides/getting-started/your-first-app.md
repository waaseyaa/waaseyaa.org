---
title: "Tutorial: Build a Todo App"
category: getting-started
order: 3
description: Build a working todo app from scratch with Waaseyaa
---

# Tutorial: Build a Todo App

In about 20 minutes you will have a small **todo list** in the browser: add tasks, mark them done, delete them, and (optionally) talk to the same data over JSON:API.

**Prerequisites:** a working Waaseyaa app from the [Installation](./installation.md) guide.

## How this tutorial is organized

You will do things in the order Waaseyaa projects are usually built:

| Step | You add… | Why it matters |
|------|----------|----------------|
| **1** | A `Todo` **entity class** | One PHP object = one row of todo data, with a little behavior (complete / toggle). |
| **2** | **Config** in `config/entity-types.php` | Tells the framework the fields (`title`, `completed`, …) and which class to use. |
| **3** | A **service provider** with **routes** | Maps URLs like `/todos` to controller methods. |
| **4** | **Twig** templates | All HTML lives here—not in the controller. |
| **5** | A **controller** | Loads and saves `Todo` entities, returns a Twig page or a redirect. |
| **6** | **Run** the dev server and try the UI | Confirms the loop works. |
| **7** | **JSON:API** (optional in practice, built-in) | The same `todo` type is also available at `/api/todo` for tools and clients. |

If a step ever feels like “too much at once,” read the code comments inside the examples first—they are written for first-time readers.

## Before you start: access and security (short)

Waaseyaa routes are **deny-by-default** unless you opt in (for example with `allowAll()` or a permission). This walkthrough uses **public, tutorial-friendly** settings so you can focus on entities and HTTP. For real sites, you will add authentication, permissions, and real CSRF handling—see the [Routing Guide](../core/routing-guide.md) and [Access Control](../access/access-control.md) after you finish here.

## 1. Define the entity class

**What is this file?**  
`Todo` is a normal PHP class that **extends** `ContentEntityBase`. It represents **one** todo: its text, whether it is completed, and so on. Waaseyaa will load and save it through the same entity APIs everywhere (browser, API, later tests).

**What you do in this step:** add `src/Entity/Todo.php` with:

- A **type id** so the framework knows *which* kind of content this is (`'todo'`).
- A small **keys** map so Waaseyaa knows which fields play the roles of *primary id*, *uuid*, and *human-readable title* (here, the `title` field is the “label” people see in lists and APIs).
- A couple of **methods** for behavior (`isCompleted`, `toggleCompleted`).

**Copy this code:**

```php
<?php

declare(strict_types=1);

namespace App\Entity;

use Waaseyaa\Entity\ContentEntityBase;

/**
 * One todo line item. The framework can construct this from a database row,
 * so keep the public constructor to `__construct(array $values = [])` as shown.
 */
class Todo extends ContentEntityBase
{
    /** The machine name of this type — must match `id` in `config/entity-types.php` (next step). */
    protected string $entityTypeId = 'todo';

    /**
     * Which field names in storage correspond to “id”, “uuid”, and the display title.
     * @var array<string, string>
     */
    protected array $entityKeys = [
        'id' => 'id',
        'uuid' => 'uuid',
        'label' => 'title', // “label” in APIs/UI maps to the `title` field
    ];

    public function __construct(array $values = [])
    {
        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }

    public function isCompleted(): bool
    {
        return (bool) $this->get('completed');
    }

    /** Toggle the `completed` field and return $this (fluent). */
    public function toggleCompleted(): static
    {
        return $this->set('completed', !$this->isCompleted());
    }
}
```

**In plain terms:** the `$entityKeys` table is wiring—think “which column is the id, which is the title,” not business logic. The helper methods keep “mark complete / undo” on the `Todo` object itself, which keeps controllers small later.

> **Deeper reading (optional):** Storage loads entities with `new Todo(values: $row)` from your fields only. If you add extra constructor parameters, you break that path unless you are doing advanced, intentional wiring. The [Entity System](../core/entity-system.md) guide covers the full `EntityType` and storage story.

## 2. Register the entity type

Open `config/entity-types.php` and add your `EntityType` so Waaseyaa knows the **field list** and the **PHP class** you wrote in step 1:

```php
<?php

declare(strict_types=1);

use Waaseyaa\Entity\EntityType;

// Other packages can also contribute types; this file returns an array of definitions.
return [
    new EntityType(
        id: 'todo', // used in routes and in `/api/todo` (see step 7)
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

`fieldDefinitions` is where you **declare** the fields; types come from the `waaseyaa/field` package. These names (`title`, `completed`, `priority`) are what you will read and write in PHP with `$todo->get('title')` and in Twig with `todo.label` / `todo.get('priority')` once templates exist.

## 3. Create the service provider (routes)

Create `src/Provider/TodoServiceProvider.php`. A **service provider** is a single place to register your routes and (in larger apps) services. This tutorial only needs **routes**—one URL to show the list, and a few `POST` URLs to create, toggle, and delete.

```php
<?php

declare(strict_types=1);

namespace App\Provider;

use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

/** HTTP routes for the tutorial todo pages (entity *definition* stays in `entity-types.php`). */
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
        // GET /todos — list page (Twig in step 4, controller in step 5).
        $router->addRoute('todo.list', RouteBuilder::create('/todos')
            ->controller('App\Controller\TodoController::list')
            ->methods('GET')
            ->allowAll() // public for this walkthrough; lock down in production
            ->build());

        // POST /todos — form fields: `title`, `priority`
        $router->addRoute('todo.create', RouteBuilder::create('/todos')
            ->controller('App\Controller\TodoController::create')
            ->methods('POST')
            ->allowAll()
            ->csrfExempt() // tutorial only — prefer real CSRF in production
            ->build());

        // POST /todos/{todo}/toggle — {todo} id is resolved to a `Todo` for the controller
        $router->addRoute('todo.toggle', RouteBuilder::create('/todos/{todo}/toggle')
            ->controller('App\Controller\TodoController::toggle')
            ->entityParameter('todo', 'todo') // path name `todo` + entity type id `todo`
            ->methods('POST')
            ->allowAll()
            ->csrfExempt()
            ->build());

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

`entityParameter('todo', 'todo')` means: “the `{todo}` piece of the path is a **todo** id; load the entity and pass it into the controller method as `$todo`.”

### Register the provider in Composer

The HTTP kernel only loads service providers you list in **`composer.json`**. A fresh [Installation](./installation.md) project has one provider, usually `App\Provider\AppServiceProvider`. **Append** your new class to the same `extra.waaseyaa.providers` array (do not remove the default entry):

```json
"waaseyaa": {
  "providers": [
    "App\\Provider\\AppServiceProvider",
    "App\\Provider\\TodoServiceProvider"
  ]
}
```

(Alternatively you could add the same `routes()` method to `AppServiceProvider` and skip a second class—this tutorial keeps a dedicated `TodoServiceProvider` so each file has one job.)

### Rebuild the manifest

After adding or changing providers, refresh compiled discovery data:

```bash
# Rebuild the compiled manifest (providers, policies, and similar) after changes.
php vendor/bin/waaseyaa optimize:manifest
```

## 4. Create the Twig templates

**Why before the controller?** The controller in the next step will call `$this->twig->render('todo/list.html.twig', …)`. It is less confusing to **create the template files first**, then write the PHP that fills them.

Put all **HTML** in Twig—never build big HTML strings in PHP.

Create `templates/todo/_todo_row.html.twig`:

```twig
{# One row: `todo` is the entity; `completed` changes label text and strikethrough. #}
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
{# Main page: form posts to POST /todos; rows use the partial above. #}
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Todos</title>
  <style>
    {# Completed rows get class `done` on <li> from the partial. #}
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

## 5. Create the controller

Create `src/Controller/TodoController.php`. The job here is small: read the request, get **storage** for the `todo` type, call **save** / **delete**, and return a **Response** (Twig HTML or a redirect). No heredoc HTML.

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

/**
 * Web requests only: delegate domain rules to `Todo` and keep responses simple.
 * HTML comes from `templates/todo/*.twig` (step 4).
 */
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
        $title = trim((string) $request->request->get('title', ''));
        if ($title === '') {
            return new RedirectResponse('/todos');
        }

        $storage = $this->entityTypeManager->getStorage('todo');
        $todo = new Todo([
            'title' => $title,
            'completed' => false,
            'priority' => $request->request->get('priority', 'normal'),
        ]);
        $todo->enforceIsNew(); // first save = INSERT, not UPDATE
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

`toggle` and `delete` receive a **ready-made** `Todo` because the router loaded it from the URL—if the id does not exist, you get a 404 before the controller runs.

**Why `enforceIsNew()` on create?** You set field values and then save; the framework must know you mean a **new** row. Calling `enforceIsNew()` means “treat the next save as an insert.”

## 6. Run it

```bash
# Dev server (default is often port 8080 — your project’s docs may differ).
php vendor/bin/waaseyaa serve
```

Open [http://localhost:8080/todos](http://localhost:8080/todos). You should get the empty list, then:

1. Add “Learn Waaseyaa entities” and click **Add**
2. Add a few more todos with different priorities
3. **Done** / **Undo** to toggle
4. **Delete** to remove

You have the full in-browser flow: **entity** → **config** → **routes** → **Twig** → **controller**.

## 7. Add the JSON:API endpoint

The API package can expose the same `todo` type over JSON:API, usually at `/api/todo` for your `id` in step 2. You do **not** have to add controller code for this in a basic app—the route is generated from registered types.

List todos:

```bash
# Anonymous GET is typically allowed for the generated collection route.
curl http://localhost:8080/api/todo \
  -H "Content-Type: application/vnd.api+json"
```

**Create** (you usually need a logged-in session or token; otherwise POST is rejected by default):

```json
{
  "data": {
    "type": "todo",
    "attributes": {
      "title": "Call the plumber",
      "completed": false,
      "priority": "high"
    }
  }
}
```

```bash
curl -X POST http://localhost:8080/api/todo \
  -H "Content-Type: application/vnd.api+json" \
  -d '{"data":{"type":"todo","attributes":{"title":"Call the plumber","completed":false,"priority":"high"}}}'
```

Invalid body (missing `title`):

```json
{
  "data": {
    "type": "todo",
    "attributes": {
      "completed": false
    }
  }
}
```

```bash
curl -X POST http://localhost:8080/api/todo \
  -H "Content-Type: application/vnd.api+json" \
  -d '{"data":{"type":"todo","attributes":{"completed":false}}}'
```

The browser and the API both read the same database—refresh `/todos` after a successful `POST` to see new rows.

## What you built

You added a `Todo` **entity** and **fields**, registered a **type**, **routes** for the UI, **Twig** for markup, a thin **controller**, and (for free) a **JSON:API** surface. That is the day-to-day Waaseyaa shape: model first, then route, then view, then wire HTTP.

## Optional: production-style next steps

- **Access policies** and locked-down routes: [Access Control](../access/access-control.md) and re-run the manifest when you add `#[PolicyAttribute]` classes:

```bash
php vendor/bin/waaseyaa optimize:manifest
```

- **Revisions and translations** on bigger content types: [Entity System](../core/entity-system.md).

## Next steps

- **[Core Concepts](./concepts.md)** — kernel, providers, config in one place
- **[Entity System](../core/entity-system.md)** — field types, storage, and more
- **[Routing Guide](../core/routing-guide.md)** — permissions, middleware, `RouteBuilder` details
- **[Access Control](../access/access-control.md)** — the deny-by-default model in depth
