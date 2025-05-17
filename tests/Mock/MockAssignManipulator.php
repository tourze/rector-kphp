<?php

namespace Tourze\Rector4KPHP\Tests\Mock;

use PhpParser\Node;
use PhpParser\Node\Expr\Variable;

class MockAssignManipulator
{
    /**
     * 存储变量是否是赋值语句左侧部分的映射
     */
    private array $assignLeftSideMap = [];
    
    /**
     * 设置变量是否是赋值语句的左侧部分
     */
    public function setIsLeftPartOfAssign(Variable $node, bool $isLeft): void
    {
        $key = spl_object_hash($node);
        $this->assignLeftSideMap[$key] = $isLeft;
    }
    
    /**
     * 检查变量是否是赋值语句的左侧部分
     */
    public function isLeftPartOfAssign(Node $node): bool
    {
        if (!$node instanceof Variable) {
            return false;
        }
        
        $key = spl_object_hash($node);
        return isset($this->assignLeftSideMap[$key]) && $this->assignLeftSideMap[$key] === true;
    }
} 