<?php

declare(strict_types=1);

namespace Tourze\Rector4KPHP\Rule;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class AddScaleToBcFunctionsRector extends AbstractRector
{
    private array $bcFunctions = [
        'bcadd' => 3,
        'bcsub' => 3,
        'bcmul' => 3,
        'bcdiv' => 3,
        'bccomp' => 3,
        'bcpow' => 3,
        'bcsqrt' => 2,
        'bcmod' => 3,
        'bcpowmod' => 4,
    ];

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Add missing $scale argument to bc functions if not provided', []);
    }

    public function getNodeTypes(): array
    {
        return [FuncCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        // Ensure the node is a function call
        if (!$node instanceof FuncCall) {
            return null;
        }

        // Ensure it's one of the target bc functions
        if (!$this->isNames($node, array_keys($this->bcFunctions))) {
            return null;
        }

        // Check if the function already has 3 arguments
        if (count($node->args) >= $this->bcFunctions[$node->name->toString()]) {
            return null; // Already has $scale argument
        }

        // Add bcscale() as the third argument
        $node->args[] = new Arg(new FuncCall(new Node\Name('bcscale')));

        return $node;
    }
}
