---
title: Introduction
category: getting-started
order: 1
description: What Waaseyaa is, who it's for, and the philosophy behind the framework
---

# Introduction to Waaseyaa

Waaseyaa is a modern, entity-first, AI-native content management system built on PHP 8.3+ and Symfony 7. It replaces Drupal's legacy runtime with a clean, modular architecture organized as independent Composer packages.

Every subsystem — entities, fields, config, caching, routing, access control — is a standalone package with explicit interfaces, no global state, and no hidden coupling.

## Who Is Waaseyaa For?

Waaseyaa is designed for developers building:

- **Content platforms** that need structured, typed content with revisions and translations
- **CMS-like applications** where content modeling is a first-class concern
- **AI-powered applications** that need structured tool calls, vector search, and inference pipelines
- **API-first backends** with JSON:API and GraphQL endpoints generated from entity definitions

If you have experience with Drupal's content model and want that power with modern PHP practices, Waaseyaa gives you exactly that — without the legacy baggage.

## Philosophy

Waaseyaa is built on five key design principles:

### No Global State

Every service receives its dependencies through constructor injection. There are no service locators, no static registries, and no hidden singletons.

### Interface-First

Public APIs are defined as interfaces. Implementations are swappable. You program against contracts, not concrete classes.

### In-Memory Testable

Every subsystem has in-memory implementations for fast, isolated testing. You can test entity storage, config, caching, and access control without touching a database.

### Layered Architecture

The framework is organized into strict architectural layers. Each layer only depends on layers below it. There are no circular dependencies.

### AI-Native

Entity schemas automatically generate MCP tools, enabling AI agents to create, read, update, and query content through structured tool calls. AI is not an afterthought — it is woven into the architecture.

## Package Architecture

Waaseyaa is composed of 43+ packages organized into 7 architectural layers with strict downward-only dependencies:

```
Layer 6  Interfaces     cli, ssr, admin
Layer 5  AI             ai-schema, ai-agent, ai-vector, ai-pipeline
Layer 4  API            api, graphql
Layer 3  Content Types  note, node, taxonomy, media, path, menu, workflows
Layer 2  Services       access, user, routing, queue, state, validation
Layer 1  Core Data      config, entity, field, entity-storage, database-legacy
Layer 0  Foundation     cache, plugin, typed-data
```

Three meta-packages provide convenient installation:

| Meta-Package | Contents | Packages |
|---|---|---|
| `waaseyaa/core` | Foundation + Core Data + Services | 14 packages |
| `waaseyaa/cms` | Core + Content Types + API + CLI | 23 packages |
| `waaseyaa/full` | CMS + AI + GraphQL + SSR | 29 packages |

### Key Packages at a Glance

| Package | Purpose |
|---|---|
| `waaseyaa/foundation` | Kernel bootstrapping, service providers, event system |
| `waaseyaa/entity` | Entity type system with content and config entity bases |
| `waaseyaa/field` | Typed field definitions, items, and lists |
| `waaseyaa/routing` | Symfony-based routing with fluent RouteBuilder API |
| `waaseyaa/access` | Permission-based access control with policy handlers |
| `waaseyaa/config` | Configuration management with import/export |
| `waaseyaa/ssr` | Twig-based server-side rendering with theme support |
| `waaseyaa/ai-schema` | JSON Schema generation from entity definitions |
| `waaseyaa/ai-agent` | AI agent orchestration with tool execution |

## How waaseyaa.org Is Built

This documentation site itself is built with Waaseyaa. It uses the SSR package for server-side rendering, the entity system for content management, and the same routing and access control packages available to every Waaseyaa application. This means every feature documented here is also demonstrated in production by the site you are reading.

## Getting Started

Ready to build something? Here is the recommended path:

1. **[Installation](./installation.md)** — Set up a new project with Composer
2. **[Your First App](./your-first-app.md)** — Build a content type from scratch
3. **[Core Concepts](./concepts.md)** — Understand entities, fields, providers, and the kernel lifecycle

From there, explore the deep-dive guides:

- [Entity System](../core/entity-system.md) — Typed fields, revisions, translations
- [Routing Guide](../core/routing.md) — RouteBuilder, controllers, middleware
- [Access Control](../access/access-control.md) — The deny-unless-granted model
- [AI Overview](../ai/ai-overview.md) — Schema, pipeline, agent, and vector packages
