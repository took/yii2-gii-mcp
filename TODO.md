# TODO - yii2-gii-mcp

This TODO list is structured for AI agents to pick up tasks and execute them automatically.
Each task is actionable, includes specific file paths and implementation details.

**Project Goal**: Implement a Model Context Protocol (Anthropic) server in PHP that enables AI agents to interact with
Yii2 Gii for automated scaffolding and code generation.

**Protocol**: MCP (Model Context Protocol) using JSON-RPC 2.0 over stdio transport.

---

## Recent Updates (January 2025)

**Latest Session (January 2):**

- ✅ Added comprehensive Docker usage documentation (docs/DOCKER.md)
- ✅ Created Docker example configurations (examples/docker/)
- ✅ Added wrapper script for container-based MCP setup
- ✅ Updated README.md with Docker setup section
- ✅ Updated AI-MEMORY-BANK.md with Docker considerations
- ✅ Documented both host-based and container-based Docker approaches

**Previous Session (Phase 4 Implementation):**

- ✅ Extended GiiHelper with 8 new generator methods (controller, form, module, extension)
- ✅ Created GenerateController tool (full implementation)
- ✅ Created GenerateForm tool (full implementation)
- ✅ Created GenerateModule tool (full production implementation)
- ✅ Created GenerateExtension tool (full production implementation)
- ✅ Created InspectDatabase tool (full implementation for detailed schema inspection)
- ✅ Created comprehensive test suite for all tools (50+ tests with TDD approach)
- ✅ Updated examples/run.php and bin/yii2-gii-mcp with all new tools
- ✅ Updated README.md with full documentation for all 8 tools
- ✅ Enhanced GenerateModule and GenerateExtension from stubs to production implementations
- ✅ Updated TODO.md to reflect completion status

**Tool Summary:**

- **8 production-ready tools**: list-tables, inspect-database, generate-model, generate-crud, generate-controller,
  generate-form, generate-module, generate-extension
- All tools feature: preview mode, detailed output, file grouping, error handling, and input validation
- All follow preview-first safety pattern

---

## Phase 1: Core MCP Server Infrastructure (PRIORITY)

### 1.1 MCP Protocol Implementation

- [x] **Implement MCPServer.php - Core Protocol Handler** ✅
    - File: `src/MCPServer.php`
    - Implement JSON-RPC 2.0 message handling over stdio (read from stdin, write to stdout)
    - Handle MCP standard methods:
        - `initialize`: Server initialization and capability negotiation
        - `tools/list`: Return list of available tools with schemas
        - `tools/call`: Execute a specific tool with provided arguments
    - Add proper error handling following JSON-RPC 2.0 spec (error codes, messages)
    - Support server capabilities in initialize response
    - Add logging to stderr for debugging (not stdout, to avoid protocol interference)
    - Dependencies: None

- [x] **Add MCP Message Classes** ✅
    - File: `src/Protocol/Message.php` - Base message class
    - File: `src/Protocol/Request.php` - Request message with id, method, params
    - File: `src/Protocol/Response.php` - Success response with id, result
    - File: `src/Protocol/ErrorResponse.php` - Error response with code, message
    - Follow JSON-RPC 2.0 specification exactly
    - Dependencies: None

- [x] **Add Input/Output Handlers** ✅
    - File: `src/Protocol/StdioTransport.php`
    - Read JSON-RPC messages from stdin (handle line-by-line or length-prefixed)
    - Write JSON-RPC responses to stdout
    - Handle stream errors gracefully
    - Dependencies: None

### 1.2 Tool Registry System

- [x] **Create ToolRegistry.php** ✅
    - File: `src/ToolRegistry.php`
    - Dynamically register and discover tools
    - Store tools in array with name as key
    - Methods:
        - `register(ToolInterface $tool): void`
        - `get(string $name): ?ToolInterface`
        - `list(): array` - Return all registered tools with metadata
    - Validate tool names are unique
    - Dependencies: ToolInterface

