<?php

declare(strict_types=1);

namespace Tourze\Rector4KPHP\Rule;

use PhpParser\Node;
use PhpParser\Node\Const_ as NodeConst;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Const_;
use PhpParser\Node\Stmt\Expression;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class DefineToConstRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Convert define() to const', []);
    }

    public function getNodeTypes(): array
    {
        return [Expression::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Expression) {
            return null;
        }

        if (! $node->expr instanceof FuncCall) {
            return null;
        }

        $funcCall = $node->expr;

        if (! $this->isName($funcCall, 'define')) {
            return null;
        }

        if (count($funcCall->args) < 2) {
            return null;
        }

        $nameArg = $funcCall->args[0]->value;
        $valueArg = $funcCall->args[1]->value;

        if (! $nameArg instanceof String_) {
            return null;
        }

        $constName = $nameArg->value;

        $const = new NodeConst($constName, $valueArg);
        return new Const_([$const]);
    }
}
