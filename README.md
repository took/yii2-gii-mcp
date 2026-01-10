# yii2-gii-mcp

**MCP server for AI-powered Yii2 code generation and scaffolding**

yii2-gii-mcp is a PHP-based [Model Context Protocol (MCP)](https://modelcontextprotocol.io/) server that enables AI
assistants (Firebender, Claude Desktop, Cline, etc.) to interact with **Yii2's Gii code generator**. This allows AI
agents to inspect your database, generate ActiveRecord models, create CRUD interfaces, and scaffold complete application
components—all through natural language conversations.

> **Note:** This MCP server works exclusively with **Yii2 framework projects**. Yii1 and Yii3 are not supported.

## Key Features

- **Database Inspection** - List tables, columns, relationships, indexes, and constraints
- **Model Generation** - Create ActiveRecord models from database tables with relations
- **CRUD Scaffolding** - Generate complete CRUD operations (controllers, views, search models)
- **Controller Generation** - Create custom controllers with specific actions
- **Form Generation** - Generate form models for data collection and validation
- **Module Scaffolding** - Create complete Yii2 modules with directory structure
- **Extension Scaffolding** - Generate extension boilerplate with Composer packaging
- **Migration Management** - List, preview, and execute database migrations with safety confirmations
- **Project Structure Detection** - Auto-detect template type, applications, modules, and environment configuration
- **Component Inspection** - Analyze controllers, models, and views with detailed metadata extraction
- **Log Reading** - Read and filter application logs from files and database with advanced filtering
- **Preview-First Workflow** - Review all code before writing to disk (safety built-in)
- **Full MCP Support** - JSON-RPC 2.0 over stdio, works with any MCP client

## Requirements

- PHP 8.2 or higher
- Composer
- **Yii2 framework** (provided by your project)
- Yii2 Gii module (provided by your project)

**Important:** This MCP server is designed exclusively for **Yii2 framework projects**. Yii1 and Yii3 are not supported.
If you're using Yii1, please migrate to Yii2 first using
the [official Yii2 upgrade guide](https://www.yiiframework.com/doc/guide/2.0/en/intro-upgrade-from-v1).

## Quick Start

### 1. Installation

Install into your existing Yii2 project via Composer:

```bash
cd /path/to/your/yii2/project
composer require took/yii2-gii-mcp
```

### 2. Setup (Interactive)

Run the interactive setup wizard to configure everything automatically:

```bash
php vendor/took/yii2-gii-mcp/bin/interactive-setup
```

This wizard will:

- Detect your Yii2 project structure (Basic/Advanced Template)
- Create `config-mcp.php` configuration file
- Test your database connection
- Configure Firebender or Claude Desktop
- Verify the setup

**That's it!** Restart your IDE/client and you're ready to use it.

### 3. Manual Setup (Alternative)

If you prefer manual configuration:

#### Create config-mcp.php

```bash
cp vendor/took/yii2-gii-mcp/examples/config-advanced-template.php config-mcp.php
```

This smart config template automatically detects your Yii2 structure (Basic/Advanced/Advanced+API).

**Important:** Add to `.gitignore`:

```bash
echo "config-mcp.php" >> .gitignore
```

#### Configure Your MCP Client

**For Firebender (Global - recommended):**

Edit `~/.firebender/firebender.json`:

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
        "YII2_APP_PATH": "${workspaceFolder}"
      }
    }
  }
}
```

**For Claude Desktop:**

Edit your Claude config file:

- **macOS**: `~/Library/Application Support/Claude/claude_desktop_config.json`
- **Windows**: `%APPDATA%\Claude\claude_desktop_config.json`
- **Linux**: `~/.config/Claude/claude_desktop_config.json`

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

**Note:** Use absolute paths for Claude Desktop!

**For Claude Code (CLI):**

Add the MCP server to your project:

```bash
claude mcp add yii2-gii-mcp --scope project \
  -e YII2_CONFIG_PATH=/path/to/your/project/config-mcp.php \
  -e YII2_APP_PATH=/path/to/your/project \
  -- php /path/to/your/project/vendor/took/yii2-gii-mcp/bin/yii2-gii-mcp
