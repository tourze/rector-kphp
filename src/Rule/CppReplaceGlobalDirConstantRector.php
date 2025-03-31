<?php

namespace Tourze\Rector4KPHP\Rule;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\MagicConst\Dir;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\Exception\PoorDocumentationException;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * 有一些全局变量，我们改为函数调用
 */
class CppReplaceGlobalDirConstantRector extends AbstractRector
{
    /**
     * @throws PoorDocumentationException
     */
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            '强制改为双引号字符串', [
                new CodeSample(
                    // code before
                    "var_dump(__DIR__)",
                    // code after
                    "var_dump(getcwd())",
                ),
            ]
        );
    }

    public function getNodeTypes(): array
    {
        // https://github.com/rectorphp/php-parser-nodes-docs/
        return [Dir::class];
    }

    public function refactor(Node $node): Node
    {
        return new FuncCall(new Name('getcwd'));
    }
}