- [x] **Create ToolInterface** ✅
    - File: `src/Tools/ToolInterface.php`
    - Define interface methods:
        - `getName(): string` - Unique tool identifier
        - `getDescription(): string` - Human-readable description
        - `getInputSchema(): array` - JSON Schema for tool inputs
        - `execute(array $arguments): array` - Execute tool, return results
    - Dependencies: None

- [x] **Create AbstractTool Base Class** ✅
    - File: `src/Tools/AbstractTool.php`
    - Implement ToolInterface with common functionality
    - Add input validation using JSON Schema
    - Add error handling helpers
    - Add result formatting helpers
    - Dependencies: ToolInterface

### 1.3 Configuration & Bootstrap

- [x] **Add Server Configuration** ✅
    - File: `src/Config/ServerConfig.php`
    - Load configuration from environment variables or config file
    - Store Yii2 application path, Gii module settings
    - Provide getters for all config values
    - Dependencies: None

- [x] **Add Composer Dependencies** ✅
    - Update `composer.json` to require:
        - `justinrainbow/json-schema`: "^5.2" (JSON Schema validator)
        - Added `suggest` section for `yiisoft/yii2` and `yiisoft/yii2-gii`
        - Dependencies provided by parent project or dev installation
    - Dependencies: None

---

## Phase 2: Yii2 Gii Integration

### 2.1 Yii2 Bootstrap

- [x] **Create Yii2Bootstrap.php** ✅
    - File: `src/Helpers/Yii2Bootstrap.php`
    - Initialize Yii2 application context programmatically
    - Load Yii2 configuration from provided path
    - Initialize Gii module with appropriate settings
    - Establish database connection
    - Methods:
        - `initialize(string $configPath): void`
        - `getDb(): \yii\db\Connection`
        - `getGiiModule(): \yii\gii\Module`
    - Dependencies: ServerConfig

### 2.2 Gii Helper

- [x] **Create GiiHelper.php** ✅
    - File: `src/Helpers/GiiHelper.php`
    - Wrapper functions for Gii generators
    - Methods:
        - `previewModel(string $tableName, array $options): array` - Preview model code
        - `generateModel(string $tableName, array $options): array` - Generate model files
        - `previewCrud(string $modelClass, array $options): array` - Preview CRUD code
        - `generateCrud(string $modelClass, array $options): array` - Generate CRUD files
        - `validateFiles(array $files): array` - Check for file conflicts
    - Return structured arrays with file paths and contents
    - Dependencies: Yii2Bootstrap

### 2.3 Core Tools Implementation

- [x] **Create ListTables Tool** ✅
    - File: `src/Tools/ListTables.php`
    - Implement ToolInterface via AbstractTool
    - List all database tables (read-only operation)
    - Return metadata: table name, columns with types, primary keys, foreign keys
    - Input schema: Optional database connection name
    - Output: Array of table metadata
    - Mark as safe, read-only tool
    - Dependencies: AbstractTool, Yii2Bootstrap

- [x] **Create GenerateModel Tool** ✅
    - File: `src/Tools/GenerateModel.php`
    - Implement ToolInterface via AbstractTool
    - Generate Yii2 ActiveRecord model from database table
    - Input schema:
        - `tableName` (required): Database table name
        - `modelClass` (optional): Full class name for model
        - `namespace` (optional): Namespace for model
        - `preview` (default: true): Preview mode (no file writes)
    - Output: Array with file paths and generated code
    - Use GiiHelper for preview/generate operations
    - Validate table exists before generation
    - Dependencies: AbstractTool, GiiHelper

- [x] **Create GenerateCrud Tool** ✅
    - File: `src/Tools/GenerateCrud.php`
    - Implement ToolInterface via AbstractTool
    - Generate CRUD scaffolding (controller + views)
    - Input schema:
        - `modelClass` (required): Full model class name
        - `controllerClass` (optional): Controller class name
        - `viewPath` (optional): Path for views
        - `preview` (default: true): Preview mode
    - Output: Array with file paths and generated code
    - Use GiiHelper for preview/generate operations
    - Validate model class exists
    - Dependencies: AbstractTool, GiiHelper

### 2.4 Safety Features