```

Then add the allowed tools to your `.claude/settings.json`:

```json
{
  "allowedTools": [
    "mcp__yii2-gii-mcp:*"
  ]
}
```

This allows all yii2-gii-mcp tools to run without requiring manual approval for each call.

**Note:** Use absolute paths! The `--scope project` option stores the configuration in `.mcp.json` in your project
directory.

### 4. Docker Setup

**If your Yii2 project runs in Docker Desktop**, see the Docker-specific guide:

**→ [docs/DOCKER.md](docs/DOCKER.md)**

This covers:

- Two setup options: MCP on host (recommended) or inside container
- Database connection configuration for Docker
- Firebender and Claude Desktop configs for Docker
- Troubleshooting Docker-specific issues
- Example configurations and wrapper scripts

**Quick Docker Start (Host-Based - Recommended):**

```bash
# 1. Install on host (faster, simpler)
composer require took/yii2-gii-mcp

# 2. Run interactive setup
php vendor/took/yii2-gii-mcp/bin/interactive-setup
# Configure database as 127.0.0.1:3306 (Docker exposes ports to host)

# 3. Configure Firebender with examples/docker/firebender-host.json
# 4. Restart PhpStorm - Done!
```

For container-based setup or troubleshooting, see the [full Docker guide](docs/DOCKER.md).

### 5. Verify Installation

Test your setup with the diagnostic tool:

```bash
php vendor/took/yii2-gii-mcp/bin/diagnose
```

Or test manually:

```bash
YII2_CONFIG_PATH=config-mcp.php php vendor/took/yii2-gii-mcp/examples/test-list-tables.php
```

### 6. First Use

Ask your AI assistant (Firebender, Claude, etc.):

```
"Detect my Yii2 project structure and show me what template type I'm using"
"What environment is currently configured in my Yii2 application?"
"Show me the last 50 error logs from all applications"
"Read warning and error logs from the frontend application from the last 24 hours"
"Search for 'database connection' errors in console logs"
"List all database tables using yii2-gii-mcp inspect-database"
"Show me the structure of the user table using yii2-gii-mcp list-tables user"
"Generate a migration to create the table foo with some random fields"
"Generate a migration to create the table bar with a bar_data text field where each bar has exactly one parent foo"
"Generate ActiveRecord models for the foo and bar tables using yii2-gii-mcp (in /web or prefered in /common if available)"
"Create CRUD operations (in the Backend/BackOffice if available) for the foo table using yii2-gii-mcp"
"Update the controller and CRUD views for foo to support the relation to bar ("inline" update of bar_data/insert new bars)."
```

The AI will use the MCP tools to inspect your database and generate code!

## Usage Examples

### Database Inspection

```
You: "What tables are in my database?"
AI: [Uses list-tables tool] "Found 15 tables: users, posts, comments, ..."

You: "Show me the schema for the users table"
AI: [Uses inspect-database] "The users table has columns: id (int), username (varchar), ..."
```

### Model Generation

```
You: "Generate a User model from the users table"
AI: [Uses generate-model with preview] "Here's a preview of the User model..."

You: "Looks good, generate it"
AI: [Writes file] "Created User model at app/models/User.php"
```

### CRUD Scaffolding

```
You: "I need CRUD operations for the Post model"
AI: [Uses generate-crud] "Generated:
  - PostController.php
  - PostSearch.php
  - views/post/index.php
  - views/post/view.php
  - views/post/create.php
  - views/post/update.php"
