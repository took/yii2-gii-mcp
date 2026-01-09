# TODO - yii2-gii-mcp

This TODO list is structured for AI agents to pick up tasks and execute them automatically.
Each task is actionable, includes specific file paths and implementation details.

**Project Goal**: Implement a Model Context Protocol (Anthropic) server in PHP that enables AI agents to interact with
Yii2 Gii for automated scaffolding and code generation.

**Protocol**: MCP (Model Context Protocol) using JSON-RPC 2.0 over stdio transport.

---

## Completed Work (Version 1.1.0 - Released January 10, 2026)

### Phase 1-3: Core Infrastructure ✅
- MCP server implementation with JSON-RPC 2.0 over stdio
- Yii2 bootstrap and environment setup
- Basic Gii integration with preview-first workflow
- Tool registry system and abstract tool base

### Phase 4-7: Code Generation & Migration Tools ✅
- **8 Gii Code Generation Tools**: 
  - `generate-model`: ActiveRecord models with relations
  - `generate-crud`: Complete CRUD operations
  - `generate-controller`: Custom controllers
  - `generate-form`: Form models
  - `generate-module`: Module scaffolding
  - `generate-extension`: Extension boilerplate
- **3 Migration Management Tools**: 
  - `list-migrations`: List with status
  - `create-migration`: Create with field definitions and advanced options (indexes, FKs, enums)
  - `execute-migration`: Execute with SQL preview and safety confirmations
- **2 Database Inspection Tools**: 
  - `list-tables`: List all tables with metadata
  - `inspect-database`: Detailed schema inspection
- Template auto-detection (Basic/Advanced/Advanced+API)
- Interactive setup wizard and diagnostic tools
- Comprehensive documentation and Docker support

### Phase 8-10: Analysis & Inspection Tools ✅
- **Project Structure Detection** (`detect-application-structure`):
  - Template type detection (Basic/Advanced/Advanced+API)
  - Application and module discovery
  - Environment analysis with init system support
  - Entry point detection and configuration parsing
  
- **Component Inspection** (`inspect-components`):
  - Controller analysis (actions, filters, behaviors, PHPDoc)
  - Model analysis (attributes, rules, relations, scenarios)
  - View discovery organized by controller
  - Deep code analysis using PHP Reflection + nikic/php-parser
  - Component type filtering and detail levels
  
- **Application Logging** (`read-logs`):
  - Multi-source support (FileTarget and DbTarget)
  - Multi-application log aggregation
  - Advanced filtering (level, category, time range, search)
  - Yii2 log format parsing with stack traces
  - Statistics and summary generation

### Summary
- **Total**: 14 production-ready tools
- **Test Coverage**: 450+ automated tests, 60% code coverage
- **Documentation**: Complete AI memory bank and user guides
- **Setup Tools**: Interactive wizard and diagnostic utilities
- **Safety Features**: Preview mode, confirmations, validation

---

## Future Development

### Debugging & Monitoring

#### Debugger Integration
- [ ] **Create DebuggerInspector Tool**
    - File: `src/Tools/InspectDebugger.php`
    - Integrate with Yii2 Debug module
    - Show:
        - Request/response data for recent requests
        - Database queries with execution time and traces
        - Logged messages (all severity levels)
        - Application routes
        - Performance profiling data
        - Events triggered during request
        - Asset bundles loaded
    - Input schema:
        - `requestId` (optional): Specific request to inspect
        - `limit` (default: 10): Number of recent requests
        - `category` (optional): Filter by 'db', 'log', 'routes', 'profile', 'all'
    - Output: Structured debug data
    - Read-only operation
    - Dependencies: yiisoft/yii2-debug module, AbstractTool
    - Note: Requires Debug module to be enabled in application config

### Routing & URL Management

- [ ] **Create InspectRoutes Tool**
    - File: `src/Tools/InspectRoutes.php`
    - Analyze application routes and URL rules
    - Features:
        - List all available routes from controllers
        - Show URL manager rules and patterns
        - Test route matching (URL → route)
        - Test URL creation (route → URL)
        - List pretty URL rules
        - Show route parameters and defaults
    - Input schema:
        - `application` (optional): Application name (frontend/backend/console)
        - `pattern` (optional): Filter routes by pattern
        - `testUrl` (optional): Test URL parsing
        - `testRoute` (optional): Test URL creation
    - Output: Routes list with controllers, actions, URL rules, test results
    - Read-only operation
    - Dependencies: AbstractTool, UrlManager, RouteAnalyzer helper