- [x] **Add File Conflict Detection** ✅
    - File: `src/Helpers/FileHelper.php`
    - Check if files already exist before generation
    - Methods:
        - `checkConflicts(array $files): array` - Return conflicting files
        - `canWrite(string $path): bool` - Check if path is writable
    - Prevent accidental overwrites
    - Dependencies: None

- [x] **Add Input Validation** ✅
    - File: `src/Helpers/ValidationHelper.php`
    - Validate table names, class names, namespaces
    - Prevent SQL injection in table names
    - Validate paths are within project boundaries
    - Methods:
        - `validateTableName(string $name): bool`
        - `validateClassName(string $name): bool`
        - `validatePath(string $path, string $basePath): bool`
    - Dependencies: None

---

## Phase 3: Testing & Examples

### 3.1 Unit Tests

- [x] **Test Infrastructure Setup** ✅
    - Added PHPUnit 11.0 and configured testing framework
    - Added Codeception 5.0 configuration
    - Created Makefile with test targets (test, coverage, clean)
    - Added phpunit.xml and codeception.yml configuration
    - Updated .gitignore for test artifacts
    - Dependencies: PHPUnit, Codeception

- [x] **Test Protocol Classes** ✅
    - File: `tests/Unit/Protocol/ErrorResponseTest.php`
    - Test error response creation and serialization
    - Test standard error methods (parseError, methodNotFound, etc.)
    - Test JSON-RPC error codes
    - 11 test cases covering ErrorResponse
    - Dependencies: Protocol classes

- [x] **Test Tool Registry** ✅
    - File: `tests/Unit/ToolRegistryTest.php`
    - Test tool registration and retrieval
    - Test duplicate tool name handling
    - Test list all tools
    - 9 test cases with mock tools
    - Dependencies: ToolRegistry, mock tools

- [x] **Test AbstractTool** ✅
    - File: `tests/Unit/Tools/AbstractToolTest.php`
    - Test input validation with JSON Schema
    - Test helper methods (createResult, createError, formatTable)
    - Test parameter extraction (required/optional)
    - 9 test cases with MockTool
    - Dependencies: AbstractTool

- [x] **Test MCP Protocol (Functional)** ✅
    - File: `tests/Functional/MCPProtocolCest.php`
    - File: `tests/Functional/StdioTransportCest.php`
    - Test initialize method, tools/list, tools/call with mock tools
    - Test error handling (method not found, invalid JSON)
    - Test stdio communication with memory streams
    - 13 functional tests, 48 assertions
    - No Yii2 dependency
    - Dependencies: Codeception, MockSimpleTool

- [ ] **Test MCP Server (Unit)** (Future)
    - File: `tests/Unit/MCPServerTest.php`
    - Additional unit tests for server internals
    - Test message routing and error handling
    - Dependencies: MCPServer, mocks

- [ ] **Test Individual Tools** (Future)
    - File: `tests/Unit/Tools/ListTablesTest.php`
    - File: `tests/Unit/Tools/GenerateModelTest.php`
    - File: `tests/Unit/Tools/GenerateCrudTest.php`
    - Mock Yii2 database connections and Gii generators
    - Test input validation and output format
    - Dependencies: Tool classes, Yii2 mocks

### 3.2 Integration Example

- [x] **Create Working Example Script** ✅
    - File: `examples/run.php`
    - Complete workflow demonstration with all 3 tools
    - Includes sample input/output
    - Comments explaining each step
    - Demo mode with example JSON-RPC messages
    - Dependencies: All core classes

- [x] **Add Example Configuration** ✅
    - File: `examples/config.php`
    - Example Yii2 configuration for testing
    - Database connection settings with placeholders
    - Gii module configuration
    - Dependencies: None

- [x] **Create Executable Bin Script** ✅
    - File: `bin/yii2-gii-mcp`
    - CLI executable for easy server startup
    - Demo mode support (--demo flag)
    - Debug logging support (DEBUG env var)
    - Error handling and helpful messages
    - Dependencies: All core classes

### 3.3 Documentation

- [x] **Add MIT License** ✅
    - File: `LICENSE`
    - Add complete MIT license text
    - Include copyright year and author name
    - Dependencies: None

