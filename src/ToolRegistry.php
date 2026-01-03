<?php

namespace Took\Yii2GiiMCP;

use InvalidArgumentException;
use Took\Yii2GiiMCP\Tools\ToolInterface;

/**
 * Tool Registry - Manages registration and discovery of MCP tools
 */
class ToolRegistry
{
    /**
     * @var array<string, ToolInterface> Registered tools indexed by name
     */
    private array $tools = [];

    /**
     * Register a tool
     *
     * @param ToolInterface $tool Tool to register
     * @throws InvalidArgumentException If tool name is already registered
     */
    public function register(ToolInterface $tool): void
    {
        $name = $tool->getName();

        if (isset($this->tools[$name])) {
            throw new InvalidArgumentException("Tool with name '{$name}' is already registered");
        }

        $this->tools[$name] = $tool;
    }

    /**
     * Get a tool by name
     *
     * @param string $name Tool name
     * @return ToolInterface|null Tool instance or null if not found
     */
    public function get(string $name): ?ToolInterface
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * List all registered tools with their metadata
     *
     * Returns array in MCP tools/list format:
     * [
     *   ['name' => 'tool1', 'description' => '...', 'inputSchema' => [...}],
     *   ['name' => 'tool2', 'description' => '...', 'inputSchema' => [...}],
     * ]
     *
     * @return array List of tool metadata
     */
    public function list(): array
    {
        $toolList = [];

        foreach ($this->tools as $tool) {
            $toolList[] = [
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
                'inputSchema' => $tool->getInputSchema(),
            ];
        }

        return $toolList;
    }

    /**
     * Check if a tool is registered
     *
     * @param string $name Tool name
     * @return bool True if tool is registered
     */
    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    /**
     * Get count of registered tools
     *
     * @return int Number of registered tools
     */
    public function count(): int
    {
        return count($this->tools);
    }

    /**
     * Remove all registered tools
     */
    public function clear(): void
    {
        $this->tools = [];
    }

    /**
     * Get all registered tool names
     *
     * @return array<string> Array of tool names
     */
    public function getNames(): array
    {
        return array_keys($this->tools);
    }
}
