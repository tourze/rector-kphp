<?php

declare(strict_types=1);

namespace Tourze\Rector4KPHP\Rule;

use PhpParser\Node;
use PHPStan\Analyser\MutatingScope;
use Rector\NodeManipulator\AssignManipulator;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * PHP是弱类型语言，变量可以重复使用，甚至变更类型
 * 我们在这里对变量的赋值语句做一次标记。
 * 如果一个变量已经存在，那我们就不应该再有同名变量，这里要对它重命名一次，没必要重复赋值
 */
final class MarkReassignVariableRector extends AbstractRector
{
    public function __construct(private readonly AssignManipulator $assignManipulator)
    {
    }

    private ?string $fileName = null;

    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    public function setFileName(?string $fileName): void
    {
        $this->fileName = $fileName;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('如果有重复的变量赋值语句，那我们尝试重命名', [
            new CodeSample('$a = 1; $a = 2;', '$a = 1; $a1 = 2;'),
        ]);
    }

    public function getNodeTypes(): array
    {
        return [
            Node\Expr\Variable::class,
        ];
    }

    private array $variableRenameMap = []; // 用于存储作用域中的变量重命名映射

    /**
     * @param Node\Expr\Variable $node
     * @return null
     */
    public function refactor(Node $node)
    {
        /** @var MutatingScope $scope */
        $scope = $node->getAttribute(AttributeKey::SCOPE);
        if (!$scope) {
            return null;
        }

        // 获取当前变量名
        $varName = $this->getName($node);
        if ($varName === null) {
            return null;
        }

        // 取得当前作用域的标识
        $scopeKey = $scope->getFunction()?->getName() ?? 'global';

        // 初始化当前作用域的重命名映射
        if (!isset($this->variableRenameMap[$scopeKey])) {
            $this->variableRenameMap[$scopeKey] = [];
        }

        // 如果变量已经存在了，一般就是在函数内，正在赋值的这个变量就是 param 才会这样
        if (in_array($varName, $scope->getDefinedVariables()) && !isset($this->variableRenameMap[$scopeKey][$varName])) {
            $this->variableRenameMap[$scopeKey][$varName] = $varName;
        }

        // 检查变量是否是赋值的左边部分
        //var_dump($this->assignManipulator->isLeftPartOfAssign($node));
        if ($this->assignManipulator->isLeftPartOfAssign($node)) {
            if (isset($this->variableRenameMap[$scopeKey][$varName])) {
                // 如果该变量已存在于映射中，生成新名称
                $newVarName = $this->generateNewVariableName($varName, $scopeKey);
                $node->name = $newVarName; // 更新当前节点的变量名
            } else {
                // 第一次遇到变量，记录变量到映射中
                $this->variableRenameMap[$scopeKey][$varName] = $varName;
            }
        } else {
            // 如果是变量的引用部分，检查是否需要替换为新的变量名
            if (isset($this->variableRenameMap[$scopeKey][$varName])) {
                $node->name = $this->variableRenameMap[$scopeKey][$varName];
            }
        }

        return $node;
    }

    private function generateNewVariableName(string $baseName, string $scopeKey): string
    {
        // 为变量生成唯一的新名称
        $index = 1;
        while (in_array("{$baseName}__i__{$index}", $this->variableRenameMap[$scopeKey], true)) {
            $index++;
        }
        $newName = "{$baseName}__i__{$index}";
        $this->variableRenameMap[$scopeKey][$baseName] = $newName;

        return $newName;
    }
}
