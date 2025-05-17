<?php

namespace Tourze\Rector4KPHP\Tests\Visitor;

use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PHPUnit\Framework\TestCase;
use Tourze\Rector4KPHP\Visitor\CodeCollector;

class CodeCollectorTest extends TestCase
{
    private CodeCollector $collector;
    
    protected function setUp(): void
    {
        $this->collector = new CodeCollector();
    }
    
    public function testInitialState(): void
    {
        $this->assertEmpty($this->collector->getUsedClasses());
        $this->assertEmpty($this->collector->getUsedFunctions());
        $this->assertEmpty($this->collector->getUsedConstants());
        $this->assertEmpty($this->collector->getIncludedFiles());
    }
    
    public function testClassConstFetchCollection(): void
    {
        // 创建类常量访问节点
        $classConstNode = new Expr\ClassConstFetch(
            new Name('TestClass'),
            'TEST_CONST'
        );
        
        // 模拟访问节点
        $this->collector->enterNode($classConstNode);
        
        // 检查结果
        $usedClasses = $this->collector->getUsedClasses();
        $this->assertCount(1, $usedClasses);
        $this->assertEquals('TestClass', $usedClasses[0]);
    }
    
    public function testStaticCallCollection(): void
    {
        // 创建静态方法调用节点
        $staticCallNode = new Expr\StaticCall(
            new Name('TestClass'),
            'testMethod',
            []
        );
        
        // 模拟访问节点
        $this->collector->enterNode($staticCallNode);
        
        // 检查结果
        $usedClasses = $this->collector->getUsedClasses();
        $this->assertCount(1, $usedClasses);
        $this->assertEquals('TestClass', $usedClasses[0]);
    }
    
    public function testNewExpressionCollection(): void
    {
        // 创建 new 表达式节点
        $newExprNode = new Expr\New_(
            new Name('TestClass'),
            []
        );
        
        // 模拟访问节点
        $this->collector->enterNode($newExprNode);
        
        // 检查结果
        $usedClasses = $this->collector->getUsedClasses();
        $this->assertCount(1, $usedClasses);
        $this->assertEquals('TestClass', $usedClasses[0]);
    }
    
    public function testFunctionCallCollection(): void
    {
        // 创建函数调用节点
        $funcCallNode = new Expr\FuncCall(
            new Name('testFunction'),
            []
        );
        
        // 模拟访问节点
        $this->collector->enterNode($funcCallNode);
        
        // 检查结果
        $usedFunctions = $this->collector->getUsedFunctions();
        $this->assertCount(1, $usedFunctions);
        $this->assertEquals('testFunction', $usedFunctions[0]);
    }
    
    public function testConstFetchCollection(): void
    {
        // 创建常量访问节点
        $constNode = new Expr\ConstFetch(
            new Name('TEST_CONST')
        );
        
        // 模拟访问节点
        $this->collector->enterNode($constNode);
        
        // 检查结果
        $usedConstants = $this->collector->getUsedConstants();
        $this->assertCount(1, $usedConstants);
        $this->assertEquals('TEST_CONST', $usedConstants[0]);
    }
    
    public function testBuiltInConstantsIgnored(): void
    {
        // 创建访问内置常量的节点
        $trueNode = new Expr\ConstFetch(new Name('true'));
        $falseNode = new Expr\ConstFetch(new Name('false'));
        $nullNode = new Expr\ConstFetch(new Name('null'));
        
        // 模拟访问节点
        $this->collector->enterNode($trueNode);
        $this->collector->enterNode($falseNode);
        $this->collector->enterNode($nullNode);
        
        // 检查结果 - 应该忽略 true, false, null
        $usedConstants = $this->collector->getUsedConstants();
        $this->assertCount(0, $usedConstants);
    }
} 