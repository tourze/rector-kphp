<?php

namespace Tourze\Rector4KPHP\Printer;

use PHP2AOT\Attribute\CppExpression;
use PHP2AOT\Attribute\CppFunction;
use PHP2AOT\CompatCollector;
use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\Cast;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt;
use PhpParser\PrettyPrinter\Standard;
use Twig\Environment;

/**
 * 把PHP转为C++语法
 */
class CppPrinter extends Standard
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

    public function popCounter(): int
    {
        static::$counter++;
        return static::$counter;
    }

    public function __construct(private readonly Environment $twig, array $cppFiles = [])
    {
        parent::__construct();

        // 默认要引入一部分CPP文件喔
        foreach ($cppFiles as $cppFile) {
            $this->headerBlocks[] = file_get_contents($cppFile);
        }
    }

    private function walkStatements(array $stmts): void
    {
        foreach ($stmts as $stmt) {
            // namespace要特别处理
            if ($stmt instanceof Stmt\Namespace_) {
                // 全局命名空间的内容，要塞到main函数去
                if ($stmt->name?->name === null) {
                    $this->walkStatements($stmt->stmts);
                    continue;
                }
            }

            // PHP没明确的main函数，我们这里需要自己模拟一次main
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
                $this->mainBlocks[] = $stmt;
                continue;
            }

            // 其他默认都塞header吧
            $this->headerBlocks[] = $stmt;
        }
    }

    /**
     * 生成代码
     */
    public function generate(string $fileName, array $stmts): string
    {
        $this->walkStatements($stmts);

        $headerBlocks = $this->prettyPrint($this->headerBlocks);
        $mainBlocks = $this->prettyPrint($this->mainBlocks);

        $template = file_get_contents(__DIR__ . '/../cpp/main.cpp');
        $template = str_replace('// [headerBlock]', $headerBlocks, $template);
        $template = str_replace('// [mainBlocks]', $mainBlocks, $template);
        return trim($template);
    }

    /**
     * 打印语法兼容
     */
    protected function pStmt_Echo(Stmt\Echo_ $node): string
    {
        //dump($node);
        return 'std::cout << (' . $this->pCommaSeparated($node->exprs) . ');';
    }

    protected function pStmt_Function(Stmt\Function_ $node): string
    {
        $returnType = $node->returnType;

        return ($returnType ? $this->p($returnType) : 'auto')
            . ' ' . ($node->byRef ? '&' : '') . $node->name
            . '(' . $this->pMaybeMultiline($node->params, $this->phpVersion->supportsTrailingCommaInParamList()) . ')'
            . $this->nl . '{' . $this->pStmts($node->stmts) . $this->nl . '}';
    }

    protected function pStmt_ElseIf(Stmt\ElseIf_ $node): string
    {
        return 'else if (' . $this->p($node->cond) . ') {'
            . $this->pStmts($node->stmts) . $this->nl . '}';
    }

    protected function pStmt_Static(Stmt\Static_ $node): string
    {
        return 'static auto ' . $this->pCommaSeparated($node->vars) . ';';
    }

    private function getStyledNamespace(string $namespace): string
    {
        $namespace = trim($namespace, '\\');
        $namespace = str_replace('\\', '_', $namespace);
        return strtolower($namespace);
    }

    protected function pStmt_Namespace(Stmt\Namespace_ $node): string
    {
        $namespace = null !== $node->name ? $this->p($node->name) : '';
        $namespace = $this->getStyledNamespace($namespace);
        return 'namespace ' . $namespace . ' {' . $this->pStmts($node->stmts) . $this->nl . '}';
    }

    protected function pStmt_Class(Stmt\Class_ $node): string
    {
        //dd($node);

        // 按照C++的风格，要先声明后定义
        $publicList = [];
        $protectedList = [];
        $privateList = [];

        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof Stmt\ClassMethod) {
                // php中默认是public
                if ($stmt->flags === 0) {
                    $stmt->flags = Modifiers::PUBLIC;
                }
                if ($stmt->flags & Modifiers::PUBLIC) {
                    $def = clone $stmt;
                    $def->flags = 0;
                    $def->stmts = null; // 这里我们去除代码，只返回一个定义
                    $publicList[] = $this->pStmt_ClassMethod($def);
                }
                if ($stmt->flags & Modifiers::PROTECTED) {
                    $def = clone $stmt;
                    $def->flags = 0;
                    $def->stmts = null; // 这里我们去除代码，只返回一个定义
                    $protectedList[] = $this->pStmt_ClassMethod($def);
                }
                if ($stmt->flags & Modifiers::PRIVATE) {
                    $def = clone $def;
                    $def->flags = 0;
                    $def->stmts = null; // 这里我们去除代码，只返回一个定义
                    $privateList[] = $this->pStmt_ClassMethod($def);
                }
            }
            if ($stmt instanceof Stmt\Property) {
                if ($stmt->flags & Modifiers::PUBLIC) {
                    $def = clone $stmt;
                    $publicList[] = $this->pStmt_Property($def);
                }
                if ($stmt->flags & Modifiers::PROTECTED) {
                    $def = clone $stmt;
                    $protectedList[] = $this->pStmt_Property($def);
                }
                if ($stmt->flags & Modifiers::PRIVATE) {
                    $def = clone $stmt;
                    $privateList[] = $this->pStmt_Property($def);
                }
            }
            if ($stmt instanceof Stmt\ClassConst) {
                $def = clone $stmt;
                $publicList[] = $this->pStmt_ClassConst($def);
            }
        }

        $publicList = empty($publicList) ? $this->nl : $this->nl . 'public:' . $this->nl . implode($this->nl, $publicList);
        $protectedList = empty($protectedList) ? $this->nl : $this->nl . 'protected:' . $this->nl . implode($this->nl, $protectedList);
        $privateList = empty($privateList) ? $this->nl : $this->nl . 'private:' . $this->nl . implode($this->nl, $privateList);

        $result = 'class ' . $node->name
            . (null !== $node->extends ? ' : public ' . ltrim($this->p($node->extends), '\\') : '')
            . (!empty($node->implements) ? ' implements ' . $this->pCommaSeparated($node->implements) : '')
            . $this->nl . '{'
            . $publicList
            . $protectedList
            . $privateList
            . $this->nl . '};';

        // 这里再补充实现的代码
        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof Stmt\ClassMethod) {
                $c = clone $stmt;
                $c->flags = 0;
                // 按照C++风格，这里输出的函数名要带上类名
                $c->name = clone $stmt->name;
                $c->name->name = "{$node->name}::{$c->name->name}";
                $result .= "\n" . $this->pStmt_ClassMethod($c);
            }
        }

        return $result;
    }

    protected function pStmt_Interface(Stmt\Interface_ $node): string
    {
        // 按照C++的风格，要先声明后定义
        $publicList = [];
        $protectedList = [];
        $privateList = [];

        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof Stmt\ClassMethod) {
                // php中默认是public
                if ($stmt->flags === 0) {
                    $stmt->flags = Modifiers::PUBLIC;
                }
                if ($stmt->flags & Modifiers::PUBLIC) {
                    $def = clone $stmt;
                    $def->flags = 0;
                    $def->stmts = null; // 这里我们去除代码，只返回一个定义
                    $publicList[] = $this->pStmt_ClassMethod($def);
                }
                if ($stmt->flags & Modifiers::PROTECTED) {
                    $def = clone $stmt;
                    $def->flags = 0;
                    $def->stmts = null; // 这里我们去除代码，只返回一个定义
                    $protectedList[] = $this->pStmt_ClassMethod($def);
                }
                if ($stmt->flags & Modifiers::PRIVATE) {
                    $def = clone $def;
                    $def->flags = 0;
                    $def->stmts = null; // 这里我们去除代码，只返回一个定义
                    $privateList[] = $this->pStmt_ClassMethod($def);
                }
            }
            if ($stmt instanceof Stmt\ClassConst) {
                $def = clone $stmt;
                $publicList[] = $this->pStmt_ClassConst($def);
            }
        }

        $publicList = empty($publicList) ? $this->nl : $this->nl . 'public:' . $this->nl . implode($this->nl, $publicList);
        $protectedList = empty($protectedList) ? $this->nl : $this->nl . 'protected:' . $this->nl . implode($this->nl, $protectedList);
        $privateList = empty($privateList) ? $this->nl : $this->nl . 'private:' . $this->nl . implode($this->nl, $privateList);

        return 'class ' . $node->name
            . (null !== $node->extends ? ' : public ' . $this->p($node->extends) : '')
            . (!empty($node->implements) ? ' implements ' . $this->pCommaSeparated($node->implements) : '')
            . $this->nl . '{'
            . $publicList
            . $protectedList
            . $privateList
            . $this->nl . '};';
    }

    protected function pStmt_Use(Stmt\Use_ $node): string
    {
        // TODO 这个暂时不使用
        return '';
        return 'use ' . $this->pUseType($node->type)
            . $this->pCommaSeparated($node->uses) . ';';
    }

    protected function pStmt_Property(Stmt\Property $node): string
    {
        //dump($node);
        $result = (0 === $node->flags ? 'var ' : $this->pModifiers($node->flags))
            . ($node->type ? $this->p($node->type) . ' ' : 'auto ')
            . $this->pCommaSeparated($node->props)
            . ($node->hooks ? ' {' . $this->pStmts($node->hooks) . $this->nl . '}' : ';');

        // 进行一些脏fix
        $result = preg_replace('/auto (.*?) = std::string/', 'string \1 = std::string', $result);
        return $result;
    }

    protected function pStmt_ClassMethod(Stmt\ClassMethod $node): string
    {
        return $this->pModifiers($node->flags)
            . (null !== $node->returnType ? $this->p($node->returnType) : 'auto')
            . ' ' . ($node->byRef ? '&' : '') . $node->name
            . '(' . $this->pMaybeMultiline($node->params, $this->phpVersion->supportsTrailingCommaInParamList()) . ')'
            . (null !== $node->stmts
                ? $this->nl . '{' . $this->pStmts($node->stmts) . $this->nl . '}'
                : ';');
    }

    protected function pStmt_ClassConst(Stmt\ClassConst $node): string
    {
        return 'static constexpr '
            . (null !== $node->type ? $this->p($node->type) . ' ' : '')
            . $this->pCommaSeparated($node->consts) . ';';
    }

    protected function pExpr_Variable(Expr\Variable $node): string
    {
        if ($node->name instanceof Expr) {
            return '{' . $this->p($node->name) . '}';
        } else {
            return $node->name;
        }
    }

    protected function pExpr_Assign(Expr\Assign $node, int $precedence, int $lhsPrecedence): string
    {
        $typeName = 'auto';
        if ($node->expr instanceof Scalar\Int_) {
            $typeName = 'int';
        }
        if ($node->expr instanceof Expr\BinaryOp\Plus) {
            $typeName = 'int';
        }
        if ($node->expr instanceof Expr\BinaryOp\Minus) {
            $typeName = 'int';
        }
        if ($node->expr instanceof Expr\BinaryOp\Mul) {
            $typeName = 'int';
        }
        if ($node->expr instanceof Expr\BinaryOp\Div) {
            $typeName = 'int';
        }

        return $this->pPrefixOp(Expr\Assign::class, "$typeName " . $this->p($node->var) . ' = ', $node->expr, $precedence, $lhsPrecedence);
    }

    private function shouldWrapString(Node\Expr $node): bool
    {
        if (
            $node instanceof Expr\Variable
            || $node instanceof Expr\PostDec
            || $node instanceof Expr\PostInc
        ) {
            return true;
        }
        return false;
    }

    protected function pExpr_BinaryOp_Concat(BinaryOp\Concat $node, int $precedence, int $lhsPrecedence): string
    {
        // C++ string 风格的字符串拼接

        $left = $this->p($node->left);
        if ($this->shouldWrapString($node->left)) {
            $left = 'std::to_string(' . $left . ')';
        }

        $right = $this->p($node->right);
        if ($this->shouldWrapString($node->right)) {
            $right = 'std::to_string(' . $right . ')';
        }

        return "$left + $right";
    }

    protected function pExpr_BinaryOp_Identical(BinaryOp\Identical $node, int $precedence, int $lhsPrecedence): string
    {
        return $this->pInfixOp(BinaryOp\Identical::class, $node->left, ' == ', $node->right, $precedence, $lhsPrecedence);
    }

    protected function pExpr_BinaryOp_NotIdentical(BinaryOp\NotIdentical $node, int $precedence, int $lhsPrecedence): string
    {
        return $this->pInfixOp(BinaryOp\NotIdentical::class, $node->left, ' != ', $node->right, $precedence, $lhsPrecedence);
    }

    protected function pExpr_Print(Expr\Print_ $node, int $precedence, int $lhsPrecedence): string
    {
        // 这里跟 echo 逻辑应该是一致的
        return 'std::cout << (' . $this->pCommaSeparated([$node->expr]) . ');';
    }

    protected function pExpr_StaticCall(Expr\StaticCall $node): string
    {
        // C++中没有 parent::，只有 par::
        if ($node->class->name === 'parent') {
            $node->class->name = 'par';
        }
        return parent::pExpr_StaticCall($node);
    }

    protected function pExpr_AssignOp_Concat(AssignOp\Concat $node, int $precedence, int $lhsPrecedence): string
    {
        $left = $this->p($node->var);
        $right = $this->p($node->expr);
        return "$left.append($right)";
    }

    protected function pExpr_BinaryOp_Coalesce(BinaryOp\Coalesce $node, int $precedence, int $lhsPrecedence): string
    {
        $left = $this->p($node->left);
        $right = $this->p($node->right);
        return "isset($left) ? $left : $right";
    }

    protected function pExpr_Cast_String(Cast\String_ $node, int $precedence, int $lhsPrecedence): string
    {
        $expr = $this->p($node->expr);
        return "std::to_string($expr)";
    }

    protected function pExpr_Cast_Double(Cast\Double $node, int $precedence, int $lhsPrecedence): string
    {
        $kind = $node->getAttribute('kind', Cast\Double::KIND_DOUBLE);

        $expr = $this->p($node->expr);
        if ($kind === Cast\Double::KIND_DOUBLE) {
            $cast = '(double)';
        } elseif ($kind === Cast\Double::KIND_FLOAT) {
            $cast = '(float)';
        } else {
            assert($kind === Cast\Double::KIND_REAL);
            $cast = '(real)';
        }
        return "static_cast<$cast>($expr)";
    }

    protected function pExpr_FuncCall(Expr\FuncCall $node): string
    {
        // 有一些函数调用，我们可以直接改用C/C++函数的，在这里统一处理，替换为 C++ 的实现
        $callName = $this->pCallLhs($node->name);

        // 检查是否有C++的兼容实现
        $function = CompatCollector::FUNC_PREFIX . ltrim($callName, '\\');
        try {
            $reflection = new \ReflectionFunction($function);

            // 第一种情况，直接替换为C++的语法表达式
            $attributes = $reflection->getAttributes(CppExpression::class);
            if (!empty($attributes)) {
                $attribute = $attributes[0]->newInstance();
                /** @var CppExpression $attribute */

                $template = $this->twig->createTemplate("{% autoescape false %}{$attribute->template}{% endautoescape %}");
                $context = [
                    'arguments' => $this->pMaybeMultiline($node->args),
                ];
                foreach ($node->args as $i => $arg) {
                    $context['argument' . $i] = $this->p($arg);
                }
                return $template->render($context);
            }

            // 第二种情况，直接替换为C++中的函数实现
            $attributes = $reflection->getAttributes(CppFunction::class);
            if (!empty($attributes)) {
                foreach ($attributes as $attribute) {
                    $attribute = $attribute->newInstance();
                    /** @var CppFunction $attribute */
                    if (!in_array($attribute->code, $this->headerBlocks)) {
                        array_unshift($this->headerBlocks, $attribute->code);
                    }
                }
            }
        } catch (\Throwable $exception) {
        }

        // 有命名空间的话，写法不一样
        if (str_contains($callName, '\\')) {
            $callName = trim($callName, '\\');
            $tmp = explode('\\', $callName);
            if (count($tmp) > 1) {
                $method = array_pop($tmp);
                $namespace = implode('_', $tmp);
                $callName = strtolower("$namespace::$method");
            }
        }

        return $callName . '(' . $this->pMaybeMultiline($node->args) . ')';
    }

    protected function pScalar_String(Scalar\String_ $node): string
    {
        // C++暂时都使用双引号字符串
        $node->setAttribute('kind', Scalar\String_::KIND_DOUBLE_QUOTED);
        // 强制使用 string 类
        return 'std::string(' . parent::pScalar_String($node) . ')';
    }

    protected function pScalar_InterpolatedString(Scalar\InterpolatedString $node): string
    {
        if ($node->getAttribute('kind') === Scalar\String_::KIND_HEREDOC) {
            return parent::pScalar_InterpolatedString($node);
        }

        $strList = [];
        foreach ($node->parts as $part) {
            if ($part instanceof Node\InterpolatedStringPart) {
                $strList[] = '"' . $this->escapeString($part->value, '"') . '"';
            } else {
                // 这里强制转一次
                $strList[] = 'std::to_string(' . $this->p($part) . ')';
            }
        }
        return implode(' + ', $strList);
    }

    protected function pVarLikeIdentifier(Node\VarLikeIdentifier $node): string
    {
        return $node->name;
    }

    protected function pNullableType(Node\NullableType $node): string
    {
        // TODO C++中找不到对应写法。。。
        return $this->p($node->type);
    }

    protected function pPropertyItem(Node\PropertyItem $node): string
    {
        return $node->name
            . (null !== $node->default ? ' = ' . $this->p($node->default) : '');
    }

    protected function pModifiers(int $modifiers): string
    {
        return ''
            //. ($modifiers & Stmt\Class_::MODIFIER_PUBLIC ? 'public ' : '')
            //. ($modifiers & Stmt\Class_::MODIFIER_PROTECTED ? 'protected ' : '')
            //. ($modifiers & Stmt\Class_::MODIFIER_PRIVATE ? 'private ' : '')
            . ($modifiers & Stmt\Class_::MODIFIER_STATIC ? 'static ' : '')
            . ($modifiers & Stmt\Class_::MODIFIER_ABSTRACT ? 'abstract ' : '')
            . ($modifiers & Stmt\Class_::MODIFIER_FINAL ? 'final ' : '')
            . ($modifiers & Stmt\Class_::MODIFIER_READONLY ? 'readonly ' : '');
    }

    private function getScalarType(Scalar $value): string
    {
        if ($value instanceof Scalar\Int_) {
            return 'int';
        }
        return $value->getType();
    }

    protected function pConst(Node\Const_ $node): string
    {
        $prefix = '';
        if ($node->value instanceof Scalar) {
            $prefix = $this->getScalarType($node->value) . ' ';
        }
        return $prefix . $node->name . ' = ' . $this->p($node->value);
    }

    protected function pParam(Node\Param $node): string
    {
        $isOptional = false;

        $defaultValue = '';
        if ($node->default) {
            $val = $this->p($node->default);
            if ($val === 'null') {
                $val = 'std::nullopt';
                $isOptional = true;
            }
            $defaultValue = ' = ' . $val;
        }

        $type = '';
        if ($node->type) {

            // 如果类型中带有null，那我们要修改为可选类型
            if ($node->type instanceof Node\UnionType) {
                foreach ($node->type->types as $k => $subType) {
                    if ($subType->name === 'null') {
                        $isOptional = true;
                        unset($node->type->types[$k]);
                    }
                }
                $node->type->types = array_values($node->type->types);
            }

            $type = $this->p($node->type);
            if ($isOptional) {
                $type = 'std::optional<' . $type . '>';
            }
            $type = $type . ' ';
        }

        return $this->pAttrGroups($node->attrGroups, true)
            . $this->pModifiers($node->flags)
            . $type
            . ($node->byRef ? '&' : '')
            . ($node->variadic ? '...' : '')
            . $this->p($node->var)
            . $defaultValue
            . ($node->hooks ? ' {' . $this->pStmts($node->hooks) . $this->nl . '}' : '');
    }
}
