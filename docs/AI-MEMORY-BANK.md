# yii2-gii-mcp - AI Memory Bank

**Complete Technical Reference for AI Agents**

This document serves as a comprehensive memory bank for AI coding assistants working with the yii2-gii-mcp project. It contains complete technical specifications, architecture details, configuration options, tool documentation, and historical context.

**Last Updated**: January 2026  
**Project Version**: Phase 1-4 Complete  
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

yii2-gii-mcp is a PHP-based MCP (Model Context Protocol) server that enables AI agents to interact with Yii2's Gii code generator for automated scaffolding and code generation. It bridges the gap between AI assistants and Yii2 development workflows.

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

- **From Yii1**: Use the [official Yii2 upgrade guide](https://www.yiiframework.com/doc/guide/2.0/en/intro-upgrade-from-v1)
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
│         Tools (8 total)                 │
│  - ListTables (read-only)               │
│  - InspectDatabase (read-only)          │
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
      "version": "1.0.0"
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
      "version": "1.0.0"
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
      "description": "Namespace for model (default: 'app\\models')"
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
1. Validate table exists
2. Generate model code using Gii
3. If preview=true: Return code preview
4. If preview=false: Write files, return status

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

**Input Schema**:
```json
{
  "type": "object",
  "properties": {
    "modelClass": {
      "type": "string",
      "description": "Full model class name (e.g., 'app\\models\\User') (required)"
    },
    "controllerClass": {
      "type": "string",
      "description": "Controller class name (optional, auto-generated)"
    },
    "viewPath": {
      "type": "string",
      "description": "View path (optional, default: @app/views/<controller>)"
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
1. Validate model class exists
2. Generate all CRUD files
3. If preview=true: Return file list with code
4. If preview=false: Write files, return status

**Safety Features**:
- Model class existence check
- File conflict detection
- Preview mode default

**Implementation**: `src/Tools/GenerateCrud.php`

### 5. generate-controller

**Purpose**: Generate Yii2 controller with custom actions

**Type**: Writes files (preview mode default)

**Input Schema**:
```json
{
  "type": "object",
  "properties": {
    "controllerID": {
      "type": "string",
      "description": "Controller ID (e.g., 'post', 'admin/user') (required)"
    },
    "actions": {
      "type": "string",
      "description": "Comma-separated action IDs (e.g., 'index,view,create')"
    },
    "namespace": {
      "type": "string",
      "description": "Namespace (default: 'app\\controllers')"
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

**Example**:
```json
{
  "controllerID": "post",
  "actions": "index,view,create,update,delete",
  "namespace": "app\\controllers"
}
```

**Output**: Controller with action methods and view rendering

**Implementation**: `src/Tools/GenerateController.php`

### 6. generate-form

**Purpose**: Generate Yii2 form model for data collection and validation

**Type**: Writes files (preview mode default)

**Input Schema**:
```json
{
  "type": "object",
  "properties": {
    "modelClass": {
      "type": "string",
      "description": "Form model class name (e.g., 'ContactForm') (required)"
    },
    "namespace": {
      "type": "string",
      "description": "Namespace (default: 'app\\models')"
    },
    "viewPath": {
      "type": "string",
      "description": "View path (optional)"
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

---

## Configuration

### Environment Variables

The MCP server is configured via environment variables:

| Variable | Description | Required | Default |
|----------|-------------|----------|---------|
| `YII2_CONFIG_PATH` | Path to Yii2 config file | **Yes** | - |
| `YII2_APP_PATH` | Path to Yii2 application root | No | Inferred from config |
| `GII_ENABLED` | Enable Gii module | No | `true` |
| `DB_CONNECTION` | Database connection component ID | No | `db` |
| `DEBUG` | Enable debug logging to stderr | No | `false` |

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

// Check for Advanced Template
if (is_dir($baseDir . '/common') && is_dir($baseDir . '/frontend')) {
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

**File**: `~/.firebender/firebender.json`

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

**File**: `.firebender/firebender.json` (in project root)

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
   - Checks global config (`~/.firebender/firebender.json`)
   - Checks local config (`.firebender/firebender.json`)
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

**Test Suites**:
1. **Unit Tests** (`tests/Unit/`)
2. **Functional Tests** (`tests/Functional/`)

### Test Coverage

**Current Status**: 50+ tests

**Unit Tests** (37+ tests):
- Protocol classes (ErrorResponse, Request, Response)
- ToolRegistry with mock tools
- AbstractTool base class functionality
- All 8 tool classes (schema validation, input parsing)

**Functional Tests** (13 tests):
- MCP protocol testing (initialize, tools/list, tools/call)
- Error handling (method not found, invalid JSON, invalid params)
- StdioTransport I/O with memory streams
- Mock tool execution

**Note**: Some tests marked as skipped (TDD approach) pending advanced Yii2/Gii mocking infrastructure for full integration testing.

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

1. **Path incorrect**: Verify paths in `~/.firebender/firebender.json`
   ```bash
   ls -la vendor/took/yii2-gii-mcp/bin/yii2-gii-mcp
   ls -la config-mcp.php
   ```

2. **JSON syntax error**: Validate JSON:
   ```bash
   cat ~/.firebender/firebender.json | python -m json.tool
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

**Basic Template**:
- Models: `app\models`
- Controllers: `app\controllers`

**Advanced Template**:
- Common models: `common\models`
- Frontend controllers: `frontend\controllers`
- Backend controllers: `backend\controllers`
- API controllers: `api\controllers`

**Always ask user** which component/namespace for Advanced template if unclear.

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
3. Generate CRUD
4. Show summary of all created files

**Example**:
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
- All 8 production-ready tools
- Configuration for all MCP clients
- Safety features and validation
- Setup tools and diagnostics
- Testing infrastructure
- Development guidelines
- Complete troubleshooting guide
- Implementation history

**For AI Agents**: Use this document as your primary reference. It contains everything needed to effectively use and extend this MCP server.

**For Humans**: See `/README.md` for a quick start guide.

**Project Status**: Phase 1-4 complete. All core functionality implemented and tested. Ready for production use.

**Next Phase**: Custom Gii templates, advanced code inspection, database diagram generation.

---

**Document Version**: 1.0  
**Last Updated**: January 2026  
**Maintained By**: AI-assisted development (Firebender, Claude)
