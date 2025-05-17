<?php

namespace Tourze\Rector4KPHP\Tests\Mock;

use PhpParser\Node;
use PhpParser\Node\Expr\Variable;

class MockMarkReassignVariableRector extends MockRector
{
    private MockAssignManipulator $assignManipulator;
    private ?string $fileName = null;
    private array $variableRenameMap = [];
    
    public function __construct(MockAssignManipulator $assignManipulator)
    {
        $this->assignManipulator = $assignManipulator;
    }
    
    public function getFileName(): ?string
    {
        return $this->fileName;
    }
    
    public function setFileName(?string $fileName): void
    {
        $this->fileName = $fileName;
    }
    
    public function getRuleDefinition(): MockSymplifyRuleDefinition
    {
        return new MockSymplifyRuleDefinition('如果有重复的变量赋值语句，那我们尝试重命名');
    }
    
    public function getNodeTypes(): array
    {
        return [Variable::class];
    }
    
    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof Variable) {
            return null;
        }
        
        // 获取作用域属性，若没有则返回空
        $scope = $node->getAttribute('scope');
        if (!$scope) {
            return null;
        }
        
        // 获取变量名
        $varName = $node->name;
        if (!is_string($varName)) {
            return null;
        }
        
        // 使用作用域中的函数名作为键，如果没有则用'global'
        $scopeKey = $scope->getFunction()?->getName() ?? 'global';
        
        // 初始化变量重命名映射
        if (!isset($this->variableRenameMap[$scopeKey])) {
            $this->variableRenameMap[$scopeKey] = [];
        }
        
        // 检查变量是否已定义
        $definedVars = $scope->getDefinedVariables();
        if (in_array($varName, $definedVars) && !isset($this->variableRenameMap[$scopeKey][$varName])) {
            $this->variableRenameMap[$scopeKey][$varName] = $varName;
        }
        
        // 检查是否是赋值语句左侧
        if ($this->assignManipulator->isLeftPartOfAssign($node)) {
            if (isset($this->variableRenameMap[$scopeKey][$varName])) {
                // 生成新的变量名
                $newVarName = $this->generateNewVariableName($varName, $scopeKey);
                $node->name = $newVarName;
            } else {
                // 记录变量
                $this->variableRenameMap[$scopeKey][$varName] = $varName;
            }
        } else {
            // 非赋值语句，检查是否需要替换
            if (isset($this->variableRenameMap[$scopeKey][$varName])) {
                $node->name = $this->variableRenameMap[$scopeKey][$varName];
            }
        }
        
        return $node;
    }
    
    private function generateNewVariableName(string $baseName, string $scopeKey): string
    {
        $index = 1;
        while (in_array("{$baseName}__i__{$index}", $this->variableRenameMap[$scopeKey], true)) {
            $index++;
        }
        $newName = "{$baseName}__i__{$index}";
        $this->variableRenameMap[$scopeKey][$baseName] = $newName;
        
        return $newName;
    }
} 