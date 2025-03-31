<?php

namespace Tourze\Rector4KPHP\Rule;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassLike;
use Rector\PhpParser\Node\BetterNodeFinder;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

class ChangeMethodCallToKphpCompatCallRector extends AbstractRector
{
    public function __construct(
        private readonly BetterNodeFinder $betterNodeFinder,
    )
    {
    }

    public function getNodeTypes(): array
    {
        return [MethodCall::class];
    }

    public function refactor(Node $node):?Node
    {
        if ($node instanceof MethodCall &&!$this->isMethodExistingInClass($node, $node->var)) {
            $oldName = $node->name->name;
            var_dump($oldName);

            $newArray = [];
            foreach ($node->args as $arg) {
                $newArray[] = new ArrayItem($arg->value);
            }

            $node->name->name = 'kphp_compat_call';
            $node->args = [
                new Arg(new String_($oldName)),
                new Arg(new Array_($newArray)),
            ];

            return $node;
        }

        return null;
    }

    private function isMethodExistingInClass(MethodCall $methodCall, Node $classNode): bool
    {
        if (!$classNode instanceof Node\Expr\Variable) {
            return false;
        }

        $class = $this->betterNodeFinder->findFirstInstanceOf($classNode, ClassLike::class);
        if (!$class) {
            return false;
        }

        $methodName = $this->getName($methodCall->name);

        return $class->getMethod($methodName) !== null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('把 __call 重命名为 kphp_compat_call', []);
    }
}
