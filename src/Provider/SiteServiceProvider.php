<?php

declare(strict_types=1);

namespace WaaseyaaOrg\Provider;

use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class SiteServiceProvider extends ServiceProvider
{
    public function register(): void {}

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
    }
}
