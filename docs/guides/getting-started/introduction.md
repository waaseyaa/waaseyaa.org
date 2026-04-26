---
title: Introduction
category: getting-started
order: 1
description: What Waaseyaa is, who it's for, and the philosophy behind the framework
---

# What Is Waaseyaa

Waaseyaa is an entity-first, AI-native content management system. It runs on PHP 8.4+ and Symfony 7. It replaces Drupal's legacy runtime with a clean, modular architecture organized as independent Composer packages.

Every subsystem is a standalone package. Entities, fields, config, caching, routing, access control. Each has explicit interfaces, no global state, and no hidden coupling.

## Who Waaseyaa Is For

You should use Waaseyaa if you are building:

- **Content platforms** that need structured, typed content with revisions and translations
- **CMS-like applications** where content modeling is a first-class concern
- **AI-powered applications** that need structured tool calls, vector search, and inference pipelines
- **API-first backends** with JSON:API and GraphQL endpoints generated from entity definitions

If you know Drupal's content model and want that same capability with modern PHP, Waaseyaa gives you that. No legacy baggage.

## Design Principles

Waaseyaa is built on five principles.

### No Global State

Every service receives its dependencies through constructor injection. No service locators, no static registries, no hidden singletons.

### Interface-First

Public APIs are defined as interfaces. Implementations are swappable. You program against contracts, not concrete classes.

### In-Memory Testable

Every subsystem has in-memory implementations for fast, isolated testing. You can test entity storage, config, caching, and access control without touching a database.

### Layered Architecture

The framework is organized into strict architectural layers. Each layer only depends on layers below it. No circular dependencies.

### AI-Native

Entity schemas automatically generate MCP tools. AI agents can create, read, update, and query content through structured tool calls. AI is built into the architecture, not bolted on.

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

This diagram shows the layer hierarchy. A package at Layer 3 can depend on Layers 0 through 2 but never on Layer 4 or above.

Three meta-packages provide convenient installation:

| Meta-Package | Contents | Packages |
|---|---|---|
| `waaseyaa/core` | Foundation + Core Data + Services | 14 packages |
| `waaseyaa/cms` | Core + Content Types + API + CLI | 23 packages |
| `waaseyaa/full` | CMS + AI + GraphQL + SSR | 29 packages |

### Key Packages

| Package | Purpose |
|---|---|
| `waaseyaa/foundation` | Kernel bootstrapping, service providers, event system |
| `waaseyaa/entity` | Entity type system with content and config entity bases |
| `waaseyaa/field` | Typed field definitions, items, and lists |
| `waaseyaa/routing` | Symfony-based routing with fluent RouteBuilder API |
| `waaseyaa/access` | Permission-based access control with policy handlers |
| `waaseyaa/config` | Configuration management with import/export |
| `waaseyaa/ssr` | Twig SSR, themes, and typed `Class::method` app controllers (`AppControllerMethodInvoker`) |
| `waaseyaa/ai-schema` | JSON Schema generation from entity definitions |
| `waaseyaa/ai-agent` | AI agent orchestration with tool execution |

## How waaseyaa.org Is Built

This documentation site runs on Waaseyaa. It uses the SSR package for server-side rendering, the entity system for content management, and the same routing and access control packages available to every Waaseyaa application. Every feature documented here is demonstrated in production by the site you are reading.

## Getting Started

Here is the recommended path:

1. **[Installation](./installation.md)** — Set up a new project with Composer
2. **[Build a Todo App](./your-first-app.md)** — Create a working app with entities, routes, and CRUD in 20 minutes
3. **[Core Concepts](./concepts.md)** — Understand entities, fields, providers, and the kernel lifecycle

From there, explore the deep-dive guides:

- [Entity System](../core/entity-system.md) — Typed fields, revisions, translations
- [Routing Guide](../core/routing-guide.md) — RouteBuilder, controllers, middleware
- [Access Control](../access/access-control.md) — The deny-unless-granted model
- [AI Overview](../ai/ai-overview.md) — Schema, pipeline, agent, and vector packages
