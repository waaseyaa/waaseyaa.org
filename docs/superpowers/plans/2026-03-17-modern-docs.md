# Modern Documentation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a hybrid documentation section to waaseyaa.org that combines hand-written guides with auto-fetched package READMEs, rendered through a unified markdown pipeline.

**Architecture:** A CLI command fetches package READMEs from GitHub at deploy time into `storage/docs/`. A DocsController serves `/docs` routes, rendering markdown (both fetched packages and local guides) to HTML via league/commonmark. Navigation is built from a generated `index.json`. All docs extend the existing dark/amber brand via a two-column Twig layout.

**Tech Stack:** PHP 8.4, Waaseyaa framework (routing, SSR, CLI, service providers), league/commonmark, symfony/yaml, Twig, Prism.js

**Spec:** `docs/superpowers/specs/2026-03-17-modern-docs-design.md`

---

## File Map

| File | Action | Responsibility |
|---|---|---|
| `composer.json` | Modify | Add league/commonmark, symfony/yaml |
| `config/docs-manifest.php` | Create | Package → category/order/title/description mapping |
| `src/Service/DocsRenderer.php` | Create | Parse frontmatter + convert markdown to HTML |
| `src/Service/DocsFetcher.php` | Create | Fetch READMEs from GitHub API, write to storage |
| `src/Service/DocsNavigationBuilder.php` | Create | Build index.json from guides + packages |
| `src/Command/DocsFetchCommand.php` | Create | CLI command wrapping DocsFetcher + NavigationBuilder |
| `src/Controller/DocsController.php` | Create | Handle /docs routes, render pages |
| `src/Provider/SiteServiceProvider.php` | Modify | Register docs routes + services |
| `templates/docs/layout.html.twig` | Create | Two-column docs layout (sidebar + content) |
| `templates/docs/landing.html.twig` | Create | /docs index with category cards |
| `templates/docs/category.html.twig` | Create | Category page listing all pages |
| `templates/docs/page.html.twig` | Create | Individual doc page with prev/next |
| `templates/base.html.twig` | Modify | Add "Docs" nav link |
| `public/css/site.css` | Modify | Add docs layout + markdown content styles |
| `.gitignore` | Modify | Add storage/docs/ |
| `deploy.php` | Modify | Add docs:fetch task |
| `.github/workflows/deploy.yml` | Modify | Run docs:fetch during deploy |
| `docs/guides/getting-started/introduction.md` | Create | Guide: What is Waaseyaa |
| `docs/guides/getting-started/installation.md` | Create | Guide: Installation |
| `docs/guides/getting-started/your-first-app.md` | Create | Guide: Build first app |
| `docs/guides/getting-started/concepts.md` | Create | Guide: Core concepts |
| `docs/guides/core/entity-system.md` | Create | Guide: Entity deep dive |
| `docs/guides/core/routing.md` | Create | Guide: Routing |
| `docs/guides/access/access-control.md` | Create | Guide: Access control |
| `docs/guides/ai/ai-overview.md` | Create | Guide: AI packages |

---

## Task 1: Add Composer Dependencies

**Files:**
- Modify: `composer.json`

- [ ] **Step 1: Check if symfony/yaml is already available**

Run: `php -r "require 'vendor/autoload.php'; echo class_exists('Symfony\Component\Yaml\Yaml') ? 'YES' : 'NO';"`

If YES, skip adding symfony/yaml.

- [ ] **Step 2: Add dependencies**

```bash
composer require league/commonmark symfony/yaml
```

If symfony/yaml was already available from step 1, run only:

```bash
composer require league/commonmark
```

Expected: packages install successfully, `composer.json` and `composer.lock` updated.

- [ ] **Step 3: Commit**

```bash
git add composer.json composer.lock
git commit -m "deps: add league/commonmark and symfony/yaml for docs rendering"
```

---

## Task 2: Create DocsRenderer Service

**Files:**
- Create: `src/Service/DocsRenderer.php`

This service has two responsibilities: extracting YAML frontmatter from markdown content, and converting markdown to HTML.

- [ ] **Step 1: Create DocsRenderer**

```php
<?php

declare(strict_types=1);

namespace WaaseyaaOrg\Service;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;
use Symfony\Component\Yaml\Yaml;

final class DocsRenderer
{
    private MarkdownConverter $converter;

    public function __construct()
    {
        $environment = new Environment([
            'heading_permalink' => [
                'html_class' => 'docs-anchor',
                'symbol' => '#',
                'insert' => 'after',
                'title' => 'Permalink',
            ],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $environment->addExtension(new HeadingPermalinkExtension());

        $this->converter = new MarkdownConverter($environment);
    }

    /**
     * Extract YAML frontmatter and markdown body from raw content.
     *
     * @return array{frontmatter: array<string, mixed>, body: string}
     */
    public function parseFrontmatter(string $raw): array
    {
        if (preg_match('/\A---\s*\n(.+?)\n---\s*\n(.*)\z/s', $raw, $matches)) {
            return [
                'frontmatter' => Yaml::parse($matches[1]),
                'body' => $matches[2],
            ];
        }

        return [
            'frontmatter' => [],
            'body' => $raw,
        ];
    }

    public function renderMarkdown(string $markdown): string
    {
        return $this->converter->convert($markdown)->getContent();
    }
}
```

- [ ] **Step 2: Verify the class loads**

```bash
php -r "require 'vendor/autoload.php'; new WaaseyaaOrg\Service\DocsRenderer(); echo 'OK';"
```

Expected: `OK`

- [ ] **Step 3: Commit**

```bash
git add src/Service/DocsRenderer.php
git commit -m "feat(docs): add DocsRenderer for frontmatter parsing and markdown conversion"
```

---

## Task 3: Create Package Manifest

**Files:**
- Create: `config/docs-manifest.php`

This maps every framework package to its docs category, order, title, and description. It's the single source of truth for navigation.

- [ ] **Step 1: List actual packages from the framework repo**

```bash
ls -1 /home/fsd42/dev/waaseyaa/packages/
```

Use the output to build the complete manifest.

- [ ] **Step 2: Create the manifest file**

Create `config/docs-manifest.php` with the following structure. The category slugs match the URL segments (`getting-started`, `core`, `content`, `api`, `ai`, `access`, `infrastructure`, `developer-tools`, `frontend`, `extensibility`):

