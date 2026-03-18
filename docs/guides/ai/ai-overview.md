---
title: AI Overview
category: ai
order: 100
description: How the AI packages work together for intelligent applications
---

# AI Overview

Waaseyaa is AI-native: entity schemas automatically generate structured tool definitions, enabling AI agents to create, read, update, and query content through typed interfaces. This guide covers the four AI packages and how they work together.

## The Four AI Packages

Waaseyaa's AI capabilities are split across four Layer 5 packages:

| Package | Purpose | Key Classes |
|---|---|---|
| `waaseyaa/ai-schema` | JSON Schema generation from entity definitions | `SchemaGenerator`, `SchemaValidator` |
| `waaseyaa/ai-pipeline` | Inference orchestration with prompt assembly and retry | `Pipeline`, `PipelineExecutor`, `PipelineStepInterface` |
| `waaseyaa/ai-agent` | Agent orchestration with tool execution and audit | `AgentExecutor`, `AgentInterface`, `AgentContext` |
| `waaseyaa/ai-vector` | Vector embedding storage and similarity search | `VectorStoreInterface`, `EmbeddingProviderInterface`, `SimilarityResult` |

### Dependency Flow

The packages have a clear dependency chain:

```
ai-agent  ──depends on──>  ai-schema
    │                          │
    └──depends on──>  ai-pipeline
                               │
ai-vector  (independent, integrates with ai-pipeline for RAG)
```

- `ai-schema` is the foundation: it derives structured schemas from entity types
- `ai-pipeline` orchestrates LLM calls, using schemas for typed input/output
- `ai-agent` chains tool calls, managing conversation context
- `ai-vector` provides semantic search, integrating with pipelines for RAG

## ai-schema: Schema Generation

The `waaseyaa/ai-schema` package bridges entity type definitions and AI systems. It reads your entity types and field definitions to generate:

- **JSON Schema** — Structured schemas describing entity fields, types, and constraints
- **MCP Tool definitions** — Model Context Protocol tools that AI agents can call

### How It Works

Every field type in Waaseyaa implements `jsonSchema()`, which returns the JSON Schema representation of that field. The `SchemaGenerator` collects these across an entity type to produce a complete schema:

```php
use Waaseyaa\AiSchema\SchemaGenerator;

$generator = new SchemaGenerator($entityTypeManager);

// Generate JSON Schema for the 'article' entity type
$schema = $generator->generate('article');

// Result: a complete JSON Schema object describing
// all fields, their types, required constraints, etc.
```

### Schema Validation

The `SchemaValidator` validates data against generated schemas before it reaches the entity system:

```php
use Waaseyaa\AiSchema\SchemaValidator;

$validator = new SchemaValidator();
$result = $validator->validate($inputData, $schema);

if (!$result->isValid()) {
    // Handle validation errors
}
```

This is essential for AI workflows where language models produce structured output that must conform to entity field definitions.

### Automatic MCP Tools

When entity schemas are generated, they can be exposed as MCP (Model Context Protocol) tools. This means any MCP-compatible AI client can discover and use your entity types as tools — creating, reading, updating, and querying content through structured calls with no additional code.

## ai-pipeline: Inference Orchestration

The `waaseyaa/ai-pipeline` package is the execution layer between your application logic and LLM providers. It handles:

- **Prompt assembly** — Building prompts from templates and context
- **Model invocation** — Calling LLM APIs (OpenAI, Ollama, etc.)
- **Response parsing** — Extracting structured data from model responses
- **Retry logic** — Handling transient failures and rate limits

### Pipeline Steps

Pipelines are composed of steps that implement `PipelineStepInterface`:

```php
use Waaseyaa\AiPipeline\PipelineStepInterface;

class SummarizeStep implements PipelineStepInterface
{
    public function execute(array $context): array
    {
        // $context contains the accumulated pipeline state.
        // Each step reads from context, processes, and returns
        // updated context for the next step.

        $content = $context['content'];

        // Call the LLM via the pipeline executor...
        $summary = $this->llm->complete("Summarize: {$content}");

        return array_merge($context, ['summary' => $summary]);
    }
}
```

### Running a Pipeline

```php
use Waaseyaa\AiPipeline\Pipeline;
use Waaseyaa\AiPipeline\PipelineExecutor;

$pipeline = new Pipeline([
    new ExtractContentStep(),
    new SummarizeStep(),
    new ClassifyStep(),
]);

$executor = new PipelineExecutor();
$result = $executor->run($pipeline, [
    'entity' => $article,
    'content' => $article->get('body'),
]);

// $result contains the accumulated context from all steps
$summary = $result['summary'];
$categories = $result['categories'];
```

Pipelines are composable: you can chain any combination of steps for different workflows.

## ai-agent: Agent Orchestration

The `waaseyaa/ai-agent` package provides a higher-level abstraction for AI agent workflows. While pipelines execute a fixed sequence of steps, agents make decisions about which tools to call and in what order.

