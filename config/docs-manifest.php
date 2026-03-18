<?php

declare(strict_types=1);

return [
    // Core
    'foundation' => ['title' => 'Foundation', 'category' => 'core', 'order' => 1, 'description' => 'Kernel, service providers, and application bootstrap'],
    'core' => ['title' => 'Core', 'category' => 'core', 'order' => 2, 'description' => 'Core framework utilities and helpers'],
    'config' => ['title' => 'Config', 'category' => 'core', 'order' => 3, 'description' => 'Configuration loading and management'],
    'state' => ['title' => 'State', 'category' => 'core', 'order' => 4, 'description' => 'Application state management'],
    'routing' => ['title' => 'Routing', 'category' => 'core', 'order' => 5, 'description' => 'HTTP routing with RouteBuilder fluent API'],
    'entity' => ['title' => 'Entity', 'category' => 'core', 'order' => 6, 'description' => 'Entity type definitions and lifecycle'],
    'field' => ['title' => 'Field', 'category' => 'core', 'order' => 7, 'description' => 'Typed field system for entities'],
    'entity-storage' => ['title' => 'Entity Storage', 'category' => 'core', 'order' => 8, 'description' => 'Entity persistence and query layer'],
    'relationship' => ['title' => 'Relationship', 'category' => 'core', 'order' => 9, 'description' => 'Entity relationship definitions and resolution'],

    // Content
    'node' => ['title' => 'Node', 'category' => 'content', 'order' => 1, 'description' => 'Content node entity type'],
    'taxonomy' => ['title' => 'Taxonomy', 'category' => 'content', 'order' => 2, 'description' => 'Vocabulary and term management'],
    'menu' => ['title' => 'Menu', 'category' => 'content', 'order' => 3, 'description' => 'Menu and navigation structures'],
    'media' => ['title' => 'Media', 'category' => 'content', 'order' => 4, 'description' => 'File and media entity management'],
    'cms' => ['title' => 'CMS', 'category' => 'content', 'order' => 5, 'description' => 'Content management system integration layer'],

    // API
    'api' => ['title' => 'JSON:API', 'category' => 'api', 'order' => 1, 'description' => 'Zero-config JSON:API endpoints for entities'],
    'graphql' => ['title' => 'GraphQL', 'category' => 'api', 'order' => 2, 'description' => 'GraphQL schema auto-generated from entity types'],
    'mcp' => ['title' => 'MCP', 'category' => 'api', 'order' => 3, 'description' => 'Model Context Protocol server integration'],

    // AI
    'ai-agent' => ['title' => 'AI Agent', 'category' => 'ai', 'order' => 1, 'description' => 'AI agent framework and tool calling'],
    'ai-pipeline' => ['title' => 'AI Pipeline', 'category' => 'ai', 'order' => 2, 'description' => 'Multi-step AI processing pipelines'],
    'ai-schema' => ['title' => 'AI Schema', 'category' => 'ai', 'order' => 3, 'description' => 'Schema extraction for AI context'],
    'ai-vector' => ['title' => 'AI Vector', 'category' => 'ai', 'order' => 4, 'description' => 'Vector embeddings and similarity search'],

    // Access & Users
    'access' => ['title' => 'Access Control', 'category' => 'access', 'order' => 1, 'description' => 'Deny-unless-granted permission model'],
    'user' => ['title' => 'User', 'category' => 'access', 'order' => 2, 'description' => 'User entity and authentication'],

    // Infrastructure
    'cache' => ['title' => 'Cache', 'category' => 'infrastructure', 'order' => 1, 'description' => 'Caching layer with multiple backends'],
    'queue' => ['title' => 'Queue', 'category' => 'infrastructure', 'order' => 2, 'description' => 'Background job processing'],
    'database-legacy' => ['title' => 'Database (Legacy)', 'category' => 'infrastructure', 'order' => 3, 'description' => 'Legacy database abstraction layer'],
    'search' => ['title' => 'Search', 'category' => 'infrastructure', 'order' => 4, 'description' => 'Full-text search integration'],
    'mail' => ['title' => 'Mail', 'category' => 'infrastructure', 'order' => 5, 'description' => 'Email sending and templating'],

    // Developer Tools
    'cli' => ['title' => 'CLI', 'category' => 'developer-tools', 'order' => 1, 'description' => 'Console commands and CLI framework'],
    'testing' => ['title' => 'Testing', 'category' => 'developer-tools', 'order' => 2, 'description' => 'Test utilities and base test cases'],
    'validation' => ['title' => 'Validation', 'category' => 'developer-tools', 'order' => 3, 'description' => 'Input validation and constraint system'],
    'telescope' => ['title' => 'Telescope', 'category' => 'developer-tools', 'order' => 4, 'description' => 'Request monitoring and debugging'],
    'typed-data' => ['title' => 'Typed Data', 'category' => 'developer-tools', 'order' => 5, 'description' => 'Type-safe data structures'],
    'github' => ['title' => 'GitHub', 'category' => 'developer-tools', 'order' => 6, 'description' => 'GitHub integration and webhooks'],

    // Frontend
    'ssr' => ['title' => 'SSR', 'category' => 'frontend', 'order' => 1, 'description' => 'Server-side rendering with Twig'],
    'admin-surface' => ['title' => 'Admin Surface', 'category' => 'frontend', 'order' => 2, 'description' => 'Admin UI scaffolding'],
    'admin' => ['title' => 'Admin', 'category' => 'frontend', 'order' => 3, 'description' => 'Administration backend'],

    // Extensibility
    'plugin' => ['title' => 'Plugin', 'category' => 'extensibility', 'order' => 1, 'description' => 'Plugin discovery and lifecycle'],
    'workflows' => ['title' => 'Workflows', 'category' => 'extensibility', 'order' => 2, 'description' => 'State machine workflows for entities'],
    'i18n' => ['title' => 'Internationalization', 'category' => 'extensibility', 'order' => 3, 'description' => 'Translation and localization'],
    'path' => ['title' => 'Path', 'category' => 'extensibility', 'order' => 4, 'description' => 'URL path aliases and redirects'],
    'note' => ['title' => 'Note', 'category' => 'extensibility', 'order' => 5, 'description' => 'Note entity type for lightweight content'],
    'full' => ['title' => 'Full', 'category' => 'extensibility', 'order' => 6, 'description' => 'Full framework meta-package'],
];
