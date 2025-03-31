<?php

declare(strict_types=1);

namespace Tourze\Rector4KPHP\Rule;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class RemoveNestingRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Convert min/max calls with more than 2 arguments to nested calls', [
            new CodeSample(
                'max(1, 2, 3);',
                'max(max(1, 2), 3);'
            ),
            new CodeSample(
                'min(5, 4, 3, 2);',
                'min(min(min(5, 4), 3), 2);'
            ),
        ]);
    }

    public function getNodeTypes(): array
    {
        return [FuncCall::class];
    }

    private array $functions = [
        'min',
        'max',
    ];

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof FuncCall) {
            return null;
        }

        if (!$node->name instanceof Name) {
            return null;
        }

        $functionName = strtolower((string) $node->name);
        if (!in_array($functionName, $this->functions, true)) {
            return null;
        }

        if (count($node->args) <= 2) {
            return null;
        }

        // Convert to nested calls
        return $this->convertToNestedCalls($node);
    }

    private function convertToNestedCalls(FuncCall $funcCall): FuncCall
    {
        $functionName = $funcCall->name;
        $args = $funcCall->args;

        // Start with the first two arguments
        $nestedCall = new FuncCall($functionName, [$args[0], $args[1]]);

        // Iterate through the remaining arguments and create nested calls
        for ($i = 2; $i < count($args); $i++) {
            $nestedCall = new FuncCall($functionName, [$nestedCall, $args[$i]]);
        }

        return $nestedCall;
    }
}
