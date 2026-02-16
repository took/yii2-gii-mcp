# yii2-gii-mcp - AI Memory Bank

**Complete Technical Reference for AI Agents**

This document serves as a comprehensive memory bank for AI coding assistants working with the yii2-gii-mcp project. It
contains complete technical specifications, architecture details, configuration options, tool documentation, and
historical context.

**Last Updated**: January 9, 2026  
**Project Version**: Phase 1-10 Complete (60% Test Coverage, 14 Production Tools)  
**MCP Protocol Version**: 2024-11-05

---

## Table of Contents

1. [Project Overview](#project-overview)
2. [Architecture](#architecture)
3. [MCP Protocol Implementation](#mcp-protocol-implementation)
4. [Available Tools](#available-tools)
5. [Configuration](#configuration)
6. [MCP Client Setup](#mcp-client-setup)
7. [Yii2 Integration](#yii2-integration)
8. [Safety Features](#safety-features)
9. [Setup Tools](#setup-tools)
10. [Testing](#testing)
11. [Development Guidelines](#development-guidelines)
12. [File Structure](#file-structure)
13. [Troubleshooting](#troubleshooting)
14. [Implementation History](#implementation-history)
15. [Best Practices for AI Agents](#best-practices-for-ai-agents)

---

## Project Overview

### Purpose

yii2-gii-mcp is a PHP-based MCP (Model Context Protocol) server that enables AI agents to interact with Yii2's Gii code
generator for automated scaffolding and code generation. It bridges the gap between AI assistants and Yii2 development
workflows.

### Goals

1. **Zero-config for standard projects**: Automatic detection of Yii2 project structure
2. **AI-optimized**: Natural language prompts translate to code generation
3. **Safety-first**: Preview mode by default, file conflict detection
4. **Comprehensive**: Cover all major Gii generators (models, CRUD, controllers, forms, modules, extensions)
5. **Standard protocol**: Full MCP compliance for compatibility with any MCP client

### Key Capabilities

- **Database Inspection**: Read-only operations to explore schema
- **Code Generation**: Safe generation with preview workflow
- **Multi-template Support**: Works with Basic, Advanced, and Advanced+API Yii2 templates
- **Validation**: SQL injection prevention, path traversal protection
- **Error Handling**: Structured JSON-RPC 2.0 error responses
- **Test Coverage**: 60% code coverage with 329 comprehensive tests (no Yii2 dependency)

### Framework Scope

**Important:** This MCP server is designed exclusively for **Yii2 framework projects**.

- ✅ **Supported**: Yii2 (all templates: Basic, Advanced, Advanced+API)
- ❌ **Not Supported**: Yii1 (legacy framework, different architecture)
- ❌ **Not Supported**: Yii3 (next-generation framework, different architecture)

**Why Yii2 only?**

- This MCP server uses **Yii2 Gii generators** which are framework-specific
- Yii1 has a different generator system (incompatible)
- Yii3 is under development with a completely different architecture
- To support other frameworks would require separate implementations

**Migration Guidance:**

- **From Yii1**: Use
  the [official Yii2 upgrade guide](https://www.yiiframework.com/doc/guide/2.0/en/intro-upgrade-from-v1)
- **To Yii3**: Wait for Yii3 stable release, then consider a separate MCP server implementation

---

## Architecture

### High-Level Architecture

```
┌─────────────────────────────────────────┐
│         MCP Client (AI Agent)           │
│       (Firebender, Claude, Cline)       │
└─────────────────────────────────────────┘
                 ↕ JSON-RPC 2.0
           (stdin/stdout/stderr)
┌─────────────────────────────────────────┐
│         MCPServer                       │
│  ┌───────────────────────────────────┐  │
│  │  Protocol Layer                   │  │
│  │  - JSON-RPC 2.0 handling          │  │
│  │  - stdio transport                │  │
│  │  - Error handling                 │  │
│  └───────────────────────────────────┘  │
│  ┌───────────────────────────────────┐  │
│  │  Tool Registry                    │  │
│  │  - Tool discovery                 │  │
│  │  - Input validation               │  │
│  └───────────────────────────────────┘  │
└─────────────────────────────────────────┘
                 ↕
┌─────────────────────────────────────────┐
│         Tools (14 total)                │
│  - ListTables (read-only)               │
│  - InspectDatabase (read-only)          │
│  - ListMigrations (read-only)           │
│  - CreateMigration (with preview)       │
│  - ExecuteMigration (with safety+SQL)   │
│  - DetectApplicationStructure (read)    │
│  - InspectComponents (read-only)        │
│  - ReadLogs (read-only)                 │
│  - GenerateModel (with preview)         │
│  - GenerateCrud (with preview)          │
│  - GenerateController (with preview)    │
│  - GenerateForm (with preview)          │
│  - GenerateModule (with preview)        │
│  - GenerateExtension (with preview)     │
└─────────────────────────────────────────┘
                 ↕
┌─────────────────────────────────────────┐
│      Yii2 + Gii Integration             │
│  - Yii2Bootstrap                        │
│  - Database schema inspection           │
│  - Code generation                      │
└─────────────────────────────────────────┘
```

### Core Components

#### 1. MCPServer (`src/MCPServer.php`)

**Responsibilities**:

- JSON-RPC 2.0 message handling
- stdio transport management (read from stdin, write to stdout)
- Method routing (`initialize`, `tools/list`, `tools/call`)
- Error handling and logging (to stderr only)

**Key Methods**:

- `start()`: Main event loop reading from stdin
- `handleRequest(Request $request)`: Route requests to appropriate handlers
- `handleInitialize(array $params)`: MCP protocol initialization
- `handleToolsList()`: Return available tools
- `handleToolsCall(array $params)`: Execute specific tool

#### 2. Protocol Layer (`src/Protocol/`)

**Classes**:

- `Message.php`: Base message class
- `Request.php`: Request message (id, method, params)
- `Response.php`: Success response (id, result)
- `ErrorResponse.php`: Error response (code, message, data)
- `StdioTransport.php`: I/O handling for stdin/stdout

**JSON-RPC 2.0 Compliance**:

- All messages are JSON objects
- Requests have: `jsonrpc`, `id`, `method`, `params`
- Responses have: `jsonrpc`, `id`, `result` (or `error`)
- Error codes follow JSON-RPC 2.0 spec

#### 3. Tool Registry (`src/ToolRegistry.php`)

**Purpose**: Manage and discover available tools

**Methods**:

- `register(ToolInterface $tool)`: Add tool to registry
- `get(string $name)`: Retrieve tool by name
- `list()`: Get all tools with metadata
- `has(string $name)`: Check if tool exists

**Tool Registration**:
Tools are registered in `bin/yii2-gii-mcp` executable during server initialization.

#### 4. Tool Interface (`src/Tools/ToolInterface.php`)

**Contract**:

```php
interface ToolInterface
{
    public function getName(): string;
    public function getDescription(): string;
    public function getInputSchema(): array;
    public function execute(array $arguments): array;
}
```

**AbstractTool** (`src/Tools/AbstractTool.php`):

- Base implementation with common functionality
- Input validation using JSON Schema
- Helper methods: `createResult()`, `createError()`, `formatTable()`
- Parameter extraction: `getRequiredParam()`, `getOptionalParam()`

---

## MCP Protocol Implementation

### Protocol Version

This server implements MCP protocol version **2024-11-05**.

### Supported Methods

#### 1. `initialize`

**Purpose**: Initialize MCP connection and negotiate capabilities

**Request**:

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "initialize",
  "params": {
    "protocolVersion": "2024-11-05",
    "capabilities": {},
    "clientInfo": {
      "name": "Firebender",
      "version": "1.1.0"
    }
  }
}
```

**Response**:

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "result": {
    "protocolVersion": "2024-11-05",
    "capabilities": {
      "tools": {}
    },
    "serverInfo": {
      "name": "yii2-gii-mcp",
      "version": "1.1.0"
    }
  }
}
```

#### 2. `tools/list`

**Purpose**: Get list of available tools with schemas

**Request**:

```json
{
  "jsonrpc": "2.0",
  "id": 2,
  "method": "tools/list"
}
```

**Response**:

```json
{
  "jsonrpc": "2.0",
  "id": 2,
  "result": {
    "tools": [
      {
        "name": "list-tables",
        "description": "Lists all database tables",
        "inputSchema": {
          "type": "object",
          "properties": {
            "connection": {
              "type": "string",
              "description": "Database connection ID"
            }
          }
        }
      }
    ]
  }
}
```

#### 3. `tools/call`

**Purpose**: Execute a specific tool

**Request**:

```json
{
  "jsonrpc": "2.0",
  "id": 3,
  "method": "tools/call",
  "params": {
    "name": "list-tables",
    "arguments": {
      "connection": "db"
    }
  }
}
```

**Response**:

```json
{
  "jsonrpc": "2.0",
  "id": 3,
  "result": {
    "content": [
      {
        "type": "text",
        "text": "Found 15 tables:\n\n[{\"name\":\"users\",...}]"
      }
    ]
  }
}
```

### Error Responses

**Standard Error Codes**:

- `-32700`: Parse error (invalid JSON)
- `-32600`: Invalid request
- `-32601`: Method not found
- `-32602`: Invalid params
- `-32603`: Internal error
- `-32000` to `-32099`: Server-defined errors

**Example Error**:

```json
{
  "jsonrpc": "2.0",
  "id": 3,
  "error": {
    "code": -32602,
    "message": "Invalid params",
    "data": {
      "details": "Missing required parameter: tableName"
    }
  }
}
```

### Transport

**stdio Transport**:

- **stdin**: Read JSON-RPC requests line by line
- **stdout**: Write JSON-RPC responses (one per line)
- **stderr**: Debug logging (never protocol messages)

**Message Format**:

- Each message is a single line of JSON
- Messages are newline-terminated
- No length prefixing (rely on newline delimiter)

---

## Available Tools

### 1. list-tables

**Purpose**: List all database tables with metadata

**Type**: Read-only (safe)

**Input Schema**:

```json
{
  "type": "object",
  "properties": {
    "connection": {
      "type": "string",
      "description": "Database connection component ID (default: 'db')"
    },
    "detailed": {
      "type": "boolean",
      "description": "Include detailed column information (default: true)"
    }
  }
}
```

**Example Usage**:

```json
{
  "name": "list-tables",
  "arguments": {
    "connection": "db",
    "detailed": true
  }
}
```

**Output**:

- Table names
- Column information (name, type, size, nullable, default)
- Primary keys
- Foreign keys (if detailed=true)

**Implementation**: `src/Tools/ListTables.php`

### 2. inspect-database

**Purpose**: Detailed database schema inspection

**Type**: Read-only (safe)

**Input Schema**:

```json
{
  "type": "object",
  "properties": {
    "connection": {
      "type": "string",
      "description": "Database connection ID"
    },
    "tablePattern": {
      "type": "string",
      "description": "SQL LIKE pattern to filter tables (e.g., 'user%')"
    },
    "includeViews": {
      "type": "boolean",
      "description": "Include database views (default: false)"
    }
  }
}
```

**Output**:

- Complete table schemas
- Column definitions (type, size, precision, scale)
- Indexes (type, columns, unique)
- Foreign keys (source, target, on delete, on update)
- Constraints (check, unique, default)

**Use Cases**:

- Before model generation (understand relationships)
- Database documentation
- Schema analysis

**Implementation**: `src/Tools/InspectDatabase.php`

### 3. generate-model

**Purpose**: Generate Yii2 ActiveRecord model from database table

**Type**: Writes files (preview mode default)

**Template Support**: Automatically detects Basic or Advanced Template and uses appropriate defaults

**Input Schema**:

```json
{
  "type": "object",
  "properties": {
    "tableName": {
      "type": "string",
      "description": "Database table name (required)"
    },
    "modelClass": {
      "type": "string",
      "description": "Model class name (optional, auto-generated from table)"
    },
    "namespace": {
      "type": "string",
      "description": "Namespace for model (default: 'common\\models' for Advanced Template, 'app\\models' for Basic Template). For Advanced Template, you can specify: common\\models, frontend\\models, backend\\models, or api\\models"
    },
    "baseClass": {
      "type": "string",
      "description": "Base class (default: 'yii\\db\\ActiveRecord')"
    },
    "db": {
      "type": "string",
      "description": "DB connection component (default: 'db')"
    },
    "generateRelations": {
      "type": "string",
      "enum": ["all", "none"],
      "description": "Generate relation methods (default: 'all')"
    },
    "generateLabelsFromComments": {
      "type": "boolean",
      "description": "Use column comments for labels (default: true)"
    },
    "preview": {
      "type": "boolean",
      "description": "Preview mode - don't write files (default: true)"
    }
  },
  "required": ["tableName"]
}
```

**Workflow**:

1. Detect template type (Basic or Advanced)
2. Set default namespace based on template type
3. Validate table exists
4. Generate model code using Gii
5. If preview=true: Return code preview
6. If preview=false: Write files, return status

**Usage Examples**:

Generate model in common/models (Advanced Template default):
```json
{
  "tableName": "users",
  "preview": true
}
```

Generate model in frontend/models (Advanced Template):
```json
{
  "tableName": "users",
  "namespace": "frontend\\models",
  "preview": true
}
```

Generate model in backend/models (Advanced Template):
```json
{
  "tableName": "users",
  "namespace": "backend\\models",
  "preview": true
}
```

Generate model in api/models (Advanced Template with API):
```json
{
  "tableName": "users",
  "namespace": "api\\models",
  "preview": true
}
```

Generate model in app/models (Basic Template):
```json
{
  "tableName": "users",
  "preview": true
}
```

**Output (preview=true)**:

```json
{
  "content": [
    {
      "type": "text",
      "text": "Preview of User.php:\n\n<?php\nnamespace app\\models;\n..."
    }
  ]
}
```

**Output (preview=false)**:

```json
{
  "content": [
    {
      "type": "text",
      "text": "Generated files:\n✓ app/models/User.php"
    }
  ]
}
```

**Safety Features**:

- Defaults to preview mode
- File conflict detection
- Table existence validation
- SQL injection prevention in table names

**Implementation**: `src/Tools/GenerateModel.php`

### 4. generate-crud

**Purpose**: Generate complete CRUD scaffolding (controller, search model, views)

**Type**: Writes files (preview mode default)

**Template Support**: Automatically detects Basic or Advanced Template and uses appropriate defaults

**Input Schema**:

```json
{
  "type": "object",
  "properties": {
    "modelClass": {
      "type": "string",
      "description": "Full model class name (e.g., 'app\\models\\User' or 'common\\models\\User') (required)"
    },
    "component": {
      "type": "string",
      "enum": ["frontend", "frontpage", "backend", "backoffice", "api", "common"],
      "description": "For Advanced Template: which component to generate CRUD into. Supports both standard (frontend/backend) and alternative naming (frontpage/backoffice). If not specified, auto-detects from model namespace or defaults to frontend."
    },
    "controllerClass": {
      "type": "string",
      "description": "Controller class name (optional, auto-generated based on model and component)"
    },
    "viewPath": {
      "type": "string",
      "description": "View path (optional, auto-generated: @app/views/<controller> for Basic, @{component}/views/<model> for Advanced)"
    },
    "baseControllerClass": {
      "type": "string",
      "description": "Base controller class (default: 'yii\\web\\Controller')"
    },
    "indexWidgetType": {
      "type": "string",
      "enum": ["grid", "list"],
      "description": "Index page widget (default: 'grid')"
    },
    "searchModelClass": {
      "type": "string",
      "description": "Search model class (optional, auto-generated)"
    },
    "enableI18N": {
      "type": "boolean",
      "description": "Enable internationalization (default: false)"
    },
    "preview": {
      "type": "boolean",
      "description": "Preview mode (default: true)"
    }
  },
  "required": ["modelClass"]
}
```

**Generated Files**:

- Controller (e.g., `UserController.php`)
- Search model (e.g., `UserSearch.php`)
- Views:
    - `index.php` (list with GridView/ListView)
    - `view.php` (detail view)
    - `create.php` (create form)
    - `update.php` (update form)
    - `_form.php` (shared form partial)
    - `_search.php` (search form partial)

**Workflow**:

1. Detect template type (Basic or Advanced)
2. Auto-detect component from model namespace or use specified component
3. Validate model class exists
4. Generate all CRUD files with template-aware paths
5. If preview=true: Return file list
6. If preview=false: Write files, return status

**Usage Examples**:

Generate CRUD for common model (Advanced Template, auto-detects frontend):
```json
{
  "modelClass": "common\\models\\User",
  "preview": true
}
```
Result: Controller in `frontend\controllers\UserController`, Search in `frontend\models\UserSearch`, Views in `@frontend/views/user`

Generate CRUD in backend component (Advanced Template):
```json
{
  "modelClass": "common\\models\\User",
  "component": "backend",
  "preview": true
}
```
Result: Controller in `backend\controllers\UserController`, Search in `backend\models\UserSearch`, Views in `@backend/views/user`

Generate CRUD in api component (Advanced Template):
```json
{
  "modelClass": "common\\models\\Product",
  "component": "api",
  "preview": true
}
```
Result: Controller in `api\controllers\ProductController`, Search in `api\models\ProductSearch`, Views in `@api/views/product`

Generate CRUD for model already in specific component (Advanced Template):
```json
{
  "modelClass": "backend\\models\\AdminSettings",
  "preview": true
}
```
Result: Auto-detects backend component from model namespace

Generate CRUD for Basic Template:
```json
{
  "modelClass": "app\\models\\Post",
  "preview": true
}
```
Result: Controller in `app\controllers\PostController`, Search in `app\models\PostSearch`, default views path

**Safety Features**:

- Model class existence check
- File conflict detection
- Preview mode default

**Implementation**: `src/Tools/GenerateCrud.php`

### 5. generate-controller

**Purpose**: Generate Yii2 controller with custom actions

**Type**: Writes files (preview mode default)

**Template Support**: Automatically detects Basic or Advanced Template and uses appropriate defaults

**Input Schema**:

```json
{
  "type": "object",
  "properties": {
    "controllerID": {
      "type": "string",
      "description": "Controller ID (e.g., 'post', 'admin/user') (required)"
    },
    "component": {
      "type": "string",
      "enum": ["frontend", "backend", "api", "common"],
      "description": "For Advanced Template: which component to generate controller into. Defaults to frontend."
    },
    "actions": {
      "type": "string",
      "description": "Comma-separated action IDs (e.g., 'index,view,create')"
    },
    "namespace": {
      "type": "string",
      "description": "Namespace (default: 'app\\controllers' for Basic, '{component}\\controllers' for Advanced)"
    },
    "baseClass": {
      "type": "string",
      "description": "Base class (default: 'yii\\web\\Controller')"
    },
    "preview": {
      "type": "boolean",
      "description": "Preview mode (default: true)"
    }
  },
  "required": ["controllerID"]
}
```

**Usage Examples**:

Generate controller in frontend (Advanced Template default):
```json
{
  "controllerID": "post",
  "actions": "index,view,create,update,delete",
  "preview": true
}
```

Generate controller in backend (Advanced Template):
```json
{
  "controllerID": "admin-user",
  "component": "backend",
  "actions": "index,view,create,update,delete",
  "preview": true
}
```

Generate controller in api (Advanced Template):
```json
{
  "controllerID": "product",
  "component": "api",
  "actions": "index,view,create",
  "preview": true
}
```

Generate controller in Basic Template:
```json
{
  "controllerID": "post",
  "actions": "index,view",
  "preview": true
}
```

**Output**: Controller with action methods and view rendering

**Implementation**: `src/Tools/GenerateController.php`

### 6. generate-form

**Purpose**: Generate Yii2 form model for data collection and validation

**Type**: Writes files (preview mode default)

**Template Support**: Automatically detects Basic or Advanced Template and uses appropriate defaults

**Input Schema**:

```json
{
  "type": "object",
  "properties": {
    "modelClass": {
      "type": "string",
      "description": "Form model class name (e.g., 'ContactForm', 'LoginForm') (required)"
    },
    "component": {
      "type": "string",
      "enum": ["frontend", "backend", "api", "common"],
      "description": "For Advanced Template: which component to generate form into. Defaults to common for shared forms."
    },
    "namespace": {
      "type": "string",
      "description": "Namespace (default: 'app\\models' for Basic, 'common\\models' for Advanced)"
    },
    "viewPath": {
      "type": "string",
      "description": "View path (default: '@app/views' for Basic, '@{component}/views' for Advanced)"
    },
    "viewName": {
      "type": "string",
      "description": "View file name (optional)"
    },
    "scenarioName": {
      "type": "string",
      "description": "Scenario name (default: 'default')"
    },
    "preview": {
      "type": "boolean",
      "description": "Preview mode (default: true)"
    }
  },
  "required": ["modelClass"]
}
```

**Usage Examples**:

Generate form in common (Advanced Template, shared across components):
```json
{
  "modelClass": "ContactForm",
  "preview": true
}
```

Generate form in frontend (Advanced Template):
```json
{
  "modelClass": "NewsletterForm",
  "component": "frontend",
  "preview": true
}
```

Generate form in backend (Advanced Template):
```json
{
  "modelClass": "SettingsForm",
  "component": "backend",
  "preview": true
}
```

Generate form in Basic Template:
```json
{
  "modelClass": "ContactForm",
  "preview": true
}
```

**Generated Files**:

- Form model class with attributes and validation rules
- Optional view file

**Use Cases**:

- Contact forms
- Registration forms
- Custom data entry forms

**Implementation**: `src/Tools/GenerateForm.php`

### 7. generate-module

**Purpose**: Generate Yii2 module structure with directory layout

**Type**: Writes files (preview mode default)

**Input Schema**:

```json
{
  "type": "object",
  "properties": {
    "moduleID": {
      "type": "string",
      "description": "Module ID (e.g., 'admin', 'api') (required)"
    },
    "moduleClass": {
      "type": "string",
      "description": "Module class name (optional, auto-generated)"
    },
    "preview": {
      "type": "boolean",
      "description": "Preview mode (default: true)"
    }
  },
  "required": ["moduleID"]
}
```

**Generated Structure**:

```
modules/admin/
├── Module.php
├── controllers/
│   └── DefaultController.php
├── models/
├── views/
│   └── default/
│       └── index.php
└── assets/
```

**Use Cases**:

- Admin panels
- API modules
- Modular application structure

**Implementation**: `src/Tools/GenerateModule.php`

### 8. generate-extension

**Purpose**: Generate Yii2 extension scaffolding with Composer packaging

**Type**: Writes files (preview mode default)

**Input Schema**:

```json
{
  "type": "object",
  "properties": {
    "vendorName": {
      "type": "string",
      "description": "Vendor name (e.g., 'mycompany') (required)"
    },
    "packageName": {
      "type": "string",
      "description": "Package name (e.g., 'yii2-widget') (required)"
    },
    "namespace": {
      "type": "string",
      "description": "Root namespace (optional)"
    },
    "type": {
      "type": "string",
      "description": "Extension type (default: 'yii2-extension')"
    },
    "title": {
      "type": "string",
      "description": "Extension title"
    },
    "description": {
      "type": "string",
      "description": "Extension description"
    },
    "keywords": {
      "type": "string",
      "description": "Comma-separated keywords"
    },
    "authorName": {
      "type": "string",
      "description": "Author name"
    },
    "authorEmail": {
      "type": "string",
      "description": "Author email"
    },
    "preview": {
      "type": "boolean",
      "description": "Preview mode (default: true)"
    }
  },
  "required": ["vendorName", "packageName"]
}
```

**Generated Structure**:

```
yii2-widget/
├── composer.json
├── LICENSE
├── README.md
├── src/
└── tests/
```

**Implementation**: `src/Tools/GenerateExtension.php`

### 9. list-migrations

**Purpose**: List all database migrations with their status

**Type**: Read-only (safe)

**Input Schema**:

```json
{
  "type": "object",
  "properties": {
    "status": {
      "type": "string",
      "enum": ["all", "applied", "pending"],
      "default": "all",
      "description": "Filter migrations by status"
    },
    "limit": {
      "type": "integer",
      "default": 10,
      "description": "Limit number of results (0 for all)"
    }
  }
}
```

**Output**:

- Migration names
- Status (applied/pending)
- Applied timestamps (for applied migrations)
- Summary counts

**Use Cases**:

- Check migration status before applying
- Review migration history
- Identify pending migrations

**Implementation**: `src/Tools/ListMigrations.php`

### 10. create-migration

**Purpose**: Create new migration files with comprehensive options

**Type**: Writes files (preview mode default)

**Input Schema**:

```json
{
  "type": "object",
  "properties": {
    "name": {
      "type": "string",
      "description": "Migration name (e.g., 'create_users_table', 'add_status_to_posts')"
    },
    "fields": {
      "type": "array",
      "items": {"type": "string"},
      "description": "Field definitions (e.g., ['name:string(255):notNull', 'email:string:notNull:unique'])"
    },
    "migrationType": {
      "type": "string",
      "enum": ["create", "add", "drop", "junction", "custom"],
      "default": "custom",
      "description": "Type of migration"
    },
    "tableName": {
      "type": "string",
      "description": "Explicit table name (optional, extracted from name)"
    },
    "junctionTable1": {
      "type": "string",
      "description": "First table for junction type (e.g., 'users')"
    },
    "junctionTable2": {
      "type": "string",
      "description": "Second table for junction type (e.g., 'posts')"
    },
    "migrationPath": {
      "type": "string",
      "description": "Custom migration directory path"
    },
    "migrationNamespace": {
      "type": "string",
      "description": "Migration namespace (e.g., 'app\\migrations')"
    },
    "templateFile": {
      "type": "string",
      "description": "Custom template file path"
    },
    "useTablePrefix": {
      "type": "boolean",
      "default": true,
      "description": "Use table prefix from db config"
    },
    "addTimestamps": {
      "type": "boolean",
      "default": false,
      "description": "Add created_at and updated_at columns"
    },
    "addSoftDelete": {
      "type": "boolean",
      "default": false,
      "description": "Add deleted_at column for soft deletes"
    },
    "addForeignKeys": {
      "type": "boolean",
      "default": false,
      "description": "Auto-generate FKs for fields ending with _id"
    },
    "onDeleteCascade": {
      "type": "boolean",
      "default": false,
      "description": "Use CASCADE for ON DELETE (default: RESTRICT)"
    },
    "comment": {
      "type": "string",
      "description": "Table comment"
    },
    "indexes": {
      "type": "array",
      "items": {"type": "string"},
      "description": "Index definitions (e.g., ['email'], ['user_id,post_id'])"
    },
    "foreignKeys": {
      "type": "array",
      "items": {"type": "object"},
      "description": "Explicit FK definitions with custom actions"
    },
    "preview": {
      "type": "boolean",
      "default": true,
      "description": "Preview mode (default: true)"
    }
  },
  "required": ["name"]
}
```

**Migration Types**:

- **create**: Create table migration with field definitions
- **add**: Add columns to existing table
- **drop**: Drop table or columns
- **junction**: Create junction table for many-to-many relationships
- **custom**: Empty migration with safeUp/safeDown methods

**Field Definition Format**:

- Basic: `name:type` (e.g., `name:string`)
- With size: `name:string(255)` or `price:decimal(10,2)`
- With modifiers: `name:string:notNull:unique`
- With default: `status:integer:defaultValue(1)`
- Complex: `email:string(255):notNull:unique:comment('User email address')`
- Enum: `status:enum('draft','published','archived'):notNull:defaultValue('draft')`

**Supported Types**:

- string, text, smallint, integer, bigint
- float, double, decimal
- datetime, timestamp, time, date
- binary, boolean, money, json
- enum (e.g., enum('value1','value2','value3'))

**Supported Modifiers**:

- notNull, unique, unsigned
- defaultValue(value)
- comment('text')
- check('condition')
- append('RAW SQL')

**Advanced Features**:

- **Auto timestamps**: Adds created_at/updated_at columns
- **Soft deletes**: Adds deleted_at column
- **Auto foreign keys**: Detects _id fields and creates FK constraints
- **Custom indexes**: Generate indexes for specific columns (single or composite)
- **Custom FK actions**: Full control over ON DELETE/ON UPDATE (CASCADE, RESTRICT, SET NULL, SET DEFAULT, NO ACTION)
- **Junction tables**: Creates many-to-many relationship tables
- **Custom namespaces**: Support for modular migrations
- **Table prefixes**: Respects Yii2 table prefix configuration

**Example Usage**:

Create table migration:

```json
{
  "name": "create_users_table",
  "migrationType": "create",
  "fields": [
    "username:string(255):notNull:unique",
    "email:string(255):notNull:unique",
    "status:integer:defaultValue(1)",
    "role_id:integer:notNull"
  ],
  "addTimestamps": true,
  "addForeignKeys": true
}
```

Add columns migration:

```json
{
  "name": "add_profile_fields_to_users",
  "migrationType": "add",
  "tableName": "users",
  "fields": [
    "bio:text:null",
    "avatar:string(255):null",
    "last_login:timestamp:null"
  ]
}
```

Junction table migration:

```json
{
  "name": "create_user_post_junction",
  "migrationType": "junction",
  "junctionTable1": "users",
  "junctionTable2": "posts"
}
```

Enum field migration:

```json
{
  "name": "create_posts_table",
  "migrationType": "create",
  "fields": [
    "title:string(255):notNull",
    "content:text:notNull",
    "status:enum('draft','published','archived'):notNull:defaultValue('draft')",
    "priority:enum('low','medium','high'):defaultValue('medium')"
  ],
  "addTimestamps": true
}
```

Migration with custom indexes:

```json
{
  "name": "create_posts_table",
  "migrationType": "create",
  "fields": [
    "title:string(255):notNull",
    "user_id:integer:notNull",
    "status:string(20):notNull",
    "created_at:timestamp:notNull"
  ],
  "indexes": ["user_id", "status", "user_id,created_at"],
  "addTimestamps": false
}
```

Migration with custom FK actions:

```json
{
  "name": "create_posts_table",
  "migrationType": "create",
  "fields": [
    "title:string(255):notNull",
    "user_id:integer:notNull",
    "category_id:integer:null"
  ],
  "foreignKeys": [
    {
      "field": "user_id",
      "table": "users",
      "onDelete": "CASCADE",
      "onUpdate": "RESTRICT"
    },
    {
      "field": "category_id",
      "table": "categories",
      "onDelete": "SET NULL",
      "onUpdate": "CASCADE"
    }
  ]
}
```

**Use Cases**:

- Create complex table migrations with full schema
- Add columns with proper constraints and foreign keys
- Generate junction tables for relationships
- Use custom templates for organization standards
- Support for modular Yii2 applications with namespaces

**Implementation**: `src/Tools/CreateMigration.php`

### 11. execute-migration

**Purpose**: Execute migration operations with mandatory safety confirmations, and preview SQL for existing migrations

**Type**: Modifies database (requires confirmations) / Read-only for SQL preview

**Input Schema**:

```json
{
  "type": "object",
  "properties": {
    "operation": {
      "type": "string",
      "enum": ["up", "down", "create", "redo", "fresh"],
      "description": "Migration operation (required)"
    },
    "migrationName": {
      "type": "string",
      "description": "Migration name (required for down/redo; for create: new name). For SQL preview: name of existing migration to preview."
    },
    "direction": {
      "type": "string",
      "enum": ["up", "down"],
      "default": "up",
      "description": "Migration direction for SQL preview (up or down). Only used when preview=true and migrationName is provided."
    },
    "migrationCount": {
      "type": "integer",
      "default": 1,
      "description": "Number of migrations (for up/down)"
    },
    "fields": {
      "type": "array",
      "items": {"type": "string"},
      "description": "Field definitions for create (e.g., ['name:string:notNull', 'email:string:unique'])"
    },
    "confirmation": {
      "type": "string",
      "description": "REQUIRED: Must be exact string 'yes'"
    },
    "destructiveConfirmation": {
      "type": "string",
      "description": "REQUIRED for down/fresh/redo: Must be 'I understand this will modify the database'"
    },
    "preview": {
      "type": "boolean",
      "default": true,
      "description": "Preview mode (default: true). Set migrationName to preview SQL for existing migration."
    }
  },
  "required": ["operation", "confirmation"]
}
```

**Operations**:

- **up**: Apply pending migrations
- **down**: Revert applied migrations
- **create**: Create new migration file (with optional field definitions)
- **redo**: Revert and re-apply recent migration
- **fresh**: Drop all tables and re-apply all migrations

**SQL Preview Mode**:
When `preview=true` and `migrationName` is provided (without operation 'create'), this tool shows the SQL statements
that would be executed by that migration:

- Set `migrationName` to the name of an existing migration
- Set `direction` to 'up' or 'down' (default: 'up')
- Returns SQL preview without executing anything (read-only, safe)
- Example:
  `{"operation": "up", "migrationName": "m240107_create_users", "direction": "up", "confirmation": "yes", "preview": true}`

**Safety Features**:

- Preview mode enabled by default
- `confirmation="yes"` required for all operations
- `destructiveConfirmation="I understand this will modify the database"` required for down/fresh/redo
- All operations logged to stderr
- Detailed execution results returned

**Field Definition Format** (for create operation):

```
name:type[:modifier[:modifier...]]

Examples:
- "name:string:notNull"
- "email:string:notNull:unique"
- "status:integer:defaultValue(1)"
- "price:decimal(10,2):notNull"
- "created_at:timestamp:notNull"
```

**Workflow**:

1. AI calls with preview=true to show what will happen
2. User reviews and approves
3. AI calls with preview=false and required confirmations
4. Tool executes and returns results

**Use Cases**:

- Apply pending migrations
- Create new migrations with field definitions
- Revert migrations (with safety confirmations)
- Fresh database setup

**⚠️ WARNING**: This tool can modify database structure. Always preview first!

**Implementation**: `src/Tools/ExecuteMigration.php`

### 12. detect-application-structure

**Purpose**: Auto-detect Yii2 project structure, template type, applications, modules, and environment configuration

**Type**: Read-only (safe)

**Input Schema**:

```json
{
  "type": "object",
  "properties": {
    "basePath": {
      "type": "string",
      "description": "Base path to scan (defaults to project root from configuration)",
      "default": ""
    }
  }
}
```

**Output Structure**:

```json
{
  "templateType": "basic|advanced|advanced-api",
  "basePath": "/path/to/project",
  "applications": [
    {
      "name": "frontend|frontpage|backend|backoffice|console|api|app",
      "path": "/full/path",
      "type": "web|console|api",
      "hasWeb": true,
      "entryPoints": [
        {
          "file": "web/index.php",
          "env": "dev",
          "debug": true
        }
      ],
      "modules": [
        {
          "id": "admin",
          "path": "/path/to/module",
          "class": null
        }
      ]
    }
  ],
  "environments": {
    "available": ["dev", "prod", "index"],
    "current": "dev",
    "currentDetails": {
      "YII_ENV": "dev",
      "YII_DEBUG": true,
      "detectedFrom": "frontend/web/index.php"
    },
    "sources": ["environments-folder", "index-files", "config-files", "env-files"]
  }
}
```

**Features**:

**Template Detection**:
- Basic Template: Detects `/app` and `/config` directories
- Advanced Template: Detects `/common` and `/console` directories
- Advanced+API: Detects Advanced structure plus `/api` directory

**Application Discovery**:
- Scans for application directories (frontend, backend, console, api)
- Identifies application type (web, console, api)
- Detects entry points (index.php, index-test.php, yii)
- Parses YII_ENV and YII_DEBUG constants from entry files

**Module Detection**:
- Scans `/modules` directories in each application
- Checks for Module.php files
- Returns module IDs and paths

**Environment Analysis**:
- **Init System**: Scans `environments/` folder for available environments
- **Current Environment**: Compares actual index.php files with environment templates
- **Config Files**: Detects *-local.php, *-prod.php, *-dev.php patterns
- **.env Files**: Checks for .env, .env.local, .env.prod, etc.
- **YII_ENV Constants**: Parses defined environment constants

**Use Cases**:

1. **Project Discovery**: Understand unfamiliar Yii2 project structure
2. **Environment Verification**: Check which environment is currently configured
3. **Module Inventory**: List all available modules across applications
4. **Setup Validation**: Verify init system is properly configured
5. **Documentation**: Generate project structure documentation

**Example Usage**:

Detect structure of current project:

```json
{
  "name": "detect-application-structure",
  "arguments": {}
}
```

Analyze specific path:

```json
{
  "name": "detect-application-structure",
  "arguments": {
    "basePath": "/path/to/yii2/project"
  }
}
```

**Implementation**:
- Tool: `src/Tools/DetectApplicationStructure.php`
- Helper: `src/Helpers/ProjectStructureHelper.php`

**Helper Methods** (`ProjectStructureHelper`):

- `detectTemplateType(string $basePath): string` - Returns 'basic', 'advanced', or 'advanced-api'
- `findApplicationDirs(string $basePath): array` - Find all application directories
- `isYii2Application(string $path): bool` - Verify valid Yii2 app structure
- `getApplicationType(string $path): string` - Determine app type (web/console/api)
- `findModules(string $appPath): array` - Scan for modules in application
- `detectEnvironments(string $basePath): array` - Comprehensive environment detection
- `scanEnvironmentsFolder(string $basePath): array` - Get available environments from environments/
- `detectCurrentEnvironment(string $basePath): ?array` - Detect currently configured environment
- `parseIndexPhpFile(string $filePath): array` - Parse YII_ENV and YII_DEBUG from index.php
- `compareIndexFiles(string $actualFile, string $basePath): ?string` - Match with environment templates
- `detectEnvironmentsFromConfigFiles(string $basePath): array` - Find environments from config patterns
- `hasEnvFiles(string $basePath): bool` - Check for .env files

**Advanced Environment Detection**:

The tool implements a comprehensive multi-source environment detection strategy:

1. **Primary Detection** (Advanced Template Init System):
   - Scans `environments/` folder for available environment configurations (dev, prod, index, etc.)
   - Reads actual `index.php` files from `frontend/web/`, `backend/web/`, `api/web/`
   - Parses YII_ENV and YII_DEBUG constants using regex
   - Reads template `index.php` files from `environments/dev/`, `environments/prod/`, etc.
   - Compares actual files with templates to determine current environment
   - Reports matched environment with high confidence

2. **Secondary Detection** (Config File Patterns):
   - Scans config directories for patterns: `*-local.php`, `*-prod.php`, `*-dev.php`, `*-test.php`, `*-staging.php`
   - Searches in: `config/`, `common/config/`, `frontend/config/`, `backend/config/`, `console/config/`, `api/config/`
   - Adds detected environments to available list

3. **Tertiary Detection** (.env Files):
   - Checks for: `.env`, `.env.local`, `.env.prod`, `.env.dev`, `.env.test`, `.env.staging`
   - Indicates modern environment configuration approach
   - Added as detection source for completeness

4. **Result Aggregation**:
   - Available: Combined list from all sources (deduplicated)
   - Current: Best match from init system comparison or YII_ENV constant value
   - Details: YII_ENV, YII_DEBUG values, source file
   - Sources: List of detection methods used

**Output Format**:

Human-readable text with:
- Template type and base path
- Application listings with details
- Entry points with environment constants
- Module inventories
- Environment analysis
- JSON representation for programmatic access

**⚠️ Notes**:

- Read-only operation (no modifications)
- Handles missing directories gracefully
- Falls back to basic template if structure unclear
- Works without Yii2 initialization (filesystem only)

### 13. read-logs

**Purpose**: Read and filter Yii2 application logs from files and database with advanced filtering capabilities

**Type**: Read-only (safe)

**Input Schema**:

```json
{
  "type": "object",
  "properties": {
    "application": {
      "type": "string",
      "description": "Specific application (frontend/backend/console/api/app) or empty for all",
      "default": ""
    },
    "source": {
      "type": "string",
      "enum": ["file", "db", "both"],
      "description": "Log source: file (runtime/logs), db (log table), or both",
      "default": "both"
    },
    "level": {
      "type": "string",
      "enum": ["error", "warning", "info", "trace"],
      "description": "Filter by log level (optional)"
    },
    "category": {
      "type": "string",
      "description": "Filter by category (supports wildcards: application.*, yii\\db\\*)",
      "default": ""
    },
    "since": {
      "type": "string",
      "description": "Start datetime (ISO 8601: 2024-01-09T12:00:00 or YYYY-MM-DD HH:MM:SS)",
      "default": ""
    },
    "until": {
      "type": "string",
      "description": "End datetime (ISO 8601: 2024-01-09T18:00:00 or YYYY-MM-DD HH:MM:SS)",
      "default": ""
    },
    "search": {
      "type": "string",
      "description": "Full-text search in messages (case-insensitive)",
      "default": ""
    },
    "limit": {
      "type": "integer",
      "description": "Maximum entries to return (default: 100)",
      "default": 100
    }
  }
}
```

**Features**:

**Multi-Source Support**:
- FileTarget: Reads from `runtime/logs/app.log` in each application
- DbTarget: Queries log table in database
- Both: Aggregates logs from files and database

**Multi-Application Support**:
- Auto-discovers logs across all applications (frontend, backend, console, api)
- Aggregates logs from multiple applications with application name prefix
- Sorts by timestamp (newest first)
- Option to filter by specific application

**Advanced Filtering**:
- **Level**: error, warning, info, trace
- **Category**: Exact match or wildcard patterns (e.g., `application.*`, `yii\db\*`)
- **Time Range**: Filter by since/until timestamps
- **Full-Text Search**: Case-insensitive search in message and trace
- **Limit**: Control number of results returned

**Log Format Parsing**:
- Yii2 format: `YYYY-MM-DD HH:MM:SS [IP][userId][sessionId][level][category] message`
- Multi-line stack trace handling
- Extracts: timestamp, IP, userId, sessionId, level, category, message, trace

**Output**:
- Human-readable summary with statistics
- Structured JSON with all log entries
- Statistics by level and by application
- Total and returned counts

**Example Usage**:

Show last 50 errors from all applications:

```json
{
  "name": "read-logs",
  "arguments": {
    "level": "error",
    "limit": 50
  }
}
```

Search for database errors in frontend:

```json
{
  "name": "read-logs",
  "arguments": {
    "application": "frontend",
    "search": "database",
    "level": "error"
  }
}
```

Get warnings from last 24 hours:

```json
{
  "name": "read-logs",
  "arguments": {
    "level": "warning",
    "since": "2024-01-08 12:00:00",
    "limit": 100
  }
}
```

Filter by category with wildcard:

```json
{
  "name": "read-logs",
  "arguments": {
    "category": "yii\\db\\*",
    "level": "error"
  }
}
```

**Common Log Locations**:
- Basic Template: `{basePath}/runtime/logs/app.log`
- Advanced Template: 
  - `{basePath}/frontend/runtime/logs/app.log`
  - `{basePath}/backend/runtime/logs/app.log`
  - `{basePath}/console/runtime/logs/app.log`
  - `{basePath}/api/runtime/logs/app.log`

**Database Log Table**:
- Table name: typically `log`, `logs`, or `yii_log`
- Columns: id, level, category, log_time, prefix, message
- Level mapping: 1=error, 2=warning, 4=info, 8=trace

**Implementation**:
- Tool: `src/Tools/ReadLogs.php`
- Helper: `src/Helpers/LogReaderHelper.php`

**Helper Methods** (`LogReaderHelper`):

- `findLogFiles(string $basePath, ?string $application): array` - Find log directories
- `readLogFile(string $filePath, array $filters): array` - Read and parse log file
- `parseLogLine(string $line): ?array` - Parse single log line
- `readDbLogs(Connection $db, array $filters): array` - Read from database
- `getLogTableName(Connection $db): ?string` - Find log table name
- `applyFilters(array $logs, array $filters): array` - Apply all filters
- `mapLogLevel(mixed $level): string` - Convert numeric to string level
- `logLevelToNumber(string $level): int` - Convert string to numeric level
- `aggregateLogs(array $logsByApp, array $filters): array` - Merge and sort logs

**Use Cases**:

1. **Error Monitoring**: Quickly find recent errors across all applications
2. **Debugging**: Search for specific error messages or exceptions
3. **Performance Analysis**: Filter by category (e.g., database queries)
4. **Audit Trail**: Review logs from specific time periods
5. **Application Health**: Check warning and error trends
6. **Development**: Monitor console application logs during development

**⚠️ Notes**:

- Read-only operation (no log modifications)
- Handles missing log files gracefully
- Database logs require DbTarget configuration
- Large log files may take time to parse
- Limit parameter prevents memory issues with large result sets
- Case-insensitive full-text search

### 14. inspect-components

**Purpose**: List and analyze application components including controllers, models, and views with detailed metadata extraction

**Type**: Read-only (safe)

**Input Schema**:

```json
{
  "type": "object",
  "properties": {
    "application": {
      "type": "string",
      "description": "Application name (frontend/backend/console/api). Empty for current application.",
      "default": ""
    },
    "module": {
      "type": "string",
      "description": "Module name within application. Empty for main application.",
      "default": ""
    },
    "componentType": {
      "type": "string",
      "enum": ["controllers", "models", "views", "all"],
      "description": "Filter by component type",
      "default": "all"
    },
    "includeDetails": {
      "type": "boolean",
      "description": "Include detailed metadata (actions, rules, relations, etc.)",
      "default": true
    }
  }
}
```

**Features**:

**Controller Analysis**:
- Lists all controller classes in application/module
- Extracts action methods (actionXxx) and inline actions
- Parses behaviors configuration
- Identifies filters (AccessControl, VerbFilter, etc.)
- Extracts action parameters and PHPDoc comments
- Shows parent controller class

**Model Analysis**:
- Lists all model classes (ActiveRecord and form models)
- Extracts attributes from rules and public properties
- Parses validation rules with complete configuration
- Identifies scenarios configuration
- For ActiveRecord: extracts table name and relations
- Shows parent model class

**View Discovery**:
- Lists view files organized by controller
- Scans view directories for .php files
- Groups views by their corresponding controller

**Component Type Filtering**:
- `all`: Shows controllers, models, and views (default)
- `controllers`: Shows only controllers
- `models`: Shows only models
- `views`: Shows only view files

**Detail Levels**:
- `includeDetails: true`: Full analysis with metadata (actions, rules, relations, etc.)
- `includeDetails: false`: Basic listing with names and files only

**Output**:
- Human-readable summary with statistics
- Detailed component listings with metadata
- JSON representation for programmatic access

**Example Usage**:

Inspect all components in current application:

```json
{
  "name": "inspect-components",
  "arguments": {
    "componentType": "all"
  }
}
```

List only controllers in backend application:

```json
{
  "name": "inspect-components",
  "arguments": {
    "application": "backend",
    "componentType": "controllers",
    "includeDetails": true
  }
}
```

Analyze models in a specific module:

```json
{
  "name": "inspect-components",
  "arguments": {
    "application": "frontend",
    "module": "shop",
    "componentType": "models"
  }
}
```

Quick view list without details:

```json
{
  "name": "inspect-components",
  "arguments": {
    "componentType": "views",
    "includeDetails": false
  }
}
```

**Sample Output Structure**:

```json
{
  "application": "backend",
  "module": null,
  "basePath": "/path/to/backend",
  "controllers": [
    {
      "type": "controller",
      "class": "backend\\controllers\\UserController",
      "shortName": "UserController",
      "namespace": "backend\\controllers",
      "file": "/path/to/controllers/UserController.php",
      "parent": "yii\\web\\Controller",
      "actions": [
        {
          "id": "index",
          "method": "actionIndex",
          "parameters": [],
          "comment": "Lists all users"
        },
        {
          "id": "view",
          "method": "actionView",
          "parameters": [
            {"name": "id", "type": "int", "optional": false}
          ]
        }
      ],
      "behaviors": [
        {
          "name": "access",
          "class": "yii\\filters\\AccessControl"
        }
      ],
      "filters": [
        {
          "name": "access",
          "class": "yii\\filters\\AccessControl"
        }
      ]
    }
  ],
  "models": [
    {
      "type": "model",
      "class": "backend\\models\\User",
      "shortName": "User",
      "namespace": "backend\\models",
      "file": "/path/to/models/User.php",
      "parent": "yii\\db\\ActiveRecord",
      "tableName": "{{%user}}",
      "attributes": ["id", "username", "email", "status"],
      "rules": [
        [["username", "email"], "required"],
        ["email", "email"],
        ["status", "integer"]
      ],
      "relations": [
        {
          "name": "profile",
          "method": "getProfile",
          "comment": "@return \\yii\\db\\ActiveQuery"
        }
      ],
      "scenarios": {
        "create": ["username", "email", "status"],
        "update": ["username", "email"]
      }
    }
  ],
  "views": {
    "user": ["index.php", "view.php", "create.php", "update.php"],
    "site": ["index.php", "error.php"]
  }
}
```

**Implementation**:
- Tool: `src/Tools/InspectComponents.php`
- Helper: `src/Helpers/ComponentAnalyzer.php`

**Helper Methods** (`ComponentAnalyzer`):

- `analyzeController(string $filePath): ?array` - Extract controller metadata
- `analyzeModel(string $filePath): ?array` - Extract model metadata
- `extractActions(ReflectionClass $class): array` - Find action methods
- `extractBehaviors(ReflectionClass $class): array` - Parse behaviors() method
- `extractFilters(ReflectionClass $class): array` - Extract filter behaviors
- `extractValidationRules(ReflectionClass $class): array` - Parse rules() method
- `extractRelations(ReflectionClass $class): array` - Find ActiveRecord relations
- `extractScenarios(ReflectionClass $class): array` - Parse scenarios() method
- `extractAttributes(ReflectionClass $class): array` - Get model attributes
- `extractTableName(ReflectionClass $class): ?string` - Get ActiveRecord table name
- `parseMethodReturnValue(ReflectionMethod $method): mixed` - Use php-parser for AST analysis
- `getClassFromFile(string $filePath): ?ReflectionClass` - Load class via reflection
- `isController(string $className): bool` - Check if class is a controller
- `isModel(string $className): bool` - Check if class is a model
- `isActiveRecord(string $className): bool` - Check if class extends ActiveRecord

**Technology**:
- Uses PHP Reflection API for class introspection
- Uses nikic/php-parser for AST parsing of method return values
- Combines reflection with static analysis for accurate results

**Use Cases**:

1. **Code Discovery**: Understand unfamiliar codebase structure and components
2. **API Documentation**: Generate component inventory for documentation
3. **Code Review**: Analyze controllers for action coverage and filters
4. **Refactoring Planning**: Identify models and their dependencies (relations)
5. **Testing Coverage**: List all actions that need test coverage
6. **Module Analysis**: Inspect components within specific modules
7. **View Inventory**: Find all view files and their controllers
8. **Route Planning**: Discover available controller actions for routing

**⚠️ Notes**:

- Read-only operation (no file modifications)
- Requires PHP files to be syntactically valid
- Automatically handles missing directories gracefully
- Works with Basic and Advanced template structures
- Supports module-level component analysis
- Uses php-parser for deeper code analysis (requires nikic/php-parser dependency)
- Reflection requires class files to be loadable (autoloading must work)

---

## Configuration

### Environment Variables

The MCP server is configured via environment variables:

| Variable           | Description                      | Required | Default              |
|--------------------|----------------------------------|----------|----------------------|
| `YII2_CONFIG_PATH` | Path to Yii2 config file         | **Yes**  | -                    |
| `YII2_APP_PATH`    | Path to Yii2 application root    | No       | Inferred from config |
| `GII_ENABLED`      | Enable Gii module                | No       | `true`               |
| `DB_CONNECTION`    | Database connection component ID | No       | `db`                 |
| `DEBUG`            | Enable debug logging to stderr   | No       | `false`              |

### Configuration File (config-mcp.php)

**Purpose**: Unified configuration for MCP server that works across all Yii2 template types.

**Smart Template** (`examples/config-advanced-template.php`):

This configuration file automatically detects your Yii2 project structure:

```php
<?php

$baseDir = dirname(__DIR__);

// Auto-detect Yii2 template type
$configFiles = [];
$componentsDir = null;

// Check for Advanced Template (supports both standard and alternative naming)
if (is_dir($baseDir . '/common') && (is_dir($baseDir . '/frontend') || is_dir($baseDir . '/frontpage'))) {
    $configFiles = [
        $baseDir . '/common/config/main.php',
        $baseDir . '/common/config/main-local.php',
    ];
    $componentsDir = $baseDir . '/common';
}
// Check for Basic Template
elseif (is_file($baseDir . '/config/web.php')) {
    $configFiles = [
        $baseDir . '/config/web.php',
    ];
    $componentsDir = $baseDir;
}

// Merge configurations
$config = ['components' => [], 'modules' => []];
foreach ($configFiles as $file) {
    if (file_exists($file)) {
        $fileConfig = require $file;
        $config = array_merge_recursive($config, $fileConfig);
    }
}

// Ensure Gii module is enabled
$config['modules']['gii'] = [
    'class' => 'yii\gii\Module',
    'allowedIPs' => ['*'],
];

// Set base path
$config['basePath'] = $componentsDir;

return $config;
```

**Template Types Supported**:

1. **Basic Template**: Single `config/web.php`
2. **Advanced Template**: `common/config/main.php` + `main-local.php`
3. **Advanced + API Template**: Additional `api` directory

**Best Practice**: Copy to project root as `config-mcp.php` and add to `.gitignore`.

---

## MCP Client Setup

### Overview

The MCP server works with any MCP-compatible client. Configuration involves specifying:

1. Command to run PHP
2. Path to `yii2-gii-mcp` executable
3. Environment variables (YII2_CONFIG_PATH, etc.)

### Firebender (PhpStorm) - Global Setup (Recommended)

**File**: `~/firebender.json`

**Configuration**:

```json
{
  "mcpServers": {
    "yii2-gii": {
      "command": "php",
      "args": [
        "${workspaceFolder}/vendor/took/yii2-gii-mcp/bin/yii2-gii-mcp"
      ],
      "env": {
        "YII2_CONFIG_PATH": "${workspaceFolder}/config-mcp.php",
        "YII2_APP_PATH": "${workspaceFolder}",
        "DEBUG": "false"
      }
    }
  }
}
```

**Advantages**:

- Configure once, works for all Yii2 projects
- Uses `${workspaceFolder}` for automatic path resolution
- No absolute paths needed
- Portable across machines

**Per-Project Setup**:
Each Yii2 project only needs:

1. Package installed: `composer require took/yii2-gii-mcp`
2. Config file: `config-mcp.php` in project root
3. Restart PhpStorm

**Important**: After config changes, **completely restart PhpStorm** (not just close window).

### Firebender - Project-Specific Setup

**File**: `firebender.json` (in project root)

**Configuration**: Same as global, but scoped to project only.

**Use Case**: When you need project-specific settings (custom config paths, debug mode, etc.).

### Claude Desktop

**Config File Locations**:

- **macOS**: `~/Library/Application Support/Claude/claude_desktop_config.json`
- **Windows**: `%APPDATA%\Claude\claude_desktop_config.json`
- **Linux**: `~/.config/Claude/claude_desktop_config.json`

**Configuration**:

```json
{
  "mcpServers": {
    "yii2-gii": {
      "command": "php",
      "args": [
        "/absolute/path/to/your/project/vendor/took/yii2-gii-mcp/bin/yii2-gii-mcp"
      ],
      "env": {
        "YII2_CONFIG_PATH": "/absolute/path/to/your/project/config-mcp.php",
        "YII2_APP_PATH": "/absolute/path/to/your/project"
      }
    }
  }
}
```

**Important**: Claude Desktop requires **absolute paths** (no workspace variables).

**Verification**: Look for 🔌 icon in Claude Desktop showing "yii2-gii" connected.

### Claude Code (VSCode Extension)

**File**: `.vscode/settings.json` (project) or global settings

**Configuration**:

```json
{
  "claude.mcpServers": {
    "yii2-gii": {
      "command": "php",
      "args": [
        "${workspaceFolder}/vendor/took/yii2-gii-mcp/bin/yii2-gii-mcp"
      ],
      "env": {
        "YII2_CONFIG_PATH": "${workspaceFolder}/config-mcp.php",
        "YII2_APP_PATH": "${workspaceFolder}"
      }
    }
  }
}
```

**Advantages**: Uses `${workspaceFolder}` for portability.

### Cline (VSCode Extension)

**File**: `.vscode/settings.json`

**Configuration**:

```json
{
  "cline.mcpServers": {
    "yii2-gii": {
      "command": "php",
      "args": [
        "${workspaceFolder}/vendor/took/yii2-gii-mcp/bin/yii2-gii-mcp"
      ],
      "env": {
        "YII2_CONFIG_PATH": "${workspaceFolder}/config-mcp.php",
        "YII2_APP_PATH": "${workspaceFolder}"
      }
    }
  }
}
```

### Cursor IDE

**File**: `~/.cursor/config.json` or Cursor settings

**Configuration**:

```json
{
  "mcp.servers": {
    "yii2-gii": {
      "command": "php",
      "args": [
        "/absolute/path/to/your/project/vendor/took/yii2-gii-mcp/bin/yii2-gii-mcp"
      ],
      "env": {
        "YII2_CONFIG_PATH": "/absolute/path/to/your/project/config-mcp.php",
        "YII2_APP_PATH": "/absolute/path/to/your/project"
      }
    }
  }
}
```

### Generic MCP Client

**Pattern**:

```json
{
  "command": "php",
  "args": ["<path-to-vendor>/took/yii2-gii-mcp/bin/yii2-gii-mcp"],
  "env": {
    "YII2_CONFIG_PATH": "<path-to-config-mcp.php>",
    "YII2_APP_PATH": "<path-to-project-root>",
    "DEBUG": "false"
  }
}
```

**Transport**: JSON-RPC 2.0 over stdio (stdin/stdout).

### Docker Desktop Setup

**Overview**: When Yii2 project runs in Docker containers, there are two main approaches:

**Option A: Host-Based MCP (Recommended)**

- MCP server runs on host machine (PHP 8.2+)
- Connects to Docker database via exposed ports
- Simpler configuration, better performance
- No Docker exec overhead

**Configuration Pattern**:

```json
{
  "mcpServers": {
    "yii2-gii": {
      "command": "php",
      "args": ["${workspaceFolder}/vendor/took/yii2-gii-mcp/bin/yii2-gii-mcp"],
      "env": {
        "YII2_CONFIG_PATH": "${workspaceFolder}/config-mcp.php"
      }
    }
  }
}
```

**config-mcp.php for Docker**:

```php
'db' => [
    'dsn' => 'mysql:host=127.0.0.1;dbname=yii2_app',  // Docker port mapped to host
    'username' => 'root',
    'password' => 'secret',
]
```

**Option B: Container-Based MCP**

- MCP server runs inside Docker container
- Uses docker exec to communicate
- Requires wrapper script
- No host PHP requirement

**Wrapper Script Pattern** (`bin/yii2-gii-mcp-docker`):

```bash
#!/bin/bash
exec docker exec -i your-php-container \
    php /var/www/html/vendor/took/yii2-gii-mcp/bin/yii2-gii-mcp
```

**Configuration Pattern**:

```json
{
  "mcpServers": {
    "yii2-gii": {
      "command": "${workspaceFolder}/bin/yii2-gii-mcp-docker",
      "args": [],
      "env": {
        "YII2_CONFIG_PATH": "/var/www/html/config-mcp.php"  // Container path!
      }
    }
  }
}
```

**config-mcp.php for Container**:

```php
'db' => [
    'dsn' => 'mysql:host=mysql;dbname=yii2_app',  // Docker service name
    'username' => 'root',
    'password' => 'secret',
]
```

**Database Connectivity**:

- **Host-based**: Use `127.0.0.1` (Docker exposes ports to host via port mapping)
- **Container-based**: Use Docker service names (e.g., `mysql`, `db`)
- **Port mapping required**: Ensure `ports: ["3306:3306"]` in docker-compose.yml

**Complete Documentation**: See [DOCKER.md](DOCKER.md) for comprehensive guide with troubleshooting.

**Example Configs**: See `examples/docker/` for ready-to-use configurations.

---

## Yii2 Integration

### Yii2 Bootstrap Process

**File**: `src/Helpers/Yii2Bootstrap.php`

**Purpose**: Initialize Yii2 application context programmatically without web server.

**Process**:

1. Load Yii2 autoloader
2. Load configuration from `YII2_CONFIG_PATH`
3. Create Yii2 application instance
4. Initialize Gii module
5. Establish database connection

**Methods**:

- `initialize(string $configPath)`: Bootstrap Yii2 with given config
- `getDb()`: Get database connection component
- `getGiiModule()`: Get Gii module instance
- `getApplication()`: Get Yii2 application instance
- `detectTemplateType()`: Detect Yii2 template type ('basic' or 'advanced')
- `getDefaultModelNamespace()`: Get appropriate default namespace for models based on template type
- `getDefaultControllerNamespace()`: Get appropriate default namespace for controllers based on template type

**Template Detection**:

The bootstrap automatically detects which Yii2 template is being used:

- **Advanced Template**: Detected by presence of `/common` and `/console` directories
- **Basic Template**: Detected by presence of `/app` and `/config` directories

**Alternative Naming Support**:

The MCP server fully supports both standard and alternative naming conventions in Advanced Template:

- **Standard**: `frontend`, `backend` (traditional Yii2 naming)
- **Alternative**: `frontpage`, `backoffice` (custom project naming)

Both conventions are automatically detected and handled correctly. When both exist, standard names take priority.

**Smart Defaults Based on Template Type**:

- **Models**: `common\models` for Advanced Template, `app\models` for Basic Template
- **Controllers**: `frontend\controllers` (or `frontpage\controllers`) for Advanced Template, `app\controllers` for Basic Template
- **Forms**: Default to `common\models` for Advanced (shared forms), `app\models` for Basic

This detection is used across all code generation tools (`generate-model`, `generate-crud`, `generate-controller`, `generate-form`) to provide appropriate defaults without requiring explicit configuration.

### GiiHelper Integration

**File**: `src/Helpers/GiiHelper.php`

**Purpose**: Wrapper around Gii generators for easier programmatic access.

**Key Methods**:

- `previewModel(string $tableName, array $options): array`
- `generateModel(string $tableName, array $options): array`
- `previewCrud(string $modelClass, array $options): array`
- `generateCrud(string $modelClass, array $options): array`
- `previewController(string $controllerID, array $options): array`
- `generateController(string $controllerID, array $options): array`
- `previewForm(string $modelClass, array $options): array`
- `generateForm(string $modelClass, array $options): array`
- `previewModule(string $moduleID, array $options): array`
- `generateModule(string $moduleID, array $options): array`
- `previewExtension(array $options): array`
- `generateExtension(array $options): array`

**Return Format**:

```php
[
    'files' => [
        [
            'path' => '/path/to/file.php',
            'content' => '<?php ...',
            'operation' => 'create', // or 'skip', 'overwrite'
        ],
    ],
    'summary' => 'Generated X files',
]
```

### MigrationHelper Integration

**File**: `src/Helpers/MigrationHelper.php`

**Purpose**: Wrapper around Yii2 migrate component for programmatic migration management.

**Key Methods**:

- `getMigrations(string $status = 'all'): array` - Get migrations by status (applied/pending/all)
- `getMigrationHistory(int $limit = 10): array` - Get migration history with timestamps
- `previewMigrationSql(string $name, string $direction = 'up'): string` - Preview SQL without executing
- `executeMigration(string $operation, array $params): array` - Execute migration operations
- `createMigration(string $name, array $fields = []): string` - Create migration file with field definitions
- `validateMigrationName(string $name): bool` - Validate migration exists
- `getMigrationByName(string $name): ?object` - Get specific migration instance

**Field Definition Support**:

The `createMigration()` method supports field definitions to leverage Yii2's migration builder:

```php
$fields = [
    'name:string(255):notNull',
    'email:string:notNull:unique',
    'status:integer:defaultValue(1)',
    'price:decimal(10,2):notNull',
    'created_at:timestamp:notNull',
    'is_active:boolean:defaultValue(true)',
];

$file = $helper->createMigration('create_users_table', $fields);
```

**Field Format**: `name:type[:size][:modifier[:modifier...]]`

**Supported Types**: string, integer, text, decimal, timestamp, datetime, date, time, boolean, binary, money, json

**Supported Modifiers**: notNull, unique, defaultValue(value), unsigned, comment('text')

**Return Format**:

```php
[
    'operation' => 'up',  // or 'down', 'create', 'redo', 'fresh'
    'result' => 0,        // Exit code
    'output' => '...',    // Console output
    'migrations_applied' => 1,  // For up operations
    'migrations_reverted' => 1, // For down operations
    'file' => '/path/to/migration.php',  // For create operations
]
```

### Database Connection

**Component**: `db` (default, configurable via `DB_CONNECTION` env var)

**Required in Config**:

```php
'components' => [
    'db' => [
        'class' => 'yii\db\Connection',
        'dsn' => 'mysql:host=localhost;dbname=mydb',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
],
```

**Supported Databases**:

- MySQL / MariaDB
- PostgreSQL
- SQLite
- MSSQL
- Oracle (any database supported by Yii2)

---

## Safety Features

### 1. Preview Mode by Default

**Philosophy**: All generation tools default to `preview: true` to prevent accidental file writes.

**Workflow**:

1. AI calls tool with `preview: true`
2. Tool returns code preview
3. User reviews and approves
4. AI calls tool with `preview: false`
5. Tool writes files

**Implementation**:

```php
protected function getPreviewParam(array $arguments): bool
{
    return $this->getOptionalParam($arguments, 'preview', true);
}
```

### 2. File Conflict Detection

**Purpose**: Prevent accidental overwrite of existing files.

**Checks**:

- File existence before generation
- Write permission on target directory
- Return list of conflicts to user

**Implementation** (`src/Helpers/FileHelper.php`):

```php
public static function checkConflicts(array $files): array
{
    $conflicts = [];
    foreach ($files as $file) {
        if (file_exists($file['path'])) {
            $conflicts[] = $file['path'];
        }
    }
    return $conflicts;
}
```

### 3. Input Validation

**Purpose**: Prevent SQL injection, path traversal, and malicious inputs.

**Validations**:

- Table names: Alphanumeric + underscores only
- Class names: Valid PHP identifiers
- Paths: Within project boundaries
- Namespaces: Valid PHP namespace syntax

**Implementation** (`src/Helpers/ValidationHelper.php`):

```php
public static function validateTableName(string $name): bool
{
    return preg_match('/^[a-zA-Z0-9_]+$/', $name) === 1;
}

public static function validateClassName(string $name): bool
{
    return preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name) === 1;
}

public static function validatePath(string $path, string $basePath): bool
{
    $realPath = realpath($path);
    $realBase = realpath($basePath);
    return $realPath !== false && strpos($realPath, $realBase) === 0;
}
```

### 4. Table/Model Existence Checks

**Before Generation**:

- Verify table exists in database
- Check model class is loadable
- Validate relationships are resolvable

**Error Handling**: Return structured error if validation fails.

### 5. Error Handling

**JSON-RPC 2.0 Compliant**:
All errors follow standard error response format with code, message, and optional data.

**Error Categories**:

- Configuration errors (-32001)
- Database errors (-32002)
- Validation errors (-32602)
- File system errors (-32003)
- Gii generator errors (-32004)

---

## Setup Tools

### 1. Interactive Setup Wizard

**File**: `bin/interactive-setup`

**Purpose**: Automated setup wizard for first-time configuration.

**Features**:

- Auto-detects Yii2 project type (Basic/Advanced/Advanced+API)
- Creates `config-mcp.php` with intelligent config merging
- Tests database connection
- Configures Firebender (local or global) or Claude Desktop
- Shows final instructions and next steps

**Usage**:

```bash
cd /path/to/your/yii2/project
php vendor/took/yii2-gii-mcp/bin/interactive-setup
```

**Workflow**:

1. Detect project structure
2. Ask user questions (with smart defaults)
3. Create config-mcp.php (with backup if exists)
4. Test database connection
5. Ask which MCP client to configure
6. Create/update client config
7. Display success message and usage examples

**UI Features**:

- Colored terminal output (green for success, red for errors)
- Progress indicators
- Clear instructions
- Helpful error messages with solutions

**Code Quality**:

- Helper functions for UI (`printSuccess()`, `printError()`, `printWarning()`)
- Separation of concerns
- Error handling at all critical points

### 2. Diagnostic Tool

**File**: `bin/diagnose`

**Purpose**: Comprehensive diagnostic tool to check setup and find problems.

**Checks**:

1. **Project Structure**
    - Composer project exists
    - Yii2 dependency installed
    - Vendor directory present

2. **Template Detection**
    - Identifies Basic/Advanced/Advanced+API template
    - Lists detected components (frontend, backend, api, console)

3. **MCP Server Executable**
    - Checks `bin/yii2-gii-mcp` exists
    - Verifies executable permissions
    - Tests if it's runnable

4. **Configuration**
    - `config-mcp.php` exists and readable
    - Config returns valid array
    - Has required keys (components, modules)
    - Database component configured
    - Gii module enabled

5. **Database Connection**
    - Tests actual database connection
    - Lists available tables
    - Shows sample table names

6. **Firebender Configuration**
    - Checks global config (`~/firebender.json`)
    - Checks local config (`firebender.json`)
    - Validates JSON syntax
    - Verifies yii2-gii server entry

7. **PHP Environment**
    - PHP version (>= 8.2)
    - Required extensions (PDO, pdo_mysql, etc.)

**Output Format**:

```
=== Project Structure ===
✓ Composer project
✓ Yii2 dependency

=== Template Detection ===
✓ Template type: Advanced Template
  Components: frontend, backend, console

=== Configuration ===
✓ config-mcp.php exists
✓ Database connection configured
  DSN: mysql:host=localhost;dbname=mydb
✓ Gii module enabled

=== Database Connection ===
✓ Connected successfully
  Tables found: 15
  Sample: users, posts, comments, ...

=== Summary ===
✓ All checks passed!
```

**Usage**:

```bash
cd /path/to/your/yii2/project
php vendor/took/yii2-gii-mcp/bin/diagnose
```

**Error Messages**: Include specific solutions and commands to fix issues.

---

## Testing

### Test Infrastructure

**Framework**: Codeception 5.0 (unified testing)

**Philosophy**:

- KISS principle - one testing framework only
- No Yii2 dependency in tests (fast, simple setup)
- TDD approach - tests guide implementation
- Reflection API for testing private methods without Yii2 dependencies

**Test Suites**:

1. **Unit Tests** (`tests/Unit/`)
2. **Functional Tests** (`tests/Functional/`)

### Test Coverage Achievement (Phase 6 - January 2026)

**Current Coverage: 60.25%** (up from initial 29.96%)

**Statistics**:

- **Total Tests**: 329
- **Total Assertions**: 1,139
- **Success Rate**: 100% ✅
- **Lines Covered**: 1,127/1,869
- **Methods Covered**: 136/203 (67.00%)

#### Coverage by Component

**Excellent Coverage (>70%)**:

- ✅ **ToolRegistry**: 100.00% (17/17 lines)
- ✅ **Protocol/Request**: 100.00% (26/26 lines)
- ✅ **Protocol/Response**: 100.00% (17/17 lines)
- ✅ **Protocol/Message**: 100.00% (6/6 lines)
- ✅ **AbstractTool**: 98.36% (60/61 lines)
- ✅ **Helpers/ValidationHelper**: 95.83% (46/48 lines)
- ✅ **Protocol/ErrorResponse**: 95.00% (57/60 lines)
- ✅ **Config/ServerConfig**: 95.83%
- ✅ **Protocol/StdioTransport**: 85.71% (36/42 lines)
- ✅ **MCPServer**: 81.90% (86/105 lines) - *Improved from 7.62%!*
- ✅ **Tools/GenerateModule**: 74.17% (89/120 lines) - *Improved from 23.33%!*

**Good Coverage (60-70%)**:

- ✅ **Tools/GenerateForm**: 69.03% (78/113 lines)
- ✅ **Tools/GenerateController**: 67.23% (80/119 lines)
- ✅ **Tools/GenerateCrud**: 66.88% (103/154 lines)
- ✅ **Tools/GenerateExtension**: 66.85% (119/178 lines)
- ✅ **Tools/GenerateModel**: 64.49% (89/138 lines)

**Improved Coverage (20-60%)**:

- ✅ **Tools/InspectDatabase**: 40.16% (49/122 lines)
- ✅ **Helpers/FileHelper**: 28.54%
- ✅ **Tools/ListTables**: 24.69% (schema tests)

### Comprehensive Test Files Created

#### Phase 1-5: Core Infrastructure (213 tests)

1. **ValidationHelperTest.php** - 20 tests
    - Class name, namespace, path validation
    - Sanitization methods (table names, class names)
    - Security checks (path traversal detection)
    - Error message generation

2. **FileHelperTest.php** - 26 tests
    - File I/O operations with temp files
    - Conflict detection
    - Backup creation
    - Path manipulation

3. **ServerConfigTest.php** - 15 tests
    - Environment variable parsing
    - Boolean value conversion
    - Path inference logic
    - Validation and error messaging

4. **MCPServerTest.php** - 20+ tests
    - Initialization flow
    - Request routing (tools/list, tools/call)
    - Error handling scenarios
    - Protocol version handling
    - State management

5. **Protocol Tests** (87 tests total):
    - **RequestTest.php** - 20 tests (JSON-RPC validation)
    - **ResponseTest.php** - 20 tests (serialization, types)
    - **MessageTest.php** - 18 tests (base protocol)
    - **StdioTransportTest.php** - 29 tests (I/O, streams, EOF)

#### Phase 6: Tools Enhancement (116 additional tests)

6. **GenerateModelTest.php** - 20 tests
    - Schema validation (all properties, defaults, enums)
    - `formatGiiResult()` testing via Reflection
    - Preview vs generation mode
    - Validation errors, conflicts, generic errors
    - Content display formatting

7. **GenerateFormTest.php** - 18 tests
    - Schema structure and property descriptions
    - `formatGiiResult()` in both modes
    - Error handling (validation, conflicts, generic)
    - Multiple file handling

8. **GenerateCrudTest.php** - 18 tests
    - Widget type validation (grid/list enum)
    - Base controller class defaults
    - i18n settings
    - `formatGiiResult()` with file grouping
    - Preview mode helpful information

9. **GenerateExtensionTest.php** - 19 tests
    - Vendor/package name validation
    - Extension type enum (yii2-extension, library)
    - `validateName()` method testing
    - Composer.json generation preview
    - Multiple file formatting

10. **GenerateModuleEnhancedTest.php** - 17 tests
    - Module ID validation (lowercase, dashes, underscores)
    - `validateModuleID()` comprehensive testing
    - `formatGiiResult()` with file grouping
    - Module.php content display
    - Directory structure generation

11. **InspectDatabaseTest.php** - 17 tests
    - Schema validation (no required fields)
    - Table pattern description examples
    - `formatOutput()` method testing
    - Empty database handling
    - Multiple table formatting with JSON

12. **GenerateControllerTest.php** - 26 tests
    - Controller ID validation
    - Actions validation (comma-separated)
    - `validateControllerID()` and `validateActions()` testing
    - Result formatting for all scenarios

13. **ListTablesTest.php** - 8 tests
    - Schema validation
    - Connection and detailed defaults
    - Tool metadata validation

### Testing Methodology Without Yii2

#### ✅ What We Successfully Test Without Yii2

**Schema & API Validation**:

- All input schemas (structure, types, defaults, enum values)
- JSON Schema compliance
- Property descriptions and examples
- Required vs optional parameters
- additionalProperties restrictions

**Business Logic (via Reflection API)**:

```php
$reflection = new ReflectionClass($tool);
$method = $reflection->getMethod('formatGiiResult');
$method->setAccessible(true);
$result = $method->invoke($tool, $testData, $previewMode);
```

**Validation Methods Tested**:

- `validateName()` (GenerateExtension)
- `validateModuleID()` (GenerateModule)
- `validateControllerID()` (GenerateController)
- `validateActions()` (GenerateController)
- All ValidationHelper methods

**Result Formatting Tested**:

- `formatGiiResult()` for all generation tools
- `formatOutput()` for inspection tools
- `formatTableInfo()` structure validation
- Preview vs generation mode differences
- Error handling paths (validation, conflicts, generic)

**Helper Functions**:

- ValidationHelper (sanitization, validation, security)
- FileHelper (file operations, backups, conflicts)
- All static utility methods

**Protocol Layer**:

- Complete JSON-RPC 2.0 message handling
- Request/Response serialization & validation
- Transport layer with stream I/O
- Error response formatting
- EOF detection and handling

**Configuration**:

- Environment variable parsing
- Boolean value conversion
- Path inference and validation

#### ⚠️ What Requires Yii2 (Integration Tests)

- `doExecute()` methods with actual Gii generators
- Database operations (requires yii\db\Connection)
- File generation workflows with Yii2 file system
- Yii2Bootstrap initialization with real application
- Full end-to-end tool execution

### Testing Techniques Used

1. **Reflection API** - Access and test private methods without exposing them
   ```php
   $reflection = new ReflectionClass($tool);
   $method = $reflection->getMethod('privateMethod');
   $method->setAccessible(true);
   $result = $method->invoke($tool, $arguments);
   ```

2. **Mock Objects** - PHPUnit mocks for dependencies
   ```php
   $bootstrap = $this->createMock(Yii2Bootstrap::class);
   ```

3. **Stream Testing** - php://memory for I/O operations
   ```php
   $stream = fopen('php://memory', 'r+');
   ```

4. **Temporary Files** - sys_get_temp_dir() for file operations
   ```php
   $tempFile = tempnam(sys_get_temp_dir(), 'test_');
   ```

5. **Edge Case Testing**:
    - Empty inputs
    - Invalid formats
    - SQL injection attempts
    - Path traversal detection
    - Special characters
    - Boundary conditions

6. **Error Scenario Coverage**:
    - Validation failures
    - File conflicts
    - Generic errors
    - Missing parameters
    - Invalid types

### Running Tests

**Via Makefile**:

```bash
make test                  # Run all tests
make test-unit             # Unit tests only
make test-functional       # Functional tests only
make coverage              # Generate coverage report
make clean                 # Clean test artifacts
```

**Via Composer**:

```bash
composer test
composer test-unit
composer test-functional
```

**Direct Codeception**:

```bash
vendor/bin/codecept run
vendor/bin/codecept run Unit
vendor/bin/codecept run Functional
vendor/bin/codecept run --coverage --coverage-html
```

### Test Dependency Policy

**Important**: Tests do NOT require Yii2 installation.

**Why**:

- Fast test execution
- Simple contributor setup
- No database required for unit tests
- Clear separation of concerns

**Mocking Strategy**:

- Mock Yii2 application in tests
- Mock database connections
- Mock Gii generators for full workflow tests (future)

### Writing New Tests

**For New Tools**:

1. Create unit test in `tests/Unit/Tools/YourToolTest.php`
2. Test input schema validation
3. Test error handling
4. Mock Yii2 dependencies as needed
5. Follow existing test patterns

**Example Test Structure**:

```php
class YourToolTest extends \Codeception\Test\Unit
{
    public function testGetName()
    {
        $tool = new YourTool();
        $this->assertEquals('your-tool', $tool->getName());
    }
    
    public function testInputSchemaValidation()
    {
        $tool = new YourTool();
        $schema = $tool->getInputSchema();
        $this->assertArrayHasKey('type', $schema);
        $this->assertEquals('object', $schema['type']);
    }
}
```

---

## Development Guidelines

### Adding New Tools

**Steps**:

1. **Create Tool Class** (`src/Tools/YourTool.php`):

```php
<?php

namespace Took\Yii2GiiMcp\Tools;

class YourTool extends AbstractTool
{
    public function getName(): string
    {
        return 'your-tool';
    }
    
    public function getDescription(): string
    {
        return 'Description of what your tool does';
    }
    
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'param1' => [
                    'type' => 'string',
                    'description' => 'First parameter',
                ],
            ],
            'required' => ['param1'],
        ];
    }
    
    protected function doExecute(array $arguments): array
    {
        // Validate inputs
        $param1 = $this->getRequiredParam($arguments, 'param1');
        
        // Your logic here
        
        // Return result
        return $this->createResult('Tool output');
    }
}
```

2. **Register Tool** (`bin/yii2-gii-mcp`):

```php
$registry = new ToolRegistry();
$registry->register(new YourTool());
```

3. **Write Tests** (`tests/Unit/Tools/YourToolTest.php`):

```php
class YourToolTest extends \Codeception\Test\Unit
{
    public function testBasicFunctionality()
    {
        $tool = new YourTool();
        // Test here
    }
}
```

4. **Update Documentation**: Add tool to this memory bank and README.md.

### Coding Standards

**PSR-12**: Follow PSR-12 coding standard for all PHP code.

**Key Rules**:

- 4 spaces for indentation (no tabs)
- Opening braces on same line for classes/functions
- Type hints for all parameters and return types
- DocBlocks for public methods
- One class per file

**Tools**:

- PHP-CS-Fixer for automatic formatting
- PHPStan for static analysis (future)

### Error Handling

**Always**:

- Return structured JSON-RPC 2.0 errors
- Use appropriate error codes
- Include helpful error messages
- Add data field with details for debugging

**Example**:

```php
return $this->createError(
    -32602,
    'Invalid params',
    ['details' => 'Table name must be alphanumeric']
);
```

### Logging

**Important**: Log to stderr only, never to stdout.

**Why**: stdout is reserved for JSON-RPC protocol messages.

**Usage**:

```php
if (getenv('DEBUG') === 'true') {
    fwrite(STDERR, "Debug: Processing table {$tableName}\n");
}
```

### Dependencies

**Minimize External Dependencies**:

- Core PHP only for most functionality
- Leverage Yii2 and Gii built-in features
- JSON Schema validator for input validation
- Codeception for testing

**Current Dependencies** (`composer.json`):

```json
{
  "require": {
    "php": ">=8.2",
    "justinrainbow/json-schema": "^5.2"
  },
  "require-dev": {
    "codeception/codeception": "^5.0",
    "codeception/module-asserts": "^3.0"
  },
  "suggest": {
    "yiisoft/yii2": "Required for Yii2 integration (provided by parent project)",
    "yiisoft/yii2-gii": "Required for code generation (provided by parent project)"
  }
}
```

---

## File Structure

### Project Layout

```
yii2-gii-mcp/
├── bin/                            # Executable scripts
│   ├── yii2-gii-mcp               # MCP server executable
│   ├── interactive-setup          # Setup wizard
│   ├── diagnose                   # Diagnostic tool
│   └── setup-project              # Simple setup script
├── src/                           # Source code
│   ├── MCPServer.php              # Main MCP server
│   ├── ToolRegistry.php           # Tool management
│   ├── Config/
│   │   └── ServerConfig.php       # Configuration
│   ├── Protocol/                  # JSON-RPC protocol
│   │   ├── Message.php
│   │   ├── Request.php
│   │   ├── Response.php
│   │   ├── ErrorResponse.php
│   │   └── StdioTransport.php
│   ├── Tools/                     # MCP tools
│   │   ├── ToolInterface.php
│   │   ├── AbstractTool.php
│   │   ├── ListTables.php
│   │   ├── InspectDatabase.php
│   │   ├── GenerateModel.php
│   │   ├── GenerateCrud.php
│   │   ├── GenerateController.php
│   │   ├── GenerateForm.php
│   │   ├── GenerateModule.php
│   │   └── GenerateExtension.php
│   └── Helpers/                   # Helper classes
│       ├── Yii2Bootstrap.php      # Yii2 initialization
│       ├── GiiHelper.php          # Gii wrapper
│       ├── FileHelper.php         # File operations
│       └── ValidationHelper.php   # Input validation
├── tests/                         # Codeception tests
│   ├── Unit/                      # Unit tests
│   │   ├── Protocol/
│   │   ├── Tools/
│   │   └── ToolRegistryTest.php
│   └── Functional/                # Functional tests
│       ├── MCPProtocolCest.php
│       └── StdioTransportCest.php
├── examples/                      # Examples and templates
│   ├── config.php                 # Basic config
│   ├── config-advanced-template.php  # Smart config template
│   ├── run.php                    # Example MCP client
│   ├── test-server.php            # Protocol test
│   └── test-list-tables.php       # Functional test
├── docs/                          # Documentation
│   ├── AI-MEMORY-BANK.md          # This file
│   └── README.md                  # Docs navigation
├── composer.json                  # Composer config
├── codeception.yml                # Test config
├── Makefile                       # Build tasks
├── README.md                      # Human-friendly docs
├── TODO.md                        # Development roadmap
└── LICENSE                        # MIT license
```

### Key Files

**Entry Points**:

- `bin/yii2-gii-mcp` - MCP server executable
- `bin/interactive-setup` - Setup wizard
- `bin/diagnose` - Diagnostic tool

**Core Classes**:

- `src/MCPServer.php` - Main server logic
- `src/ToolRegistry.php` - Tool management
- `src/Protocol/StdioTransport.php` - I/O handling

**Tool Implementations**:

- All tools in `src/Tools/`
- Extend `AbstractTool`
- Implement `ToolInterface`

**Configuration**:

- `src/Config/ServerConfig.php` - Server config
- `examples/config-advanced-template.php` - Template for projects

---

## Troubleshooting

### Common Issues and Solutions

#### 1. "Could not load Yii2"

**Cause**: Yii2 not installed or not in autoload path.

**Solution**:

```bash
composer require yiisoft/yii2
composer require yiisoft/yii2-gii --dev
```

#### 2. "Configuration file not found"

**Cause**: `YII2_CONFIG_PATH` not set or pointing to wrong file.

**Solution**:

```bash
# Create config-mcp.php
cp vendor/took/yii2-gii-mcp/examples/config-advanced-template.php config-mcp.php

# Or run setup
php vendor/took/yii2-gii-mcp/bin/interactive-setup
```

#### 3. "Database connection not found"

**Cause**: Missing or misconfigured `db` component.

**Solution**: Add to `config-mcp.php`:

```php
'components' => [
    'db' => [
        'class' => 'yii\db\Connection',
        'dsn' => 'mysql:host=localhost;dbname=mydb',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
],
```

#### 4. MCP Server Not Connecting in Firebender

**Causes and Solutions**:

1. **Path incorrect**: Verify paths in `~/firebender.json`
   ```bash
   ls -la vendor/took/yii2-gii-mcp/bin/yii2-gii-mcp
   ls -la config-mcp.php
   ```

2. **JSON syntax error**: Validate JSON:
   ```bash
   cat ~/firebender.json | python -m json.tool
   ```

3. **PhpStorm not restarted**: Completely restart PhpStorm (File → Exit).

4. **Check logs**: Help → Show Log in Files, search for "MCP" or "yii2-gii".

#### 5. "Tools not available" in AI Assistant

**Diagnostic Steps**:

```bash
# 1. Run diagnostics
php vendor/took/yii2-gii-mcp/bin/diagnose

# 2. Test server manually
YII2_CONFIG_PATH=config-mcp.php DEBUG=1 php vendor/took/yii2-gii-mcp/bin/yii2-gii-mcp

# 3. Test list-tables tool
YII2_CONFIG_PATH=config-mcp.php php vendor/took/yii2-gii-mcp/examples/test-list-tables.php
```

#### 6. Files Not Generated (Preview Shows Code But Nothing Written)

**Cause**: This is by design! Default is preview mode.

**Solution**: Ask AI to generate with `preview: false` or "generate for real".

#### 7. Permission Denied Errors

**Cause**: Target directory not writable.

**Solution**:

```bash
# Check permissions
ls -la app/models/

# Fix permissions
chmod 755 app/models/
```

#### 8. "Could not detect Yii2 template structure"

**Cause**: Non-standard project structure.

**Solution**: Create custom `config-mcp.php`:

```php
<?php
return [
    'id' => 'my-app',
    'basePath' => __DIR__,
    'components' => [
        'db' => [/* your db config */],
    ],
    'modules' => [
        'gii' => [
            'class' => 'yii\gii\Module',
            'allowedIPs' => ['*'],
        ],
    ],
];
```

### Debug Mode

Enable detailed logging:

**In Firebender**:

```json
{
  "env": {
    "DEBUG": "1"
  }
}
```

**From Command Line**:

```bash
DEBUG=1 YII2_CONFIG_PATH=config-mcp.php php vendor/took/yii2-gii-mcp/bin/yii2-gii-mcp
```

**Logs**: Written to stderr, visible in IDE console or terminal.

### Getting Help

**Diagnostic Tool**: First line of defense

```bash
php vendor/took/yii2-gii-mcp/bin/diagnose
```

**Example Scripts**: Test individual components

```bash
# Test protocol
php vendor/took/yii2-gii-mcp/examples/test-server.php

# Test list-tables tool
php vendor/took/yii2-gii-mcp/examples/test-list-tables.php
```

**GitHub Issues**: Report bugs with diagnostic output.

---

## Implementation History

This section documents major improvements and changes made to the project.

### Phase 1-2: Core Implementation (December 2024)

**Completed**:

- ✅ Full JSON-RPC 2.0 over stdio transport
- ✅ MCP protocol support (version 2024-11-05)
- ✅ Tool registry system
- ✅ Yii2 bootstrap integration
- ✅ First 3 tools: ListTables, GenerateModel, GenerateCrud

**Key Decisions**:

- stdio transport (not HTTP) for simplicity
- Preview mode by default for safety
- JSON Schema for input validation
- Codeception for all testing (KISS principle)

### Phase 3: Extended Tools (January 2025)

**Completed**:

- ✅ InspectDatabase tool (detailed schema inspection)
- ✅ GenerateController tool
- ✅ GenerateForm tool
- ✅ GenerateModule tool (full production implementation)
- ✅ GenerateExtension tool (full production implementation)

**Total Tools**: 8 production-ready tools

### Phase 4: Setup Improvements (January 2026)

**Motivation**: Make setup easier for developers, testers, and AI agents.

**Completed Features**:

#### 1. Interactive Setup Wizard (`bin/interactive-setup`)

**Features**:

- Automatic Yii2 project type detection (Basic/Advanced/Advanced+API)
- Automatic `config-mcp.php` creation with config merging
- Database connection testing
- Firebender configuration (local or global)
- Claude Desktop configuration
- Built-in MCP server connection test
- Colored terminal output
- Intelligent defaults
- Backup option for existing configs

**Usage**:

```bash
php vendor/took/yii2-gii-mcp/bin/interactive-setup
```

**Workflow**:

1. Detect project structure
2. Create config-mcp.php (with backup if exists)
3. Test database connection
4. Ask which MCP client (Firebender/Claude/Skip)
5. Create appropriate configuration
6. Show final instructions

#### 2. Diagnostic Tool (`bin/diagnose`)

**Features**:

- Project structure validation (Composer, Yii2, vendor)
- Template type detection (Basic/Advanced/Advanced+API)
- MCP server executable checks
- Configuration validation (`config-mcp.php`)
- Database connection testing
- Table listing
- Firebender configuration checks (local and global)
- PHP version and extensions verification
- Concrete solution suggestions for problems

**Usage**:

```bash
php vendor/took/yii2-gii-mcp/bin/diagnose
```

#### 3. Improved Error Messages (`src/Config/ServerConfig.php`)

**Before**:

```
Yii2 config path not set. Set YII2_CONFIG_PATH environment variable.
```

**After**:

```
Yii2 config path not set.
  → Set YII2_CONFIG_PATH environment variable
  → Or create config-mcp.php in your project root
  → Quick fix: php vendor/took/yii2-gii-mcp/bin/interactive-setup

For help, run: php vendor/took/yii2-gii-mcp/bin/diagnose
```

**Implementation**: New `getErrorMessage()` method with structured, actionable error messages.

#### 4. Documentation Overhaul

**Created**:

- `QUICKSTART.md` - Fast-track installation guide
- `SETUP_GUIDE.md` - Comprehensive setup guide
- `SETUP_IMPROVEMENTS.md` - Technical documentation of improvements
- Updated `README.md` with better structure

**Focus**: Clear, actionable, example-driven documentation for both humans and AI agents.

### Phase 5: Documentation Consolidation (January 2026)

**Completed**:

- ✅ Consolidated root .md files into brief human-friendly README.md
- ✅ Created comprehensive AI-MEMORY-BANK.md for AI agents
- ✅ Removed redundant documentation files
- ✅ Clear separation: humans get quick start, AI agents get complete reference

**Deleted Files**:

- SETUP_GUIDE.md (consolidated)
- SETUP_IMPROVEMENTS.md (consolidated)
- QUICKSTART.md (consolidated)
- docs/firebender-setup.md (consolidated)
- docs/firebender-global-setup.md (consolidated)

**New Structure**:

- `/README.md` - Minimal overview + quick start for humans
- `/docs/AI-MEMORY-BANK.md` - Complete technical reference for AI agents
- `/docs/README.md` - Navigation for documentation

### Phase 6: Comprehensive Test Coverage (January 2026)

**Motivation**: Achieve production-ready test coverage without Yii2 runtime dependencies.

**Completed Features**:

#### 1. Coverage Achievement: 29.96% → 60.25% (+100% improvement!)

**Key Milestones**:

- ✅ **329 total tests** (from ~140)
- ✅ **1,139 assertions** (from ~600)
- ✅ **60.25% line coverage** (from 29.96%)
- ✅ **67% method coverage**
- ✅ **100% test success rate**

#### 2. Enhanced Testing Methodology

**Reflection API Usage**:

- Test private methods without exposing them
- Validate business logic (formatGiiResult, validation methods)
- Maintain encapsulation while ensuring coverage

**Example Pattern**:

```php
$reflection = new ReflectionClass($tool);
$method = $reflection->getMethod('formatGiiResult');
$method->setAccessible(true);
$result = $method->invoke($tool, $testData, true);
```

#### 3. Comprehensive Tool Test Files

**Phase 6 Enhancements** (116 new tests):

1. **GenerateModelTest.php** - 20 tests (64.49% coverage)
2. **GenerateFormTest.php** - 18 tests (69.03% coverage)
3. **GenerateCrudTest.php** - 18 tests (66.88% coverage)
4. **GenerateExtensionTest.php** - 19 tests (66.85% coverage)
5. **GenerateModuleEnhancedTest.php** - 17 tests (74.17% coverage)
6. **InspectDatabaseTest.php** - 17 tests (40.16% coverage)
7. **GenerateControllerTest.php** - Enhanced to 26 tests (67.23% coverage)
8. **ListTablesTest.php** - 8 schema validation tests

**Each test file covers**:

- Input schema validation (structure, types, defaults, enums)
- Property descriptions and examples
- Validation methods (via Reflection)
- formatGiiResult() for all scenarios:
    - Preview mode (with content display)
    - Generation mode (with file status)
    - Validation errors
    - File conflicts
    - Generic errors
- Error message formatting
- Interface compliance

#### 4. Testing Without Yii2 Dependencies

**✅ Successfully Tested**:

- Complete input schema validation
- All private validation methods (via Reflection)
- Result formatting logic (preview vs generation)
- Error handling paths
- Helper functions (ValidationHelper, FileHelper)
- Protocol layer (JSON-RPC 2.0)
- Configuration parsing
- Security validation (injection, traversal)

**⚠️ Requires Integration Tests** (with Yii2):

- doExecute() with actual Gii generators
- Database operations
- File generation workflows
- Yii2Bootstrap with real application

#### 5. Coverage by Component

**Perfect Coverage (100%)**:

- ToolRegistry (17/17 lines)
- Protocol/Request (26/26 lines)
- Protocol/Response (17/17 lines)
- Protocol/Message (6/6 lines)

**Excellent Coverage (>80%)**:

- AbstractTool: 98.36%
- ValidationHelper: 95.83%
- ServerConfig: 95.83%
- ErrorResponse: 95.00%
- StdioTransport: 85.71%
- MCPServer: 81.90% (from 7.62%!)

**Good Coverage (60-80%)**:

- GenerateModule: 74.17% (from 23.33%!)
- GenerateForm: 69.03%
- GenerateController: 67.23%
- GenerateCrud: 66.88%
- GenerateExtension: 66.85%
- GenerateModel: 64.49%

#### 6. Testing Techniques Established

**Reflection API**:

```php
$method = $reflection->getMethod('privateMethod');
$method->setAccessible(true);
$result = $method->invoke($tool, $args);
```

**Mock Objects**:

```php
$bootstrap = $this->createMock(Yii2Bootstrap::class);
$tool = new GenerateModel($bootstrap);
```

**Stream Testing**:

```php
$stream = fopen('php://memory', 'r+');
fwrite($stream, $jsonData);
rewind($stream);
```

**Temporary Files**:

```php
$tempFile = tempnam(sys_get_temp_dir(), 'test_');
// Test file operations
unlink($tempFile);
```

#### 7. Edge Case & Security Testing

**Edge Cases Covered**:

- Empty inputs
- Null values
- Invalid formats
- Boundary conditions
- Special characters

**Security Tests**:

- SQL injection attempts in table names
- Path traversal detection (../, absolute paths)
- Input sanitization validation
- Class name security checks

#### 8. Quality Metrics

**Test Quality**:

- No skipped tests for testable components
- All tests have meaningful assertions
- Comprehensive error scenario coverage
- Type safety validation
- Interface compliance verification

**Code Quality Impact**:

- 60% overall coverage milestone achieved
- All critical paths tested
- Regression protection in place
- Documentation through tests
- Production-ready confidence

### Phase 7: Migration Management Tools (January 2026)

**Motivation**: Enable AI agents to manage database migrations programmatically with safety features.

**Completed Features**:

#### 1. MigrationHelper (`src/Helpers/MigrationHelper.php`)

**Purpose**: Wrapper for Yii2 migrate component

**Key Features**:

- Get migrations by status (applied/pending/all)
- Preview migration SQL without executing
- Execute migration operations (up/down/create/redo/fresh)
- Create migrations with field definitions
- Support Yii2 migration builder syntax

**Field Definition Support**:

```php
$fields = [
    'name:string(255):notNull',
    'email:string:notNull:unique',
    'status:integer:defaultValue(1)',
];
```

#### 2. ListMigrations Tool (`src/Tools/ListMigrations.php`)

**Type**: Read-only (safe)

**Features**:

- List migrations filtered by status (all/applied/pending)
- Show applied timestamps
- Display summary counts
- Limit results for performance

**Use Cases**: Check migration status, review history, identify pending migrations

#### 3. PreviewMigration Tool (`src/Tools/PreviewMigration.php`)

**Type**: Read-only (safe)

**Features**:

- Preview SQL for up/down directions
- No database modifications
- Validate migration exists
- Formatted SQL output

**Use Cases**: Review SQL before applying, understand migration actions, verify correctness

#### 4. ExecuteMigration Tool (`src/Tools/ExecuteMigration.php`)

**Type**: Potentially destructive (full safety features)

**Safety Features**:

- Preview mode enabled by default
- `confirmation="yes"` required for all operations
- `destructiveConfirmation="I understand this will modify the database"` required for down/fresh/redo
- All operations logged to stderr
- Detailed execution results

**Operations**:

- **up**: Apply pending migrations
- **down**: Revert migrations (requires destructive confirmation)
- **create**: Create new migration with field definitions
- **redo**: Revert and re-apply (requires destructive confirmation)
- **fresh**: Drop all tables and re-apply (requires destructive confirmation)

**Field Definitions for Create**:
Supports Yii2 migration builder format with types, sizes, and modifiers:

- Types: string, integer, text, decimal, timestamp, boolean, json, etc.
- Modifiers: notNull, unique, defaultValue(value), unsigned, comment('text')

#### 5. Comprehensive Test Coverage

**New Test Files** (75 tests total):

- `tests/Unit/Helpers/MigrationHelperTest.php` (20 tests)
    - Field definition parsing
    - Migration content generation
    - Table name extraction
    - Column building
- `tests/Unit/Tools/ListMigrationsTest.php` (17 tests)
    - Input schema validation
    - Status enum validation
    - Output formatting
- `tests/Unit/Tools/PreviewMigrationTest.php` (16 tests)
    - Required parameters
    - Direction enum
    - SQL preview formatting
- `tests/Unit/Tools/ExecuteMigrationTest.php` (22 tests)
    - Safety confirmation validation
    - Destructive operation checks
    - Preview result generation
    - Operation requirements validation

**Testing Approach**:

- Reflection API for testing private methods
- Mock Yii2Bootstrap to avoid dependencies
- Comprehensive validation logic testing
- Edge case and security testing

#### 6. Documentation Updates

**README.md**:

- Added "Migration Management" to key features
- Added migration tools to Available Tools table
- Updated project status to "Phase 1-7 Complete"
- Updated tool count from 8 to 11

**AI-MEMORY-BANK.md**:

- Documented all 3 migration tools (list-migrations, preview-migration, execute-migration)
- Added MigrationHelper to Helpers section
- Added Phase 7 to Implementation History
- Added migration workflow to Best Practices

**TODO.md**:

- Marked section 5.3 (Migration Management Tools) as complete

#### 7. Integration

**bin/yii2-gii-mcp**:

- Registered 3 new migration tools
- Updated debug message to show 11 tools
- Added use statements for migration classes

**Total New Code**:

- 4 new source files (~950 lines)
- 4 new test files (~900 lines)
- ~1,850 total new lines

**Total Tools**: 11 production-ready tools (8 Gii generators + 3 migration tools)

**Tool Consolidation (DRY)**:

- PreviewMigration functionality merged into ExecuteMigration
- SQL preview now available via ExecuteMigration with preview=true and migrationName
- Reduced code duplication while maintaining all functionality

**Test Statistics Update**:

- Tests increased from 329 to 404+ total (includes new SQL preview tests)
- Comprehensive migration tool coverage
- All tests passing

### Phase 8: Enhanced Migration Creation Tool (January 2026)

**Motivation**: Separate migration creation from execution and add comprehensive parameters matching Yii2's
migrate/create command capabilities.

**Completed Features**:

#### 1. CreateMigration Tool (`src/Tools/CreateMigration.php`)

**Purpose**: Dedicated tool for creating migrations with all Yii2 migrate/create parameters

**Key Enhancements**:

- **Migration Types**: create, add, drop, junction, custom
- **Advanced Field Definitions**: Full support for types, sizes, and modifiers
- **Auto Features**: Timestamps, soft deletes, foreign keys
- **Custom Paths**: Support for custom migration directories and namespaces
- **Template Support**: Custom migration templates
- **Junction Tables**: Automatic many-to-many relationship table generation
- **Table Prefixes**: Respect Yii2 table prefix configuration

**Migration Types Supported**:

- **create**: Full table creation with field definitions and constraints
- **add**: Add columns to existing tables
- **drop**: Drop tables or specific columns
- **junction**: Generate many-to-many junction tables with FK constraints
- **custom**: Empty migration template for custom SQL

**Field Definition Format**:

```
name:type[:size][:modifier[:modifier...]]

Examples:
- "name:string(255):notNull"
- "email:string:notNull:unique"
- "status:integer:defaultValue(1)"
- "price:decimal(10,2):notNull:defaultValue(0.00)"
- "created_at:timestamp:notNull"
```

**Advanced Parameters**:

- `name`: Migration name (required, snake_case)
- `fields`: Array of field definitions
- `migrationType`: Type of migration (create/add/drop/junction/custom)
- `tableName`: Explicit table name (optional, extracted from name)
- `junctionTable1`/`junctionTable2`: For junction tables
- `migrationPath`: Custom directory for migration files
- `migrationNamespace`: Namespace for namespaced migrations
- `templateFile`: Custom template file path
- `useTablePrefix`: Apply table prefix from db config (default: true)
- `addTimestamps`: Auto-add created_at/updated_at columns
- `addSoftDelete`: Auto-add deleted_at column
- `addForeignKeys`: Auto-generate FKs for _id fields
- `onDeleteCascade`: Use CASCADE vs RESTRICT for FKs
- `comment`: Table comment/description

**Automatic Features**:

- **Timestamps**: Adds `created_at` and `updated_at` timestamp columns
- **Soft Deletes**: Adds `deleted_at` timestamp column
- **Foreign Keys**: Detects fields ending with `_id` and creates FK constraints
- **Junction Tables**: Full many-to-many setup with indexes and FKs

#### 2. Enhanced MigrationHelper

**New Methods**:

- `createMigrationAdvanced(array $params)`: Create migration with all advanced options
- `generateAdvancedMigrationContent()`: Generate migration code based on type
- `generateUpContent()`: Generate safeUp() method content
- `generateDownContent()`: Generate safeDown() method content
- `parseFieldDefinitionAdvanced()`: Enhanced field parsing with sizes
- `generateForeignKeys()`: Auto-generate FK constraints
- `generateDropForeignKeys()`: Auto-generate FK drop code

**Migration Content Generation**:

- Smart detection of table names from migration names
- Automatic pluralization for foreign key tables
- Index generation for junction tables
- Comment support for tables
- Proper indentation and code formatting

**Example Outputs**:

Create Table Migration:

```php
$this->createTable('{{%users}}', [
    'id' => $this->primaryKey(),
    'username' => $this->string(255)->notNull()->unique(),
    'email' => $this->string(255)->notNull()->unique(),
    'status' => $this->integer()->defaultValue(1),
    'created_at' => $this->timestamp()->notNull(),
    'updated_at' => $this->timestamp()->notNull(),
]);

// Add foreign keys
$this->addForeignKey(
    'fk-users-role_id',
    '{{%users}}',
    'role_id',
    '{{%roles}}',
    'id',
    'RESTRICT'
);
```

Junction Table Migration:

```php
$this->createTable('{{%user_posts}}', [
    'id' => $this->primaryKey(),
    'user_id' => $this->integer()->notNull(),
    'post_id' => $this->integer()->notNull(),
    'created_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
]);

// Create indexes
$this->createIndex('idx-user_posts-user_id', '{{%user_posts}}', 'user_id');
$this->createIndex('idx-user_posts-post_id', '{{%user_posts}}', 'post_id');

// Add foreign keys
$this->addForeignKey('fk-user_posts-user_id', '{{%user_posts}}', 'user_id', '{{%users}}', 'id', 'CASCADE');
$this->addForeignKey('fk-user_posts-post_id', '{{%user_posts}}', 'post_id', '{{%posts}}', 'id', 'CASCADE');
```

#### 3. Integration & Documentation

**bin/yii2-gii-mcp**:

- Registered CreateMigration tool
- Updated tool count from 11 to 12
- Added use statement for CreateMigration class

**AI-MEMORY-BANK.md**:

- Comprehensive documentation of CreateMigration tool
- All parameters documented with examples
- Field definition format explained
- Migration type descriptions
- Use case examples

**Benefits**:

- Clear separation: CreateMigration for creation, ExecuteMigration for execution
- Full parity with Yii2's migrate/create command
- AI agents can create complex migrations programmatically
- Support for modular applications with namespaces
- Enterprise features: soft deletes, timestamps, auto-FKs

**Total New Code** (Phase 8):

- 1 new tool file (~730 lines)
- Enhanced MigrationHelper (~700 additional lines)
- ~1,430 total new lines

**Total Tools**: 12 production-ready tools (8 Gii generators + 4 migration tools)

#### Phase 8 Enhancement: Index Generation & Custom FK Actions (January 9, 2026)

**Motivation**: Complete the migration creation tool with index generation and full foreign key action control.

**Completed Features**:

**1. Index Generation for Specific Columns**

- New `indexes` parameter (array of strings)
- Support for single column indexes: `['email']`
- Support for composite indexes: `['user_id,created_at']`
- Auto-generated index names: `idx-{table}-{column1}-{column2}`
- Automatic index removal in `safeDown()`

**2. Custom Foreign Key Actions**

- New `foreignKeys` parameter (array of objects)
- Per-field FK configuration: `{field, table, column, onDelete, onUpdate}`
- Support all SQL FK actions: CASCADE, RESTRICT, SET NULL, SET DEFAULT, NO ACTION
- Custom referenced column support (not just 'id')
- Backward compatible with existing `addForeignKeys` boolean
- Invalid actions automatically converted to RESTRICT for safety

**Code Changes**:

- `src/Helpers/MigrationHelper.php` (~200 lines added)
  - `generateIndexes()` - Create indexes with proper formatting
  - `generateDropIndexes()` - Remove indexes in safeDown()
  - `generateForeignKeysExplicit()` - Create FKs with custom actions
  - `generateDropForeignKeysExplicit()` - Remove explicit FKs
  - FK action validation
  - Updated method signatures for new parameters

- `src/Tools/CreateMigration.php` (~100 lines added)
  - Added `indexes` and `foreignKeys` to input schema
  - Updated parameter extraction and passing
  - Enhanced preview output to show indexes and FKs

**Test Coverage**:

- Added 11 comprehensive tests to `MigrationHelperTest.php`
- All 36 tests pass (100% success rate)
- 126 total assertions
- Tests cover: single/composite indexes, all FK actions, validation, edge cases

**Documentation Updates**:

- `docs/PHASE-8-CREATE-MIGRATION-ENHANCEMENT.md`: Complete feature documentation with examples
- `docs/AI-MEMORY-BANK.md`: Updated tool parameters, advanced features, and usage examples

**Benefits**:

- Production-ready migrations with proper indexes from the start
- Full control over referential integrity behavior
- No manual SQL required
- Database optimization built-in
- Safer data deletion patterns

---

## Best Practices for AI Agents

This section provides guidelines for AI coding assistants using this MCP server.

### Understanding the Workflow

**Typical Interaction**:

1. **Inspect**: Use `list-tables` or `inspect-database` to understand schema
2. **Preview**: Use generation tools with `preview: true` to show code
3. **Review**: Present preview to user for approval
4. **Generate**: Use generation tools with `preview: false` to write files
5. **Verify**: Confirm file creation and show summary

### Tool Selection Guide

**For Database Exploration**:

- Use `list-tables` for quick overview
- Use `inspect-database` for detailed schema including relationships

**For Code Generation**:

- Always start with preview mode
- Use specific namespaces when generating for Advanced template
- Check for existing files before generation

**Tool Decision Tree**:

```
User wants code?
├─ Just explore database? → list-tables or inspect-database
├─ Generate model? → inspect-database (check relations) → generate-model
├─ Generate CRUD? → Ensure model exists → generate-crud
├─ Custom controller? → generate-controller
├─ Form for data entry? → generate-form
├─ New module? → generate-module
└─ Create extension? → generate-extension
```

### Prompt Interpretation

**User Says** → **What to Do**:

- "Show tables" → `list-tables`
- "What columns does X have?" → `inspect-database` with tablePattern
- "Generate User model" → `generate-model` (preview first!)
- "Create CRUD for User" → `generate-crud` (check model exists first)
- "I need a contact form" → `generate-form`
- "Set up admin module" → `generate-module`

### Namespace Handling

**Automatic Detection**:

The `generate-model` tool automatically detects the Yii2 template type and uses appropriate defaults:

- **Advanced Template**: Defaults to `common\models` (shared models accessible by all components)
- **Basic Template**: Defaults to `app\models`

**Basic Template Structure**:

- Models: `app\models`
- Controllers: `app\controllers`

**Advanced Template Structure**:

- **Common models** (default): `common\models` - Shared across all components
- **Frontend models**: `frontend\models` - Frontend-specific models
- **Backend models**: `backend\models` - Admin/backend-specific models
- **API models**: `api\models` - API-specific models (if API component exists)
- Frontend controllers: `frontend\controllers`
- Backend controllers: `backend\controllers`
- API controllers: `api\controllers`

**Best Practices for Advanced Template**:

1. **Use `common\models` by default** - Most models should be shared (this is the automatic default)
2. **Use component-specific namespaces** only when the model is truly specific to that component
3. **Always ask user** if unclear which namespace to use for controllers or component-specific models
4. **The tool will automatically use the correct default**, so no need to specify namespace unless overriding

**Examples**:

```
User: "Generate a User model"
AI: The tool will automatically detect Advanced Template and use common\models

User: "Generate a Product model in the frontend"
AI: Call generate-model with namespace="frontend\\models"

User: "Generate models for the users and posts tables"
AI: Both will default to common\models (no namespace parameter needed)
```

### Error Handling

**When Tool Returns Error**:

1. Parse error message and code
2. Check if it's a common issue (table not found, file exists, etc.)
3. Suggest solution to user
4. Offer to retry with corrected parameters

**Example**:

```
Error: Table 'usr' not found (code: -32002)
→ "The table 'usr' doesn't exist. Did you mean 'users'? 
   Let me list available tables for you."
```

### Preview Workflow

**Best Practice**:

```
1. AI: Calls generate-model with preview: true
2. AI: "Here's the User model I'll generate: [shows code]"
3. User: "Looks good" or "Change namespace to backend\models"
4. AI: Calls generate-model with preview: false (or with updated params)
5. AI: "✓ Created app/models/User.php"
```

**Never skip preview** unless user explicitly requests it.

### Multi-Step Generation

**For CRUD Generation**:

1. Check if model exists (if not, offer to generate it)
2. Generate model (if needed)
3. Generate CRUD with appropriate component
4. Show summary of all created files

**Example - Basic Template**:

```
User: "I need CRUD for the party table"

AI Steps:
1. inspect-database (check party table exists)
2. Check if Party model exists
3. If not: generate-model (Party) with preview
4. User approves
5. generate-model (Party) with preview: false
6. generate-crud (Party) with preview
7. User approves
8. generate-crud (Party) with preview: false
9. Show summary: "Created Party.php, PartyController.php, 
   PartySearch.php, and 6 view files"
```

**Example - Advanced Template**:

```
User: "I need backend CRUD for the users table"

AI Steps:
1. inspect-database (check users table exists)
2. Check if User model exists in common\models
3. If not: generate-model with preview (defaults to common\models)
4. User approves
5. generate-model with preview: false
6. generate-crud with modelClass="common\\models\\User", component="backend", preview: true
7. User approves
8. generate-crud with component="backend", preview: false
9. Show summary: "Created backend\controllers\UserController.php, 
   backend\models\UserSearch.php, and 6 view files in @backend/views/user"
```

**Best Practices for Advanced Template CRUD**:

1. **Always ask which component** if unclear (frontend/backend/api)
2. **Common models by default** - Generate models in common\models for sharing
3. **Component-specific CRUDs** - Generate CRUD in the appropriate component
4. **Auto-detection works** - If model already has component namespace, it auto-detects
5. **Frontend is default** - If model is in common\models and no component specified, CRUD goes to frontend

### Common Patterns

**Pattern 1: Explore Then Generate**

```
1. list-tables → Show user available tables
2. User picks one
3. inspect-database → Show details
4. generate-model → Preview
5. generate-crud → Preview
6. Generate both → Files created
```

**Pattern 2: Batch Model Generation**

```
User: "Generate models for all tables"

1. list-tables → Get all table names
2. For each table:
   a. generate-model with preview (show one example)
3. Ask user: "Generate models for all X tables?"
4. For each table:
   a. generate-model with preview: false
5. Show summary
```

**Pattern 3: Incremental CRUD**

```
1. Generate model
2. Test/review model
3. Generate CRUD based on model
4. Test/review CRUD
5. Customize (add actions, modify views)
```

**Pattern 4: Migration Workflow**

```
User: "Create a migration for the users table"

AI Steps:
1. list-migrations (check current status)
2. execute-migration with operation=create, preview=true
3. Show preview: "Will create migration with these fields..."
4. User approves
5. execute-migration with confirmation="yes", preview=false
6. Show result: "Created migration file: m240107_120000_create_users_table.php"
7. Suggest: "Would you like to preview the migration before applying it?"
8. If yes: preview-migration
9. If approved: execute-migration with operation=up
```

### Migration Management Best Practices

**For List Migrations**:

- Use before any migration operation to understand current state
- Filter by status (pending/applied) to focus on relevant migrations
- Check for pending migrations before deploying

**For SQL Preview**:

- **ALWAYS preview before executing** a migration
- Use execute-migration with preview=true, migrationName, and direction
- Review SQL for both up and down directions
- Verify table names, column types, and constraints
- Check for potential data loss in down migrations

**For Execute Migration**:

**Safety Protocol**:

1. **List first**: Always call list-migrations to understand state
2. **Preview SQL**: Use execute-migration with preview=true, migrationName, and direction to review SQL
3. **Preview the operation**: Call execute-migration with preview=true (without migrationName for operation preview)
4. **User approval**: Get explicit user confirmation
5. **Execute**: Call with confirmation and preview=false

**Confirmation Requirements**:

- **All operations**: `confirmation="yes"` (exact string)
- **Destructive ops** (down/fresh/redo): Additional
  `destructiveConfirmation="I understand this will modify the database"`
- **Never bypass**: These are safety features, not optional

**Creating Migrations**:

```
User: "Create a migration for users table with name, email, and status"

AI:
1. Call create-migration with:
   - name="create_users_table"
   - migrationType="create"
   - fields=["name:string(255):notNull", "email:string:notNull:unique", "status:integer:defaultValue(1)"]
   - addTimestamps=true
   - preview=true
2. Show preview with generated migration code
3. If approved, call again with preview=false
4. Migration file created with all fields defined
```

**Field Definition Format**:

- Basic: `name:type`
- With size: `name:string(255)`
- With modifiers: `name:string:notNull:unique`
- With default: `status:integer:defaultValue(1)`
- Complex: `price:decimal(10,2):notNull:defaultValue(0.00)`

**Applying Migrations**:

```
User: "Apply pending migrations"

AI:
1. list-migrations (show pending)
2. For critical migrations: execute-migration with preview=true, migrationName, direction="up"
3. execute-migration with operation=up, preview=true
4. Show: "Will apply X migrations"
5. If approved: execute-migration with confirmation="yes", preview=false
```

**Reverting Migrations**:

```
User: "Revert the last migration"

AI:
1. list-migrations (show recent applied)
2. execute-migration with preview=true, migrationName, direction=down (to show SQL)
3. Show SQL and WARNING
4. execute-migration with operation=down, preview=true (to show operation preview)
5. Show destructive warning
6. If approved: execute-migration with:
   - operation="down"
   - confirmation="yes"
   - destructiveConfirmation="I understand this will modify the database"
   - preview=false
```

**Never**:

- ❌ Skip confirmations
- ❌ Execute without preview
- ❌ Use fresh operation without explicit user request
- ❌ Apply down migrations without showing the SQL
- ❌ Batch execute migrations without user seeing the list

**Always**:

- ✅ List migrations first
- ✅ Preview SQL before executing
- ✅ Show preview of operation
- ✅ Get explicit confirmation
- ✅ Warn about destructive operations
- ✅ Show execution results

### Safety Checks

**Before Generation**:

- ✅ Check table/model exists
- ✅ Validate namespace is appropriate
- ✅ Check target directory is writable
- ✅ Preview first, then generate

**After Generation**:

- ✅ Confirm files were created
- ✅ List all generated files
- ✅ Suggest next steps (run migrations, test, etc.)

### Communicating with Users

**Be Clear**:

- Explain what you're doing before each tool call
- Show previews in readable format
- Confirm file creation with file paths
- Suggest next steps

**Be Helpful**:

- If error occurs, explain and suggest fix
- Offer alternatives ("Or would you prefer...")
- Provide examples when asking questions

**Example Good Communication**:

```
AI: "I'll inspect the party table to see its structure and relationships."
    [Calls inspect-database]
AI: "The party table has 8 columns including relations to venue and artists.
     I'll generate a Party model with these relationships.
     Here's a preview: [shows code]
     Should I create this model?"
User: "Yes"
AI: [Calls generate-model with preview: false]
AI: "✓ Created common/models/Party.php
     The model includes getVenue() and getArtists() relation methods.
     Would you like me to generate CRUD operations for this model?"
```

### Testing and Verification

**After Setup**:

- Suggest running `bin/diagnose` to verify setup
- Test with simple tool like `list-tables`
- Verify file permissions if generation fails

**After Generation**:

- Confirm files exist
- Suggest running Yii2 to test ("Try accessing /party in your browser")
- Offer to make adjustments

### Advanced Usage

**Custom Templates** (future):

- When available, ask user about template preferences
- Offer to customize generated code

**Batch Operations**:

- Offer to generate multiple models at once
- Ask for confirmation before batch operations

**Error Recovery**:

- If generation fails, parse error and retry
- Suggest fixing underlying issue (permissions, config, etc.)

---

## Conclusion

This AI Memory Bank contains complete technical documentation for the yii2-gii-mcp project. It covers:

- Architecture and design
- Full MCP protocol implementation
- All 12 production-ready tools
- Configuration for all MCP clients
- Safety features and validation
- Setup tools and diagnostics
- Testing infrastructure
- Development guidelines
- Complete troubleshooting guide
- Implementation history

**For AI Agents**: Use this document as your primary reference. It contains everything needed to effectively use and
extend this MCP server.

**For Humans**: See `/README.md` for a quick start guide.

**Project Status**: Phase 1-8 complete with enhancements. All core functionality implemented and tested. 12 production-ready tools (8 Gii
generators + 4 migration tools including enhanced CreateMigration with index generation and custom FK actions). Code consolidation applied (DRY principle). Ready for production use.

**Next Phase**: Custom Gii templates, advanced code inspection, database diagram generation.

---

**Document Version**: 1.2  
**Last Updated**: January 9, 2026  
**Maintained By**: AI-assisted development (Firebender, Claude)
