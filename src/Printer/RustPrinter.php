<?php

namespace Tourze\Rector4KPHP\Printer;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt;
use PhpParser\PrettyPrinter\Standard;

/**
 * 把PHP转为Rust语法
 */
class RustPrinter extends Standard
{
    private static int $counter = 0;

    /**
     * @var array 头部通用的代码
     */
    private array $headerBlocks = [];

    /**
     * @var array 入口逻辑
     */
    private array $mainBlocks = [];

    /**
     * @var array 收集所有全局变量名
     */
    private array $globalVariableNames = [];

    /**
     * @var array 记录函数的参数，是否是nullable，用于在生成调用参数时使用
     */
    private array $functionArgumentNullable = [];

    /**
     * @var array 记录函数返回值是否可以为null
     */
    private array $functionReturnNullable = [];

    public function popCounter(): int
    {
        static::$counter++;
        return static::$counter;
    }

    /**
     * 生成代码
     */
    public function generate(string $fileName, array $stmts): string
    {
        // PHP没明确的main函数，我们这里需要自己模拟一次main
        foreach ($stmts as $stmt) {
            if (in_array($stmt::class, [
                Stmt\Echo_::class,
                Stmt\Return_::class,
                Stmt\Expression::class,
                Stmt\If_::class,
                Stmt\Switch_::class,
                Stmt\While_::class,
                Stmt\For_::class,
                Stmt\Foreach_::class,
                Stmt\Do_::class,
            ])) {
                $this->mainBlocks[] = $this->prettyPrint([$stmt]);
            } else {
                $this->headerBlocks[] = $this->prettyPrint([$stmt]);
            }
        }

        $topBlocks = implode("\n", $this->headerBlocks);
        $mainBlocks = implode("\n    ", $this->mainBlocks);
        return <<<RUST
// source: {$fileName}

{$topBlocks}

fn main() {
    {$mainBlocks}
}
RUST;
    }

    protected function pScalar_String(Scalar\String_ $node): string
    {
        // Rust中字符串，都是用双引号的
        $kind = $node->getAttribute('kind', Scalar\String_::KIND_SINGLE_QUOTED);
        if ($kind === Scalar\String_::KIND_SINGLE_QUOTED) {
            $node->setAttribute('kind', Scalar\String_::KIND_DOUBLE_QUOTED);
        }
        return parent::pScalar_String($node);
    }

    protected function pScalar_Float(Scalar\Float_ $node): string
    {
        return strval($node->getAttribute('rawValue', $node->value));
    }

    protected function pConst(Node\Const_ $node): string
    {
        // const ABC: &str = "abc"; 需要带上类型
        return "{$node->name}: {$this->guessValueType($node->value)} = {$this->p($node->value)}";
    }

    /**
     * 函数参数的定义需要同步
     */
    protected function pParam(Node\Param $node): string
    {
        return $this->pAttrGroups($node->attrGroups, true)
            . $this->pModifiers($node->flags)
            . 'mut '
            . $this->p($node->var)
            . ($node->type ? ': ' . $this->p($node->type) . ' ' : '')
            . ($node->default ? ' = ' . $this->p($node->default) : '')
            . ($node->hooks ? ' {' . $this->pStmts($node->hooks) . $this->nl . '}' : '');
    }

    protected function pNullableType(Node\NullableType $node): string
    {
        return 'Option<' . $this->p($node->type) . '>';
    }

    protected function pArg(Node\Arg $node): string
    {
        $value = $this->p($node->value);

        return ($node->name ? $node->name->toString() . ': ' : '')
            . ($node->byRef ? '&' : '') . ($node->unpack ? '...' : '')
            . $value;
    }

    /**
     * 猜测类型
     */
    private function guessValueType(Scalar|Expr\UnaryMinus $value): string
    {
        if ($value instanceof Scalar\String_) {
            return '&str';
        }

        if ($value instanceof Scalar\Int_) {
            return 'i64';
        }
        if ($value instanceof Expr\UnaryMinus) {
            return 'i64';
        }

        return '';
    }

