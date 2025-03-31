<?php

namespace Tourze\Rector4KPHP\Rule;

use PhpParser\Node;
use PhpParser\Node\Scalar\String_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\Exception\PoorDocumentationException;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * 按照C++的字符串要求，都转为双引号字符串
 */
class CppDoubleStyleStringRector extends AbstractRector
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
                    "\$a = 'TEST';",
                    // code after
                    "\$a = \"TEST\";",
                ),
            ]
        );
    }

    public function getNodeTypes(): array
    {
        // https://github.com/rectorphp/php-parser-nodes-docs/
        return [String_::class];
    }

    /**
     * @param String_ $node
     * @return String_
     */
    public function refactor(Node $node): String_
    {
        return new String_($node->value, [
            'kind' => String_::KIND_DOUBLE_QUOTED,
        ]);
    }
}
