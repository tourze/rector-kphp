<?php

namespace Tourze\Rector4KPHP\Enum;

use PhpParser\Builder\Class_;
use PhpParser\Builder\Method;
use PhpParser\Builder\Param;
use PhpParser\Builder\Property as PropertyBuilder;
use PhpParser\BuilderHelpers;
use PhpParser\Node;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Enum_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * 把 Enum 转为一个 Class
 */
class EnumToClassWithStaticMethodRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            '将 PHP 8 原生枚举转换为普通类实现',
            []
        );
    }

    public function getNodeTypes(): array
    {
        return [Enum_::class];
    }

    public function refactor(Node|Enum_ $node): ?Node
    {
        $builder = new Class_($node->name->name);
        $builder->makeFinal();
        $builder->implement(...$node->implements);
        foreach ($this->buildClassStatements($node) as $statement) {
            $builder->addStmt($statement);
        }
        $result = $builder->getNode();

        $class = new Stmt\Class_($result->name, [
            'flags' => $result->flags,
            'extends' => $result->extends,
            'implements' => $result->implements,
            'stmts' => $result->stmts,
            'attrGroups' => $result->attrGroups,
        ], ['startLine' => $node->getStartLine(), 'endLine' => $node->getEndLine()]);
        $class->namespacedName = $node->namespacedName;

        $this->removeSelfConstFetch($class);

        return $class;
    }

    private function buildClassStatements(Enum_ $enum): \Traversable
    {
        // 如果是Backed类型，那么会有类型的，否则我们统一当整形处理
        $defaultScalarType = new Node\Identifier('int');
        $scalarType = $enum->scalarType;

        // 静态成员存储所有可能的值
        $property = new PropertyBuilder('instances');
        $property->makePrivate();
        $property->makeStatic();
        $property->setType(new Name('array'));
        yield $property->getNode();

        // 专门存储name的成员
        $builder = new PropertyBuilder('name');
        $builder->makePublic();
        //$builder->makeReadonly();
        $builder->setType('string');
        yield $builder->getNode();

        // 专门存储value的成员
        $builder = new PropertyBuilder('value');
        $builder->makePublic();
        //$builder->makeReadonly();
        $builder->setType($scalarType ?: $defaultScalarType);
        yield $builder->getNode();

        // 增加构造函数来赋值value
        $method = new Method('__construct');
        $method->makePrivate();
        $param = new Param('name');
        $param->setType('string');
        $method->addParam($param->getNode());
        $param = new Param('value');
        $param->setType($scalarType ?: $defaultScalarType);
        $method->addParam($param->getNode());
        $method->addStmt($this->nodeFactory->createPropertyAssignment('name'));
        $method->addStmt($this->nodeFactory->createPropertyAssignment('value'));
        yield $method->getNode();

        $instancesFetch = new StaticPropertyFetch(new Name('self'), 'instances');

        $cases = [];
        foreach ($enum->stmts as $stmt) {
            // 旧的枚举case逻辑，我们转为同名的方法
            if ($stmt instanceof Node\Stmt\EnumCase) {
                $value = BuilderHelpers::normalizeValue($stmt->expr ?: count($cases));
                $dim = new Node\Expr\ArrayDimFetch(
                    $instancesFetch,
                    $value,
                );

                $method = new Method($stmt->name->name);
                $method->makePublic();
                $method->makeStatic();
                $method->setReturnType(new Node\Identifier('self'));
                $method->addStmt(new Node\Stmt\If_(
                    new Node\Expr\BooleanNot(
                        new Node\Expr\Isset_([$dim])
                    ),
                    [
                        'stmts' => [
                            new Node\Stmt\Expression(new Node\Expr\Assign(
                                $dim,
                                new Node\Expr\New_(new Node\Name('self'), [
                                    $this->nodeFactory->createArg($stmt->name->name),
                                    $this->nodeFactory->createArg($value),
                                ])
                            )),
                        ],
                    ]
                ));
                $method->addStmt(new Node\Stmt\Return_($dim));
                yield $method->getNode();

                $cases[] = $stmt->name->name;
                continue;
            }

            yield $stmt;
        }

        // 收集并返回所有枚举
        $method = new Method('cases');
        $method->makeStatic();
        $method->makePublic();
        $method->setReturnType(new Name('array'));
        $method->setDocComment('/**
     * @return self[]
     */');
        $items = [];
        foreach ($cases as $case) {
            $items[] = $this->nodeFactory->createStaticCall('self', $case);
        }
        $method->addStmt(new Stmt\Return_($this->nodeFactory->createArray($items)));
        yield $method->getNode();

        $method = new Method('from');
        $method->makeStatic();
        $method->makePublic();
        $method->setReturnType(new Name('self'));
        $param = new Param('value');
        $param->setType($scalarType ?: $defaultScalarType);
        $method->addParam($param->getNode());
        $v = new Node\Expr\Variable('v');
        $method->addStmt(new Stmt\Foreach_(
            $this->nodeFactory->createStaticCall('self', 'cases'),
            $v,
            [
                'stmts' => [
                    new Stmt\If_(
                        new Node\Expr\BinaryOp\Identical(
                            $this->nodeFactory->createPropertyFetch($v, 'value'),
                            new Node\Expr\Variable('value'),
                        ),
                        [
                            'stmts' => [
                                new Stmt\Return_($v),
                            ],
                        ],
                    ),
                ],
            ],
        ));
        $method->addStmt(new Stmt\Expression(
            new Node\Expr\Throw_(
                new Node\Expr\New_(
                    new Name\FullyQualified(\ValueError::class),
                )
            )
        ));
        yield $method->getNode();

        $method = new Method('tryFrom');
        $method->makeStatic();
        $method->makePublic();
        $method->setReturnType(new NullableType(new Node\Identifier('self')));
        $param = new Param('value');
        $param->setType($scalarType ?: $defaultScalarType);
        $method->addParam($param->getNode());
        $method->addStmt(new Stmt\TryCatch(
            [
                new Stmt\Return_(
                    $this->nodeFactory->createStaticCall(
                        'self',
                        'from',
                        [
                            $this->nodeFactory->createArg(new Node\Expr\Variable('value')),
                        ]
                    ),
                ),
            ],
            [
                new Stmt\Catch_(
                    [new Name\FullyQualified(\ValueError::class)],
                    new Node\Expr\Variable('exception'),
                    [
                        new Stmt\Return_($this->nodeFactory->createNull()),
                    ],
                ),
            ],
        ));
        yield $method->getNode();
    }

    /**
     * 在枚举类内部使用 self 或 static 调用自己枚举值时，我们修改为直接使用 fqcn，以减少其他规则的实现难度
     */
    private function removeSelfConstFetch(Stmt\Class_ $class): void
    {
        $this->traverseNodesWithCallable($class, function (Node $node) use ($class) {
            if (!$node instanceof Node\Expr\ClassConstFetch) {
                return null;
            }

            return $this->nodeFactory->createClassConstFetch($class->namespacedName->name, $node->name->name);
        });
    }
}
