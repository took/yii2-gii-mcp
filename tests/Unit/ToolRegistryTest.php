<?php

namespace Tests\Unit;

use Codeception\Test\Unit;
use InvalidArgumentException;
use Took\Yii2GiiMCP\ToolRegistry;
use Took\Yii2GiiMCP\Tools\ToolInterface;

/**
 * Test ToolRegistry class
 */
class ToolRegistryTest extends Unit
{
    private ToolRegistry $registry;

    public function testRegisterTool(): void
    {
        $tool = $this->createMockTool('test-tool');

        $this->registry->register($tool);

        $this->assertTrue($this->registry->has('test-tool'));
        $this->assertSame($tool, $this->registry->get('test-tool'));
    }

    /**
     * Create a mock tool for testing
     */
    private function createMockTool(
        string $name,
        string $description = 'Test tool',
        array  $inputSchema = []
    ): ToolInterface
    {
        $tool = $this->createMock(ToolInterface::class);

        $tool->method('getName')->willReturn($name);
        $tool->method('getDescription')->willReturn($description);
        $tool->method('getInputSchema')->willReturn($inputSchema);

        return $tool;
    }

    public function testGetNonExistentTool(): void
    {
        $result = $this->registry->get('non-existent');

        $this->assertNull($result);
    }

    public function testRegisterDuplicateToolThrowsException(): void
    {
        $tool1 = $this->createMockTool('test-tool');
        $tool2 = $this->createMockTool('test-tool');

        $this->registry->register($tool1);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Tool with name 'test-tool' is already registered");

        $this->registry->register($tool2);
    }

    public function testListTools(): void
    {
        $tool1 = $this->createMockTool('tool-one', 'First tool', ['type' => 'object']);
        $tool2 = $this->createMockTool('tool-two', 'Second tool', ['type' => 'string']);

        $this->registry->register($tool1);
        $this->registry->register($tool2);

        $list = $this->registry->list();

        $this->assertIsArray($list);
        $this->assertCount(2, $list);

        $this->assertEquals('tool-one', $list[0]['name']);
        $this->assertEquals('First tool', $list[0]['description']);
        $this->assertEquals(['type' => 'object'], $list[0]['inputSchema']);

        $this->assertEquals('tool-two', $list[1]['name']);
        $this->assertEquals('Second tool', $list[1]['description']);
        $this->assertEquals(['type' => 'string'], $list[1]['inputSchema']);
    }

    public function testCount(): void
    {
        $this->assertEquals(0, $this->registry->count());

        $this->registry->register($this->createMockTool('tool-1'));
        $this->assertEquals(1, $this->registry->count());

        $this->registry->register($this->createMockTool('tool-2'));
        $this->assertEquals(2, $this->registry->count());
    }

    public function testGetNames(): void
    {
        $this->registry->register($this->createMockTool('tool-a'));
        $this->registry->register($this->createMockTool('tool-b'));
        $this->registry->register($this->createMockTool('tool-c'));

        $names = $this->registry->getNames();

        $this->assertIsArray($names);
        $this->assertCount(3, $names);
        $this->assertContains('tool-a', $names);
        $this->assertContains('tool-b', $names);
        $this->assertContains('tool-c', $names);
    }

    public function testClear(): void
    {
        $this->registry->register($this->createMockTool('tool-1'));
        $this->registry->register($this->createMockTool('tool-2'));

        $this->assertEquals(2, $this->registry->count());

        $this->registry->clear();

        $this->assertEquals(0, $this->registry->count());
        $this->assertFalse($this->registry->has('tool-1'));
        $this->assertFalse($this->registry->has('tool-2'));
    }

    public function testHas(): void
    {
        $this->assertFalse($this->registry->has('test-tool'));

        $this->registry->register($this->createMockTool('test-tool'));

        $this->assertTrue($this->registry->has('test-tool'));
    }

    protected function setUp(): void
    {
        $this->registry = new ToolRegistry();
    }
}
