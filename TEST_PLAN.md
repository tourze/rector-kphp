# 单元测试计划与进度

本文档记录 `rector-kphp` 包的单元测试进度和计划。

## 测试目标

- 确保每个组件都有相应的单元测试
- 测试覆盖所有主要功能和边界条件
- 保持代码高质量和可维护性

## 已完成测试

| 模块 | 类名 | 测试文件 | 测试内容 | 状态 |
|------|------|----------|----------|------|
| Enum | `EnumToClassWithStaticMethodRector` | `EnumToClassWithStaticMethodRectorTest` | 基本枚举转类、标量枚举转类 | ✅ |
| Rule | `MarkReassignVariableRector` | `MarkReassignVariableRectorTest` | 变量重命名规则、作用域处理 | ✅ |
| Printer | `CppPrinter` | `CppPrinterTest` | 计数器功能 | ✅ |
| Visitor | `CodeCollector` | `CodeCollectorTest` | 类引用收集、函数调用收集、常量收集 | ✅ |

## 待完成测试

| 模块 | 类名 | 优先级 | 备注 |
|------|------|--------|------|
| Enum | `EnumDefaultValueToNullRector` | 中 | 枚举默认值转换 |
| Enum | `EnumCaseToStaticCallRector` | 中 | 枚举案例转静态调用 |
| Rule | `SelfStaticToSpecificClassRector` | 高 | 替换 self/static 关键字 |
| Rule | `RemovePublicFromConstRector` | 中 | 移除常量的 public 修饰符 |
| Rule | `ForceArrayKindLongRector` | 中 | 强制使用长数组语法 |
| Rule | `AddKphpNoReturnForNeverRector` | 高 | 为 never 返回类型添加注解 |
| Rule | `CompleteDefaultArgumentsRector` | 高 | 补全默认参数 |
| Rule | `SwitchToWhileIfRector` | 中 | switch 语句转换 |
| Rule | `AddScaleToBcFunctionsRector` | 中 | 为 BC 函数添加精度参数 |
| Rule | `ChangeMethodCallToKphpCompatCallRector` | 高 | 方法调用兼容性转换 |
| Rule | `DefineToConstRector` | 中 | define 转 const |
| Rule | `CppReplaceGlobalDirConstantRector` | 中 | 全局目录常量替换 |
| Rule | `CppDoubleStyleStringRector` | 中 | C++ 字符串样式转换 |
| Rule | `CppReserveWordRector` | 高 | C++ 保留字处理 |
| Rule | `AddIntTypeToIncrementedParamsRector` | 高 | 为自增参数添加类型 |
| Rule | `RemoveNestingRector` | 中 | 减少嵌套层级 |
| Printer | `RustPrinter` | 低 | Rust 代码生成 |
| Visitor | `IncludeVisitor` | 中 | include 语句处理 |

## 测试策略

1. **模块化测试**：每个类都有独立的测试类
2. **行为驱动测试**：测试用例聚焦于方法的行为和结果
3. **边界测试**：测试边界条件和异常情况
4. **模拟依赖**：通过 Mock 对象隔离测试单元

## 注意事项

- 测试应保持独立性，不应依赖外部系统或服务
- 测试代码应遵循与正式代码相同的编码规范
- 测试应覆盖所有公共 API 和关键功能路径 