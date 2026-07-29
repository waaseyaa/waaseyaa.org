---
title: "Tutorial: Build a Todo App"
category: getting-started
order: 3
description: Build a working todo app from scratch with Waaseyaa
---

In about 20 minutes you will have a small **todo list** in the browser: add tasks, mark them done, delete them, and (optionally) talk to the same data over JSON:API.

**Prerequisites:** complete the [Installation](./installation.md) guide first, including `php vendor/bin/waaseyaa db:init`, so SQLite exists and core migrations have run. The examples below use the project directory name `my-site` (from `composer create-project waaseyaa/waaseyaa my-site`); adjust paths if you used a different folder name.

## How this tutorial is organized

You will do things in the order Waaseyaa projects are usually built:

| Step | You add… | Why it matters |
|------|----------|----------------|
| **1** | A `Todo` **entity class** | One PHP object = one row of todo data, with a little behavior (complete / toggle). |
| **2** | **Config** in `config/entity-types.php` | Tells the framework the fields (`title`, `completed`, …) and which class to use. |
| **3** | A **service provider** with **routes** | Maps URLs like `/todos` to controller methods and marks `{todo}` as an entity segment. |
| **4** | **Twig** templates | All HTML lives here—not in the controller. |
| **5** | A **controller** | Typed SSR actions: the framework loads `Todo` from the URL when you type-hint it—no manual `load()`. |
| **5.1** | An **access policy** for `todo` | JSON:API applies *view* / *create* access checks; a small policy makes public API behavior match your `allowAll()` web routes. |
| **6** | **Run** the dev server and try the UI | Confirms the loop works. |
| **7** | **JSON:API** (optional in practice, built-in) | The same `todo` type is also available at `/api/todo` for tools and clients. |

If a step ever feels like “too much at once,” read the code comments inside the examples first—they are written for first-time readers.

## Before you start: access and security (short)

Waaseyaa routes are **deny-by-default** unless you opt in (for example with `allowAll()` or a permission). This walkthrough uses **public, tutorial-friendly** settings on the **web** routes. **JSON:API** still evaluates **entity access** for list and create operations, so this tutorial adds a small **access policy** in step 5.1 after the controller. For real sites, you will add authentication, permissions, and real CSRF handling—see the [Routing Guide](../core/routing-guide.md) and [Access Control](../access/access-control.md) after you finish here.

## 1. Define the entity class

**What is this file?**  
`Todo` is a normal PHP class that **extends** `ContentEntityBase`. It represents **one** todo: its text, whether it is completed, and so on. Waaseyaa will load and save it through the same entity APIs everywhere (browser, API, later tests).

**What you do in this step:** add `src/Entity/Todo.php` with:

- **`#[ContentEntityType(id: 'todo', api: true)]`** — the machine name is required for **typed route binding** (step 5); `api: true` opts the type into **JSON:API** routes (step 7).
- **`#[ContentEntityKeys(label: 'title')]`** — maps the logical **label** key to your `title` field. Omitted keys (`id`, `uuid`, …) default to sensible identity column names; override them on the attribute if your storage uses different names (see [Entity system](../core/entity-system.md)).
- A **`#[Field]`** attribute on each public typed property — this is where fields are **declared**. The field type is inferred from the PHP property type (`string $title` → `string` field, `bool $completed` → `boolean`); pass `type:` explicitly when you want something else.
- **`read: FieldReadLevel::Public`** on each field — Waaseyaa is **fail-closed**: fields without a read level are treated as internal and cannot be read by application code. Public is right for this tutorial.
- A couple of **methods** for behavior (`isCompleted`, `toggleCompleted`).

**No** `$entityTypeId` / `$entityKeys` properties and **no** custom constructor: `ContentEntityBase` merges class attributes (including inherited `#[ContentEntityKeys]`) and fills defaults before calling `EntityBase`. Your app code can keep using `new Todo(['title' => '…'])`.

**Copy this code:**

```php
<?php

declare(strict_types=1);

namespace App\Entity;

use Waaseyaa\Entity\Attribute\ContentEntityKeys;
use Waaseyaa\Entity\Attribute\ContentEntityType;
use Waaseyaa\Entity\Attribute\Field;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\FieldReadLevel;

#[ContentEntityType(id: 'todo', label: 'Todo', api: true)]
#[ContentEntityKeys(label: 'title')]
class Todo extends ContentEntityBase
{
    #[Field(label: 'Title', required: true, read: FieldReadLevel::Public)]
    public string $title = '';

    #[Field(label: 'Completed', read: FieldReadLevel::Public)]
    public bool $completed = false;

    #[Field(label: 'Priority', read: FieldReadLevel::Public)]
    public string $priority = 'normal';

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

**In plain terms:** everything about the type—its machine name, keys, and **fields**—lives on the **class** as PHP 8 attributes. The field names (`title`, `completed`, `priority`) are what you will read and write in PHP with `$todo->get('title')` and in Twig with `todo.label` / `todo.get('priority')` once templates exist.

> **Deeper reading:** Hydration calls `ContentEntityBase::fromStorage()` with a context object; the default constructor path accepts `values` plus optional overrides. The canonical framework spec for SSR dispatch is [app-controller-invocation](https://github.com/waaseyaa/framework/blob/main/docs/specs/app-controller-invocation.md). The [Entity system](../core/entity-system.md) guide covers `EntityType`, attributes, and storage.

## 2. Register the entity type

Open `config/entity-types.php` and register the type. Because the class already carries its own metadata (step 1), registration is **one line**—`EntityType::fromClass()` reflects on the attributes and builds the full definition:

```php
<?php

