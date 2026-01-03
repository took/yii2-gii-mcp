<?php

namespace Tests\Support;

use Took\Yii2GiiMCP\Tools\AbstractTool;

/**
 * Simple mock tool for testing that doesn't require Yii2
 */
class MockSimpleTool extends AbstractTool
{
    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'mock-simple';
    }

    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return 'A simple mock tool for testing the MCP protocol';
    }

    /**
     * {@inheritDoc}
     */
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'message' => [
                    'type' => 'string',
                    'description' => 'Message to echo back',
                    'default' => 'Hello from mock tool',
                ],
            ],
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function doExecute(array $arguments): array
    {
        $message = $this->getOptionalParam($arguments, 'message', 'Hello from mock tool');
        return $this->createResult("Mock tool executed: {$message}");
    }
}