```php
<?php

declare(strict_types=1);

return [
    // Core
    'foundation' => ['title' => 'Foundation', 'category' => 'core', 'order' => 1, 'description' => 'Kernel, service providers, and application bootstrap'],
    'core' => ['title' => 'Core', 'category' => 'core', 'order' => 2, 'description' => 'Core framework utilities and helpers'],
    'config' => ['title' => 'Config', 'category' => 'core', 'order' => 3, 'description' => 'Configuration loading and management'],
    'state' => ['title' => 'State', 'category' => 'core', 'order' => 4, 'description' => 'Application state management'],
    'routing' => ['title' => 'Routing', 'category' => 'core', 'order' => 5, 'description' => 'HTTP routing with RouteBuilder fluent API'],
    'entity' => ['title' => 'Entity', 'category' => 'core', 'order' => 6, 'description' => 'Entity type definitions and lifecycle'],
    'field' => ['title' => 'Field', 'category' => 'core', 'order' => 7, 'description' => 'Typed field system for entities'],
    'entity-storage' => ['title' => 'Entity Storage', 'category' => 'core', 'order' => 8, 'description' => 'Entity persistence and query layer'],

    // Content
    'node' => ['title' => 'Node', 'category' => 'content', 'order' => 1, 'description' => 'Content node entity type'],
    'taxonomy' => ['title' => 'Taxonomy', 'category' => 'content', 'order' => 2, 'description' => 'Vocabulary and term management'],
    'menu' => ['title' => 'Menu', 'category' => 'content', 'order' => 3, 'description' => 'Menu and navigation structures'],
    'media' => ['title' => 'Media', 'category' => 'content', 'order' => 4, 'description' => 'File and media entity management'],
    'cms' => ['title' => 'CMS', 'category' => 'content', 'order' => 5, 'description' => 'Content management system integration layer'],

    // API
    'api' => ['title' => 'JSON:API', 'category' => 'api', 'order' => 1, 'description' => 'Zero-config JSON:API endpoints for entities'],
    'graphql' => ['title' => 'GraphQL', 'category' => 'api', 'order' => 2, 'description' => 'GraphQL schema auto-generated from entity types'],
    'mcp' => ['title' => 'MCP', 'category' => 'api', 'order' => 3, 'description' => 'Model Context Protocol server integration'],

    // AI
    'ai-agent' => ['title' => 'AI Agent', 'category' => 'ai', 'order' => 1, 'description' => 'AI agent framework and tool calling'],
    'ai-pipeline' => ['title' => 'AI Pipeline', 'category' => 'ai', 'order' => 2, 'description' => 'Multi-step AI processing pipelines'],
    'ai-schema' => ['title' => 'AI Schema', 'category' => 'ai', 'order' => 3, 'description' => 'Schema extraction for AI context'],
    'ai-vector' => ['title' => 'AI Vector', 'category' => 'ai', 'order' => 4, 'description' => 'Vector embeddings and similarity search'],

    // Access & Users
    'access' => ['title' => 'Access Control', 'category' => 'access', 'order' => 1, 'description' => 'Deny-unless-granted permission model'],
    'user' => ['title' => 'User', 'category' => 'access', 'order' => 2, 'description' => 'User entity and authentication'],

    // Infrastructure
    'cache' => ['title' => 'Cache', 'category' => 'infrastructure', 'order' => 1, 'description' => 'Caching layer with multiple backends'],
    'queue' => ['title' => 'Queue', 'category' => 'infrastructure', 'order' => 2, 'description' => 'Background job processing'],
    'database-legacy' => ['title' => 'Database (Legacy)', 'category' => 'infrastructure', 'order' => 3, 'description' => 'Legacy database abstraction layer'],
    'search' => ['title' => 'Search', 'category' => 'infrastructure', 'order' => 4, 'description' => 'Full-text search integration'],
    'mail' => ['title' => 'Mail', 'category' => 'infrastructure', 'order' => 5, 'description' => 'Email sending and templating'],

    // Developer Tools
    'cli' => ['title' => 'CLI', 'category' => 'developer-tools', 'order' => 1, 'description' => 'Console commands and CLI framework'],
    'testing' => ['title' => 'Testing', 'category' => 'developer-tools', 'order' => 2, 'description' => 'Test utilities and base test cases'],
    'validation' => ['title' => 'Validation', 'category' => 'developer-tools', 'order' => 3, 'description' => 'Input validation and constraint system'],
    'telescope' => ['title' => 'Telescope', 'category' => 'developer-tools', 'order' => 4, 'description' => 'Request monitoring and debugging'],
    'typed-data' => ['title' => 'Typed Data', 'category' => 'developer-tools', 'order' => 5, 'description' => 'Type-safe data structures'],

    // Frontend
    'ssr' => ['title' => 'SSR', 'category' => 'frontend', 'order' => 1, 'description' => 'Server-side rendering with Twig'],
    'admin-surface' => ['title' => 'Admin Surface', 'category' => 'frontend', 'order' => 2, 'description' => 'Admin UI scaffolding'],

    // Extensibility
    'plugin' => ['title' => 'Plugin', 'category' => 'extensibility', 'order' => 1, 'description' => 'Plugin discovery and lifecycle'],
    'workflows' => ['title' => 'Workflows', 'category' => 'extensibility', 'order' => 2, 'description' => 'State machine workflows for entities'],
    'i18n' => ['title' => 'Internationalization', 'category' => 'extensibility', 'order' => 3, 'description' => 'Translation and localization'],
    'path' => ['title' => 'Path', 'category' => 'extensibility', 'order' => 4, 'description' => 'URL path aliases and redirects'],
    'note' => ['title' => 'Note', 'category' => 'extensibility', 'order' => 5, 'description' => 'Note entity type for lightweight content'],
];
```

Reconcile this list against the actual output from step 1. Add any packages found that are missing, remove any that don't exist.

- [ ] **Step 3: Commit**

```bash
git add config/docs-manifest.php
git commit -m "feat(docs): add package manifest with category and ordering metadata"
```

---

## Task 4: Create DocsFetcher Service

**Files:**
- Create: `src/Service/DocsFetcher.php`

Fetches READMEs from GitHub and writes them to `storage/docs/packages/`. Uses atomic directory swap for safety.

- [ ] **Step 1: Create DocsFetcher**