declare(strict_types=1);

use Waaseyaa\Entity\EntityType;

// Other packages can also contribute types; this file returns an array of definitions.
return [
    EntityType::fromClass(\App\Entity\Todo::class),
];
```

`fromClass()` also accepts options the class itself does not express, such as `revisionable: true` or `translatable: true`—you will not need them here. (The `EntityType` constructor still exists for definitions without field-attribute metadata, like config entity types, but its field-definitions slot is `@internal`: application code declares fields with `#[Field]` attributes, never constructor arrays.)

## 3. Create the service provider (routes)

Create `src/Provider/TodoServiceProvider.php`. A **service provider** is a single place to register your routes and (in larger apps) services. This tutorial only needs **routes**—one URL to show the list, and a few `POST` URLs to create, toggle, and delete.

`entityParameter('todo', 'todo')` tells the router that `{todo}` is an **entity** segment (type `todo`). The SSR **app-controller invoker** will load the row and pass a `Todo` instance into any action parameter typed as `Todo`.

Optional **`bind('todo', Todo::class)`** adds an extra check after load: the concrete class must satisfy the binding (useful when multiple classes implement the same entity type edge case).

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
        $router->addRoute('todo.list', RouteBuilder::create('/todos')
            ->controller('App\Controller\TodoController::list')
            ->methods('GET')
            ->allowAll()
            ->build());

        $router->addRoute('todo.create', RouteBuilder::create('/todos')
            ->controller('App\Controller\TodoController::create')
            ->methods('POST')
            ->allowAll()
            ->csrfExempt() // tutorial only — prefer real CSRF in production
            ->build());

        $router->addRoute('todo.toggle', RouteBuilder::create('/todos/{todo}/toggle')
            ->controller('App\Controller\TodoController::toggle')
            ->entityParameter('todo', 'todo')
            ->bind('todo', \App\Entity\Todo::class)
            ->methods('POST')
            ->allowAll()
            ->csrfExempt()
            ->build());

        $router->addRoute('todo.delete', RouteBuilder::create('/todos/{todo}/delete')
            ->controller('App\Controller\TodoController::delete')
            ->entityParameter('todo', 'todo')
            ->bind('todo', \App\Entity\Todo::class)
            ->methods('POST')
            ->allowAll()
            ->csrfExempt()
            ->build());
    }
}
```

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

Create `src/Controller/TodoController.php`. SSR **app controllers** registered as `Class::method` strings are dispatched through `Waaseyaa\SSR\SsrPageHandler::dispatchAppController`, which uses **`AppControllerMethodInvoker`** to build action arguments:

- **Services** (constructor): same allowlist as before (`EntityTypeManagerInterface`, `Twig\Environment`, …).
- **Action parameters**: every parameter must have a **named type** (PHP 8.4). The invoker binds:
  - **Entities** — parameters whose type implements `EntityInterface`, when the route declares `entity:{type}` for a matching path key (after `entityParameter()`). The row is **loaded for you**; missing id → **404**, wrong class after load → **500** (`AppControllerTypeMismatchException`).
  - **`Symfony\Component\HttpFoundation\Request`** — the current request (form body, query, …).
  - **Scalars / backed enums** — from route attributes; invalid values → **400** (`InvalidAppControllerArgumentException`).
  - **`#[MapRoute]` / `#[MapQuery]`** — opt-in bags for the **entire** route params or query arrays when you really need them (strict mode discourages implicit “grab everything” signatures).

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
final class TodoController
{
    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
        private readonly Environment $twig,
    ) {}

    public function list(): Response
    {
        $repository = $this->entityTypeManager->getRepository('todo');
        $todos = $repository->findBy([]);

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

        $repository = $this->entityTypeManager->getRepository('todo');
        $todo = $repository->create([
            'title' => $title,
            'completed' => false,
            'priority' => $request->request->get('priority', 'normal'),
        ]);
        $repository->save($todo);

        return new RedirectResponse('/todos');
    }

    public function toggle(Todo $todo): Response
    {
        $todo->toggleCompleted();
        $this->entityTypeManager->getRepository('todo')->save($todo);

        return new RedirectResponse('/todos');
    }

    public function delete(Todo $todo): Response
    {
        $this->entityTypeManager->getRepository('todo')->delete($todo);

        return new RedirectResponse('/todos');
    }
}
```

**The repository is the persistence API.** `getRepository('todo')` returns the high-level entity repository: `create()` builds a new, unsaved entity with field defaults applied (so `save()` knows it is an **INSERT**), `save()` inserts or updates, `find()` loads by id, `findBy()` queries by criteria (an empty array returns everything), and `delete()` removes one entity.

**Why no manual `find()` on toggle/delete?** The invoker resolved `{todo}` to a `Todo` before your method runs. Invalid or unknown ids surface as **404** automatically—you do not need a private `resolveRouteTodo()` helper in application code.

**Strict mode reminder:** with strict app-controller mode (the default), your `Todo` class **must** declare `#[ContentEntityType]` so the binder can resolve the entity type id for `Todo` parameters. See [Routing guide](../core/routing-guide.md) and the framework spec linked above.

