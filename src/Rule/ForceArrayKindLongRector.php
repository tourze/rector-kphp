<?php

declare(strict_types=1);

namespace Tourze\Rector4KPHP\Rule;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

class ForceArrayKindLongRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [Array_::class];
    }

    public function refactor(Node|Array_ $node): ?Node
    {
        $node->setAttribute('kind', Array_::KIND_LONG);
        return $node;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            '强制数组使用长格式语法',
            []
        );
    }
}