```php
<?php

declare(strict_types=1);

namespace WaaseyaaOrg\Service;

use Symfony\Component\Yaml\Yaml;

final class DocsFetcher
{
    private const GITHUB_API = 'https://api.github.com';
    private const REPO = 'waaseyaa/framework';
    private const RAW_BASE = 'https://raw.githubusercontent.com/waaseyaa/framework/main/packages';

    public function __construct(
        private readonly string $storagePath,
        private readonly array $manifest,
        private readonly ?string $githubToken = null,
    ) {}

    /**
     * Fetch all package READMEs and write to storage.
     *
     * @return array{fetched: list<string>, skipped: list<string>, errors: list<string>}
     */
    public function fetch(): array
    {
        $tempDir = $this->storagePath . '/packages_tmp_' . uniqid();
        $targetDir = $this->storagePath . '/packages';

        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $result = ['fetched' => [], 'skipped' => [], 'errors' => []];

        foreach (array_keys($this->manifest) as $packageName) {
            $url = self::RAW_BASE . '/' . $packageName . '/README.md';
            $content = $this->httpGet($url);

            if ($content === null) {
                $result['errors'][] = $packageName;
                continue;
            }

            if (trim($content) === '') {
                $result['skipped'][] = $packageName;
                continue;
            }

            $content = $this->ensureFrontmatter($packageName, $content);
            file_put_contents($tempDir . '/' . $packageName . '.md', $content);
            $result['fetched'][] = $packageName;
        }

        // Atomic swap: rename old dir, move new into place, remove old
        $oldDir = $this->storagePath . '/packages_old_' . uniqid();
        if (is_dir($targetDir)) {
            rename($targetDir, $oldDir);
        }
        rename($tempDir, $targetDir);
        if (is_dir($oldDir)) {
            $this->removeDirectory($oldDir);
        }

        return $result;
    }

    private function ensureFrontmatter(string $packageName, string $content): string
    {
        $meta = $this->manifest[$packageName] ?? null;
        if ($meta === null) {
            return $content;
        }

        $defaults = [
            'title' => $meta['title'],
            'category' => $meta['category'],
            'order' => $meta['order'],
            'description' => $meta['description'],
        ];

        // If content already has frontmatter, merge with manifest defaults (README wins)
        if (preg_match('/\A---\s*\n(.+?)\n---\s*\n(.*)\z/s', $content, $matches)) {
            $existing = Yaml::parse($matches[1]) ?? [];
            $merged = array_merge($defaults, $existing);
            $frontmatter = Yaml::dump($merged);
            return "---\n" . $frontmatter . "---\n\n" . $matches[2];
        }

        $frontmatter = Yaml::dump($defaults);

        return "---\n" . $frontmatter . "---\n\n" . $content;
    }

    private function httpGet(string $url): ?string
    {
        $headers = ['User-Agent: waaseyaa-docs-fetcher'];
        if ($this->githubToken !== null) {
            $headers[] = 'Authorization: token ' . $this->githubToken;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);

        $result = @file_get_contents($url, false, $context);
        if ($result === false) {
            return null;
        }

        // Check for 404 or other errors via response headers
        $status = $http_response_header[0] ?? '';
        if (!str_contains($status, '200')) {
            return null;
        }

        return $result;
    }

    private function removeDirectory(string $dir): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($dir);
    }
}
```

- [ ] **Step 2: Verify class loads**

```bash
php -r "require 'vendor/autoload.php'; new WaaseyaaOrg\Service\DocsFetcher('/tmp/test', []); echo 'OK';"
```

Expected: `OK`

- [ ] **Step 3: Commit**

```bash
git add src/Service/DocsFetcher.php
git commit -m "feat(docs): add DocsFetcher for GitHub README retrieval with atomic swap"
```

---

## Task 5: Create DocsNavigationBuilder Service

**Files:**
- Create: `src/Service/DocsNavigationBuilder.php`

Builds `index.json` from both guide files and fetched package files.

- [ ] **Step 1: Create DocsNavigationBuilder**

```php
<?php

declare(strict_types=1);

namespace WaaseyaaOrg\Service;

final class DocsNavigationBuilder
{
    private const CATEGORY_LABELS = [
        'getting-started' => 'Getting Started',
        'core' => 'Core',
        'content' => 'Content',
        'api' => 'API',
        'ai' => 'AI',
        'access' => 'Access & Users',
        'infrastructure' => 'Infrastructure',
        'developer-tools' => 'Developer Tools',
        'frontend' => 'Frontend',
        'extensibility' => 'Extensibility',
    ];

    private const CATEGORY_ORDER = [
        'getting-started', 'core', 'content', 'api', 'ai',
        'access', 'infrastructure', 'developer-tools', 'frontend', 'extensibility',
    ];

    public function __construct(
        private readonly DocsRenderer $renderer,
        private readonly string $guidesPath,
        private readonly string $storagePath,
    ) {}

    /**
     * Build the navigation index from guides and packages.
     *
     * @return array<string, array{label: string, pages: list<array{slug: string, title: string, description: string, order: int, source: string}>}>
     */
    public function build(): array
    {
        $index = [];

        // Initialize categories in display order
        foreach (self::CATEGORY_ORDER as $cat) {
            $index[$cat] = [
                'label' => self::CATEGORY_LABELS[$cat],
                'pages' => [],
            ];
        }

        // Scan guides
        $this->scanGuides($index);

        // Scan fetched packages
        $this->scanPackages($index);

        // Check for slug collisions
        $this->detectCollisions($index);

        // Sort pages within each category by order
        foreach ($index as &$category) {
            usort($category['pages'], fn($a, $b) => $a['order'] <=> $b['order']);
        }

        // Remove empty categories
        $index = array_filter($index, fn($cat) => !empty($cat['pages']));

        return $index;
    }

    public function buildAndSave(): string
    {
        $index = $this->build();
        $path = $this->storagePath . '/index.json';

        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }

        file_put_contents($path, json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    private function scanGuides(array &$index): void
    {
        if (!is_dir($this->guidesPath)) {
            return;
        }

        foreach (glob($this->guidesPath . '/*/*.md') as $file) {
            $content = file_get_contents($file);
            $parsed = $this->renderer->parseFrontmatter($content);
            $fm = $parsed['frontmatter'];

            $slug = pathinfo($file, PATHINFO_FILENAME);
            $category = $fm['category'] ?? basename(dirname($file));

            if (!isset($index[$category])) {
                continue;
            }

            $index[$category]['pages'][] = [
                'slug' => $slug,
                'title' => $fm['title'] ?? $slug,
                'description' => $fm['description'] ?? '',
                'order' => $fm['order'] ?? 99,
                'source' => 'guide',
            ];
        }
    }

    private function scanPackages(array &$index): void
    {
        $packagesDir = $this->storagePath . '/packages';
        if (!is_dir($packagesDir)) {
            return;
        }

        foreach (glob($packagesDir . '/*.md') as $file) {
            $content = file_get_contents($file);
            $parsed = $this->renderer->parseFrontmatter($content);
            $fm = $parsed['frontmatter'];

            $slug = pathinfo($file, PATHINFO_FILENAME);
            $category = $fm['category'] ?? 'core';

            if (!isset($index[$category])) {
                continue;
            }

            $index[$category]['pages'][] = [
                'slug' => $slug,
                'title' => $fm['title'] ?? $slug,
                'description' => $fm['description'] ?? '',
                'order' => $fm['order'] ?? 99,
                'source' => 'package',
            ];
        }
    }

    private function detectCollisions(array $index): void
    {
        foreach ($index as $category => $data) {
            $slugs = [];
            foreach ($data['pages'] as $page) {
                if (isset($slugs[$page['slug']])) {
                    throw new \RuntimeException(
                        "Slug collision in category '{$category}': '{$page['slug']}' exists as both {$slugs[$page['slug']]} and {$page['source']}. "
                        . "Fix the manifest or rename the guide file.",
                    );
                }
                $slugs[$page['slug']] = $page['source'];
            }
        }
    }
}
```

