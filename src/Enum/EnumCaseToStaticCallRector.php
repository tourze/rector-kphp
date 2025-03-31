<?php

namespace Tourze\Rector4KPHP\Enum;

use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * 枚举的调用是类似 ConstFetch 处理的，我们转为静态方法调用
 */
class EnumCaseToStaticCallRector extends AbstractRector
{
    use EnumHelperTrait;

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            '将枚举常量访问转换为静态方法调用',
            []
        );
    }

    public function getNodeTypes(): array
    {
        return [ClassConstFetch::class];
    }

    public function refactor(Node|ClassConstFetch $node): ?Node
    {
        if (!isset($node->class->name)) {
            return null;
        }
        if (!$this->isEnum($node->class->name)) {
            return null;
        }

        return $this->nodeFactory->createStaticCall($node->class->name, $node->name->name);
    }
}
