<?php

namespace Tourze\Rector4KPHP\Rule;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\Equal;
use PhpParser\Node\Stmt\Break_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Switch_;
use PhpParser\Node\Stmt\While_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class SwitchToWhileIfRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Change switch to while + if statements', [
            new CodeSample(
                <<<'CODE_SAMPLE'
    $i="abc";
    switch ($i) {
        case "ab":
            echo "This doesn't work... :(
";
            break;
        case "abcd":
            echo "This works!
";
            break;
        case "blah":
            echo "Hmmm, no worki
";
            break;
        default:
            echo "Inner default...
";
    }
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
    $i="abc";
    while (true) {
        if ($i == "ab") {
            echo "This doesn't work... :(
";
            break;
        }
        if ($i == "abcd") {
            echo "This works!
";
            break;
        }
        if ($i == "blah") {
            echo "Hmmm, no worki
";
            break;
        }
        echo "Inner default...
";
        break;
    }
CODE_SAMPLE
            ),
        ]);
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Switch_) {
            return null;
        }

        $newStatements = [];

        // Create a while(true) loop
        $whileNode = new While_($this->nodeFactory->createTrue());

        foreach ($node->cases as $case) {
            if ($case->cond !== null) {
                $condition = new Equal($node->cond, $case->cond);
                $ifNode = new If_($condition);
                $ifNode->stmts = $case->stmts;
                $ifNode->stmts[] = new Break_();
                $newStatements[] = $ifNode;
            } else {
                // Default case
                $newStatements = array_merge($newStatements, $case->stmts);
                $newStatements[] = new Break_();
            }
        }

        $whileNode->stmts = $newStatements;

        return $whileNode;
    }

    public function getNodeTypes(): array
    {
        return [Switch_::class];
    }
}