- [ ] **Step 2: Verify class loads**

```bash
php -r "
require 'vendor/autoload.php';
\$r = new WaaseyaaOrg\Service\DocsRenderer();
new WaaseyaaOrg\Service\DocsNavigationBuilder(\$r, '/tmp', '/tmp');
echo 'OK';
"
```

Expected: `OK`

- [ ] **Step 3: Commit**

```bash
git add src/Service/DocsNavigationBuilder.php
git commit -m "feat(docs): add DocsNavigationBuilder for index.json generation with collision detection"
```

---

## Task 6: Create DocsFetchCommand

**Files:**
- Create: `src/Command/DocsFetchCommand.php`

CLI command that orchestrates fetching + index building.

- [ ] **Step 1: Create the command**

```php
<?php

declare(strict_types=1);

namespace WaaseyaaOrg\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use WaaseyaaOrg\Service\DocsFetcher;
use WaaseyaaOrg\Service\DocsNavigationBuilder;

#[AsCommand(name: 'docs:fetch', description: 'Fetch package READMEs from GitHub and build docs navigation index')]
final class DocsFetchCommand extends Command
{
    public function __construct(
        private readonly DocsFetcher $fetcher,
        private readonly DocsNavigationBuilder $navBuilder,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Fetching package READMEs from GitHub...</info>');

        $result = $this->fetcher->fetch();

        $output->writeln(sprintf('  Fetched: %d packages', count($result['fetched'])));

        if (!empty($result['skipped'])) {
            $output->writeln(sprintf('  Skipped (empty): %s', implode(', ', $result['skipped'])));
        }

        if (!empty($result['errors'])) {
            $output->writeln(sprintf('<error>  Errors: %s</error>', implode(', ', $result['errors'])));
        }

        $output->writeln('<info>Building navigation index...</info>');

        try {
            $indexPath = $this->navBuilder->buildAndSave();
            $output->writeln(sprintf('  Index written to: %s', $indexPath));
        } catch (\RuntimeException $e) {
            $output->writeln(sprintf('<error>Build failed: %s</error>', $e->getMessage()));
            return self::FAILURE;
        }

        if (!empty($result['errors'])) {
            $output->writeln('<error>Failed: some packages could not be fetched. Deploy should not continue with missing docs.</error>');
            return self::FAILURE;
        }

        $output->writeln('<info>Done.</info>');

        return self::SUCCESS;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Command/DocsFetchCommand.php
git commit -m "feat(docs): add docs:fetch CLI command"
```

---

## Task 7: Create DocsController

> **Note:** The controller must exist before the service provider can reference it (Task 8).

**Files:**
- Create: `src/Controller/DocsController.php`

Serves the three docs routes. Follows the same controller pattern as `PageController`.

- [ ] **Step 1: Read PageController for exact patterns**

Read `src/Controller/PageController.php` to confirm the exact method signature and return type.

- [ ] **Step 2: Create DocsController**

(See full code below in the original Task 8 — moved here for correct dependency order.)

- [ ] **Step 3: Commit**

```bash
git add src/Controller/DocsController.php
git commit -m "feat(docs): add DocsController with landing, category, and page routes"
```

---

## Task 8: Register Services and Routes in SiteServiceProvider

**Files:**
- Modify: `src/Provider/SiteServiceProvider.php`

Wire up the new services, command, and docs routes.

- [ ] **Step 1: Investigate framework patterns**

Before writing any registration code, read these files to understand the exact APIs:

1. Read `src/Provider/SiteServiceProvider.php` — see how existing routes are registered (string format for controller references, RouteBuilder method chaining)
2. Read the base class `vendor/waaseyaa/foundation/src/ServiceProvider/ServiceProvider.php` — understand what methods are available (`register()`, `routes()`, `commands()`, container API)
3. Read how `PageController` gets instantiated — check if there's auto-wiring or manual registration
4. Read `vendor/waaseyaa/cli/src/` — understand command registration patterns

**Critical:** The existing routes use string-based controller references like `'WaaseyaaOrg\\Controller\\PageController::home'`, NOT array syntax. Match this format exactly.

- [ ] **Step 2: Add service registration**

Add use statements:

```php
use WaaseyaaOrg\Command\DocsFetchCommand;
use WaaseyaaOrg\Controller\DocsController;
use WaaseyaaOrg\Service\DocsFetcher;
use WaaseyaaOrg\Service\DocsNavigationBuilder;
use WaaseyaaOrg\Service\DocsRenderer;
```

In `register()`, bind the docs services. Adapt the container API to match what you found in Step 1. The services to register:

```php
$basePath = dirname(__DIR__, 2);
$manifest = require $basePath . '/config/docs-manifest.php';
$storagePath = $basePath . '/storage/docs';
$guidesPath = $basePath . '/docs/guides';

$renderer = new DocsRenderer();
// Register: DocsRenderer, DocsFetcher, DocsNavigationBuilder, DocsController
// Use the same container API pattern found in the base ServiceProvider
```

- [ ] **Step 3: Add route registration**

In the `routes()` method, add docs routes after the existing page routes. Use the **same controller reference format** as existing routes (string-based):

```php
$router->addRoute('docs.landing', RouteBuilder::create('/docs')
    ->controller('WaaseyaaOrg\\Controller\\DocsController::landing')
    ->methods('GET')
    ->render()
    ->build());

$router->addRoute('docs.category', RouteBuilder::create('/docs/{category}')
    ->controller('WaaseyaaOrg\\Controller\\DocsController::category')
    ->methods('GET')
    ->render()
    ->build());

$router->addRoute('docs.page', RouteBuilder::create('/docs/{category}/{slug}')
    ->controller('WaaseyaaOrg\\Controller\\DocsController::page')
    ->methods('GET')
    ->render()
    ->build());
```

**Note:** Check from Step 1 whether `->allowAll()` exists on `RouteBuilder`. Only chain it if it's a real method. The existing page routes may not use it — match their pattern.

- [ ] **Step 4: Add command registration**

Based on what you found in Step 1, register `DocsFetchCommand`. The framework likely uses a `commands()` method on the service provider that returns `list<Command>`. The command needs `DocsFetcher` and `DocsNavigationBuilder` injected via constructor.

- [ ] **Step 5: Commit**

```bash
git add src/Provider/SiteServiceProvider.php
git commit -m "feat(docs): register docs services, routes, and command in SiteServiceProvider"
```

---

## Task 9: Create Twig Templates

