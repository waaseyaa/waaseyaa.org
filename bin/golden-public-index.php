<?php

declare(strict_types=1);

// When used as a PHP built-in server router script, return false for existing
// static files so PHP serves them directly (CSS, JS, fonts, images, etc.).
// This is a no-op under FPM/Caddy in production.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (is_file($file)) {
        return false;
    }
}

use Symfony\Component\HttpFoundation\Response;

require __DIR__ . '/../vendor/autoload.php';

$projectRoot = dirname(__DIR__);
if (is_file($projectRoot . '/.env')) {
    // Default missing APP_ENV to production (not Symfony's implicit "dev") so production
    // deploys never accidentally run with the wrong environment if the line is omitted.
    (new \Symfony\Component\Dotenv\Dotenv())->loadEnv($projectRoot . '/.env', 'APP_ENV', 'production');
}

$kernel = new \Waaseyaa\Foundation\Kernel\HttpKernel($projectRoot);

try {
    $response = $kernel->handle();
} catch (\Throwable $e) {
    $payload = json_encode([
        'jsonapi' => ['version' => '1.1'],
        'errors' => [['status' => '500', 'title' => 'Internal Server Error', 'detail' => $e->getMessage()]],
    ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    $response = new Response($payload, 500, ['Content-Type' => 'application/vnd.api+json']);
}

$response->send();
