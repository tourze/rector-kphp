<?php

namespace Tourze\Rector4KPHP\Visitor;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\NodeVisitorAbstract;

class CodeCollector extends NodeVisitorAbstract
{
    /** @var array<string> */
    private array $usedClasses = [];

    /** @var array<string> */
    private array $usedFunctions = [];

    /** @var array<string> */
    private array $includedFiles = [];

    /** @var array<string> */
    private array $usedConstants = [];

    public function enterNode(Node $node)
    {
        if ($node instanceof Expr\ClassConstFetch) {
            $this->addUsedClass($node->class->name);
        } elseif ($node instanceof Expr\StaticCall) {
            $this->addUsedClass($node->class->name);
        } elseif ($node instanceof Expr\New_) {
            $this->addUsedClass($node->class->name);
        } elseif ($node instanceof Expr\StaticPropertyFetch) {
            $this->addUsedClass($node->class->name);
        } elseif ($node instanceof Expr\FuncCall && (!$node->name instanceof Expr\Variable)) {
            $name = $node->name->name;
            $this->addFunction($name);
        } elseif ($node instanceof Expr\Include_) {
            if ($node->expr instanceof Node\Scalar\String_) {
                $this->addFunction($node->expr->value);
            }
        } elseif ($node instanceof ConstFetch) {
            $this->addConstant($node->name->name);
        }

        // 类的实现也需要分析下
        if ($node instanceof Node\Stmt\Class_) {
            if ($node->extends) {
                $this->addUsedClass($node->extends->name);
            }
            foreach ($node->implements as $implement) {
                $this->addUsedClass($implement->name);
            }
        }

        // 接口的处理
        if ($node instanceof Node\Stmt\Interface_) {
            if ($node->extends) {
                foreach ($node->extends as $extend) {
                    $this->addUsedClass($extend->name);
                }
            }
        }

        // use也可能引发引入
        if ($node instanceof Node\Stmt\Use_) {
            foreach ($node->uses as $use) {
                $this->addUsedClass($use->name->name);
            }
        }

        // 方法参数中携带的定义
        if ($node instanceof Node\Stmt\ClassMethod) {
            // 返回值
            if ($node->returnType instanceof Node\Name\FullyQualified) {
                $this->addUsedClass($node->returnType->name);
            }
            // TODO 参数
        }

        // catch
        if ($node instanceof Node\Stmt\Catch_) {
            foreach ($node->types as $type) {
                $this->addUsedClass($type->name);
            }
        }

        //dump($node);
    }

    private function addUsedClass(string $class): void
    {
        if (in_array($class, ['self', 'static', 'parent', \Attribute::class])) {
            return;
        }
        if (str_starts_with($class, 'PHP2AOT')) {
            return;
        }
        $this->usedClasses[] = $class;
        $this->usedClasses = array_values(array_unique($this->usedClasses));
    }

    private function addFunction(string $name): void
    {
        $this->usedFunctions[] = $name;
        $this->usedFunctions = array_values(array_unique($this->usedFunctions));
    }

    /**
     * @return array<string>
     */
    public function getUsedClasses(): array
    {
        return $this->usedClasses;
    }

    /**
     * @return array<string>
     */
    public function getUsedFunctions(): array
    {
        return $this->usedFunctions;
    }

    /**
     * @return array<string>
     */
    public function getIncludedFiles(): array
    {
        return $this->includedFiles;
    }

    private function addConstant(string $name): void
    {
        if (in_array($name, ['true', 'false', 'null'])) {
            return;
        }

        $this->usedConstants[] = $name;
        $this->usedConstants = array_values(array_unique($this->usedConstants));
    }

    public function getUsedConstants(): array
    {
        return $this->usedConstants;
    }
}