> **Note:** The DocsController code from the original Task 8 has been moved to Task 7 above. The full DocsController source is preserved below for reference.

<details>
<summary>DocsController full source (for Task 7 Step 2)</summary>

```php
<?php

declare(strict_types=1);

namespace WaaseyaaOrg\Controller;

use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\SSR\SsrResponse;
use WaaseyaaOrg\Service\DocsRenderer;

final class DocsController
{
    private ?array $index = null;

    public function __construct(
        private readonly Environment $twig,
        private readonly DocsRenderer $renderer,
        private readonly string $storagePath,
        private readonly string $guidesPath,
    ) {}

    public function landing(array $params, array $query, $account, HttpRequest $request): SsrResponse
    {
        $index = $this->getIndex();

        return new SsrResponse($this->twig->render('docs/landing.html.twig', [
            'path' => '/docs',
            'index' => $index,
        ]));
    }

    public function category(array $params, array $query, $account, HttpRequest $request): SsrResponse
    {
        $category = $params['category'] ?? '';
        $index = $this->getIndex();

        if (!isset($index[$category])) {
            return new SsrResponse(
                $this->twig->render('docs/landing.html.twig', [
                    'path' => '/docs',
                    'index' => $index,
                ]),
                404,
            );
        }

        return new SsrResponse($this->twig->render('docs/category.html.twig', [
            'path' => '/docs/' . $category,
            'index' => $index,
            'category' => $category,
            'categoryData' => $index[$category],
        ]));
    }

    public function page(array $params, array $query, $account, HttpRequest $request): SsrResponse
    {
        $category = $params['category'] ?? '';
        $slug = $params['slug'] ?? '';
        $index = $this->getIndex();

        if (!isset($index[$category])) {
            return new SsrResponse(
                $this->twig->render('docs/landing.html.twig', ['path' => '/docs', 'index' => $index]),
                404,
            );
        }

        // Find the page in the index
        $pageInfo = null;
        foreach ($index[$category]['pages'] as $p) {
            if ($p['slug'] === $slug) {
                $pageInfo = $p;
                break;
            }
        }

        if ($pageInfo === null) {
            return new SsrResponse(
                $this->twig->render('docs/landing.html.twig', ['path' => '/docs', 'index' => $index]),
                404,
            );
        }

        // Load the markdown file
        $markdown = $this->loadMarkdown($category, $slug, $pageInfo['source']);
        if ($markdown === null) {
            return new SsrResponse(
                $this->twig->render('docs/landing.html.twig', ['path' => '/docs', 'index' => $index]),
                404,
            );
        }

        $parsed = $this->renderer->parseFrontmatter($markdown);
        $html = $this->renderer->renderMarkdown($parsed['body']);

        // Build prev/next within the same category
        $pages = $index[$category]['pages'];
        $currentIdx = null;
        foreach ($pages as $i => $p) {
            if ($p['slug'] === $slug) {
                $currentIdx = $i;
                break;
            }
        }

        $prev = ($currentIdx > 0) ? $pages[$currentIdx - 1] : null;
        $next = ($currentIdx < count($pages) - 1) ? $pages[$currentIdx + 1] : null;

        return new SsrResponse($this->twig->render('docs/page.html.twig', [
            'path' => '/docs/' . $category . '/' . $slug,
            'index' => $index,
            'category' => $category,
            'categoryData' => $index[$category],
            'page' => $pageInfo,
            'content' => $html,
            'prev' => $prev,
            'prevUrl' => $prev ? '/docs/' . $category . '/' . $prev['slug'] : null,
            'next' => $next,
            'nextUrl' => $next ? '/docs/' . $category . '/' . $next['slug'] : null,
        ]));
    }

    private function getIndex(): array
    {
        if ($this->index !== null) {
            return $this->index;
        }

        $indexFile = $this->storagePath . '/index.json';
        if (!file_exists($indexFile)) {
            return [];
        }

        $this->index = json_decode(file_get_contents($indexFile), true) ?? [];
        return $this->index;
    }

    private function loadMarkdown(string $category, string $slug, string $source): ?string
    {
        if ($source === 'guide') {
            $path = $this->guidesPath . '/' . $category . '/' . $slug . '.md';
        } else {
            $path = $this->storagePath . '/packages/' . $slug . '.md';
        }

        if (!file_exists($path)) {
            return null;
        }

        return file_get_contents($path);
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Controller/DocsController.php
git commit -m "feat(docs): add DocsController with landing, category, and page routes"
```

</details>

---

## Task 9: Create Twig Templates

**Files:**
- Create: `templates/docs/layout.html.twig`
- Create: `templates/docs/landing.html.twig`
- Create: `templates/docs/category.html.twig`
- Create: `templates/docs/page.html.twig`

- [ ] **Step 1: Create docs layout template**

Create `templates/docs/layout.html.twig`:

```twig
{% extends "base.html.twig" %}

{% block content %}
<div class="docs-layout">
    <aside class="docs-sidebar">
        <nav class="docs-nav">
            {% for catSlug, catData in index %}
                <div class="docs-nav-category">
                    <h4 class="docs-nav-heading">{{ catData.label }}</h4>
                    <ul class="docs-nav-list">
                        {% for page in catData.pages %}
                            <li>
                                <a href="/docs/{{ catSlug }}/{{ page.slug }}"
                                   class="docs-nav-link{% if path == '/docs/' ~ catSlug ~ '/' ~ page.slug %} active{% endif %}">
                                    {{ page.title }}
                                </a>
                            </li>
                        {% endfor %}
                    </ul>
                </div>
            {% endfor %}
        </nav>
    </aside>
    <div class="docs-content">
        {% block docs_content %}{% endblock %}
    </div>
</div>
{% endblock %}
```

- [ ] **Step 2: Create landing template**

Create `templates/docs/landing.html.twig`:

```twig
{% extends "base.html.twig" %}

{% block title %}Documentation{% endblock %}
{% block description %}Waaseyaa framework documentation — guides, tutorials, and package reference.{% endblock %}

{% block content %}
<div class="page-header">
    <h1>Documentation</h1>
    <p>Learn how to build modern content platforms with Waaseyaa.</p>
</div>

{% if index['getting-started'] is defined %}
<section class="content-section">
    <h2>Start Here</h2>
    <div class="features-grid">
        {% for page in index['getting-started'].pages %}
            <a href="/docs/getting-started/{{ page.slug }}" class="card docs-card-link">
                <h3>{{ page.title }}</h3>
                <p>{{ page.description }}</p>
            </a>
        {% endfor %}
    </div>
</section>
{% endif %}

<section class="content-section">
    <h2>Browse by Category</h2>
    <div class="features-grid">
        {% for catSlug, catData in index %}
            {% if catSlug != 'getting-started' %}
                <a href="/docs/{{ catSlug }}" class="card docs-card-link">
                    <h3>{{ catData.label }}</h3>
                    <p>{{ catData.pages|length }} {{ catData.pages|length == 1 ? 'page' : 'pages' }}</p>
                </a>
            {% endif %}
        {% endfor %}
    </div>
</section>
{% endblock %}
```

