<?php

namespace Took\Yii2GiiMCP\Tools;

use InvalidArgumentException;
use RuntimeException;

/**
 * Interface for MCP Tools
 *
 * All tools must implement this interface to be registered and executed
 * by the MCP server.
 */
interface ToolInterface
{
    /**
     * Get the unique tool identifier
     *
     * Should be a kebab-case string (e.g., 'list-tables', 'generate-model')
     *
     * @return string Unique tool name
     */
    public function getName(): string;

    /**
     * Get human-readable tool description
     *
     * This description will be shown to the AI agent to help it understand
     * what the tool does and when to use it.
     *
     * @return string Tool description
     */
    public function getDescription(): string;

    /**
     * Get JSON Schema for tool input validation
     *
     * Returns a JSON Schema object describing the expected input parameters.
     * Example:
     * [
     *     'type' => 'object',
     *     'properties' => [
     *         'tableName' => [
     *             'type' => 'string',
     *             'description' => 'Database table name',
     *         ],
     *     ],
     *     'required' => ['tableName'],
     * ]
     *
     * @return array JSON Schema for input parameters
     */
    public function getInputSchema(): array;

    /**
     * Execute the tool with provided arguments
     *
     * The arguments array should match the schema defined in getInputSchema().
     * This method should validate inputs and return structured results.
     *
     * @param array $arguments Tool arguments (validated against input schema)
     * @return array Tool execution result
     * @throws InvalidArgumentException If arguments are invalid
     * @throws RuntimeException If tool execution fails
     */
    public function execute(array $arguments): array;
}
