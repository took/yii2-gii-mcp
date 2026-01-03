<?php

namespace Took\Yii2GiiMCP\Tools;

use InvalidArgumentException;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use RuntimeException;

/**
 * Abstract base class for MCP Tools
 *
 * Provides common functionality for tool implementation including:
 * - Input validation using JSON Schema
 * - Error handling helpers
 * - Result formatting helpers
 */
abstract class AbstractTool implements ToolInterface
{
    /**
     * {@inheritDoc}
     */
    abstract public function getName(): string;

    /**
     * {@inheritDoc}
     */
    abstract public function getDescription(): string;

    /**
     * Execute the tool with input validation
     *
     * This final method wraps doExecute() with validation.
     *
     * {@inheritDoc}
     */
    final public function execute(array $arguments): array
    {
        // Validate inputs first
        $this->validateInput($arguments);

        // Execute the actual tool logic
        return $this->doExecute($arguments);
    }

    /**
     * Validate input arguments against the tool's input schema
     *
     * @param array $arguments Input arguments to validate
     * @throws InvalidArgumentException If validation fails
     */
    protected function validateInput(array $arguments): void
    {
        $schema = $this->getInputSchema();

        if (empty($schema)) {
            // No schema defined, skip validation
            return;
        }

        // Convert arrays to objects for JSON Schema validation
        $data = json_decode(json_encode($arguments));
        $schemaObject = json_decode(json_encode($schema));

        // Validate using JSON Schema validator
        $validator = new Validator();
        $validator->validate(
            $data,
            $schemaObject,
            Constraint::CHECK_MODE_APPLY_DEFAULTS
        );

        if (!$validator->isValid()) {
            $errors = [];
            foreach ($validator->getErrors() as $error) {
                $property = $error['property'] ? "[{$error['property']}] " : '';
                $errors[] = $property . $error['message'];
            }

            throw new InvalidArgumentException(
                'Input validation failed: ' . implode(', ', $errors)
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    abstract public function getInputSchema(): array;

    /**
     * Perform the actual tool execution
     *
     * Subclasses should implement this method instead of execute().
     * Input validation will already be performed.
     *
     * @param array $arguments Validated input arguments
     * @return array Tool execution result
     * @throws RuntimeException If tool execution fails
     */
    abstract protected function doExecute(array $arguments): array;

    /**
     * Create a successful result response
     *
     * @param mixed $data Result data
     * @param string $type Content type (default: 'text')
     * @return array Formatted result
     */
    protected function createResult(mixed $data, string $type = 'text'): array
    {
        return [
            'type' => $type,
            'text' => is_string($data) ? $data : json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ];
    }

    /**
     * Create a result with structured data
     *
     * @param array $data Structured data
     * @return array Formatted result
     */
    protected function createDataResult(array $data): array
    {
        return [
            'type' => 'text',
            'text' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ];
    }

    /**
     * Create an error result (for non-fatal errors that should be returned to the AI)
     *
     * Note: For validation errors or fatal errors, throw exceptions instead.
     * This is for operational errors that the AI should know about.
     *
     * @param string $message Error message
     * @param array|null $details Additional error details
     * @return array Error result
     */
    protected function createError(string $message, ?array $details = null): array
    {
        $text = "Error: {$message}";

        if ($details !== null) {
            $text .= "\n\nDetails:\n" . json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        return [
            'type' => 'text',
            'text' => $text,
        ];
    }

    /**
     * Extract a required parameter from arguments
     *
     * @param array $arguments Input arguments
     * @param string $name Parameter name
     * @return mixed Parameter value
     * @throws InvalidArgumentException If parameter is missing
     */
    protected function getRequiredParam(array $arguments, string $name): mixed
    {
        if (!array_key_exists($name, $arguments)) {
            throw new InvalidArgumentException("Missing required parameter: {$name}");
        }

        return $arguments[$name];
    }

    /**
     * Extract an optional parameter from arguments with default value
     *
     * @param array $arguments Input arguments
     * @param string $name Parameter name
     * @param mixed $default Default value if not present
     * @return mixed Parameter value or default
     */
    protected function getOptionalParam(array $arguments, string $name, mixed $default = null): mixed
    {
        return $arguments[$name] ?? $default;
    }

    /**
     * Format a table of data for display
     *
     * @param array $headers Table headers
     * @param array $rows Table rows (array of arrays)
     * @return string Formatted table
     */
    protected function formatTable(array $headers, array $rows): string
    {
        if (empty($rows)) {
            return "No data available.";
        }

        // Calculate column widths
        $widths = array_map('strlen', $headers);
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $widths[$i] = max($widths[$i], strlen((string)$cell));
            }
        }

        // Build table
        $output = [];

        // Header row
        $headerRow = '';
        foreach ($headers as $i => $header) {
            $headerRow .= str_pad($header, $widths[$i] + 2);
        }
        $output[] = trim($headerRow);

        // Separator
        $separator = '';
        foreach ($widths as $width) {
            $separator .= str_repeat('-', $width + 2);
        }
        $output[] = trim($separator);

        // Data rows
        foreach ($rows as $row) {
            $dataRow = '';
            foreach ($row as $i => $cell) {
                $dataRow .= str_pad((string)$cell, $widths[$i] + 2);
            }
            $output[] = trim($dataRow);
        }

        return implode("\n", $output);
    }
}
