<?php

namespace Took\Yii2GiiMCP\Helpers;

use RuntimeException;
use Throwable;
use Took\Yii2GiiMCP\Config\ServerConfig;
use yii\base\Application;
use yii\db\Connection;
use yii\gii\Module as GiiModule;

/**
 * Yii2 Bootstrap Helper
 *
 * Initializes Yii2 application context programmatically for MCP server.
 * Uses singleton pattern to ensure single initialization.
 */
class Yii2Bootstrap
{
    private static ?self $instance = null;
    private ?Application $app = null;
    private ServerConfig $config;
    private bool $initialized = false;
    private ?string $templateType = null;

    /**
     * Private constructor for singleton pattern
     *
     * @param ServerConfig $config Server configuration
     */
    private function __construct(ServerConfig $config)
    {
        $this->config = $config;
    }

    /**
     * Get singleton instance
     *
     * @param ServerConfig|null $config Server configuration (required on first call)
     * @return self
     * @throws RuntimeException If config not provided on first call
     */
    public static function getInstance(?ServerConfig $config = null): self
    {
        if (self::$instance === null) {
            if ($config === null) {
                throw new RuntimeException('ServerConfig required on first getInstance() call');
            }
            self::$instance = new self($config);
        }

        return self::$instance;
    }

    /**
     * Reset singleton instance (for testing)
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * Initialize Yii2 application
     *
     * Loads Yii2, creates application instance, and initializes Gii module.
     *
     * @throws RuntimeException If initialization fails
     */
    public function initialize(): void
    {
        if ($this->initialized) {
            return; // Already initialized
        }

        // Validate configuration
        $errors = $this->config->validate();
        if (! empty($errors)) {
            throw new RuntimeException(
                'Configuration validation failed: ' . implode(', ', $errors)
            );
        }

        // Load Yii2 framework
        $this->loadYii2();

        // Load application configuration
        $appConfig = $this->loadAppConfig();

        // Ensure Gii module is configured
        if ($this->config->isGiiEnabled()) {
            $appConfig = $this->ensureGiiModule($appConfig);
        }

        // Create application instance
        $this->createApplication($appConfig);

        $this->initialized = true;
    }

    /**
     * Load Yii2 framework files
     *
     * @throws RuntimeException If Yii2 cannot be loaded
     */
    private function loadYii2(): void
    {
        // Check if Yii class is already available
        if (class_exists('\Yii', false)) {
            return; // Already loaded
        }

        $appPath = $this->config->getYii2AppPath();

        // Try common Yii2 paths
        $possiblePaths = [
            $appPath . '/vendor/yiisoft/yii2/Yii.php',
            $appPath . '/../vendor/yiisoft/yii2/Yii.php',
            __DIR__ . '/../../vendor/yiisoft/yii2/Yii.php',
            __DIR__ . '/../../../yiisoft/yii2/Yii.php',
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                require_once $path;

                return;
            }
        }