- [x] **Enhance README.md** ✅
    - Add MCP client setup instructions
    - Document how to connect MCP clients (Claude Desktop, other AI tools)
    - Add architecture diagram: MCP Client ↔ MCP Server ↔ Yii2/Gii
    - Document each tool with input/output examples
    - Add installation and configuration steps
    - Include troubleshooting section
    - Dependencies: Working implementation

- [x] **Add CONTRIBUTING.md** ✅
    - File: `CONTRIBUTING.md`
    - PSR-12 coding standards
    - How to add new tools
    - Testing requirements
    - Pull request process
    - Dependencies: None

- [x] **Add Docker Usage Documentation** ✅
    - File: `docs/DOCKER.md`
    - Complete guide for Docker Desktop users
    - Two setup options: host-based (recommended) and container-based
    - Configuration examples for Firebender and Claude Desktop
    - Database connection patterns for Docker environments
    - Troubleshooting Docker-specific issues
    - Example configurations: `examples/docker/`
    - Wrapper script for container-based setup
    - Updated README.md with Docker section
    - Updated AI-MEMORY-BANK.md with Docker considerations
    - Dependencies: None

---

## Phase 4: Advanced Features (FUTURE)

### 4.1 Additional Read-Only Tools

- [x] **Database Schema Inspection Tool** ✅
    - File: `src/Tools/InspectDatabase.php`
    - Query tables, columns, indexes, foreign keys, constraints
    - Return structured JSON schema information
    - Read-only operation
    - Dependencies: AbstractTool, Yii2Bootstrap

- [ ] **Model Inspection Tool** (Future)
    - File: `src/Tools/InspectModel.php`
    - Read existing model files
    - Extract attributes, validation rules, relations
    - Return structured model metadata
    - Read-only operation
    - Dependencies: AbstractTool, PHP reflection

### 4.2 Advanced Features

- [ ] **Custom Template Support**
    - Allow AI agents to specify custom Gii templates
    - Configure output paths and file naming conventions
    - File: `src/Helpers/TemplateHelper.php`

- [ ] **Preview Approval Workflow**
    - Interactive mode where preview is shown and user approves before generation
    - Add approval flag to tool responses
    - Require explicit approval for file writes

- [ ] **Database Diagram Generation**
    - Generate ER diagrams from database structure
    - Export as DOT, PlantUML, or Mermaid format
    - File: `src/Tools/GenerateDiagram.php`

### 4.3 CI/CD Pipeline

- [x] **Add GitHub Actions Workflow** ✅
    - File: `.github/workflows/test.yml`
    - Run tests on push/PR
    - Validate PHP syntax (PHP 8.2+)
    - Check PSR-12 coding standards with PHP-CS-Fixer
    - Generate code coverage reports

- [ ] **Add Code Quality Tools**
    - PHP-CS-Fixer for PSR-12 compliance
    - PHPStan for static analysis
    - Add configuration files

---

## Implementation Order (Recommended)

1. `src/Tools/ToolInterface.php` - Define tool contract
2. `src/Tools/AbstractTool.php` - Base tool implementation
3. `src/ToolRegistry.php` - Tool management
4. `src/Protocol/Message.php` + related classes - MCP messages
5. `src/Protocol/StdioTransport.php` - I/O handling
6. `src/MCPServer.php` - Main server with protocol handling
7. `src/Config/ServerConfig.php` - Configuration management
8. `src/Helpers/Yii2Bootstrap.php` - Initialize Yii2 context
9. `src/Tools/ListTables.php` - First working tool (read-only, safe)
10. Test with `examples/run.php` against real Yii2 project
11. `src/Helpers/GiiHelper.php` - Gii integration wrapper
12. `src/Tools/GenerateModel.php` - Model generation
13. `src/Tools/GenerateCrud.php` - CRUD generation
14. `src/Helpers/FileHelper.php` + `ValidationHelper.php` - Safety features
15. Tests for all components
16. Documentation updates (README, CONTRIBUTING, LICENSE)

---

## Phase 5: Project Structure Detection & Migration Tools (FUTURE)

### 5.1 Application Structure Detection

