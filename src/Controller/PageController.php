<?php

declare(strict_types=1);

namespace WaaseyaaOrg\Controller;

use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\SSR\SsrResponse;

final class PageController
{
    public function __construct(
        private readonly Environment $twig,
    ) {}

    public function home(array $params, array $query, $account, HttpRequest $request): SsrResponse
    {
        return new SsrResponse($this->twig->render('home.html.twig', ['path' => '/']));
    }

    public function features(array $params, array $query, $account, HttpRequest $request): SsrResponse
    {
        return new SsrResponse($this->twig->render('features.html.twig', ['path' => '/features']));
    }

    public function gettingStarted(array $params, array $query, $account, HttpRequest $request): SsrResponse
    {
        return new SsrResponse($this->twig->render('getting-started.html.twig', ['path' => '/getting-started']));
    }

    public function about(array $params, array $query, $account, HttpRequest $request): SsrResponse
    {
        return new SsrResponse($this->twig->render('about.html.twig', ['path' => '/about']));
    }
}