### AgentExecutor

The `AgentExecutor` manages the agent loop:

1. Send the current context to the language model
2. The model decides which tool to call (or to respond directly)
3. Execute the tool call
4. Feed the result back to the model
5. Repeat until the agent produces a final response

```php
use Waaseyaa\AiAgent\AgentExecutor;
use Waaseyaa\AiAgent\AgentContext;

$context = new AgentContext(
    systemPrompt: 'You are a content management assistant.',
    tools: $schemaGenerator->getTools(['article', 'taxonomy_term']),
);

$executor = new AgentExecutor($pipelineExecutor);
$response = $executor->run($context, 'Create an article about PHP 8.4 features');

// The agent may have:
// 1. Called the "create_article" tool with structured data
// 2. Called the "list_taxonomy_terms" tool to find categories
// 3. Called the "update_article" tool to assign a category
// 4. Returned a final response confirming what was created
```

### Audit Logging

The agent package includes audit logging for every tool call. This provides a complete trace of what the agent did, which is essential for:

- Debugging agent behavior
- Compliance and accountability
- Understanding how content was created or modified

### Integration with ai-schema

The agent depends on `ai-schema` for its tool definitions. When you pass entity type IDs to `getTools()`, the schema generator produces MCP-compatible tool definitions that the agent can call. The tools map directly to entity CRUD operations:

- `create_{entity_type}` — Create a new entity
- `read_{entity_type}` — Load an entity by ID
- `update_{entity_type}` — Update entity fields
- `list_{entity_type}` — Query entities with filters

## ai-vector: Embedding and Search

The `waaseyaa/ai-vector` package adds semantic search capabilities through vector embeddings.

### Embedding Providers

The package supports multiple embedding backends, configured in `config/waaseyaa.php`:

```php
'ai' => [
    // 'ollama' or 'openai'. Empty disables embedding generation.
    'embedding_provider' => 'ollama',
    'ollama_endpoint' => 'http://127.0.0.1:11434/api/embeddings',
    'ollama_model' => 'nomic-embed-text',

    // Or use OpenAI
    // 'embedding_provider' => 'openai',
    // 'openai_api_key' => '...',
    // 'openai_embedding_model' => 'text-embedding-3-small',

    // Fields used to generate embedding text per entity type
    'embedding_fields' => [
        'node' => ['title', 'body'],
        'article' => ['title', 'body'],
    ],
],
```

### Storing Embeddings

The `VectorStoreInterface` provides an abstraction over vector databases:

```php
use Waaseyaa\AiVector\VectorStoreInterface;

// Store an embedding for an entity
$vectorStore->store(
    entityTypeId: 'article',
    entityId: 42,
    embedding: $embeddingProvider->embed($text),
);
```

### Similarity Search

Perform semantic search to find related content:

```php
$results = $vectorStore->search(
    query: $embeddingProvider->embed('PHP performance optimization'),
    entityTypeId: 'article',
    limit: 10,
);

foreach ($results as $result) {
    // $result is a SimilarityResult with entityId and score
    $article = $storage->load($result->entityId);
    echo sprintf('%s (score: %.3f)', $article->label(), $result->score);
}
```

### RAG Workflows

The vector package integrates with the pipeline package for retrieval-augmented generation (RAG):

1. User asks a question
2. The question is embedded using the embedding provider
3. Similar content is retrieved from the vector store
4. Retrieved content is injected into the LLM prompt as context
5. The LLM generates a response grounded in your actual content

```php
$pipeline = new Pipeline([
    new EmbedQueryStep($embeddingProvider),
    new RetrieveContextStep($vectorStore, limit: 5),
    new GenerateResponseStep($llm),
]);

$result = $executor->run($pipeline, [
    'query' => 'What are our best practices for content migration?',
]);
```

This pattern ensures AI responses are grounded in your actual content rather than relying solely on the model's training data.

## Example: Full AI Workflow

Here is how the four packages work together in a complete workflow:

```
1. Schema (ai-schema)
   Entity definitions → JSON Schema → MCP tool definitions

2. Pipeline (ai-pipeline)
   Prompt + Context → LLM call → Structured response

3. Agent (ai-agent)
   User request → Tool selection → Entity operations → Response

4. Vector (ai-vector)
   Entity content → Embeddings → Semantic search → RAG context
```

A concrete example: an editorial assistant that helps writers:

1. Writer asks: "Find articles about performance and summarize the key points"
2. `ai-vector` searches for articles semantically related to "performance"
3. `ai-pipeline` sends the retrieved articles through a summarization pipeline
4. `ai-agent` orchestrates the full flow, calling vector search and summarization tools
5. `ai-schema` ensures all entity interactions use typed, validated schemas

## Next Steps

- **[Entity System](../core/entity-system.md)** — The entities that AI packages operate on
- **[Access Control](../access/access-control.md)** — How AI agents respect permissions
- **[Installation](../getting-started/installation.md)** — Setting up the AI embedding provider