- [ ] **Step 3: Create category template**

Create `templates/docs/category.html.twig`:

```twig
{% extends "docs/layout.html.twig" %}

{% block title %}{{ categoryData.label }}{% endblock %}
{% block description %}Waaseyaa {{ categoryData.label }} documentation.{% endblock %}

{% block docs_content %}
<div class="docs-page-header">
    <h1>{{ categoryData.label }}</h1>
</div>
<div class="docs-category-list">
    {% for page in categoryData.pages %}
        <a href="/docs/{{ category }}/{{ page.slug }}" class="docs-category-item">
            <h3>{{ page.title }}</h3>
            <p>{{ page.description }}</p>
        </a>
    {% endfor %}
</div>
{% endblock %}
```

- [ ] **Step 4: Create page template**

Create `templates/docs/page.html.twig`:

```twig
{% extends "docs/layout.html.twig" %}

{% block title %}{{ page.title }}{% endblock %}
{% block description %}{{ page.description }}{% endblock %}

{% block docs_content %}
<article class="docs-article">
    <div class="docs-page-header">
        <p class="docs-breadcrumb">
            <a href="/docs">Docs</a> / <a href="/docs/{{ category }}">{{ categoryData.label }}</a>
        </p>
        <h1>{{ page.title }}</h1>
        {% if page.description %}
            <p class="docs-page-desc">{{ page.description }}</p>
        {% endif %}
    </div>

    <div class="docs-body">
        {{ content|raw }}
    </div>

    <nav class="docs-prev-next">
        {% if prev %}
            <a href="{{ prevUrl }}" class="docs-prev">
                <span class="docs-prev-next-label">&larr; Previous</span>
                <span class="docs-prev-next-title">{{ prev.title }}</span>
            </a>
        {% else %}
            <span></span>
        {% endif %}
        {% if next %}
            <a href="{{ nextUrl }}" class="docs-next">
                <span class="docs-prev-next-label">Next &rarr;</span>
                <span class="docs-prev-next-title">{{ next.title }}</span>
            </a>
        {% endif %}
    </nav>
</article>
{% endblock %}
```

- [ ] **Step 5: Commit**

```bash
git add templates/docs/
git commit -m "feat(docs): add Twig templates for docs layout, landing, category, and page"
```

---

## Task 10: Add Docs Styles to site.css

**Files:**
- Modify: `public/css/site.css`

Add all docs-specific styles in a `/* --- Docs --- */` section at the end of the file, before the responsive media queries.

- [ ] **Step 1: Read current site.css**

Read `public/css/site.css` to find the insertion point (before the `/* --- Responsive: Tablet --- */` comment).

- [ ] **Step 2: Add docs styles**

Insert before the responsive section:

```css
/* --- Docs --- */
.docs-layout {
    display: flex;
    max-width: 1200px;
    margin: 0 auto;
    min-height: calc(100vh - 64px - 120px);
}

.docs-sidebar {
    width: 260px;
    flex-shrink: 0;
    border-right: 1px solid var(--color-border);
    background: var(--color-surface);
    position: sticky;
    top: 64px;
    height: calc(100vh - 64px);
    overflow-y: auto;
    padding: 1.5rem 0;
}

.docs-nav-category {
    margin-bottom: 1.5rem;
}

.docs-nav-heading {
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--color-accent);
    padding: 0 1.25rem;
    margin-bottom: 0.5rem;
}

.docs-nav-list {
    list-style: none;
}

.docs-nav-link {
    display: block;
    padding: 0.35rem 1.25rem;
    color: var(--color-text-muted);
    font-size: 0.9rem;
    transition: color 0.2s, border-color 0.2s;
    border-left: 3px solid transparent;
}

.docs-nav-link:hover {
    color: var(--color-text);
}

.docs-nav-link.active {
    color: var(--color-text);
    border-left-color: var(--color-accent);
    font-weight: 600;
}

.docs-content {
    flex: 1;
    min-width: 0;
    max-width: 780px;
    margin: 0 auto;
    padding: 2rem 2.5rem 4rem;
}

.docs-page-header {
    margin-bottom: 2rem;
}

.docs-page-header h1 {
    font-size: 2rem;
    font-weight: 800;
    letter-spacing: -0.02em;
    margin-bottom: 0.5rem;
}

.docs-breadcrumb {
    font-size: 0.85rem;
    color: var(--color-text-muted);
    margin-bottom: 0.75rem;
}

.docs-breadcrumb a {
    color: var(--color-text-muted);
}

.docs-breadcrumb a:hover {
    color: var(--color-accent);
}

.docs-page-desc {
    color: var(--color-text-muted);
    font-size: 1.1rem;
}

/* Docs markdown body */
.docs-body h2 {
    font-size: 1.4rem;
    font-weight: 700;
    margin-top: 2.5rem;
    margin-bottom: 1rem;
    padding-left: 0.75rem;
    border-left: 3px solid var(--color-accent);
}

.docs-body h3 {
    font-size: 1.15rem;
    font-weight: 700;
    margin-top: 2rem;
    margin-bottom: 0.75rem;
}

.docs-body h4 {
    font-size: 1rem;
    font-weight: 600;
    margin-top: 1.5rem;
    margin-bottom: 0.5rem;
}

.docs-body p {
    color: var(--color-text-muted);
    margin-bottom: 1rem;
    line-height: 1.7;
}

.docs-body ul,
.docs-body ol {
    padding-left: 1.5rem;
    margin-bottom: 1rem;
    color: var(--color-text-muted);
}

.docs-body li {
    margin-bottom: 0.5rem;
    line-height: 1.6;
}

.docs-body blockquote {
    border-left: 3px solid var(--color-accent);
    background: var(--color-accent-subtle);
    padding: 1rem 1.25rem;
    margin: 1.5rem 0;
    border-radius: 0 var(--radius) var(--radius) 0;
}

.docs-body blockquote p {
    margin-bottom: 0;
}

.docs-body table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 1.5rem;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius);
    overflow: hidden;
}

.docs-body th {
    text-align: left;
    padding: 0.75rem 1rem;
    font-weight: 600;
    color: var(--color-text);
    border-bottom: 1px solid var(--color-border);
}

.docs-body td {
    padding: 0.75rem 1rem;
    color: var(--color-text-muted);
    border-bottom: 1px solid var(--color-border);
}

.docs-body tr:last-child td {
    border-bottom: none;
}

.docs-body pre {
    position: relative;
}

.docs-body a {
    color: var(--color-accent);
}

.docs-body a:hover {
    color: var(--color-accent-hover);
}

.docs-anchor {
    color: var(--color-text-muted);
    text-decoration: none;
    font-size: 0.85em;
    margin-left: 0.5rem;
    opacity: 0;
    transition: opacity 0.2s;
}

.docs-body h2:hover .docs-anchor,
.docs-body h3:hover .docs-anchor,
.docs-body h4:hover .docs-anchor {
    opacity: 1;
}

/* Docs card link */
.docs-card-link {
    text-decoration: none;
    display: block;
}

/* Docs category list */
.docs-category-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.docs-category-item {
    display: block;
    text-decoration: none;
    padding: 1.25rem 1.5rem;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius);
    transition: border-color 0.2s, background 0.2s;
}

.docs-category-item:hover {
    border-color: var(--color-accent);
    background: var(--color-surface-hover);
}

.docs-category-item h3 {
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--color-accent);
    margin-bottom: 0.25rem;
}

.docs-category-item p {
    color: var(--color-text-muted);
    font-size: 0.9rem;
    margin: 0;
}

/* Docs prev/next navigation */
.docs-prev-next {
    display: flex;
    justify-content: space-between;
    margin-top: 3rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--color-border);
    gap: 1rem;
}

.docs-prev,
.docs-next {
    display: flex;
    flex-direction: column;
    text-decoration: none;
    padding: 0.75rem 1rem;
    border: 1px solid var(--color-border);
    border-radius: var(--radius);
    transition: border-color 0.2s;
    max-width: 50%;
}

.docs-prev:hover,
.docs-next:hover {
    border-color: var(--color-accent);
}

.docs-next {
    text-align: right;
    margin-left: auto;
}

.docs-prev-next-label {
    font-size: 0.8rem;
    color: var(--color-text-muted);
}

.docs-prev-next-title {
    font-weight: 600;
    color: var(--color-accent);
}
```

