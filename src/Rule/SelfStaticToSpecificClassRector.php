<?php

declare(strict_types=1);

namespace Tourze\Rector4KPHP\Rule;

use Laminas\Code\Reflection\ClassReflection;
use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use Rector\Rector\AbstractRector;
use Rector\Reflection\ReflectionResolver;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * 把一些能转换的 self / static 静态调用，改为调用具体类
 */
final class SelfStaticToSpecificClassRector extends AbstractRector
{
    public function __construct(private readonly ReflectionResolver $reflectionResolver)
    {
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            '将 self/static 静态调用转换为具体类调用',
            []
        );
    }

    public function getNodeTypes(): array
    {
        return [
            StaticCall::class,
        ];
    }

    public function refactor(Node $node): ?Node
    {
        // 检查是否是 StaticCall 类型的节点
        if ($node instanceof StaticCall) {
            // 获取当前类的反射对象
            $className = $this->getClassNameFromContext($node);

            if ($className) {
                // 使用具体类名替换 self 或 static
                return new StaticCall(new Name\FullyQualified($className), $node->name, $node->args);
            }
        }

        return null;  // 如果不需要替换，返回 null
    }

    /**
     * 获取当前类的名称（如果是 final 类或枚举类）
     */
    private function getClassNameFromContext(StaticCall $node): ?string
    {
        $classReflection = $this->reflectionResolver->resolveClassReflection($node);
        if (!$classReflection instanceof ClassReflection) {
            return null;
        }

        if ($classReflection->isEnum() || $classReflection->isFinal()) {
            return $classReflection->getName();
        }
        return null;
    }
}