- [ ] **Create RouteAnalyzer Helper**
    - File: `src/Helpers/RouteAnalyzer.php`
    - Utility methods for route inspection
    - Methods:
        - `getAllRoutes(string $appPath): array` - Scan controllers for routes
        - `getUrlRules(): array` - Get configured URL rules
        - `parseUrl(string $url): array` - Parse URL to route
        - `createUrl(string $route, array $params): string` - Create URL from route
        - `getControllerActions(string $controllerPath): array` - Extract actions
    - Dependencies: Yii2 UrlManager

### Internationalization (i18n)

- [ ] **Create ExtractTranslations Tool**
    - File: `src/Tools/ExtractTranslations.php`
    - Extract translatable strings from source code
    - Features:
        - Scan PHP files for Yii::t() calls
        - Extract categories and messages
        - Show untranslated messages
        - Generate missing translation entries
        - Support multiple message sources (files, database)
    - Input schema:
        - `sourcePath` (required): Directory to scan
        - `category` (optional): Filter by translation category
        - `format` (default: 'php'): Output format (php/po)
        - `languages` (optional): Target languages
    - Output: Extracted messages with categories, source files, line numbers
    - Read-only operation (or with writeFiles flag)
    - Dependencies: AbstractTool, Yii2 i18n component

- [ ] **Create InspectTranslations Tool**
    - File: `src/Tools/InspectTranslations.php`
    - Inspect existing translations and coverage
    - Features:
        - List all translation categories
        - Show available languages
        - Find missing translations (exist in source but not in target language)
        - Find unused translations
        - Translation coverage statistics
        - Compare translations across languages
    - Input schema:
        - `category` (optional): Filter by category
        - `language` (optional): Specific language
        - `checkCoverage` (default: true): Calculate coverage stats
    - Output: Translation inventory with coverage analysis
    - Read-only operation
    - Dependencies: AbstractTool, Yii2 i18n component

- [ ] **Create TranslationHelper**
    - File: `src/Helpers/TranslationHelper.php`
    - Utility methods for translation management
    - Methods:
        - `scanForTranslations(string $path): array` - Find Yii::t() calls
        - `getTranslationCategories(): array` - List categories
        - `getLanguages(): array` - List configured languages
        - `getMessageSource(string $category): object` - Get message source config
        - `getMissingTranslations(string $category, string $language): array`
        - `compareTranslations(string $category, array $languages): array`
    - Dependencies: Yii2 i18n component, PHP token parser

### RBAC & Permissions Testing

- [ ] **Expand RbacInspector Helper** (from Phase 5.2)
    - File: `src/Helpers/RbacInspector.php`
    - Add permission testing methods:
        - `canUserAccess(int $userId, string $permission): bool` - Test user permission
        - `getUserRoles(int $userId): array` - Get user's assigned roles
        - `testPermission(string $roleName, string $permission): bool` - Test role permission
        - `simulateAccess(array $roleNames, string $route): array` - Simulate route access
        - `getRoleHierarchy(string $role): array` - Get role inheritance tree
    - Support both DbManager and PhpManager
    - Dependencies: Yii2 RBAC component

- [ ] **Create TestRbac Tool**
    - File: `src/Tools/TestRbac.php`
    - Interactive RBAC permission testing
    - Features:
        - Test if user can access specific routes/permissions
        - Simulate role assignments
        - Show permission inheritance chains
        - Test RBAC rules execution
        - Validate RBAC configuration
    - Input schema:
        - `userId` (optional): User ID to test
        - `roleName` (optional): Role to simulate
        - `permission` (required): Permission/route to test
        - `params` (optional): Additional rule parameters
    - Output: Access result with explanation, inheritance path, applicable rules
    - Read-only operation
    - Dependencies: AbstractTool, RbacInspector helper

### Component Inspection & Analysis