- [ ] **Create DetectApplicationStructure Tool**
    - File: `src/Tools/DetectApplicationStructure.php`
    - Auto-detect Yii2 project template type (Basic/Advanced/Advanced+API)
    - Identify available applications/entry points:
        - **Advanced Template**: frontend, backend, console, common
        - **Advanced+API**: Also detect api, backoffice (alternative to backend)
        - **Basic Template**: web application
    - Scan for modules within each application
    - Detect environment configurations (dev/prod/staging from environments/ directory)
    - Input schema: Optional base path (defaults to project root)
    - Output: Structured JSON with template type, applications, modules, environments
    - Read-only operation
    - Methods in helper:
        - `detectTemplateType(string $basePath): string` - Returns 'basic', 'advanced', or 'advanced-api'
        - `detectApplications(string $basePath, string $templateType): array`
        - `scanModules(string $appPath): array`
        - `detectEnvironments(string $basePath): array`
    - Dependencies: AbstractTool, filesystem scanning

- [ ] **Create ProjectStructure Helper**
    - File: `src/Helpers/ProjectStructureHelper.php`
    - Utility methods for filesystem scanning and structure analysis
    - Methods:
        - `findApplicationDirs(string $basePath): array` - Find app directories
        - `isYii2Application(string $path): bool` - Verify valid Yii2 app structure
        - `getApplicationType(string $path): string` - Determine app type (web/console/api)
        - `findModules(string $appPath): array` - Scan for modules in app
        - `getModuleConfig(string $modulePath): array` - Parse module configuration
    - Dependencies: None

### 5.2 Component Inspection

- [ ] **Create InspectComponents Tool**
    - File: `src/Tools/InspectComponents.php`
    - List and analyze components for specified application/module
    - Discover:
        - **Controllers**: List all controllers with actions, filters, behaviors
        - **Models**: List ActiveRecord models, form models with attributes, rules, relations
        - **Views**: List view files organized by controller
        - **RBAC Items**: Roles, permissions, rules (if RBAC is configured)
    - Input schema:
        - `application` (optional): Application name (frontend/backend/console/api)
        - `module` (optional): Module name within application
        - `componentType` (optional): Filter by type (controllers/models/views/all)
        - `includeDetails` (default: true): Include detailed metadata
    - Output: Structured JSON with component listings and metadata
    - Read-only operation
    - Dependencies: AbstractTool, PHP reflection, ComponentAnalyzer helper

- [ ] **Create ComponentAnalyzer Helper**
    - File: `src/Helpers/ComponentAnalyzer.php`
    - Analyze PHP class files to extract metadata
    - Methods:
        - `analyzeController(string $filePath): array` - Extract actions, filters, behaviors
        - `analyzeModel(string $filePath): array` - Extract attributes, rules, relations, scenarios
        - `analyzeModelRelations(string $modelClass): array` - Get related models
        - `extractBehaviors(ReflectionClass $class): array` - Parse behaviors() method
        - `extractValidationRules(ReflectionClass $class): array` - Parse rules() method
        - `extractActions(ReflectionClass $class): array` - Find action methods
    - Use PHP reflection and AST parsing
    - Dependencies: PHP reflection, nikic/php-parser (optional)

- [ ] **Create RbacInspector Helper**
    - File: `src/Helpers/RbacInspector.php`
    - Inspect RBAC (Role-Based Access Control) configuration
    - Methods:
        - `getRoles(): array` - List all defined roles
        - `getPermissions(): array` - List all permissions
        - `getRules(): array` - List all RBAC rules
        - `getRoleAssignments(string $role): array` - Get role children/permissions
        - `getModelRbacItems(string $modelClass): array` - Find RBAC items for model
    - Support both DbManager and PhpManager
    - Dependencies: Yii2 RBAC component

### 5.3 Migration Management Tools

- [ ] **Create ListMigrations Tool**
    - File: `src/Tools/ListMigrations.php`
    - List available migrations with status
    - Show:
        - Applied migrations (with timestamp)
        - Pending migrations (not yet applied)
        - Migration history
    - Input schema:
        - `status` (optional): Filter by 'applied', 'pending', or 'all'
        - `limit` (optional): Limit number of results
    - Output: Array of migrations with name, status, applied_time
    - Read-only operation
    - Dependencies: AbstractTool, Yii2 migration component

