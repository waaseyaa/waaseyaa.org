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
    private readonly string $storagePath;
    private readonly string $guidesPath;

    public function __construct(
        private readonly Environment $twig,
        private readonly DocsRenderer $renderer,
    ) {
        $basePath = dirname(__DIR__, 2);
        $this->storagePath = $basePath . '/storage/docs';
        $this->guidesPath = $basePath . '/docs/guides';
    }

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
            $baseDir = $this->guidesPath;
            $path = $baseDir . '/' . $category . '/' . $slug . '.md';
        } else {
            $baseDir = $this->storagePath . '/packages';
            $path = $baseDir . '/' . $slug . '.md';
        }

        $realPath = realpath($path);
        $realBase = realpath($baseDir);

        if ($realPath === false || $realBase === false || !str_starts_with($realPath, $realBase . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return file_get_contents($realPath);
    }
}
