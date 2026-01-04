# TODO - yii2-gii-mcp

This TODO list is structured for AI agents to pick up tasks and execute them automatically.
Each task is actionable, includes specific file paths and implementation details.

**Project Goal**: Implement a Model Context Protocol (Anthropic) server in PHP that enables AI agents to interact with
Yii2 Gii for automated scaffolding and code generation.

**Protocol**: MCP (Model Context Protocol) using JSON-RPC 2.0 over stdio transport.

---


## Phase 1-3

Done

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

- [x] **Update README.md - Clarify Yii2 Scope**
    - File: `README.md`
    - Add prominent notice at the top of "Requirements" section:
        - **Framework Support**: This MCP server is designed exclusively for **Yii2 framework projects**
        - Yii1 is not supported (use Yii2 migration tools to upgrade)
        - Yii3 is out of scope (different architecture)
    - Update "Key Features" to mention "Yii2 projects"
    - Dependencies: None

- [x] **Update AI-MEMORY-BANK.md**
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
