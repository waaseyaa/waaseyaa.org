<?php

declare(strict_types=1);

namespace WaaseyaaOrg\Provider;

use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;
use WaaseyaaOrg\Command\DocsFetchCommand;
use WaaseyaaOrg\Controller\DocsController;
use WaaseyaaOrg\Service\DocsFetcher;
use WaaseyaaOrg\Service\DocsNavigationBuilder;
use WaaseyaaOrg\Service\DocsRenderer;

final class SiteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $storagePath = dirname(__DIR__, 2) . '/storage/docs';
        $guidesPath = dirname(__DIR__, 2) . '/docs/guides';

        $this->singleton(DocsRenderer::class, static fn () => new DocsRenderer());

        $this->singleton(DocsFetcher::class, static function () use ($storagePath): DocsFetcher {
            $manifest = require dirname(__DIR__, 2) . '/config/docs-manifest.php';
            $githubToken = getenv('GITHUB_TOKEN') ?: null;

            return new DocsFetcher($storagePath, $manifest, $githubToken);
        });

        $this->singleton(DocsNavigationBuilder::class, function () use ($guidesPath, $storagePath): DocsNavigationBuilder {
            $renderer = $this->resolve(DocsRenderer::class);

            return new DocsNavigationBuilder($renderer, $guidesPath, $storagePath);
        });

        $this->singleton(DocsController::class, function () use ($storagePath, $guidesPath): DocsController {
            $renderer = $this->resolve(DocsRenderer::class);
            $twig = \Waaseyaa\SSR\SsrServiceProvider::getTwigEnvironment();

            return new DocsController($twig, $renderer, $storagePath, $guidesPath);
        });
    }

    public function routes(WaaseyaaRouter $router): void
    {
        $router->addRoute(
            'page.home',
            RouteBuilder::create('/')
                ->controller('WaaseyaaOrg\\Controller\\PageController::home')
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'page.features',
            RouteBuilder::create('/features')
                ->controller('WaaseyaaOrg\\Controller\\PageController::features')
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'page.gettingStarted',
            RouteBuilder::create('/getting-started')
                ->controller('WaaseyaaOrg\\Controller\\PageController::gettingStarted')
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'page.about',
            RouteBuilder::create('/about')
                ->controller('WaaseyaaOrg\\Controller\\PageController::about')
                ->render()
                ->methods('GET')
                ->build(),
        );

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
    }

    public function commands(
        \Waaseyaa\Entity\EntityTypeManager $entityTypeManager,
        \Waaseyaa\Database\PdoDatabase $database,
        \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $dispatcher,
    ): array {
        return [
            new DocsFetchCommand(
                $this->resolve(DocsFetcher::class),
                $this->resolve(DocsNavigationBuilder::class),
            ),
        ];
    }
}