    protected function pIdentifier(Node\Identifier|Expr\UnaryMinus $node): string
    {
        // 在 Rust 中没有单独的int类型，通常使用i32等具体的整数类型
        if ($node->name === 'int') {
            return 'i64';
        }
        if ($node->name === 'string') {
            return 'String';
        }
        return $node->name;
    }

    protected function pStmt_Const(Stmt\Const_ $node): string
    {
        $result = [];
        // 不能单行同时Const
        foreach ($node->consts as $const) {
            $result[] = 'const ' . $this->p($const) . ';';
        }
        return implode("\n", $result);
    }

    protected function pStmt_Echo(Stmt\Echo_ $node): string
    {
        $result = [];
        foreach ($node->exprs as $expr) {
            // TODO 这里的打印有问题，无法打印 Option<String>
            $result[] = "print!(\"{}\", {$this->p($expr)});";
        }
        return implode("\n", $result);
    }

    protected function pStmt_Return(Stmt\Return_ $node): string
    {
        if (null !== $node->expr) {
            $v = $this->p($node->expr);
            if ($node->expr instanceof Scalar\String_) {
                $v = "String::from($v)";
            }
            // 定位这个return语句最近的一个 Stmt\Function_ ，从这里确定他是否应该是 Option 的
            $functionStatement = $this->getParentFunctionStatement($node, Stmt\Function_::class);
            if ($functionStatement && $this->functionReturnNullable[$functionStatement->name->name]) {
                $v = "Some($v)";
            }
            return "return $v;";
        }
        return 'return;';
    }

    /**
     * @template T of object
     * @param Node\Stmt $node
     * @param class-string<T> $classType
     * @return T
     */
    private function getParentFunctionStatement(Node\Stmt $node, string $classType): ?Node\Stmt
    {
        $parent = $node->getAttribute('parent');
        if (!$parent) {
            return null;
        }

        if ($parent instanceof $classType) {
            return $parent;
        }
        return $this->getParentFunctionStatement($parent, $classType);
    }

    protected function pStmt_Function(Stmt\Function_ $node): string
    {
        $returnType = '';
        // main函数比较特别的，不能有返回值
        if (null !== $node->returnType && $node->name->name !== 'main') {
            $returnType = ' -> ' . $this->p($node->returnType);
        }

        // 记录函数返回值是否可以为null
        $this->functionReturnNullable[$node->name->name] = $node->returnType instanceof Node\NullableType;

        // 记录参数是否可以为null
        $this->functionArgumentNullable[$node->name->name] = [];
        foreach ($node->params as $k => $param) {
            $this->functionArgumentNullable[$node->name->name][$k] = $param->type instanceof Node\NullableType;
        }

        $body = $this->pStmts($node->stmts);
        // 如果一个函数的返回值是可能为空的，但是函数代码最后一行又不是return，那我们自动补充一个return None算了
        if ($node->returnType instanceof Node\NullableType) {
            $lastStmt = end($node->stmts);
            if (!$lastStmt instanceof Stmt\Return_) {
                $body .= $this->nl . "return None;";
            }
        }

        return $this->pAttrGroups($node->attrGroups)
            . 'fn ' . ($node->byRef ? '&' : '') . $node->name
            . '(' . $this->pMaybeMultiline($node->params, $this->phpVersion->supportsTrailingCommaInParamList()) . ')'
            . $returnType
            . $this->nl . '{' . $body . $this->nl . '}';
    }

    protected function pStmt_If(Stmt\If_ $node): string
    {
        $cond = $this->p($node->cond);
        if (is_numeric($cond)) {
            $cond = "$cond > 0";
        }

        return 'if ' . $cond . ' {'
            . $this->pStmts($node->stmts) . $this->nl . '}'
            . ($node->elseifs ? ' ' . $this->pImplode($node->elseifs, ' ') : '')
            . (null !== $node->else ? ' ' . $this->p($node->else) : '');
    }