- [ ] **Step 3: Add responsive overrides for docs**

Add a **new** `@media (max-width: 1024px)` block (the spec says sidebar collapses at 1024px, not 600px):

```css
@media (max-width: 1024px) {
    .docs-sidebar {
        display: none;
    }

    .docs-content {
        padding: 1.5rem 1.25rem 3rem;
    }
}
```

In the existing `@media (max-width: 600px)` block, add only the prev/next stacking:

```css
.docs-prev-next {
    flex-direction: column;
}

.docs-prev,
.docs-next {
    max-width: 100%;
}
```

**Note:** For the initial launch, the mobile sidebar will be hidden (no hamburger menu). This keeps complexity low. A mobile nav drawer can be added as a follow-up.

- [ ] **Step 4: Commit**

```bash
git add public/css/site.css
git commit -m "feat(docs): add docs layout, markdown content, and navigation styles"
```

---

## Task 11: Update base.html.twig Navigation

**Files:**
- Modify: `templates/base.html.twig`

- [ ] **Step 1: Read current base.html.twig**

Read `templates/base.html.twig` to see the exact nav markup.

- [ ] **Step 2: Add Docs nav link**

Add a "Docs" link in the `<ul class="nav-links">` list, after the Home link and before Features:

```html
<li><a href="/docs" {% if path starts with '/docs' %}class="active"{% endif %}>Docs</a></li>
```

In Twig, use the `starts with` test:

```twig
<li><a href="/docs"{% if path|default('') starts with '/docs' %} class="active"{% endif %}>Docs</a></li>
```

- [ ] **Step 3: Commit**

```bash
git add templates/base.html.twig
git commit -m "feat(docs): add Docs link to main navigation"
```

---

## Task 12: Add Prism.js for Syntax Highlighting

**Files:**
- Modify: `templates/base.html.twig`
- Modify: `templates/docs/layout.html.twig`

- [ ] **Step 1: Add extension blocks to base.html.twig**

Edit `templates/base.html.twig` to add two new blocks that child templates can override:

In `<head>`, before `</head>`:
```twig
{% block extra_head %}{% endblock %}
```

Before `</body>`:
```twig
{% block extra_scripts %}{% endblock %}
```

- [ ] **Step 2: Use blocks in docs layout for Prism.js**

In `templates/docs/layout.html.twig`, override these blocks to load Prism.js (only on docs pages, not the whole site).

Add to head via `extra_head` block:

```html
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css">
```

Add scripts via `extra_scripts` block:

```twig
{% block extra_scripts %}
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-php.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-bash.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-yaml.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-json.min.js"></script>
{% endblock %}
```

**Note:** `landing.html.twig` extends `base.html.twig` directly (not the docs layout), but it has no code blocks, so it does NOT need Prism.js.

- [ ] **Step 3: Commit**

```bash
git add templates/
git commit -m "feat(docs): add Prism.js syntax highlighting for code blocks"
```

---

## Task 13: Update .gitignore and Deploy Configuration

**Files:**
- Modify: `.gitignore`
- Modify: `deploy.php`
- Modify: `.github/workflows/deploy.yml`

- [ ] **Step 1: Add storage/docs/ to .gitignore**

Read `.gitignore`, then add:

```
storage/docs/
```

- [ ] **Step 2: Update deploy.php**

Read `deploy.php` to understand the current deploy task structure. Add a task that runs `docs:fetch` after deployment (after composer install, before the symlink is swapped). The exact syntax depends on the deployer version used. Example with PHP Deployer:

```php
task('docs:fetch', function () {
    run('cd {{release_path}} && php waaseyaa docs:fetch');
});

// Add to the deploy flow after 'deploy:vendors'
after('deploy:vendors', 'docs:fetch');
```

Check the actual deployer task names and binary path used in the existing `deploy.php`.

- [ ] **Step 3: Update deploy.yml**

Read `.github/workflows/deploy.yml`. Add the `GITHUB_TOKEN` as a secret available to the deploy step. The token should be set as a repository secret in GitHub (`DOCS_GITHUB_TOKEN`). In the workflow:

```yaml
env:
  GITHUB_TOKEN: ${{ secrets.DOCS_GITHUB_TOKEN }}
```

Add this to the appropriate deploy step.

- [ ] **Step 4: Commit**

```bash
git add .gitignore deploy.php .github/workflows/deploy.yml
git commit -m "feat(docs): update gitignore and deploy pipeline for docs:fetch"
```

---

## Task 14: Write Getting Started Guides

**Files:**
- Create: `docs/guides/getting-started/introduction.md`
- Create: `docs/guides/getting-started/installation.md`
- Create: `docs/guides/getting-started/your-first-app.md`
- Create: `docs/guides/getting-started/concepts.md`

These are the deep guides written from scratch. Content should be sourced from the framework's existing README, CHANGELOG, skeleton project, and package documentation.

- [ ] **Step 1: Read framework sources for guide content**

