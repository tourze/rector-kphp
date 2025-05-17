<?php

namespace Tourze\Rector4KPHP\Tests\Enum;

use PhpParser\Node;
use PhpParser\Node\Stmt\Enum_;
use PHPUnit\Framework\TestCase;
use Tourze\Rector4KPHP\Tests\Mock\MockEnumToClassWithStaticMethodRector;
use Tourze\Rector4KPHP\Tests\Mock\MockSymplifyRuleDefinition;

class EnumToClassWithStaticMethodRectorTest extends TestCase
{
    private MockEnumToClassWithStaticMethodRector $rector;
    
    protected function setUp(): void
    {
        $this->rector = new MockEnumToClassWithStaticMethodRector();
    }
    
    public function testGetRuleDefinition(): void
    {
        $ruleDefinition = $this->rector->getRuleDefinition();
        
        $this->assertInstanceOf(MockSymplifyRuleDefinition::class, $ruleDefinition);
        $this->assertEquals('将 PHP 8 原生枚举转换为普通类实现', $ruleDefinition->getDescription());
    }
    
    public function testGetNodeTypes(): void
    {
        $nodeTypes = $this->rector->getNodeTypes();
        
        $this->assertIsArray($nodeTypes);
        $this->assertContains(Enum_::class, $nodeTypes);
    }
    
    public function testRefactorWithBasicEnum(): void
    {
        // 创建一个基本的枚举节点
        $enumNode = new Enum_(
            new Node\Identifier('Status'),
            [
                'stmts' => [
                    new Node\Stmt\EnumCase(
                        new Node\Identifier('Pending'),
                        null
                    ),
                    new Node\Stmt\EnumCase(
                        new Node\Identifier('Active'),
                        null
                    ),
                    new Node\Stmt\EnumCase(
                        new Node\Identifier('Inactive'),
                        null
                    ),
                ]
            ]
        );
        $enumNode->namespacedName = new Node\Name\FullyQualified('App\Enum\Status');
        
        $result = $this->rector->refactor($enumNode);
        
        $this->assertInstanceOf(Node\Stmt\Class_::class, $result);
        $this->assertEquals('Status', $result->name->name);
        $this->assertTrue($result->isFinal());
        
        // 检查是否生成了必要的方法
        $methodNames = array_map(function ($stmt) {
            return $stmt instanceof Node\Stmt\ClassMethod ? $stmt->name->name : null;
        }, $result->stmts);
        
        $methodNames = array_filter($methodNames);
        
        $this->assertContains('__construct', $methodNames);
        $this->assertContains('Pending', $methodNames);
        $this->assertContains('Active', $methodNames);
        $this->assertContains('Inactive', $methodNames);
        $this->assertContains('cases', $methodNames);
        $this->assertContains('from', $methodNames);
        $this->assertContains('tryFrom', $methodNames);
    }
    
    public function testRefactorWithBackedEnum(): void
    {
        // 创建一个带标量类型的枚举节点
        $enumNode = new Enum_(
            new Node\Identifier('Status'),
            [
                'stmts' => [
                    new Node\Stmt\EnumCase(
                        new Node\Identifier('Pending'),
                        new Node\Scalar\String_('pending')
                    ),
                    new Node\Stmt\EnumCase(
                        new Node\Identifier('Active'),
                        new Node\Scalar\String_('active')
                    ),
                    new Node\Stmt\EnumCase(
                        new Node\Identifier('Inactive'),
                        new Node\Scalar\String_('inactive')
                    ),
                ]
            ]
        );
        $enumNode->namespacedName = new Node\Name\FullyQualified('App\Enum\Status');
        $enumNode->scalarType = new Node\Identifier('string');
        
        $result = $this->rector->refactor($enumNode);
        
        $this->assertInstanceOf(Node\Stmt\Class_::class, $result);
        
        // 检查属性是否具有正确的类型
        $properties = array_filter($result->stmts, function ($stmt) {
            return $stmt instanceof Node\Stmt\Property;
        });
        
        $valueProperty = null;
        foreach ($properties as $property) {
            if ($property->props[0]->name->name === 'value') {
                $valueProperty = $property;
                break;
            }
        }
        
        $this->assertNotNull($valueProperty);
        $this->assertEquals('string', $valueProperty->type->name);
    }
} 