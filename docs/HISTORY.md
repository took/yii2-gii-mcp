# Release History

This document tracks the public release history of yii2-gii-mcp on GitHub.

## Version 1.1.1

**Release Date**: June 10, 2026

### Patch Summary

This is a maintenance and stability patch focused on improved error handling, configuration robustness, and user experience ahead of planned feature additions.

### Changes

- Improved error handling and test setup robustness.
- Minor code cleanup and dependency updates.

_No new features added in this patch. Minor user-facing and developer-facing improvements; recommended for all users._

## Version 1.1.0

**Release Date**: January 10, 2026

### Release Summary

Major feature release adding comprehensive project analysis, component inspection, and enhanced migration management. This release brings the total number of production-ready tools from 8 to 14, with significant improvements in code generation intelligence and safety features.

### New Features

- **Component Inspection Tool**: List and analyze application components with detailed metadata:
  - `inspect-components`: Comprehensive component analysis tool
  - Controller analysis: actions, filters, behaviors, parameters, PHPDoc comments
  - Model analysis: attributes, validation rules, scenarios, relations (for ActiveRecord)
  - View discovery: organized by controller with file listings
  - Support for application and module-level filtering
  - Deep code analysis using PHP Reflection + nikic/php-parser
  - Component type filtering (controllers/models/views/all)
  - Detailed and summary output modes
  - Read-only operation for safe inspection

- **LogReader Tool**: Read and filter Yii2 application logs:
  - `read-logs`: Comprehensive log reading and filtering tool
  - Multi-source support: FileTarget (runtime/logs) and DbTarget (log table)
  - Multi-application log aggregation across all apps (frontend, backend, console, api)
  - Advanced filtering by level (error/warning/info/trace), category (with wildcards), time range, full-text search
  - Auto-discovery of log files across application directories
  - Yii2 log format parsing with multi-line stack trace support
  - Statistics and summary generation (by level, by application)
  - Read-only operation for safe log inspection

- **Project Structure Detection Tool**: Auto-detect Yii2 project organization and configuration:
  - `detect-application-structure`: Comprehensive project analysis tool
  - Template type detection (Basic/Advanced/Advanced+API)
  - Application directory discovery (frontend, backend, console, api)
  - Module detection within applications
  - Environment analysis with init system support
  - Entry point detection and YII_ENV/YII_DEBUG parsing
  - Comparison of actual index.php files with environment templates
  - Read-only operation for safe project inspection

- **Migration Management Tools**: Added three new MCP tools for comprehensive database migration workflow:
  - `list-migrations`: List all migrations with their status (applied/pending)
  - `create-migration`: Create new migration files with advanced options and field definitions
  - `execute-migration`: Execute migrations (up/down/redo/fresh) with SQL preview and safety confirmations

### Enhancements

- **Template Auto-Detection**: Intelligent template and component detection for code generation tools:
  - Automatic detection of Yii2 template type (Basic, Advanced, Advanced+API)
  - Smart default namespace selection based on detected template and component (backend, frontend, common, console, api)
  - Applies to `generate-controller`, `generate-crud`, `generate-form`, and `generate-model` tools
  - Reduces manual configuration and improves code generation accuracy

- **Advanced Migration Creation**: Enhanced `create-migration` tool with:
  - Comprehensive field type options (string, text, integer, bigint, float, decimal, datetime, timestamp, boolean, json, binary, enum)
  - Support for indexes, foreign keys with custom actions (CASCADE, SET NULL, RESTRICT, NO ACTION), and constraints
  - Enum data type with automatic check constraint generation
  - Improved code generation with proper formatting
  - Detailed documentation for all parameters

- **SQL Preview Functionality**: `execute-migration` tool now includes:
  - Preview of SQL statements before execution
  - Validation and formatted output for migration names and directions
  - Enhanced safety with explicit user confirmations

- **Code Quality**: 
  - General code reformatting and refactoring for better maintainability
  - PHPStan static analysis integration for continuous code quality monitoring
  - Baseline configuration for managing existing issues
  - Added nikic/php-parser dependency for advanced AST analysis

### Testing & Documentation

- **Increased Test Coverage**: Improved automated test suite with higher code coverage
  - ComponentAnalyzerTest: Comprehensive tests for component analysis methods
  - InspectComponentsTest: Full tool execution tests with various scenarios
- **Claude Code CLI Support**: Added setup instructions and configuration examples for Claude Code CLI users
- **Enhanced Usage Examples**: Expanded documentation with practical examples for migration management and component inspection
- **AI Memory Bank Updates**: Complete documentation for inspect-components tool with usage examples

### Statistics

- **Tools**: 14 production-ready tools (up from 8 in v1.0.0)
  - 8 Gii code generators
  - 3 migration management tools
  - 3 analysis/inspection tools
- **Test Coverage**: 450+ automated tests with 60% code coverage
- **Lines of Code**: ~15,000 LOC (including tests and documentation)
- **Documentation**: 200+ pages of comprehensive guides for humans and AI agents

---

## Version 1.0.0

**Release Date**: January 2025

### Initial Public Release

First stable release of yii2-gii-mcp, an MCP server enabling AI assistants to interact with Yii2's Gii code generator through natural language conversations.

### Core Features

- **Database Inspection Tools**:
  - `list-tables`: List all database tables with basic schema information
  - `inspect-database`: Detailed schema inspection with columns, relationships, indexes, and constraints

- **Code Generation Tools** (8 Gii Generators):
  - `generate-model`: Create ActiveRecord models from database tables with relations
  - `generate-crud`: Generate complete CRUD operations (controllers, views, search models)
  - `generate-controller`: Create custom controllers with specific actions
  - `generate-form`: Generate form models for data collection and validation
  - `generate-module`: Create complete Yii2 modules with directory structure
  - `generate-extension`: Generate extension boilerplate with Composer packaging

### Architecture & Integration

- **Full MCP Support**: Complete JSON-RPC 2.0 over stdio implementation
- **Preview-First Workflow**: All generators default to preview mode for safety
- **Multi-Client Support**: Works with Firebender, Claude Desktop, Cline, and other MCP clients
- **Template Detection**: Automatic detection of Yii2 Basic/Advanced/Advanced+API template structures
- **Path Aliases**: Proper configuration for advanced template path aliases (@backend, @frontend, @common, @console, @api)

### Developer Experience

- **Interactive Setup Wizard**: Command-line tool (`interactive-setup`) for automated configuration
- **Diagnostic Tool**: Comprehensive testing utility (`diagnose`) for troubleshooting
- **Example Configurations**: Pre-configured templates for different Yii2 project structures
- **Docker Support**: Full documentation and configuration examples for Docker Desktop environments

### Documentation

- **AI Memory Bank**: Complete technical reference for AI agents (docs/AI-MEMORY-BANK.md)
- **Setup Guides**: Step-by-step instructions for all supported MCP clients
- **Usage Examples**: Practical examples demonstrating real-world workflows
- **Docker Guide**: Dedicated documentation for Docker-specific setup (docs/DOCKER.md)

### Testing

- **Comprehensive Test Suite**: 404+ automated tests using Codeception framework
- **Test Coverage**: 60% code coverage with unit and functional tests
- **CI/CD**: GitHub Actions workflow for automated testing

### Requirements

- PHP 8.2 or higher
- Composer
- Yii2 framework (exclusive support for Yii2, not compatible with Yii1 or Yii3)
- Yii2 Gii module

### License

Released under MIT License
