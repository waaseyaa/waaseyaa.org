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
