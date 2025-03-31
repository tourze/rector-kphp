<?php

namespace Tourze\Rector4KPHP\Rule;

use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class AddIntTypeToIncrementedParamsRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Add int type to parameters that are incremented, decremented, or used in integer-like operations within the function', [
            new CodeSample(
                <<<'CODE_SAMPLE'
function test($b): int {
    $b++;
    return $b;
}
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
function test(int $b): int {
    $b++;
    return $b;
}
CODE_SAMPLE
            ),
        ]);
    }

    public function getNodeTypes(): array
    {
        return [Function_::class, ClassMethod::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof Function_ && !$node instanceof ClassMethod) {
            return null;
        }

        $intLikelyParams = [];

        // Find parameters that are used in int-like operations
        $this->traverseNodesWithCallable($node->stmts, function (Node $subNode) use (&$intLikelyParams) {
            // Check increment/decrement operators
            if (
                $subNode instanceof Node\Expr\PreInc ||
                $subNode instanceof Node\Expr\PostInc ||
                $subNode instanceof Node\Expr\PreDec ||
                $subNode instanceof Node\Expr\PostDec
            ) {
                if ($subNode->var instanceof Variable) {
                    $varName = $this->getName($subNode->var);
                    if ($varName !== null) {
                        $intLikelyParams[] = $varName;
                    }
                }
            }

            // Check compound assignment like += and -=
            if ($subNode instanceof Node\Expr\AssignOp\Plus || $subNode instanceof Node\Expr\AssignOp\Minus) {
                if ($subNode->var instanceof Variable) {
                    $varName = $this->getName($subNode->var);
                    if ($varName !== null) {
                        $intLikelyParams[] = $varName;
                    }
                }
            }

            // Check arithmetic operations with integers
            if ($subNode instanceof Node\Expr\BinaryOp) {
                if (($subNode->left instanceof Variable && $this->isIntegerType($subNode->right)) ||
                    ($subNode->right instanceof Variable && $this->isIntegerType($subNode->left))
                ) {
                    $varName = $this->getName($subNode->left instanceof Variable ? $subNode->left : $subNode->right);
                    if ($varName !== null) {
                        $intLikelyParams[] = $varName;
                    }
                }
            }

            // Check comparisons with integers
            if ($subNode instanceof Node\Expr\BinaryOp\Greater ||
                $subNode instanceof Node\Expr\BinaryOp\GreaterOrEqual ||
                $subNode instanceof Node\Expr\BinaryOp\Smaller ||
                $subNode instanceof Node\Expr\BinaryOp\SmallerOrEqual ||
                $subNode instanceof Node\Expr\BinaryOp\Equal
            ) {
                if (($subNode->left instanceof Variable && $this->isIntegerType($subNode->right)) ||
                    ($subNode->right instanceof Variable && $this->isIntegerType($subNode->left))
                ) {
                    $varName = $this->getName($subNode->left instanceof Variable ? $subNode->left : $subNode->right);
                    if ($varName !== null) {
                        $intLikelyParams[] = $varName;
                    }
                }
            }

            return null;
        });

        // Add int type hint to the parameters that match the conditions
        foreach ($node->params as $param) {
            if ($param instanceof Param && $param->var instanceof Variable) {
                // Skip if the type is already defined
                if ($param->type) {
                    continue;
                }

                $paramName = $this->getName($param->var);
                if (in_array($paramName, $intLikelyParams, true)) {
                    $param->type = new Node\Identifier('int');
                }
            }
        }

        return $node;
    }

    private function isIntegerType(Node $node): bool
    {
        return $node instanceof Node\Scalar\LNumber;
    }
}
