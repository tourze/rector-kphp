<?php

declare (strict_types=1);

namespace Tourze\Rector4KPHP\Rule;

use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Name;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Webmozart\Assert\Assert;

/**
 * 修改常量使用到的地方
 * 这里我们直接把常量代表的标量替换回去，没必要再维护的了
 */
final class RenameConstantRector extends AbstractRector implements ConfigurableRectorInterface
{
    /**
     * @var array<string, string>
     */
    private array $oldToNewConstants = [];

    public function getRuleDefinition() : RuleDefinition
    {
        return new RuleDefinition('Replace constant by new ones', [new ConfiguredCodeSample(<<<'CODE_SAMPLE'
final class SomeClass
{
    public function run()
    {
        return MYSQL_ASSOC;
    }
}
CODE_SAMPLE
            , <<<'CODE_SAMPLE'
final class SomeClass
{
    public function run()
    {
        return MYSQLI_ASSOC;
    }
}
CODE_SAMPLE
            , ['MYSQL_ASSOC' => 'MYSQLI_ASSOC', 'OLD_CONSTANT' => 'NEW_CONSTANT'])]);
    }

    public function getNodeTypes() : array
    {
        return [ConstFetch::class];
    }

    /**
     * @param ConstFetch $node
     */
    public function refactor(Node $node) : ?Node
    {
        foreach ($this->oldToNewConstants as $oldConstant => $newConstant) {
            if (!$this->isName($node->name, $oldConstant)) {
                continue;
            }

            $node->name = new Name('\\' . trim($newConstant, '\\'));
            return $node;
//            // 这里我们直接替换为具体标量，没必要中转一层
//            $v = constant($newConstant);
//            $t = gettype($v);
//            return match ($t) {
//                'double' => new Node\Scalar\Float_($v),
//                'integer' => new Node\Scalar\Int_($v),
//                'string' => new Node\Scalar\String_($v),
//                default => $node,
//            };
        }
        return null;
    }

    /**
     * @param mixed[] $configuration
     */
    public function configure(array $configuration) : void
    {
        Assert::allString(\array_keys($configuration));
        Assert::allString($configuration);
        // 我们不需要判断这个
//        foreach ($configuration as $oldConstant => $newConstant) {
//            RectorAssert::constantName($oldConstant);
//            RectorAssert::constantName($newConstant);
//        }
        /** @var array<string, string> $configuration */
        $this->oldToNewConstants = $configuration;
    }
}
