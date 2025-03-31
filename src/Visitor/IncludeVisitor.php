<?php

namespace Tourze\Rector4KPHP\Visitor;

use PhpParser\{Node, Node\Expr\BinaryOp\Concat, Node\Scalar\String_, NodeTraverser};
use PhpParser\Node\Scalar\MagicConst;
use PhpParser\NodeVisitorAbstract;
use Rector\PhpDocParser\PhpParser\SmartPhpParser;

class IncludeVisitor extends NodeVisitorAbstract
{
    public function __construct(
        private readonly SmartPhpParser $parser,
        private readonly string $fileName,
    )
    {
    }

    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Expression && $node->expr instanceof Node\Expr\Include_) {
            // 处理 _include 逻辑
            // 尝试读取文件路径，读取到的话我们就再解析一次，把文件内容返回去
            $includePath = $this->resolveIncludePath($node->expr->expr, $this->fileName);
            if ($includePath === null || !file_exists($includePath)) {
                return NodeTraverser::REMOVE_NODE;
            }

            $ast = $this->parser->parseFile($includePath);

            // 这里还不够，要递归处理一次
            $traverser = new NodeTraverser();
            $traverser->addVisitor(new IncludeVisitor($this->parser, $includePath));
            $ast = $traverser->traverse($ast);
            if (empty($ast)) {
                return NodeTraverser::REMOVE_NODE;
            }
            return $ast;
        }
        return null;
    }

    /**
     * 解析出真实路径
     */
    private function resolveIncludePath(Node $expr, string $fileName): ?string
    {
        if ($expr instanceof String_) {
            // If the include is a simple string, resolve it directly
            return $this->resolveRelativePath($expr->value, $fileName);
        } elseif ($expr instanceof Concat) {
            // If the include is a concatenation, try to resolve it
            $left = $this->resolveIncludePath($expr->left, $fileName);
            $right = $this->resolveIncludePath($expr->right, $fileName);

            if ($left !== null && $right !== null) {
                return $left . $right;
            }
        } elseif ($expr instanceof MagicConst\Dir) {
            // If the include uses __DIR__, resolve it to the current directory
            return dirname($fileName);
        }

        // If unable to resolve, return null
        return null;
    }

    private function resolveRelativePath(string $path, string $fileName): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }
        return dirname($fileName) . DIRECTORY_SEPARATOR . $path;
    }
}
