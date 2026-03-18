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
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
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