    protected function pStmt_ElseIf(Stmt\ElseIf_ $node): string
    {
        $cond = $this->p($node->cond);
        if (is_numeric($cond)) {
            $cond = "$cond > 0";
        }

        return 'else if ' . $cond . ' {'
            . $this->pStmts($node->stmts) . $this->nl . '}';
    }

    protected function pStmt_While(Stmt\While_ $node): string
    {
        return 'while ' . $this->p($node->cond) . ' {'
            . $this->pStmts($node->stmts) . $this->nl . '}';
    }

    protected function pStmt_Switch(Stmt\Switch_ $node): string
    {
        // switch (a) { case 1: xxx; default: yyy; } 我们改用一个循环来做，这样子改动比较少
        return 'loop {'
            . $this->pStmts($node->cases) . $this->nl . '}';
    }

    protected function pStmt_Case(Stmt\Case_ $node): string
    {
        // case改为if
        if (null !== $node->cond) {
            /** @var Stmt\Switch_ $parent */
            $parent = $node->getAttribute('parent');
            return 'if ' . $this->p($parent->cond) . ' == ' . $this->p($node->cond) . ' {'
                . $this->pStmts($node->stmts) . $this->nl . '}';
        }
        // 默认分支我们直接输出即可
        return $this->pStmts($node->stmts);
    }

    /**
     * Rust的for语法跟PHP差异有点大，我们这里用while来模拟
     */
    protected function pStmt_For(Stmt\For_ $node): string
    {
        return $this->nl . $this->pCommaSeparated($node->init) . ';'
            . $this->nl . 'while '
            . $this->pCommaSeparated($node->cond)
            . ' {'
            . $this->pStmts($node->stmts)
            . $this->nl
            . $this->pCommaSeparated($node->loop) . ';'
            . $this->nl
            . '}';
    }

    protected function pStmt_Do(Stmt\Do_ $node): string
    {
        // do { ... } while 这种语法，用loop来模拟;
        return 'loop {'
            . $this->pStmts($node->stmts) . $this->nl
            . "if !({$this->p($node->cond)}) { break; }"
            . $this->nl
            . '}';
    }

    protected function pStmt_Global(Stmt\Global_ $node): string
    {
        // global $a; 这种语言是定义一个全局变量，我们要从当前上下文中删除，然后塞到头部去
        foreach ($node->vars as $var) {
            $this->headerBlocks[] = "global {$this->p($var)};";
        }
        return '';
    }

    protected function pExpr_Assign(Expr\Assign $node, int $precedence, int $lhsPrecedence): string
    {
        return $this->pPrefixOp(Expr\Assign::class, "let mut {$this->p($node->var)} = ", $node->expr, $precedence, $lhsPrecedence);
    }

    protected function pExpr_Variable(Expr\Variable $node): string
    {
        if ($node->name instanceof Expr) {
            return '${' . $this->p($node->name) . '}';
        } else {
            return $node->name;
        }
    }

    protected function pExpr_PostInc(Expr\PostInc $node): string
    {
        return $this->p($node->var) . ' += 1';
    }

    protected function pExpr_PostDec(Expr\PostDec $node): string
    {
        return $this->p($node->var) . ' -= 1';
    }

    protected function pExpr_BinaryOp_Concat(BinaryOp\Concat $node, int $precedence, int $lhsPrecedence): string
    {
        $left = $this->p($node->left);
        $right = $this->p($node->right);
        return "format!(\"{}{}\", $left, $right)";
        //return $this->pInfixOp(BinaryOp\Concat::class, $node->left, ' + ', $node->right, $precedence, $lhsPrecedence);
    }

    protected function pExpr_FuncCall(Expr\FuncCall $node): string
    {
        $argList = [];
        foreach ($node->args as $key => $arg) {
            $nullable = $this->functionArgumentNullable[$node->name->name][$key] ?? false;
            $argList[] = $nullable ? "Some({$this->p($arg)})" : $this->p($arg);
        }

        return $this->pCallLhs($node->name)
            . '(' . implode(', ', $argList) . ')';
    }
}