- [x] **Create InspectComponents Tool** ✅
    - File: `src/Tools/InspectComponents.php`
    - List and analyze components for specified application/module
    - Implemented features:
        - **Controllers**: List all controllers with actions, filters, behaviors
        - **Models**: List ActiveRecord models, form models with attributes, rules, relations
        - **Views**: List view files organized by controller
    - Input schema:
        - `application` (optional): Application name (frontend/backend/console/api)
        - `module` (optional): Module name within application
        - `componentType` (optional): Filter by type (controllers/models/views/all)
        - `includeDetails` (default: true): Include detailed metadata
    - Output: Structured JSON with component listings and metadata
    - Read-only operation
    - Dependencies: AbstractTool, PHP reflection, ComponentAnalyzer helper
    - Full test coverage with unit tests

- [x] **Create ComponentAnalyzer Helper** ✅
    - File: `src/Helpers/ComponentAnalyzer.php`
    - Analyze PHP class files to extract metadata
    - Implemented methods:
        - `analyzeController(string $filePath): array` - Extract actions, filters, behaviors
        - `analyzeModel(string $filePath): array` - Extract attributes, rules, relations, scenarios
        - `extractBehaviors(ReflectionClass $class): array` - Parse behaviors() method
        - `extractValidationRules(ReflectionClass $class): array` - Parse rules() method
        - `extractActions(ReflectionClass $class): array` - Find action methods
        - `extractRelations(ReflectionClass $class): array` - Find ActiveRecord relations
        - `parseMethodReturnValue(ReflectionMethod $method): mixed` - Use php-parser to extract return values
    - Uses PHP reflection and nikic/php-parser for AST parsing
    - Dependencies: PHP reflection, nikic/php-parser
    - Full test coverage with unit tests

- [ ] **Future Enhancement: Widget and Asset Analysis**
    - Extend ComponentAnalyzer with:
        - `analyzeWidget(string $filePath): array` - Extract widget properties and options
        - `analyzeAssetBundle(string $filePath): array` - Extract CSS/JS dependencies
    - Add widget/asset detection to InspectComponents tool

### Server Environment Tools

- [ ] **Create DetectServerEnvironment Tool**
    - File: `src/Tools/DetectServerEnvironment.php`
    - Auto-detect server environment and configuration
    - Detect: Environment type, web server, PHP version/extensions, database, Docker
    - Output: Structured environment information
    - Read-only operation
    - Dependencies: AbstractTool, PHP functions

- [ ] **Create GenerateServerConfig Tool**
    - File: `src/Tools/GenerateServerConfig.php`
    - Generate server configuration files (Apache, Nginx, Docker)
    - Include required PHP extensions, URL rewriting, best practices
    - Input: configType, serverName, documentRoot, phpVersion, environment
    - Output: Configuration file content with inline comments
    - Dependencies: AbstractTool, ServerConfigHelper

### Advanced Gii Features

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

### Guides & Documentation

- [ ] **Create Developer Guide/Cookbook**
    - File: `docs/DEVELOPER-GUIDE.md` or `docs/COOKBOOK.md`
    - Common workflows and recipes:
        - Setting up a new Yii2 project from scratch
        - Creating a complete module (models, CRUD, migrations)
        - Implementing RBAC from database design to access control
        - Setting up i18n and managing translations
        - Debugging common issues
        - Performance optimization patterns
        - Testing strategies for Yii2 applications
    - Step-by-step examples with MCP tool usage
    - Best practices and gotchas

- [ ] **Create FAQ Documentation**
    - File: `docs/FAQ.md`
    - Add common questions:
        - "Does this work with Yii1?" → No, Yii2 only
        - "What about Yii3?" → Not yet, different architecture
        - "How to migrate from Yii1 to Yii2?" → Link to official Yii2 upgrade guide
        - "Which Yii2 templates are supported?" → Basic, Advanced, Advanced+API
        - Troubleshooting common setup issues
        - MCP client compatibility questions

### Documentation Enhancements

- [ ] **Add Framework Notice to MCPServer Initialization**
    - File: `src/MCPServer.php`
    - In `initialize()` method response, add server info with framework support metadata
    - Add to server capabilities metadata

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
- **Read-only first**: Prefer read-only inspection tools before write operations
- **Safety confirmations**: All destructive operations require explicit user confirmation