        throw new RuntimeException(
            'Could not load Yii2. Make sure yiisoft/yii2 is installed. ' .
            'Searched paths: ' . implode(', ', $possiblePaths)
        );
    }

    /**
     * Load application configuration from file
     *
     * @return array Application configuration
     * @throws RuntimeException If config cannot be loaded
     */
    private function loadAppConfig(): array
    {
        $configPath = $this->config->getYii2ConfigPath();

        if (! file_exists($configPath)) {
            throw new RuntimeException("Configuration file not found: {$configPath}");
        }

        $config = require $configPath;

        if (! is_array($config)) {
            throw new RuntimeException("Configuration file must return an array: {$configPath}");
        }

        return $config;
    }

    /**
     * Ensure Gii module is configured in application config
     *
     * @param array $config Application configuration
     * @return array Modified configuration
     */
    private function ensureGiiModule(array $config): array
    {
        // Check if Gii is already configured
        if (isset($config['modules']['gii'])) {
            return $config; // Already configured
        }

        // Add Gii module configuration
        $config['modules']['gii'] = [
            'class' => 'yii\gii\Module',
            'allowedIPs' => ['*'], // Allow all IPs for MCP server
        ];

        // Also add Gii to bootstrap if not present
        if (! isset($config['bootstrap'])) {
            $config['bootstrap'] = [];
        }

        if (! in_array('gii', $config['bootstrap'], true)) {
            $config['bootstrap'][] = 'gii';
        }

        return $config;
    }

    /**
     * Create Yii2 application instance
     *
     * @param array $config Application configuration
     * @throws RuntimeException If application creation fails
     */
    private function createApplication(array $config): void
    {
        try {
            // Create web application (even though we're not using HTTP)
            // This is needed because Gii expects a web application context
            $this->app = new \yii\web\Application($config);
        } catch (Throwable $e) {
            throw new RuntimeException(
                'Failed to create Yii2 application: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Get database connection
     *
     * @param string|null $connectionId Connection component ID (default from config)
     * @return Connection Database connection
     * @throws RuntimeException If not initialized or connection not found
     */
    public function getDb(?string $connectionId = null): Connection
    {
        $this->ensureInitialized();

        $connectionId = $connectionId ?? $this->config->getDbConnection();

        $db = $this->app->get($connectionId, false);

        if ($db === null) {
            throw new RuntimeException("Database connection '{$connectionId}' not found");
        }

        if (! $db instanceof Connection) {
            throw new RuntimeException("Component '{$connectionId}' is not a database connection");
        }

        return $db;
    }

    /**
     * Ensure Yii2 is initialized
     *
     * @throws RuntimeException If not initialized
     */
    private function ensureInitialized(): void
    {
        if (! $this->initialized) {
            throw new RuntimeException('Yii2 not initialized. Call initialize() first.');
        }
    }

    /**
     * Get Gii module
     *
     * @return GiiModule Gii module instance
     * @throws RuntimeException If not initialized or Gii not available
     */
    public function getGiiModule(): GiiModule
    {
        $this->ensureInitialized();

        if (! $this->config->isGiiEnabled()) {
            throw new RuntimeException('Gii is disabled in configuration');
        }

        $gii = $this->app->getModule('gii');

        if ($gii === null) {
            throw new RuntimeException('Gii module not found in application');
        }

        if (! $gii instanceof GiiModule) {
            throw new RuntimeException('Gii module is not an instance of yii\gii\Module');
        }

        return $gii;
    }

    /**
     * Get Yii2 application instance
     *
     * @return Application Application instance
     * @throws RuntimeException If not initialized
     */
    public function getApp()
    {
        $this->ensureInitialized();

        return $this->app;
    }

    /**
     * Check if Yii2 is initialized
     *
     * @return bool
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * Detect Yii2 template type (Basic or Advanced)
     *
     * @return string 'basic' or 'advanced'
     */
    public function detectTemplateType(): string
    {
        // Return cached result if already detected
        if ($this->templateType !== null) {
            return $this->templateType;
        }

        $appPath = $this->config->getYii2AppPath();

        // Check for Advanced Template structure
        // Advanced Template has /common and /console directories
        if (is_dir($appPath . '/common') && is_dir($appPath . '/console')) {
            $this->templateType = 'advanced';

            return $this->templateType;
        }

        // Check for Basic Template structure
        // Basic Template has /app and /config directories
        if (is_dir($appPath . '/app') && is_dir($appPath . '/config')) {
            $this->templateType = 'basic';

            return $this->templateType;
        }

        // Default to basic if structure is unclear
        // This provides a safe fallback for non-standard structures
        $this->templateType = 'basic';

        return $this->templateType;
    }

    /**
     * Get default model namespace based on detected template type
     *
     * For Advanced Template: Returns 'common\models' (shared models)
     * For Basic Template: Returns 'app\models'
     *
     * @return string Default namespace for models
     */
    public function getDefaultModelNamespace(): string
    {
        $templateType = $this->detectTemplateType();

        return $templateType === 'advanced' ? 'common\\models' : 'app\\models';
    }

    /**
     * Get default controller namespace based on detected template type
     *
     * For Advanced Template: Returns 'frontend\controllers' (most common entry point)
     * For Basic Template: Returns 'app\controllers'
     *
     * @return string Default namespace for controllers
     */
    public function getDefaultControllerNamespace(): string
    {
        $templateType = $this->detectTemplateType();

        return $templateType === 'advanced' ? 'frontend\\controllers' : 'app\\controllers';
    }
}
