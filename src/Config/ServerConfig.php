<?php

namespace Took\Yii2GiiMCP\Config;

/**
 * Server Configuration Manager
 *
 * Loads configuration from environment variables with fallbacks.
 * Manages paths and settings for Yii2 integration.
 */
class ServerConfig
{
    private ?string $yii2ConfigPath = null;
    private ?string $yii2AppPath = null;
    private bool $giiEnabled = true;
    private string $dbConnection = 'db';
    private bool $debugMode = false;

    /**
     * Create server configuration from environment variables
     *
     * @param array|null $env Custom environment array (for testing), or null to use $_ENV
     */
    public function __construct(?array $env = null)
    {
        $env = $env ?? $_ENV;

        // Load configuration from environment (with fallback to getenv())
        $this->yii2ConfigPath = $env['YII2_CONFIG_PATH'] ?? getenv('YII2_CONFIG_PATH') ?: null;
        $this->yii2AppPath = $env['YII2_APP_PATH'] ?? getenv('YII2_APP_PATH') ?: null;
        $this->giiEnabled = $this->parseBool($env['GII_ENABLED'] ?? getenv('GII_ENABLED') ?: 'true');
        $this->dbConnection = $env['DB_CONNECTION'] ?? getenv('DB_CONNECTION') ?: 'db';
        $this->debugMode = $this->parseBool($env['DEBUG'] ?? getenv('DEBUG') ?: 'false');

        // Fallback: If config path not set, try to detect from app path
        if ($this->yii2ConfigPath === null && $this->yii2AppPath !== null) {
            $this->yii2ConfigPath = $this->yii2AppPath . '/config/web.php';
        }

        // Fallback: If app path not set but config path is, try to infer it
        if ($this->yii2AppPath === null && $this->yii2ConfigPath !== null) {
            // Assume config is in <app>/config/web.php pattern
            $dir = dirname($this->yii2ConfigPath);
            if (basename($dir) === 'config') {
                $this->yii2AppPath = dirname($dir);
            }
        }
    }

    /**
     * Parse boolean value from string
     *
     * @param string|bool $value Value to parse
     * @return bool
     */
    private function parseBool(string|bool $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Get path to Yii2 configuration file
     *
     * @return string|null Configuration file path
     */
    public function getYii2ConfigPath(): ?string
    {
        return $this->yii2ConfigPath;
    }

    /**
     * Set Yii2 configuration path
     *
     * @param string $path Path to config file
     * @return self
     */
    public function setYii2ConfigPath(string $path): self
    {
        $this->yii2ConfigPath = $path;
        return $this;
    }

    /**
     * Get base application path
     *
     * @return string|null Application path
     */
    public function getYii2AppPath(): ?string
    {
        return $this->yii2AppPath;
    }

    /**
     * Set Yii2 application path
     *
     * @param string $path Application base path
     * @return self
     */
    public function setYii2AppPath(string $path): self
    {
        $this->yii2AppPath = $path;
        return $this;
    }

    /**
     * Check if Gii is enabled
     *
     * @return bool
     */
    public function isGiiEnabled(): bool
    {
        return $this->giiEnabled;
    }

    /**
     * Set Gii enabled flag
     *
     * @param bool $enabled
     * @return self
     */
    public function setGiiEnabled(bool $enabled): self
    {
        $this->giiEnabled = $enabled;
        return $this;
    }

    /**
     * Get default database connection name
     *
     * @return string Connection name (default: 'db')
     */
    public function getDbConnection(): string
    {
        return $this->dbConnection;
    }

    /**
     * Set database connection name
     *
     * @param string $connection Connection name
     * @return self
     */
    public function setDbConnection(string $connection): self
    {
        $this->dbConnection = $connection;
        return $this;
    }

    /**
     * Check if debug mode is enabled
     *
     * @return bool
     */
    public function isDebugMode(): bool
    {
        return $this->debugMode;
    }

    /**
     * Set debug mode
     *
     * @param bool $debug
     * @return self
     */
    public function setDebugMode(bool $debug): self
    {
        $this->debugMode = $debug;
        return $this;
    }

    /**
     * Get configuration summary as array
     *
     * @return array Configuration summary
     */
    public function toArray(): array
    {
        return [
            'yii2ConfigPath' => $this->yii2ConfigPath,
            'yii2AppPath' => $this->yii2AppPath,
            'giiEnabled' => $this->giiEnabled,
            'dbConnection' => $this->dbConnection,
            'debugMode' => $this->debugMode,
            'isValid' => $this->isValid(),
        ];
    }

    /**
     * Check if configuration is valid
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return empty($this->validate());
    }

    /**
     * Validate configuration
     *
     * Checks that required paths exist and are accessible.
     *
     * @return array Array of validation errors (empty if valid)
     */
    public function validate(): array
    {
        $errors = [];

        // Check config path
        if ($this->yii2ConfigPath === null) {
            $errors[] = 'Yii2 config path not set.';
            $errors[] = '  → Set YII2_CONFIG_PATH environment variable';
            $errors[] = '  → Or create config-mcp.php in your project root';
            $errors[] = '  → Quick fix: php vendor/took/yii2-gii-mcp/bin/interactive-setup';
        } elseif (!file_exists($this->yii2ConfigPath)) {
            $errors[] = "Yii2 config file not found: {$this->yii2ConfigPath}";
            $errors[] = "  → Create config-mcp.php: cp vendor/took/yii2-gii-mcp/examples/config-advanced-template.php config-mcp.php";
            $errors[] = "  → Or run: php vendor/took/yii2-gii-mcp/bin/interactive-setup";
        } elseif (!is_readable($this->yii2ConfigPath)) {
            $errors[] = "Yii2 config file not readable: {$this->yii2ConfigPath}";
            $errors[] = "  → Check file permissions: chmod 644 {$this->yii2ConfigPath}";
        }

        // Check app path
        if ($this->yii2AppPath === null) {
            $errors[] = 'Yii2 app path not set.';
            $errors[] = '  → Set YII2_APP_PATH environment variable to your Yii2 project root';
            $errors[] = '  → Example: export YII2_APP_PATH=/path/to/your/yii2/project';
        } elseif (!is_dir($this->yii2AppPath)) {
            $errors[] = "Yii2 app path is not a directory: {$this->yii2AppPath}";
            $errors[] = "  → Make sure the path points to your Yii2 project root directory";
        } elseif (!is_readable($this->yii2AppPath)) {
            $errors[] = "Yii2 app path not readable: {$this->yii2AppPath}";
            $errors[] = "  → Check directory permissions";
        }

        // Add helpful context if errors found
        if (!empty($errors)) {
            $errors[] = '';
            $errors[] = 'For help, run: php vendor/took/yii2-gii-mcp/bin/diagnose';
        }

        return $errors;
    }
    
    /**
     * Get user-friendly error message
     * 
     * @return string Formatted error message with helpful suggestions
     */
    public function getErrorMessage(): string
    {
        $errors = $this->validate();
        
        if (empty($errors)) {
            return '';
        }
        
        $message = "Configuration Error:\n\n";
        $message .= implode("\n", $errors);
        
        return $message;
    }
}
