<?php

declare(strict_types=1);

namespace Tourze\Rector4KPHP\Rule;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use Rector\Rector\AbstractRector;
use SebastianBergmann\Type\NeverType;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * 判断到函数/方法的返回值是never的话，就增加一个注释 `@kphp-no-return`
 */
final class AddKphpNoReturnForNeverRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add @kphp-no-return annotation for methods or functions returning never',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
function test(): never {
    throw new Exception();
}
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
/**
 * @kphp-no-return
 */
function test(): never {
    throw new Exception();
}
CODE_SAMPLE
                ),
            ]
        );
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [Function_::class, ClassMethod::class];
    }

    /**
     * @param Function_|ClassMethod $node
     */
    public function refactor(Node $node): ?Node
    {
        // Check if the function or method has a return type
        if ($node->getReturnType() === null) {
            return null;
        }

        // Resolve the return type of the function/method
        //$returnType = $this->nodeTypeResolver->resolve($node->getReturnType());
        $returnType = $node->getReturnType();

        // Check if the return type is "never"
        if (!$returnType instanceof NeverType) {
            return null;
        }

        // Add the @kphp-no-return annotation if not already present
        $docComment = $node->getDocComment();
        $newDocText = '/** @kphp-no-return */' . PHP_EOL;
        if ($docComment !== null) {
            $newDocText .= $docComment->getText();
        }

        $node->setDocComment(new \PhpParser\Comment\Doc($newDocText));

        return $node;
    }
}
