<?php

namespace Took\Yii2GiiMCP\Tools;

use Throwable;
use Took\Yii2GiiMCP\Helpers\ProjectStructureHelper;
use Took\Yii2GiiMCP\Helpers\Yii2Bootstrap;

/**
 * Detect Application Structure Tool
 *
 * Auto-detect Yii2 project structure including:
 * - Template type (Basic/Advanced/Advanced+API)
 * - Available applications and their types
 * - Modules within applications
 * - Environment configuration (init system analysis)
 *
 * This is a read-only tool that helps understand project organization.
 */
class DetectApplicationStructure extends AbstractTool
{
    private Yii2Bootstrap $bootstrap;

    /**
     * @param Yii2Bootstrap $bootstrap Yii2 bootstrap instance
     */
    public function __construct(Yii2Bootstrap $bootstrap)
    {
        $this->bootstrap = $bootstrap;
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'detect-application-structure';
    }

    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return 'Auto-detect Yii2 project structure including template type (Basic/Advanced/Advanced+API), ' .
            'available applications, modules, and environment configuration. Analyzes the init system ' .
            'and compares index.php files with environment templates. This is a read-only operation ' .
            'that helps you understand your project organization.';
    }

    /**
     * {@inheritDoc}
     */
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'basePath' => [
                    'type' => 'string',
                    'description' => 'Base path to scan (defaults to project root from configuration)',
                    'default' => '',
                ],
            ],
            'additionalProperties' => false,
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function doExecute(array $arguments): array
    {
        try {
            // Get base path from arguments or configuration
            $basePath = $this->getOptionalParam($arguments, 'basePath', '');

            if (empty($basePath)) {
                // Use configured path
                $basePath = $this->bootstrap->getApp()->getBasePath();

                // For advanced template, get the root directory (parent of common/frontend/backend)
                $templateType = $this->bootstrap->detectTemplateType();
                if ($templateType !== 'basic') {
                    $basePath = dirname($basePath);
                }
            }

            // Normalize path
            $basePath = realpath($basePath);
            if ($basePath === false) {
                return $this->createError('Invalid base path provided');
            }

            // Detect project structure
            $structure = $this->detectStructure($basePath);

            // Format output
            $output = $this->formatStructure($structure);

            return $this->createResult($output);
        } catch (Throwable $e) {
            return $this->createError(
                'Failed to detect application structure: ' . $e->getMessage(),
                [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            );
        }
    }

    /**
     * Detect project structure
     *
     * @param string $basePath Base path to analyze
     * @return array Structure information
     */
    private function detectStructure(string $basePath): array
    {
        $structure = [
            'templateType' => ProjectStructureHelper::detectTemplateType($basePath),
            'basePath' => $basePath,
            'applications' => [],
            'environments' => ProjectStructureHelper::detectEnvironments($basePath),
        ];

        // Find applications
        $applications = ProjectStructureHelper::findApplicationDirs($basePath);

        foreach ($applications as $appName => $appPath) {
            $appInfo = [
                'name' => $appName,
                'path' => $appPath,
                'type' => ProjectStructureHelper::getApplicationType($appPath),
                'hasWeb' => is_dir($appPath . '/web') || is_dir($appPath . '/www'),
                'entryPoints' => $this->findEntryPoints($appPath),
                'modules' => ProjectStructureHelper::findModules($appPath),
            ];

            $structure['applications'][] = $appInfo;
        }

        return $structure;
    }

    /**
     * Find entry points (index.php files) in application
     *
     * @param string $appPath Application path
     * @return array Entry point information
     */
    private function findEntryPoints(string $appPath): array
    {
        $entryPoints = [];

        $possibleEntryPoints = [
            'web/index.php',
            'web/index-test.php',
            'www/index.php',
            'www/index-test.php',
            'yii',
        ];

        foreach ($possibleEntryPoints as $entryPoint) {
            $fullPath = $appPath . '/' . $entryPoint;
            if (file_exists($fullPath)) {
                $info = [
                    'file' => $entryPoint,
                ];

                // Parse PHP files for environment constants
                if (pathinfo($fullPath, PATHINFO_EXTENSION) === 'php') {
                    $parsed = ProjectStructureHelper::parseIndexPhpFile($fullPath);
                    if (!empty($parsed)) {
                        $info['env'] = $parsed['YII_ENV'] ?? 'unknown';
                        $info['debug'] = $parsed['YII_DEBUG'] ?? false;
                    }
                }

                $entryPoints[] = $info;
            }
        }

        return $entryPoints;
    }

    /**
     * Format structure information for output
     *
     * @param array $structure Structure data
     * @return string Formatted output
     */
    private function formatStructure(array $structure): string
    {
        $output = "Project Structure Analysis\n";
        $output .= str_repeat('=', 50) . "\n\n";

        // Template type
        $output .= "Template Type: " . strtoupper($structure['templateType']) . "\n";
        $output .= "Base Path: {$structure['basePath']}\n\n";

        // Applications
        $output .= "Applications (" . count($structure['applications']) . "):\n";
        $output .= str_repeat('-', 50) . "\n";

        foreach ($structure['applications'] as $app) {
            $output .= "\n[{$app['name']}]\n";
            $output .= "  Path: {$app['path']}\n";
            $output .= "  Type: {$app['type']}\n";
            $output .= "  Has Web: " . ($app['hasWeb'] ? 'Yes' : 'No') . "\n";

            if (!empty($app['entryPoints'])) {
                $output .= "  Entry Points:\n";
                foreach ($app['entryPoints'] as $entry) {
                    $output .= "    - {$entry['file']}";
                    if (isset($entry['env'])) {
                        $output .= " (env: {$entry['env']}, debug: " . ($entry['debug'] ? 'true' : 'false') . ")";
                    }
                    $output .= "\n";
                }
            }

            if (!empty($app['modules'])) {
                $output .= "  Modules (" . count($app['modules']) . "):\n";
                foreach ($app['modules'] as $module) {
                    $output .= "    - {$module['id']} ({$module['path']})\n";
                }
            }
        }

        // Environments
        $output .= "\n\nEnvironments:\n";
        $output .= str_repeat('-', 50) . "\n";

        if (!empty($structure['environments']['available'])) {
            $output .= "Available: " . implode(', ', $structure['environments']['available']) . "\n";
        } else {
            $output .= "Available: None detected\n";
        }

        if ($structure['environments']['current']) {
            $output .= "Current: {$structure['environments']['current']}\n";

            if (!empty($structure['environments']['currentDetails'])) {
                $details = $structure['environments']['currentDetails'];
                $output .= "  YII_ENV: " . ($details['YII_ENV'] ?? 'not set') . "\n";
                $output .= "  YII_DEBUG: " . (isset($details['YII_DEBUG']) ? ($details['YII_DEBUG'] ? 'true' : 'false') : 'not set') . "\n";
                if (isset($details['detectedFrom'])) {
                    $output .= "  Detected from: {$details['detectedFrom']}\n";
                }
            }
        } else {
            $output .= "Current: Not detected\n";
        }

        if (!empty($structure['environments']['sources'])) {
            $output .= "Detection sources: " . implode(', ', $structure['environments']['sources']) . "\n";
        }

        // Add JSON representation for programmatic access
        $output .= "\n\n" . str_repeat('=', 50) . "\n";
        $output .= "JSON Representation:\n";
        $output .= str_repeat('=', 50) . "\n";
        $output .= json_encode($structure, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return $output;
    }
}
