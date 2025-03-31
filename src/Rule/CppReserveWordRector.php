<?php

namespace Tourze\Rector4KPHP\Rule;

use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\Exception\PoorDocumentationException;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

class CppReserveWordRector extends AbstractRector
{
    const PREFIX = 'cpp_reserve_';

    const RESERVED_WORDS = [
        "asm", "else", "new",
        //"this",
        "auto", "enum", "operator", "throw",
        "bool", "explicit", "private", "true",
        "break", "export", "protected", "try",
        "case", "extern", "public", "typedef",
        "catch", "false", "register", "typeid",
        "char", "float", "reinterpret_cast", "typename",
        "class", "for", "return", "union",
        "const", "friend", "short", "unsigned",
        "const_cast", "goto", "signed", "using",
        "continue", "if", "sizeof", "virtual",
        "default", "inline", "static", "void",
        "delete", "int", "static_cast", "volatile",
        "do", "long", "struct", "wchar_t",
        "double", "mutable", "switch", "while",
        "dynamic_cast", "namespace", "template",
    ];

    /**
     * @throws PoorDocumentationException
     */
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            '修改变量名，避开C++保留词', [
                new CodeSample(
                // code before
                    '$float = 1;',
                    // code after
                    '$cpp_reserve_float = 1',
                ),
            ]
        );
    }

    public function getNodeTypes(): array
    {
        // https://github.com/rectorphp/php-parser-nodes-docs/
        return [Variable::class];
    }

    public function refactor(Node $node): ?Node
    {
        $name = $this->getName($node);
        if (!in_array($name, self::RESERVED_WORDS, true)) {
            return null;
        }

        $node->name = self::PREFIX . $node->name;
        return $node;
    }
}
