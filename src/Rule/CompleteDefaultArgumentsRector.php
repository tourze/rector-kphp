<?php

namespace Tourze\Rector4KPHP\Rule;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use Rector\Rector\AbstractRector;
use ReflectionFunction;
use ReflectionMethod;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class CompleteDefaultArgumentsRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            '自动为函数或方法调用补全带有默认值的缺失实参',
            [
                new CodeSample(
                    <<<'CODE_BEFORE'
function foo($a = 1, $b = 2) {}
foo(10);
CODE_BEFORE
                    ,
                    <<<'CODE_AFTER'
function foo($a = 1, $b = 2) {}
foo(10, 2);
CODE_AFTER
                )
            ]
        );
    }

    /**
     * 指定要检查/处理的节点类型
     *
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [FuncCall::class, MethodCall::class, StaticCall::class];
    }

    /**
     * 具体的重构逻辑
     */
    public function refactor(Node|FuncCall|MethodCall|StaticCall $node): ?Node
    {
        // 1) 获取当前调用已经传入的实参数量
        $currentArgCount = count($node->args);

        // 2) 分别处理函数调用、方法调用和静态方法调用
        if ($node instanceof FuncCall) {
            $name = $this->getName($node);
            if ($name === null) {
                return null;
            }

            try {
                // 获取函数反射
                $reflection = new ReflectionFunction($name);
            } catch (\ReflectionException $exception) {
                return null;
            }

            $this->completeMissingArguments($node, $reflection);

        } elseif ($node instanceof MethodCall) {
            // 获取方法所在类的类型
            $objectType = $this->nodeTypeResolver->getType($node->var);
            $methodName = $this->getName($node->name);
            if ($methodName === null || $objectType === null) {
                return null;
            }

            try {
                // TODO self/static未支持
                // 获取方法反射
                $reflection = new ReflectionMethod($objectType->getClassName(), $methodName);
            } catch (\ReflectionException $exception) {
                return null;
            }

            $this->completeMissingArguments($node, $reflection);

        } elseif ($node instanceof StaticCall) {
            $className = $this->getName($node->class);
            $methodName = $this->getName($node->name);
            if ($className === null || $methodName === null) {
                return null;
            }

            try {
                // TODO self/static未支持
                // 获取静态方法反射
                $reflection = new ReflectionMethod($className, $methodName);
            } catch (\ReflectionException $exception) {
                return null;
            }

            $this->completeMissingArguments($node, $reflection);
        }

        return $node;
    }

    /**
     * 根据反射信息补全缺失的默认参数
     */
    private function completeMissingArguments(FuncCall|MethodCall|StaticCall $node, ReflectionFunction|ReflectionMethod $reflection): void
    {
        // 获取函数/方法的参数
        $parameters = $reflection->getParameters();

        // 当前已有的参数个数
        $currentCount = count($node->args);

        // 如果传入参数 >= 形参个数，理论上就不需要补全
        if ($currentCount >= count($parameters)) {
            return;
        }

        // 依次检查缺失的参数
        for ($i = $currentCount; $i < count($parameters); $i++) {
            $parameter = $parameters[$i];

            // 可变参数，直接中断算了
            if ($parameter->isVariadic()) {
                break;
            }

            // 如果这个参数没有默认值且不是可选，就不进行补全
            if (!$parameter->isOptional()) {
                break;
            }

            // 提取默认值
            $defaultValue = $parameter->getDefaultValue();

            // 如果有默认值，直接插入
            $node->args[] = $this->nodeFactory->createArg($defaultValue);
        }
    }
}
