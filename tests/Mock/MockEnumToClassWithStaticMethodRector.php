<?php

namespace Tourze\Rector4KPHP\Tests\Mock;

use PhpParser\Builder\Class_;
use PhpParser\Node;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Enum_;

class MockEnumToClassWithStaticMethodRector extends MockRector
{
    public function getRuleDefinition(): MockSymplifyRuleDefinition
    {
        return new MockSymplifyRuleDefinition(
            '将 PHP 8 原生枚举转换为普通类实现'
        );
    }

    public function getNodeTypes(): array
    {
        return [Enum_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof Enum_) {
            return null;
        }
        
        $builder = new Class_($node->name->name);
        $builder->makeFinal();
        
        $class = new Stmt\Class_($node->name, [
            'flags' => Stmt\Class_::MODIFIER_FINAL,
            'extends' => null,
            'implements' => [],
            'stmts' => $this->createBasicStmts($node),
        ]);
        
        // 为了测试，添加namespacedName
        if (isset($node->namespacedName)) {
            $class->namespacedName = $node->namespacedName;
        }
        
        return $class;
    }
    
    private function createBasicStmts(Enum_ $node): array
    {
        $stmts = [];
        
        // 添加几个基本属性
        $nameProperty = new Stmt\Property(
            Stmt\Class_::MODIFIER_PUBLIC,
            [new Node\PropertyItem('name')]
        );
        $nameProperty->type = new Node\Identifier('string');
        $stmts[] = $nameProperty;
        
        $valueProperty = new Stmt\Property(
            Stmt\Class_::MODIFIER_PUBLIC,
            [new Node\PropertyItem('value')]
        );
        
        // 使用节点的scalarType或默认为int
        if ($node->scalarType) {
            $valueProperty->type = $node->scalarType;
        } else {
            $valueProperty->type = new Node\Identifier('int');
        }
        
        $stmts[] = $valueProperty;
        
        // 添加构造函数
        $stmts[] = new Stmt\ClassMethod(
            '__construct',
            [
                'flags' => Stmt\Class_::MODIFIER_PRIVATE,
                'params' => [
                    new Node\Param(
                        new Node\Expr\Variable('name'),
                        null,
                        new Node\Identifier('string')
                    ),
                    new Node\Param(
                        new Node\Expr\Variable('value'),
                        null,
                        $node->scalarType ?: new Node\Identifier('int')
                    ),
                ],
                'stmts' => [
                    new Stmt\Expression(
                        new Node\Expr\Assign(
                            new Node\Expr\PropertyFetch(
                                new Node\Expr\Variable('this'),
                                'name'
                            ),
                            new Node\Expr\Variable('name')
                        )
                    ),
                    new Stmt\Expression(
                        new Node\Expr\Assign(
                            new Node\Expr\PropertyFetch(
                                new Node\Expr\Variable('this'),
                                'value'
                            ),
                            new Node\Expr\Variable('value')
                        )
                    ),
                ],
            ]
        );
        
        // 为每个枚举案例创建静态方法
        $caseNames = [];
        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\EnumCase) {
                $caseNames[] = $stmt->name->name;
                $stmts[] = new Stmt\ClassMethod(
                    $stmt->name->name,
                    [
                        'flags' => Stmt\Class_::MODIFIER_PUBLIC | Stmt\Class_::MODIFIER_STATIC,
                        'returnType' => new Node\Identifier('self'),
                        'stmts' => [
                            new Stmt\Return_(
                                new Node\Expr\Variable('instance')
                            ),
                        ],
                    ]
                );
            }
        }
        
        // 添加cases方法
        $stmts[] = new Stmt\ClassMethod(
            'cases',
            [
                'flags' => Stmt\Class_::MODIFIER_PUBLIC | Stmt\Class_::MODIFIER_STATIC,
                'returnType' => new Node\Identifier('array'),
                'stmts' => [
                    new Stmt\Return_(
                        new Node\Expr\Array_()
                    ),
                ],
            ]
        );
        
        // 添加from方法
        $stmts[] = new Stmt\ClassMethod(
            'from',
            [
                'flags' => Stmt\Class_::MODIFIER_PUBLIC | Stmt\Class_::MODIFIER_STATIC,
                'params' => [
                    new Node\Param(
                        new Node\Expr\Variable('value'),
                        null,
                        $node->scalarType ?: new Node\Identifier('int')
                    ),
                ],
                'returnType' => new Node\Identifier('self'),
                'stmts' => [
                    new Stmt\Return_(
                        new Node\Expr\Variable('instance')
                    ),
                ],
            ]
        );
        
        // 添加tryFrom方法
        $stmts[] = new Stmt\ClassMethod(
            'tryFrom',
            [
                'flags' => Stmt\Class_::MODIFIER_PUBLIC | Stmt\Class_::MODIFIER_STATIC,
                'params' => [
                    new Node\Param(
                        new Node\Expr\Variable('value'),
                        null,
                        $node->scalarType ?: new Node\Identifier('int')
                    ),
                ],
                'returnType' => new NullableType(new Node\Identifier('self')),
                'stmts' => [
                    new Stmt\Return_(
                        new Node\Expr\Variable('instance')
                    ),
                ],
            ]
        );
        
        return $stmts;
    }
} 