Read these files for source material:
- `/home/fsd42/dev/waaseyaa/README.md`
- `/home/fsd42/dev/waaseyaa/skeleton/` (directory listing + key files)
- `/home/fsd42/dev/waaseyaa/packages/foundation/README.md`
- `/home/fsd42/dev/waaseyaa/packages/entity/README.md`
- `/home/fsd42/dev/waaseyaa/packages/routing/README.md`
- `/home/fsd42/dev/waaseyaa/packages/config/README.md`

- [ ] **Step 2: Write introduction.md**

```markdown
---
title: Introduction
category: getting-started
order: 1
description: What Waaseyaa is, who it's for, and the philosophy behind the framework
---

[Content based on framework README: what Waaseyaa is, the entity-first/AI-native philosophy, who it's for, comparison to other frameworks, the package architecture]
```

Write genuine content based on the framework sources. Cover:
- What Waaseyaa is (entity-first, AI-native PHP framework on Symfony components)
- Who it's for (developers building content platforms, CMS-like applications)
- Philosophy (Drupal's content model + modern PHP + AI as first-class)
- Package architecture overview (43+ composable packages)
- Link to Minoo as a real-world example

- [ ] **Step 3: Write installation.md**

```markdown
---
title: Installation
category: getting-started
order: 2
description: Set up a new Waaseyaa project with Composer
---

[Content based on skeleton project: composer create-project, directory structure, configuration, first boot]
```

Cover:
- Prerequisites (PHP 8.4+, Composer)
- `composer create-project` from skeleton
- Project directory structure
- Configuration (`config/waaseyaa.php`)
- Running the development server
- Verifying the installation

- [ ] **Step 4: Write your-first-app.md**

```markdown
---
title: Your First App
category: getting-started
order: 3
description: Build a simple content type from scratch with Waaseyaa
---

[Tutorial: define an entity type, add fields, register routes, create a controller, render with Twig]
```

Cover:
- Define a simple entity type (e.g., Article with title, body, published date)
- Add fields using the field system
- Create a service provider
- Register routes with RouteBuilder
- Create a controller returning SsrResponse
- Create a Twig template
- Test the full flow

- [ ] **Step 5: Write concepts.md**

```markdown
---
title: Core Concepts
category: getting-started
order: 4
description: Understanding entities, fields, service providers, and the kernel lifecycle
---

[Conceptual overview: entity/field model, service providers, kernel boot, request lifecycle]
```

Cover:
- Entity/field model (inspired by Drupal, typed fields, revisions, translations)
- Service providers (registration, boot lifecycle)
- Kernel lifecycle (boot → route → controller → response)
- The package system (how packages compose together)

- [ ] **Step 6: Commit**

```bash
git add docs/guides/getting-started/
git commit -m "docs: add Getting Started guides (introduction, installation, first app, concepts)"
```

---

## Task 15: Write Core and Domain Guides

**Files:**
- Create: `docs/guides/core/entity-system.md`
- Create: `docs/guides/core/routing.md`
- Create: `docs/guides/access/access-control.md`
- Create: `docs/guides/ai/ai-overview.md`

- [ ] **Step 1: Read framework sources**

Read:
- `/home/fsd42/dev/waaseyaa/packages/entity/README.md`
- `/home/fsd42/dev/waaseyaa/packages/field/README.md`
- `/home/fsd42/dev/waaseyaa/packages/routing/README.md`
- `/home/fsd42/dev/waaseyaa/packages/access/README.md`
- `/home/fsd42/dev/waaseyaa/packages/ai-agent/README.md`
- `/home/fsd42/dev/waaseyaa/packages/ai-pipeline/README.md`
- `/home/fsd42/dev/waaseyaa/packages/ai-schema/README.md`
- `/home/fsd42/dev/waaseyaa/packages/ai-vector/README.md`

- [ ] **Step 2: Write entity-system.md**

```markdown
---
title: Entity System
category: core
order: 100
description: Deep dive into entities, typed fields, revisions, and translations
---
```

Use `order: 100` to place guides after the package reference pages (which use single-digit orders from the manifest). Cover entity type definitions, field types, storage, revisions, translations, and entity lifecycle hooks.

- [ ] **Step 3: Write routing.md**

```markdown
---
title: Routing Guide
category: core
order: 101
description: Route definitions, controllers, middleware, and URL generation
---
```

Cover RouteBuilder fluent API, controller method signatures, route parameters, permission requirements, URL generation.

- [ ] **Step 4: Write access-control.md**

```markdown
---
title: Access Control Guide
category: access
order: 100
description: Understanding the deny-unless-granted permission model
---
```

Cover the access model, field-level permissions, role-based access, permission checking in controllers and templates.

- [ ] **Step 5: Write ai-overview.md**

```markdown
---
title: AI Overview
category: ai
order: 100
description: How the AI packages work together for intelligent applications
---
```

Cover the four AI packages, how they relate, example workflows (schema → pipeline → agent → vector), integration with entities.

- [ ] **Step 6: Commit**

```bash
git add docs/guides/core/ docs/guides/access/ docs/guides/ai/
git commit -m "docs: add core, access, and AI guides"
```

---

## Task 16: End-to-End Smoke Test

**Files:** None (testing only)

- [ ] **Step 1: Ensure storage directory exists**

```bash
mkdir -p storage/docs
```

- [ ] **Step 2: Run docs:fetch locally**

```bash
php waaseyaa docs:fetch
```

Check the actual CLI binary name — it might be `php public/index.php`, `./waaseyaa`, or `php bin/console`. Look at the CLI package and existing scripts to find the correct invocation.

Expected: packages fetched, index.json created in `storage/docs/`.

- [ ] **Step 3: Verify index.json was generated**

```bash
cat storage/docs/index.json | php -r "echo count(json_decode(file_get_contents('php://stdin'), true)) . ' categories';"
```

Expected: `10 categories` (or close, depending on which categories have content).

- [ ] **Step 4: Start the dev server and test routes**

```bash
# Start server (check actual command — may be php -S or caddy)
php -S localhost:8000 -t public/
```

In another terminal, test:

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/docs
# Expected: 200

curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/docs/core
# Expected: 200

curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/docs/core/entity
# Expected: 200

curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/docs/nonexistent/page
# Expected: 404
```

- [ ] **Step 5: Visual check**

Open `http://localhost:8000/docs` in a browser and verify:
- Landing page shows category cards
- Sidebar navigation renders on a docs page
- Markdown content renders with syntax highlighting
- Prev/next navigation works
- Active nav item is highlighted
- Responsive behavior works at mobile widths

- [ ] **Step 6: Commit any fixes**

```bash
git add -A
git commit -m "fix(docs): address issues found during smoke testing"
```

Only commit if fixes were needed. Skip this step if everything passed cleanly.
