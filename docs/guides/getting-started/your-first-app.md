---
title: Your First App
category: getting-started
order: 3
description: Build a simple content type from scratch with Waaseyaa
---

# Your First App

In this guide you will build a simple Article content type from scratch. By the end, you will have:

- A custom entity type with typed fields
- A service provider that registers the entity and routes
- A controller that loads and displays articles
- A Twig template that renders the article

## Step 1: Define the Entity Class

Create `src/Entity/Article.php`. Entity classes extend `ContentEntityBase` and declare their entity type ID and key mappings:

```php
<?php

declare(strict_types=1);

namespace App\Entity;

use Waaseyaa\Entity\ContentEntityBase;

class Article extends ContentEntityBase
{
    protected string $entityTypeId = 'article';

    protected array $entityKeys = [
        'id' => 'id',
        'uuid' => 'uuid',
        'label' => 'title',
    ];
}
```

The `$entityKeys` array maps logical keys to column names. The `label` key tells Waaseyaa which field provides the human-readable title.

## Step 2: Register the Entity Type

Open `config/entity-types.php` and register your new entity type:

```php
<?php

declare(strict_types=1);

use Waaseyaa\Entity\EntityType;

return [
    new EntityType(
        id: 'article',
        label: 'Article',
        class: \App\Entity\Article::class,
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
            'body' => [
                'type' => 'text',
                'label' => 'Body',
            ],
            'published_at' => [
                'type' => 'string',
                'label' => 'Published Date',
            ],
        ],
    ),
];
```

The `fieldDefinitions` array declares the fields available on this entity type. Each field specifies a type (matching field types from the `waaseyaa/field` package), a label, and optional constraints.

## Step 3: Create the Database Tables

Run the CLI command to create the storage tables for your new entity type:

```bash
bin/waaseyaa entity:create
```

This reads the entity type definitions and creates the corresponding SQLite tables.

## Step 4: Create a Service Provider

Service providers are the central place to register routes, bindings, and entity types. Create `src/Provider/ArticleServiceProvider.php`:

```php
<?php

declare(strict_types=1);

namespace App\Provider;

use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

class ArticleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register any service bindings here.
    }

    public function routes(
        WaaseyaaRouter $router,
        ?\Waaseyaa\Entity\EntityTypeManager $entityTypeManager = null,
    ): void {
        $router->addRoute('article.list', RouteBuilder::create('/articles')
            ->controller('App\Controller\ArticleController::list')
            ->methods('GET')
            ->build());

        $router->addRoute('article.view', RouteBuilder::create('/articles/{article}')
            ->controller('App\Controller\ArticleController::view')
            ->entityParameter('article', 'article')
            ->methods('GET')
            ->build());
    }
}
```

The `routes()` method uses the `RouteBuilder` fluent API to define routes. The `entityParameter()` call tells the router to automatically load an `Article` entity from the URL parameter.

### Register the Provider

After creating the provider, run the manifest compiler so the kernel discovers it:

```bash
bin/waaseyaa optimize:manifest
```

## Step 5: Create the Controller

Create `src/Controller/ArticleController.php`. Controllers are thin orchestration layers that receive the request and return a response:

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Article;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Entity\EntityTypeManagerInterface;

class ArticleController
{
    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
    ) {}

    public function list(): Response
    {
        $storage = $this->entityTypeManager->getStorage('article');
        $articles = $storage->loadMultiple();

        $html = '<h1>Articles</h1><ul>';
        foreach ($articles as $article) {
            $html .= sprintf(
                '<li><a href="/articles/%s">%s</a></li>',
                $article->id(),
                htmlspecialchars($article->label()),
            );
        }
        $html .= '</ul>';

        return new Response($html);
    }

    public function view(Article $article): Response
    {
        // The $article parameter is automatically loaded by the
        // entity parameter upcaster thanks to entityParameter().
        return new Response(sprintf(
            '<h1>%s</h1><div>%s</div><p><em>Published: %s</em></p>',
            htmlspecialchars($article->label()),
            $article->get('body') ?? '',
            $article->get('published_at') ?? 'Draft',
        ));
    }
}
```

Notice that the `view()` method receives a fully loaded `Article` entity. The routing system's parameter upcaster handled the database lookup automatically.

## Step 6: Use Twig Templates (Optional)

For richer rendering, create a Twig template instead of inline HTML. The SSR package resolves templates by entity type automatically.

Create `templates/article/view.html.twig`:

```twig
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>{{ article.label }}</title>
</head>
<body>
  <main>
    <h1>{{ article.label }}</h1>

    {% if article.get('published_at') %}
      <time>{{ article.get('published_at') }}</time>
    {% endif %}

    <div class="body">
      {{ article.get('body')|raw }}
    </div>
  </main>

  <footer>
    <a href="/articles">Back to articles</a>
  </footer>
</body>
</html>
```

Update your controller to render the template using the Twig environment:

```php
public function view(Article $article): Response
{
    $html = $this->twig->render('article/view.html.twig', [
        'article' => $article,
    ]);

    return new Response($html);
}
```

## Step 7: Seed Some Data

Create a seeder to populate your development database. Create `src/Seed/ArticleSeeder.php`:

```php
<?php

declare(strict_types=1);

namespace App\Seed;

use App\Entity\Article;
use Waaseyaa\Entity\EntityTypeManagerInterface;

class ArticleSeeder
{
    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
    ) {}

    public function run(): void
    {
        $storage = $this->entityTypeManager->getStorage('article');

        $articles = [
            ['title' => 'Getting Started with Waaseyaa', 'body' => 'Welcome to Waaseyaa...', 'published_at' => '2026-01-15'],
            ['title' => 'Understanding Entities', 'body' => 'Entities are the core...', 'published_at' => '2026-02-01'],
            ['title' => 'Building with AI', 'body' => 'Waaseyaa includes four AI packages...', 'published_at' => '2026-03-01'],
        ];

        foreach ($articles as $values) {
            $article = new Article($values);
            $article->enforceIsNew();
            $storage->save($article);
        }
    }
}
```

## Step 8: Test the Full Flow

Start the development server and verify everything works:

```bash
bin/waaseyaa serve
```

1. Visit `http://localhost:8000/articles` to see the article list
2. Click an article title to view the full article
3. Try creating articles via the JSON:API endpoint:

```bash
curl -X POST http://localhost:8000/api/article \
  -H "Content-Type: application/vnd.api+json" \
  -d '{
    "data": {
      "type": "article",
      "attributes": {
        "title": "My First Article",
        "body": "<p>Hello from the API!</p>",
        "published_at": "2026-03-18"
      }
    }
  }'
```

## What You Built

In this guide you:

- Defined an `Article` entity class extending `ContentEntityBase`
- Registered the entity type with typed field definitions
- Created a service provider with routes using the `RouteBuilder` fluent API
- Built a controller with automatic entity parameter upcasting
- Created a Twig template for rich HTML rendering
- Seeded development data

## Next Steps

- **[Core Concepts](./concepts.md)** — Understand the full entity/field model, service provider lifecycle, and kernel architecture
- **[Entity System](../core/entity-system.md)** — Deep dive into revisions, translations, and field types
- **[Routing Guide](../core/routing.md)** — Advanced routing with permissions, middleware, and URL generation
