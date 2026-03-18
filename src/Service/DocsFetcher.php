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
