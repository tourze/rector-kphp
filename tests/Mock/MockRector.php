<?php

namespace Tourze\Rector4KPHP\Tests\Mock;

use PhpParser\Node;

/**
 * 模拟 Rector 基类用于测试
 */
abstract class MockRector
{
    abstract public function getNodeTypes(): array;
    
    /**
     * @param Node $node
     * @return Node|null
     */
    abstract public function refactor(Node $node): ?Node;
} 