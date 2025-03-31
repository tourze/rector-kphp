<?php

declare(strict_types=1);

namespace Tourze\Rector4KPHP\Enum;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

class EnumDefaultValueToNullRector extends AbstractRector
{
    use EnumHelperTrait;

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            '将函数/方法参数中的枚举默认值转换为 null',
            []
        );
    }

    public function getNodeTypes(): array
    {
        return [Function_::class, ClassMethod::class];  // Apply this to function and method nodes
    }

    public function refactor(Node $node): ?Node
    {
        // Check if the node is a function or method
        if ($node instanceof Function_ || $node instanceof ClassMethod) {
            foreach ($node->params as $param) {
                // Check if the parameter has a default value and is a PHP 8 enum value
                if ($param->default instanceof Node\Expr\ClassConstFetch) {
                    // Check if the default value is an enum (example: MyEnum::VALUE)
                    if ($this->isEnum($param->default->class->name)) {
                        // Set default value to null
                        $param->default = $this->nodeFactory->createNull();
                    }
                }
            }
        }

        return $node;
    }
}
