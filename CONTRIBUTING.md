# 贡献指南

感谢你对CacheKV项目的关注！我们欢迎各种形式的贡献。

## 🤝 如何贡献

### 报告问题
- 在提交问题前，请先搜索现有的[Issues](https://github.com/g1012415019/CacheKV/issues)
- 使用清晰的标题描述问题
- 提供详细的重现步骤
- 包含相关的错误信息和环境信息

### 功能建议
- 在[Issues](https://github.com/g1012415019/CacheKV/issues)中提交功能请求
- 详细描述建议的功能和使用场景
- 解释为什么这个功能对项目有价值

### 代码贡献

#### 开发环境设置
```bash
# 克隆仓库
git clone https://github.com/g1012415019/CacheKV.git
cd CacheKV

# 安装依赖
composer install

# 运行测试
composer test
```

#### 提交流程
1. **Fork** 项目到你的GitHub账号
2. **创建分支** 用于你的修改
   ```bash
   git checkout -b feature/your-feature-name
   ```
3. **编写代码** 并确保遵循代码规范
4. **添加测试** 覆盖新功能或修复
5. **运行测试** 确保所有测试通过
   ```bash
   composer test
   ```
6. **提交更改** 使用清晰的提交信息
   ```bash
   git commit -m "feat: 添加新功能描述"
   ```
7. **推送分支** 到你的Fork
   ```bash
   git push origin feature/your-feature-name
   ```
8. **创建Pull Request** 到主仓库

## 📝 代码规范

### 编码标准
- 遵循 [PSR-4](https://www.php-fig.org/psr/psr-4/) 自动加载标准
- 遵循 [PSR-12](https://www.php-fig.org/psr/psr-12/) 编码风格
- 使用4个空格缩进，不使用Tab
- 类名使用 `PascalCase`
- 方法名使用 `camelCase`
- 常量使用 `UPPER_CASE`

### 注释规范
- 所有公共方法必须有PHPDoc注释
- 复杂逻辑需要添加行内注释
- 类和接口需要有描述性注释

### 示例
```php
<?php

namespace Asfop\CacheKV\Example;

/**
 * 示例类
 * 
 * 这是一个示例类，展示代码规范
 */
class ExampleClass
{
    /**
     * 示例方法
     * 
     * @param string $param 参数描述
     * @return bool 返回值描述
     */
    public function exampleMethod($param)
    {
        // 实现逻辑
        return true;
    }
}
```

## 🧪 测试

### 运行测试
```bash
# 运行所有测试
composer test

# 运行测试并生成覆盖率报告
composer test-coverage
```

### 编写测试
- 新功能必须包含相应的测试
- 测试文件放在 `tests/` 目录下
- 测试类名以 `Test` 结尾
- 测试方法名以 `test` 开头

## 📋 提交信息规范

使用 [Conventional Commits](https://www.conventionalcommits.org/) 规范：

```
<type>[optional scope]: <description>

[optional body]

[optional footer(s)]
```

### 类型说明
- `feat`: 新功能
- `fix`: 修复bug
- `docs`: 文档更新
- `style`: 代码格式调整
- `refactor`: 代码重构
- `test`: 测试相关
- `chore`: 构建过程或辅助工具的变动

### 示例
```
feat: 添加热点键自动续期功能

实现了基于访问频率的热点键检测和自动续期机制，
可以有效避免热点数据过期导致的性能问题。

Closes #123
```

## 🔍 代码审查

所有的Pull Request都会经过代码审查：

- **功能性**: 代码是否实现了预期功能
- **性能**: 是否有性能问题
- **安全性**: 是否存在安全隐患
- **可维护性**: 代码是否易于理解和维护
- **测试覆盖**: 是否有足够的测试覆盖

## 📄 许可证

通过贡献代码，你同意你的贡献将在 [MIT License](LICENSE) 下发布。

## 💬 交流

- **Issues**: [GitHub Issues](https://github.com/g1012415019/CacheKV/issues)
- **Discussions**: [GitHub Discussions](https://github.com/g1012415019/CacheKV/discussions)

## 🙏 致谢

感谢所有为CacheKV项目做出贡献的开发者！

---

再次感谢你的贡献！每一个贡献都让CacheKV变得更好。
