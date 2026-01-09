<?php

namespace Tests\Support;

use Took\Yii2GiiMCP\Tools\AbstractTool;

/**
 * Mock tool for testing AbstractTool base class
 */
class MockTool extends AbstractTool
{
    public function getName(): string
    {
        return 'mock-tool';
    }

    public function getDescription(): string
    {
        return 'A mock tool for testing';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'testParam' => [
                    'type' => 'string',
                    'description' => 'Test parameter',
                ],
            ],
            'required' => ['testParam'],
        ];
    }

    public function testCreateResult($data, $type = 'text'): array
    {
        return $this->createResult($data, $type);
    }

    // Expose protected methods for testing

    public function testCreateDataResult(array $data): array
    {
        return $this->createDataResult($data);
    }

    public function testCreateError(string $message, ?array $details = null): array
    {
        return $this->createError($message, $details);
    }

    public function testGetRequiredParam(array $arguments, string $name)
    {
        return $this->getRequiredParam($arguments, $name);
    }

    public function testGetOptionalParam(array $arguments, string $name, $default = null)
    {
        return $this->getOptionalParam($arguments, $name, $default);
    }

    public function testFormatTable(array $headers, array $rows): string
    {
        return $this->formatTable($headers, $rows);
    }

    protected function doExecute(array $arguments): array
    {
        $param = $this->getRequiredParam($arguments, 'testParam');

        return $this->createResult("Executed with: {$param}");
    }
}
