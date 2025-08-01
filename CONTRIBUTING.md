# 贡献指南

感谢您对 CacheKV 项目的关注！我们欢迎各种形式的贡献。

## 🤝 如何贡献

### 报告问题

如果您发现了 bug 或有功能建议，请：

1. 检查 [Issues](https://github.com/asfop1/CacheKV/issues) 确保问题未被报告
2. 创建新的 Issue，包含：
   - 清晰的标题和描述
   - 重现步骤（如果是 bug）
   - 期望的行为
   - 实际的行为
   - 环境信息（PHP 版本、操作系统等）

### 提交代码

1. **Fork 项目**
   ```bash
   git clone https://github.com/your-username/CacheKV.git
   cd CacheKV
   ```

2. **创建功能分支**
   ```bash
   git checkout -b feature/your-feature-name
   ```

3. **安装依赖**
   ```bash
   composer install
   ```

4. **编写代码**
   - 遵循 PSR-12 编码标准
   - 添加适当的注释
   - 编写单元测试

5. **运行测试**
   ```bash
   composer test
   composer cs-check
   ```

6. **提交更改**
   ```bash
   git add .
   git commit -m "feat: 添加新功能描述"
   ```

7. **推送分支**
   ```bash
   git push origin feature/your-feature-name
   ```

8. **创建 Pull Request**

## 📝 编码规范

### PHP 代码风格

我们遵循 [PSR-12](https://www.php-fig.org/psr/psr-12/) 编码标准：

```php
<?php

namespace Asfop\CacheKV;

class ExampleClass
{
    private $property;
    
    public function exampleMethod(string $parameter): string
    {
        if ($parameter === 'example') {
            return 'result';
        }
        
        return 'default';
    }
}
```

### 提交信息规范

使用 [Conventional Commits](https://www.conventionalcommits.org/) 格式：

```
<type>[optional scope]: <description>

[optional body]

[optional footer(s)]
```

**类型：**
- `feat`: 新功能
- `fix`: 修复 bug
- `docs`: 文档更新
- `style`: 代码格式调整
- `refactor`: 重构代码
- `test`: 添加或修改测试
- `chore`: 构建过程或辅助工具的变动

**示例：**
```
feat(cache): 添加标签管理功能

添加了 setWithTag 和 clearTag 方法，支持按标签批量管理缓存。

Closes #123
```

## 🧪 测试

### 运行测试

```bash
# 运行所有测试
composer test

# 运行特定测试
vendor/bin/phpunit tests/CacheKVTest.php

# 生成覆盖率报告
composer test-coverage
```

### 编写测试

为新功能编写测试：

```php
public function testNewFeature()
{
    // Arrange
    $cache = CacheKVFactory::store();
    
    // Act
    $result = $cache->newMethod('parameter');
    
    // Assert
    $this->assertEquals('expected', $result);
}
```

### 性能测试

运行基准测试：

```bash
composer benchmark
```

## 📚 文档

### 更新文档

如果您的更改影响了 API 或使用方式，请更新相应文档：

- `README.md` - 主要文档
- `docs/` - 详细文档
- 代码注释 - PHPDoc 格式

### 文档风格

- 使用清晰、简洁的语言
- 提供实际的代码示例
- 包含必要的警告和注意事项

## 🔍 代码审查

### Pull Request 检查清单

在提交 PR 之前，请确保：

- [ ] 代码遵循 PSR-12 标准
- [ ] 所有测试通过
- [ ] 添加了适当的测试
- [ ] 更新了相关文档
- [ ] 提交信息符合规范
- [ ] 没有引入破坏性更改（除非是主要版本）

### 审查过程

1. 自动化检查（CI/CD）
2. 代码审查
3. 功能测试
4. 文档审查
5. 合并到主分支

## 🚀 发布流程

### 版本号规范

我们使用 [语义化版本](https://semver.org/)：

- `MAJOR.MINOR.PATCH`
- `1.0.0` - 主要版本（破坏性更改）
- `1.1.0` - 次要版本（新功能，向后兼容）
- `1.1.1` - 补丁版本（bug 修复）

### 发布步骤

1. 更新版本号
2. 更新 CHANGELOG.md
3. 创建 Git 标签
4. 发布到 Packagist

## 🛠️ 开发环境设置

### 必需软件

- PHP 7.4+ 或 8.0+
- Composer
- Git
- Redis（可选，用于 Redis 驱动测试）

### 推荐工具

- PHPStorm 或 VS Code
- Xdebug（用于调试和覆盖率）
- Docker（用于环境隔离）

### 本地开发

```bash
# 克隆项目
git clone https://github.com/asfop1/CacheKV.git
cd CacheKV

# 安装依赖
composer install

# 运行测试
composer test

# 启动 Redis（如果需要）
docker run -d -p 6379:6379 redis:alpine

# 运行示例
php example_constants.php
```

## 📋 问题模板

### Bug 报告

```markdown
**描述问题**
简要描述遇到的问题。

**重现步骤**
1. 执行 '...'
2. 点击 '....'
3. 滚动到 '....'
4. 看到错误

**期望行为**
描述您期望发生的情况。

**实际行为**
描述实际发生的情况。

**环境信息**
- PHP 版本: [例如 8.1]
- CacheKV 版本: [例如 1.0.0]
- 操作系统: [例如 Ubuntu 20.04]

**附加信息**
添加任何其他相关信息。
```

### 功能请求

```markdown
**功能描述**
简要描述您希望添加的功能。

**使用场景**
描述这个功能的使用场景和价值。

**建议的实现**
如果有想法，描述您认为应该如何实现。

**替代方案**
描述您考虑过的其他解决方案。
```

## 🎯 路线图

查看我们的 [项目路线图](https://github.com/asfop1/CacheKV/projects) 了解未来的开发计划。

## 📞 联系我们

- GitHub Issues: [问题追踪](https://github.com/asfop1/CacheKV/issues)
- Email: asfop@example.com

感谢您的贡献！🎉