## 5.1. Add an access policy (JSON:API)

`RouteBuilder::allowAll()` applies to **the HTTP route**. The JSON:API **collection and document** handlers also run the **entity access** layer: each entity must get **`AccessResult::allowed()`** for `view` (and for create, a matching **create** check), or it will not appear in `GET` responses. To mirror this tutorial’s public web UI, add a small **access policy** for the `todo` type.

Create `src/Access/TodoAccessPolicy.php`:

```php
<?php

declare(strict_types=1);

namespace App\Access;

use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\PolicyAttribute;
use Waaseyaa\Entity\EntityInterface;

/**
 * Tutorial: open access for `todo` so JSON:API matches public web routes.
 * Replace with real permissions in production.
 */
#[PolicyAttribute(entityType: 'todo')]
final class TodoAccessPolicy implements AccessPolicyInterface
{
    public function appliesTo(string $entityTypeId): bool
    {
        return $entityTypeId === 'todo';
    }

    public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
    {
        return AccessResult::allowed('Tutorial: open view/update/delete for todo.');
    }

    public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
    {
        return $entityTypeId === 'todo'
            ? AccessResult::allowed('Tutorial: open create for todo.')
            : AccessResult::neutral('Not the todo type.');
    }
}
```

`#[PolicyAttribute(entityType: 'todo')]` registers the class with the package manifest. After adding the file, run:

```bash
php vendor/bin/waaseyaa optimize:manifest
```

(If you also changed providers, one run covers both.) See [Access Control](../access/access-control.md) for production patterns.

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

You have the full in-browser flow: **entity** → **config** → **routes** → **Twig** → **typed controller**.

## 7. Add the JSON:API endpoint

The API package exposes the same `todo` type over JSON:API at `/api/todo`—because step 1 declared **`api: true`** in `#[ContentEntityType]` (types opt in; there are no surprise API routes). You do **not** have to add handler code for this in a basic app. **You did** add an access policy in step 5.1 so `GET` returns resources instead of an empty `data` array.

List todos:

```bash
# With step 5.1, collection GET returns entities (open tutorial policy). Tighten for production.
curl http://localhost:8080/api/todo \
  -H "Content-Type: application/vnd.api+json"
```

**Create** (with the tutorial policy, POST may succeed; in locked-down sites you need a session or token):

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

You added a `Todo` **entity** whose class attributes carry the **type id, keys, and fields** (with explicit read levels), registered the **type** with a one-line `EntityType::fromClass()`, **routes** with entity segments and optional **`bind()`**, **Twig** for markup, a thin **typed SSR controller** doing CRUD through the **entity repository** (no `$params` / `$query`, no manual entity loading for `{todo}`), an **access policy** so **JSON:API** can list and mutate todos in line with the web UI, and (via one attribute flag) a **JSON:API** surface. That is the day-to-day Waaseyaa shape: model first, then route, then view, then wire HTTP and access.

## Optional: production-style next steps

- **Replace the tutorial `TodoAccessPolicy`** with real permissions, field-level rules, and authenticated accounts: [Access Control](../access/access-control.md). Re-run `php vendor/bin/waaseyaa optimize:manifest` whenever you add or change `#[PolicyAttribute]` classes.
- **Revisions and translations** on bigger content types: [Entity System](../core/entity-system.md).

## Next steps

- **[Core Concepts](./concepts.md)** — kernel, providers, config in one place
- **[Entity System](../core/entity-system.md)** — field types, storage, attributes, strict registration
- **[Routing Guide](../core/routing-guide.md)** — permissions, middleware, `RouteBuilder`, typed app controllers
- **[Access Control](../access/access-control.md)** — the deny-by-default model in depth
