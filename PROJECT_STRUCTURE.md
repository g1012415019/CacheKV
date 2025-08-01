# CacheKV 项目结构

```
CacheKV/
├── src/                          # 源代码
│   ├── Cache/                    # 缓存相关类
│   │   ├── Drivers/             # 缓存驱动
│   │   │   ├── ArrayDriver.php  # 数组驱动
│   │   │   └── RedisDriver.php  # Redis 驱动
│   │   ├── CacheDriver.php      # 驱动基类
│   │   ├── CacheManager.php     # 缓存管理器
│   │   └── KeyManager.php       # 键管理器
│   ├── Config/                  # 配置相关
│   │   └── cachekv.php         # 默认配置
│   ├── Services/                # 服务类
│   │   ├── CacheServiceBase.php # 服务基类
│   │   ├── ProductService.php   # 商品服务示例
│   │   └── UserService.php      # 用户服务示例
│   ├── CacheKV.php              # 主缓存类
│   ├── CacheKVFactory.php       # 工厂类
│   ├── CacheKVFacade.php        # 门面类
│   ├── CacheKVServiceProvider.php # 服务提供者
│   ├── CacheTemplates.php       # 缓存模板常量
│   └── helpers.php              # 辅助函数
├── tests/                       # 测试文件
│   ├── integration/             # 集成测试
│   │   ├── test-project-integration.php
│   │   ├── test-config-integration.php
│   │   ├── test-comprehensive-optimization.php
│   │   ├── test-sliding-expiration.php
│   │   ├── test-redis-injection.php
│   │   ├── test-keymanager-optimization.php
│   │   └── test-code-optimization.php
│   ├── CacheKVCompleteTest.php  # 完整功能测试
│   ├── CacheKVTest.php          # 基础功能测试
│   └── TestCase.php             # 测试基类
├── examples/                    # 示例代码
│   ├── basic/                   # 基础示例
│   │   ├── example_constants.php # 常量使用示例
│   │   ├── example.php          # 基本使用示例
│   │   └── index.php            # 入门示例
│   ├── advanced/                # 高级示例
│   │   └── benchmark.php        # 性能基准测试
│   ├── template-management.php  # 模板管理示例
│   └── README.md                # 示例说明
├── docs/                        # 文档
│   ├── api-reference.md         # API 参考
│   ├── best-practices.md        # 最佳实践
│   ├── core-features.md         # 核心功能
│   ├── examples.md              # 实战案例
│   ├── quick-start.md           # 快速开始
│   ├── check-docs.php           # 文档检查脚本
│   ├── UNIFIED_STRUCTURE.md     # 统一结构说明
│   └── EXAMPLES_MIGRATION.md    # 示例迁移说明
├── vendor/                      # Composer 依赖（忽略）
├── .gitignore                   # Git 忽略文件
├── .phpunit.result.cache        # PHPUnit 缓存（忽略）
├── composer.json                # Composer 配置
├── composer.lock                # Composer 锁定文件（忽略）
├── phpunit.xml                  # PHPUnit 配置
├── phpunit.sh                   # PHPUnit 运行脚本
├── CONTRIBUTING.md              # 贡献指南
├── LICENSE                      # 许可证
├── PROJECT_STRUCTURE.md         # 项目结构说明（本文件）
└── README.md                    # 项目说明
```

## 目录说明

### 核心目录

- **src/** - 所有源代码，遵循 PSR-4 自动加载标准
- **tests/** - 测试文件，包括单元测试和集成测试
- **examples/** - 示例代码，按复杂度分类
- **docs/** - 项目文档，包括 API 参考和使用指南

### 配置文件

- **composer.json** - 项目依赖和配置
- **phpunit.xml** - 测试配置
- **.gitignore** - Git 版本控制忽略规则

### 文档文件

- **README.md** - 项目主要说明文档
- **CONTRIBUTING.md** - 贡献者指南
- **LICENSE** - 开源许可证

## 开发工作流

1. **开发新功能** - 在 `src/` 目录中添加代码
2. **编写测试** - 在 `tests/` 目录中添加测试
3. **添加示例** - 在 `examples/` 目录中添加使用示例
4. **更新文档** - 在 `docs/` 目录中更新相关文档

## 运行命令

```bash
# 运行测试
composer test

# 运行基准测试
composer benchmark

# 运行示例
composer example

# 代码风格检查
composer cs-check

# 代码风格修复
composer cs-fix
```