- [ ] **Create PreviewMigration Tool**
    - File: `src/Tools/PreviewMigration.php`
    - Preview SQL that would be executed by migration
    - Show up() and down() SQL without executing
    - Input schema:
        - `migrationName` (required): Name of migration to preview
        - `direction` (default: 'up'): Direction 'up' or 'down'
    - Output: SQL statements that would be executed
    - Read-only operation
    - Use Yii2's migration class with dry-run approach
    - Dependencies: AbstractTool, Yii2 migration component

- [ ] **Create ExecuteMigration Tool**
    - File: `src/Tools/ExecuteMigration.php`
    - Execute migration operations with mandatory human confirmation
    - Supported operations:
        - `up`: Apply pending migrations
        - `down`: Revert migrations
        - `create`: Create new migration file
        - `fresh`: Drop all tables and re-apply all migrations
        - `redo`: Revert and re-apply recent migration
    - **CRITICAL SAFETY FEATURES**:
        - ALWAYS require `confirmation` parameter with exact value "yes"
        - For structure-changing operations (down/fresh), require additional `destructiveConfirmation` with value "I understand this will modify the database"
        - Show preview of changes before execution
        - Log all operations to stderr
        - Return detailed execution results with affected tables
    - Input schema:
        - `operation` (required): One of 'up', 'down', 'create', 'fresh', 'redo'
        - `migrationName` (optional): Specific migration (for down/redo)
        - `migrationCount` (optional): Number of migrations (for up/down)
        - `confirmation` (required): Must be exact string "yes"
        - `destructiveConfirmation` (required for down/fresh): Must be exact string "I understand this will modify the database"
        - `preview` (default: true): Show preview first
    - Output: Execution results with applied migrations and SQL executed
    - **WARNING**: Mark as potentially destructive operation
    - Dependencies: AbstractTool, Yii2 migration component, MigrationHelper

- [ ] **Create MigrationHelper**
    - File: `src/Helpers/MigrationHelper.php`
    - Wrapper for Yii2 migration operations
    - Methods:
        - `getMigrations(string $status = 'all'): array` - Get migrations by status
        - `getMigrationHistory(int $limit = 10): array` - Get migration history
        - `previewMigrationSql(string $name, string $direction): string` - Get SQL preview
        - `executeMigration(string $operation, array $params): array` - Execute migration
        - `createMigration(string $name, array $fields = []): string` - Create migration file
        - `validateMigrationName(string $name): bool` - Validate migration exists
    - Add comprehensive error handling
    - Dependencies: Yii2 migration component

### 5.4 Server Environment Detection

- [ ] **Create DetectServerEnvironment Tool**
    - File: `src/Tools/DetectServerEnvironment.php`
    - Auto-detect server environment and configuration
    - Detect:
        - **Environment Type**: LAMP, WAMP, MAMP, LEMP, Docker, other
        - **Web Server**: Apache, Nginx, version
        - **PHP Version**: Current PHP version and available extensions
        - **PHP Extensions**: List installed PHP modules
        - **Database**: Type (MySQL/PostgreSQL), version, connection status
        - **Docker**: Detect if running in Docker container
    - Input schema: None (auto-detect)
    - Output: Structured environment information
    - Read-only operation
    - Methods in helper:
        - `detectWebServer(): array` - Identify web server type/version
        - `detectPhpEnvironment(): array` - PHP version, SAPI, extensions
        - `detectDatabaseEnvironment(): array` - Database type, version
        - `isDockerEnvironment(): bool` - Check if running in Docker
        - `getRequiredPhpExtensions(): array` - List Yii2 required extensions
        - `getMissingExtensions(): array` - Extensions needed but not installed
    - Dependencies: AbstractTool, PHP functions (phpinfo, get_loaded_extensions)

