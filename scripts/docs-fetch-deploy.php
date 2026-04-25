<?php

declare(strict_types=1);

/**
 * Deploy-time docs fetch without booting Waaseyaa ConsoleKernel.
 *
 * docs:fetch runs after full kernel boot, which requires registered content types
 * in the production SQLite DB. This site ships without entity tables populated for
 * CLI-only paths; optimize:manifest uses minimal console. This script wires the
 * same services as SiteServiceProvider for DocsFetcher + DocsNavigationBuilder.
 *
 * Usage (from project root):
 *   GITHUB_TOKEN=... php scripts/docs-fetch-deploy.php
 */

$root = dirname(__DIR__);

if (!is_file($root . '/vendor/autoload.php')) {
    fwrite(STDERR, "vendor/autoload.php missing. Run composer install from project root.\n");
    exit(1);
}

require $root . '/vendor/autoload.php';

$storagePath = $root . '/storage/docs';
$guidesPath = $root . '/docs/guides';
$manifestPath = $root . '/config/docs-manifest.php';

if (!is_file($manifestPath)) {
    fwrite(STDERR, "Missing config/docs-manifest.php\n");
    exit(1);
}

/** @var array<string, array<string, mixed>> $manifest */
$manifest = require $manifestPath;

$token = getenv('GITHUB_TOKEN');
$githubToken = ($token !== false && $token !== '') ? $token : null;

$fwriteErr = static function (string $msg): void {
    fwrite(STDERR, $msg);
};

$fwriteErr("Fetching package READMEs from GitHub...\n");

$fetcher = new \WaaseyaaOrg\Service\DocsFetcher($storagePath, $manifest, $githubToken);
$result = $fetcher->fetch();

$fwriteErr(sprintf("  Fetched: %d packages\n", count($result['fetched'])));

if (!empty($result['skipped'])) {
    $fwriteErr(sprintf("  Skipped (empty): %s\n", implode(', ', $result['skipped'])));
}

if (!empty($result['errors'])) {
    $fwriteErr(sprintf("  Errors: %s\n", implode(', ', $result['errors'])));
}

$fwriteErr("Building navigation index...\n");

try {
    $renderer = new \WaaseyaaOrg\Service\DocsRenderer();
    $navBuilder = new \WaaseyaaOrg\Service\DocsNavigationBuilder($renderer, $guidesPath, $storagePath);
    $indexPath = $navBuilder->buildAndSave();
    $fwriteErr(sprintf("  Index written to: %s\n", $indexPath));
} catch (\RuntimeException $e) {
    $fwriteErr(sprintf("Build failed: %s\n", $e->getMessage()));
    exit(1);
}

if (!empty($result['errors'])) {
    $fwriteErr("Failed: some packages could not be fetched. Deploy should not continue with missing docs.\n");
    exit(1);
}

$fwriteErr("Done.\n");
exit(0);
