<?php

declare(strict_types=1);

namespace Tourze\Rector4KPHP\Rule;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassConst;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

class RemovePublicFromConstRector extends AbstractRector
{
    /**
     * 规则的目标类型
     * 这是决定该规则要作用于哪些节点的方法
     */
    public function getNodeTypes(): array
    {
        return [ClassConst::class];
    }

    /**
     * 该方法用于执行转换
     */
    public function refactor(Node|ClassConst $node): ?Node
    {
        // 检查常量是否是 public 修饰的
        $node->flags = 0;

        return $node;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            '移除常量的 public 修饰符',
            []
        );
    }
}