- [ ] **Create GenerateServerConfig Tool**
    - File: `src/Tools/GenerateServerConfig.php`
    - Generate server configuration files for different environments
    - Generate configs for:
        - **Apache2**: Virtual host config with mod_rewrite for pretty URLs
        - **Nginx**: Server block config with URL rewriting
        - **Docker**: Dockerfile and docker-compose.yml for development
        - **Docker CI/CD**: Optimized config for testing pipelines
    - Include:
        - Required PHP extensions installation commands (apt/yum)
        - Apache module enablement (a2enmod commands)
        - Nginx configuration equivalents
        - Document root setup
        - URL rewriting rules for Yii2
        - PHP-FPM configuration (for Nginx)
        - Environment-specific settings (dev/prod)
    - Input schema:
        - `configType` (required): One of 'apache', 'nginx', 'docker-dev', 'docker-ci'
        - `serverName` (optional): Domain/hostname for virtual host
        - `documentRoot` (optional): Path to web root
        - `phpVersion` (optional): Target PHP version (default: current)
        - `environment` (optional): 'dev' or 'prod' (affects settings)
    - Output: Configuration file content with inline comments
    - Read-only operation (generates content, doesn't write files)
    - Dependencies: AbstractTool, ServerConfigHelper

- [ ] **Create ServerConfigHelper**
    - File: `src/Helpers/ServerConfigHelper.php`
    - Template generation for server configurations
    - Methods:
        - `generateApacheConfig(array $params): string` - Apache virtual host
        - `generateNginxConfig(array $params): string` - Nginx server block
        - `generateDockerfile(array $params): string` - Docker image definition
        - `generateDockerCompose(array $params): string` - Docker compose file
        - `getPhpExtensionsCommands(string $phpVersion, string $os): array` - Installation commands
        - `getApacheModules(): array` - Required Apache modules
        - `getNginxModules(): array` - Required Nginx modules
    - Include best practices for Yii2 deployment
    - Dependencies: None

### 5.5 Documentation Updates

- [ ] **Update README.md - Clarify Yii2 Scope**
    - File: `README.md`
    - Add prominent notice at the top of "Requirements" section:
        - **Framework Support**: This MCP server is designed exclusively for **Yii2 framework projects**
        - Yii1 is not supported (use Yii2 migration tools to upgrade)
        - Yii3 is out of scope (different architecture)
    - Update "Key Features" to mention "Yii2 projects"
    - Dependencies: None

- [ ] **Update AI-MEMORY-BANK.md**
    - File: `docs/AI-MEMORY-BANK.md`
    - Add "Framework Scope" section to project overview
    - Clarify: Yii2 only, no Yii1/Yii3 support
    - Document reasons: MCP server uses Yii2 Gii generators specifically
    - Dependencies: None

- [ ] **Add Framework Notice to MCPServer Initialization**
    - File: `src/MCPServer.php`
    - In `initialize()` method response, add server info:
        - Server name: "yii2-gii-mcp"
        - Description: "MCP server for Yii2 framework projects (Yii2 only)"
        - Version: Current version
        - Framework support: "Yii2"
    - Add to server capabilities metadata
    - Dependencies: None

- [ ] **Create FAQ Documentation**
    - File: `docs/FAQ.md`
    - Add common questions:
        - "Does this work with Yii1?" → No, Yii2 only
        - "What about Yii3?" → Not yet, different architecture
        - "How to migrate from Yii1 to Yii2?" → Link to official Yii2 upgrade guide
        - "Which Yii2 templates are supported?" → Basic, Advanced, Advanced+API
    - Dependencies: None

---

## Notes for AI Agents

- **Preview by default**: All generation tools should default to preview mode (no file writes)
- **Validation first**: Always validate inputs before calling Gii generators
- **Error handling**: Return structured errors following JSON-RPC 2.0 specification
- **Logging**: Log to stderr, never to stdout (stdout is reserved for MCP protocol messages)
- **Testing**: Test against real Yii2 project available via composer path repository
- **PSR-12**: Follow PSR-12 coding standards for all PHP code
- **Dependencies**: Minimize external dependencies, leverage Yii2 and Gii built-in functionality
- **Framework**: This MCP server is exclusively for Yii2 projects (no Yii1, no Yii3)
