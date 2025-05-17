<?php

namespace Tourze\Rector4KPHP\Tests\Rule;

use PhpParser\Node\Expr\Variable;
use PHPUnit\Framework\TestCase;
use Tourze\Rector4KPHP\Tests\Mock\MockAssignManipulator;
use Tourze\Rector4KPHP\Tests\Mock\MockMarkReassignVariableRector;
use Tourze\Rector4KPHP\Tests\Mock\MockScope;
use Tourze\Rector4KPHP\Tests\Mock\MockSymplifyRuleDefinition;

class MarkReassignVariableRectorTest extends TestCase
{
    private MockMarkReassignVariableRector $rector;
    private MockAssignManipulator $assignManipulator;
    
    protected function setUp(): void
    {
        $this->assignManipulator = new MockAssignManipulator();
        $this->rector = new MockMarkReassignVariableRector($this->assignManipulator);
    }
    
    public function testGetRuleDefinition(): void
    {
        $ruleDefinition = $this->rector->getRuleDefinition();
        
        $this->assertInstanceOf(MockSymplifyRuleDefinition::class, $ruleDefinition);
        $this->assertEquals('如果有重复的变量赋值语句，那我们尝试重命名', $ruleDefinition->getDescription());
    }
    
    public function testGetNodeTypes(): void
    {
        $nodeTypes = $this->rector->getNodeTypes();
        
        $this->assertIsArray($nodeTypes);
        $this->assertContains(Variable::class, $nodeTypes);
    }
    
    public function testFileNameAccessors(): void
    {
        $this->assertNull($this->rector->getFileName());
        
        $testFileName = '/path/to/test.php';
        $this->rector->setFileName($testFileName);
        
        $this->assertEquals($testFileName, $this->rector->getFileName());
    }
    
    public function testRefactorWithVariableNotInAssignment(): void
    {
        // 创建一个变量节点，但不是赋值语句的左边部分
        $variableNode = new Variable('testVar');
        $this->assignManipulator->setIsLeftPartOfAssign($variableNode, false);
        
        // 设置作用域
        $scope = new MockScope(['testVar']);
        $variableNode->setAttribute('scope', $scope);
        
        // 运行 refactor
        $result = $this->rector->refactor($variableNode);
        
        // 验证结果
        $this->assertSame($variableNode, $result);
        // 没有重命名，因为它不是赋值的左边部分
        $this->assertEquals('testVar', $result->name);
    }
    
    public function testRefactorWithVariableInAssignment(): void
    {
        // 创建一个变量节点，作为赋值语句的左边部分
        $variableNode = new Variable('testVar');
        $this->assignManipulator->setIsLeftPartOfAssign($variableNode, true);
        
        // 设置作用域
        $scope = new MockScope(['testVar']);
        $variableNode->setAttribute('scope', $scope);
        
        // 运行 refactor
        $result = $this->rector->refactor($variableNode);
        
        // 验证结果
        $this->assertSame($variableNode, $result);
        // 变量应该被重命名，因为它已经在作用域中存在
        $this->assertStringStartsWith('testVar__i__', $result->name);
    }
    
    public function testVariableWithoutScope(): void
    {
        $variableNode = new Variable('testVar');
        
        // 不设置作用域属性
        
        // 运行 refactor
        $result = $this->rector->refactor($variableNode);
        
        // 应该返回 null，因为没有作用域信息
        $this->assertNull($result);
    }
} 