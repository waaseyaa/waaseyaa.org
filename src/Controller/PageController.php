<?php

declare(strict_types=1);

namespace WaaseyaaOrg\Controller;

use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final class PageController
{
    public function __construct(
        private readonly Environment $twig,
    ) {}

    public function home(): Response
    {
        $html = $this->twig->render('home.html.twig', ['path' => '/']);

        return new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function features(): Response
    {
        $html = $this->twig->render('features.html.twig', ['path' => '/features']);

        return new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function gettingStarted(): Response
    {
        $html = $this->twig->render('getting-started.html.twig', ['path' => '/getting-started']);

        return new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function about(): Response
    {
        $html = $this->twig->render('about.html.twig', ['path' => '/about']);

        return new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