```

## Available Tools

### Code Generation & Scaffolding

| Tool                  | Purpose                      | Safety          |
|-----------------------|------------------------------|-----------------|
| `list-tables`         | List all database tables     | ✅ Read-only     |
| `inspect-database`    | Detailed schema inspection   | ✅ Read-only     |
| `generate-model`      | Generate ActiveRecord models | ⚠️ Writes files |
| `generate-crud`       | Generate CRUD operations     | ⚠️ Writes files |
| `generate-controller` | Generate controllers         | ⚠️ Writes files |
| `generate-form`       | Generate form models         | ⚠️ Writes files |
| `generate-module`     | Generate modules             | ⚠️ Writes files |
| `generate-extension`  | Generate extensions          | ⚠️ Writes files |

### Migration Management

| Tool                | Purpose                                                           | Safety               |
|---------------------|-------------------------------------------------------------------|----------------------|
| `list-migrations`   | List migrations with status (applied/pending)                     | ✅ Read-only          |
| `create-migration`  | Create new migration files with field definitions                 | ⚠️ Writes files      |
| `execute-migration` | Execute migration operations (up/down/redo/fresh) and preview SQL | 🔴 Modifies database |

### Project Analysis

| Tool                           | Purpose                                                      | Safety       |
|--------------------------------|--------------------------------------------------------------|--------------|
| `detect-application-structure` | Detect project structure, template type, apps, and environments | ✅ Read-only |

### Code Analysis & Inspection

| Tool                | Purpose                                                          | Safety       |
|---------------------|------------------------------------------------------------------|--------------|
| `inspect-components` | List and analyze controllers, models, views with metadata      | ✅ Read-only |

### Logging & Debugging

| Tool        | Purpose                                                                  | Safety       |
|-------------|--------------------------------------------------------------------------|--------------|
| `read-logs` | Read and filter logs from files and database (level, category, search) | ✅ Read-only |

**Total: 14 production-ready tools**

All generation tools default to **preview mode** for safety. The `execute-migration` tool requires **explicit
confirmations** for all operations.

## Troubleshooting

### "Tools not available" in AI assistant

1. Run diagnostics: `php vendor/took/yii2-gii-mcp/bin/diagnose`
2. Check `config-mcp.php` exists
3. Verify database connection in config
4. Restart your IDE/client completely

### Database connection errors

1. Check database credentials in `config-mcp.php`
2. Ensure database is running
3. Run: `php vendor/took/yii2-gii-mcp/bin/diagnose`

### Firebender doesn't see MCP server

1. Verify `~/.firebender/firebender.json` exists and has correct JSON syntax
2. Completely restart PhpStorm (not just close window)
3. Check logs: Help → Show Log in Files

### Files not generated

1. Check write permissions on target directory
2. Ask AI to show preview first
3. Verify Gii module is enabled in config

For more troubleshooting, see the [comprehensive documentation](docs/AI-MEMORY-BANK.md).

## Documentation

- **For AI Agents**: [docs/AI-MEMORY-BANK.md](docs/AI-MEMORY-BANK.md) - Complete technical reference
- **Interactive Setup**: Run `php vendor/took/yii2-gii-mcp/bin/interactive-setup`
- **Diagnostics**: Run `php vendor/took/yii2-gii-mcp/bin/diagnose`
- **Examples**: See `examples/` directory for config templates and test scripts
- **Implementation Roadmap**: See `TODO.md` for development plans

## Development

### Running Tests

```bash
# Run all tests
make test

# Run specific test suite
make test-unit             # Unit tests
make test-functional       # Functional tests

# Generate coverage report
make coverage
```

Or with Composer:

```bash
composer test
composer test-unit
composer test-functional
```

### Adding New Tools

See [docs/AI-MEMORY-BANK.md](docs/AI-MEMORY-BANK.md) for detailed development guidelines.

## Support and Contributing

- **Issues**: Use GitHub Issues to report bugs or request features
- **Pull Requests**: Fork the repo and follow PSR-12 coding standards
- **Tests**: Include tests for new tools (we use Codeception)
- **Documentation**: Update the AI memory bank for significant changes

## Authors

- **Author**: Guido Pannenbecker <info@sd-gp.de>
- **AI Implementation**: Significant portions implemented with AI coding agents (Firebender, Claude)

## License

MIT - See LICENSE file for details.

## Project Status

**Patch 1.1.1 Released**

Patch 1.1.1 fixed some tests

**Version 1.1.0 Released** 🎉

- ✅ Full MCP protocol implementation (JSON-RPC 2.0 over stdio)
- ✅ 14 production-ready tools (8 Gii generators + 3 migration tools + 3 analysis/inspection tools)
- ✅ Comprehensive test suite (450+ tests, 60% coverage)
- ✅ Interactive setup wizard and diagnostic tools
- ✅ Complete documentation for humans and AI agents
- ✅ Migration management with safety features
- ✅ Project structure detection and analysis
- ✅ Component inspection with metadata extraction
- ✅ Application log reading and filtering
- ✅ Code consolidation (DRY principle applied)

**Next Phase**: Routing inspection, i18n tools, RBAC testing, custom templates

See `TODO.md` for detailed roadmap.
