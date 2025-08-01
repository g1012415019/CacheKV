# CacheKV 项目统一结构说明

## 🎯 统一目标

将 CacheKV 项目的所有案例、示例和文档统一为一致的风格和最佳实践，确保：
- **代码风格一致**：所有示例使用相同的编码规范
- **命名规范统一**：统一的键命名和变量命名
- **架构设计一致**：所有案例都基于 KeyManager 设计
- **最佳实践展示**：每个案例都展示推荐的使用方式

## 📁 项目结构

```
CacheKV/
├── src/                          # 核心源码
│   ├── CacheKV.php              # 主缓存类（集成 KeyManager）
│   ├── CacheKVFacade.php        # 门面类（支持模板方法）
│   ├── CacheKVServiceProvider.php # 服务提供者（支持 KeyManager 配置）
│   └── Cache/
│       ├── KeyManager.php       # 键管理器（核心新功能）
│       ├── CacheDriver.php      # 驱动接口
│       └── Drivers/             # 驱动实现
├── examples/                     # 示例代码
│   ├── README.md                # 示例说明
│   ├── key-management-quickstart.php    # Key 管理快速入门
│   ├── key-management-example.php       # Key 管理完整示例
│   └── project-integration-example.php  # 项目集成示例
├── docs/                        # 文档目录
│   ├── key-management.md        # Key 管理详细指南
│   ├── user-info-caching.md     # 用户信息缓存最佳实践
│   ├── batch-product-query.md   # 批量产品查询优化
│   ├── external-api-caching.md  # 外部 API 缓存最佳实践
│   ├── tag-based-invalidation.md # 基于标签的缓存失效
│   ├── cache-penetration-prevention.md # 缓存穿透预防
│   └── sliding-expiration.md    # 滑动过期机制
├── example.php                  # 主示例文件（统一风格）
├── test-project-integration.php # 项目联动测试
└── README.md                    # 主文档（更新了 Key 管理介绍）
```

## 🔑 统一的键管理规范

### 键命名格式
```
{app_prefix}:{env_prefix}:{version}:{business_key}
```

**示例：**
- `myapp:prod:v1:user:123` - 生产环境用户数据
- `myapp:dev:v1:product:456` - 开发环境产品数据
- `ecommerce:test:v2:order:ORD001` - 测试环境订单数据

### 模板定义规范
```php
$keyManager = new KeyManager([
    'app_prefix' => 'myapp',
    'env_prefix' => 'prod',
    'version' => 'v1',
    'templates' => [
        // 用户相关
        'user' => 'user:{id}',
        'user_profile' => 'user:profile:{id}',
        'user_settings' => 'user:settings:{id}',
        
        // 商品相关
        'product' => 'product:{id}',
        'product_detail' => 'product:detail:{id}',
        
        // 业务相关
        'order' => 'order:{id}',
        'cart' => 'cart:{user_id}',
    ]
]);
```

## 📚 统一的案例结构

每个案例文档都遵循以下结构：

### 1. 场景描述
- 明确的业务场景说明
- 实际应用中的痛点分析

### 2. 传统方案的问题
- ❌ 展示传统实现方式的问题
- 具体的代码示例和问题分析

### 3. CacheKV + KeyManager 解决方案
- ✅ 展示统一的解决方案
- 突出 KeyManager 的核心价值

### 4. 完整实现示例
- 可运行的完整代码示例
- 包含详细的注释和说明
- 展示实际业务场景的应用

### 5. 最佳实践建议
- 具体的使用建议
- 性能优化技巧
- 常见问题的解决方案

## 🎨 统一的代码风格

### 变量命名
```php
// ✅ 推荐的命名
$keyManager = new KeyManager($config);
$cache = new CacheKV($driver, 3600, $keyManager);
$userData = $cache->getByTemplate('user', ['id' => $userId], $callback);

// ❌ 避免的命名
$km = new KeyManager($config);
$c = new CacheKV($driver, 3600, $km);
$data = $c->getByTemplate('u', ['id' => $uid], $cb);
```

### 注释风格
```php
// 1. 系统配置
$keyManager = new KeyManager([...]);

// 2. 模拟数据库操作
function getUserFromDatabase($userId) {
    echo "📊 从数据库获取用户 {$userId}\n";
    // 业务逻辑...
}

// 3. 业务服务类
class UserService {
    /**
     * 获取用户信息
     */
    public function getUser($userId) {
        // 实现逻辑...
    }
}
```

### 输出格式
```php
echo "=== 功能模块名称 ===\n\n";
echo "1. 子功能描述\n";
echo "===============\n";
// 功能演示...

echo "\n2. 下一个功能\n";
echo "=============\n";
// 下一个功能...
```

## 🔧 统一的配置模式

### KeyManager 配置
```php
$keyManager = new KeyManager([
    'app_prefix' => 'myapp',      // 应用标识
    'env_prefix' => 'prod',       // 环境标识
    'version' => 'v1',            // 版本标识
    'separator' => ':',           // 分隔符
    'templates' => [              // 业务模板
        // 根据实际业务定义
    ]
]);
```

### CacheKV 初始化
```php
// 方式1：直接使用
$cache = new CacheKV($driver, 3600, $keyManager);

// 方式2：门面使用
CacheKVServiceProvider::register([
    'default' => 'array',
    'stores' => ['array' => ['driver' => ArrayDriver::class]],
    'key_manager' => $keyConfig
]);
```

## 📊 统一的示例场景

所有案例都基于以下统一的业务场景：

### 电商平台场景
- **用户管理**：用户信息、资料、设置、权限
- **商品管理**：商品信息、详情、价格、库存
- **订单管理**：订单信息、订单项、支付状态
- **内容管理**：文章、评论、分类、标签

### 通用业务场景
- **API 缓存**：外部 API 调用结果缓存
- **统计数据**：日报、月报、实时统计
- **用户会话**：登录状态、会话管理
- **搜索结果**：搜索查询结果缓存

## 🎯 核心价值体现

### 1. 统一的键管理
- 标准化的键命名规范
- 环境隔离和版本管理
- 模板化的键生成

### 2. 简化的使用方式
- 一行代码实现缓存逻辑
- 自动处理缓存命中和回填
- 智能的批量操作处理

### 3. 强大的功能特性
- 基于标签的批量失效
- 自动的缓存穿透预防
- 灵活的滑动过期机制

### 4. 完整的生态支持
- 多种驱动支持
- 门面模式支持
- 服务提供者集成

## 🚀 使用指南

### 新用户学习路径
1. **阅读主 README** - 了解核心概念
2. **运行 example.php** - 体验基本功能
3. **查看快速入门** - `examples/key-management-quickstart.php`
4. **深入学习案例** - 选择相关的业务场景案例
5. **集成到项目** - 参考 `examples/project-integration-example.php`

### 开发者集成步骤
1. **安装依赖**：`composer require asfop/cache-kv`
2. **配置 KeyManager**：定义应用的键模板
3. **初始化 CacheKV**：选择合适的驱动
4. **业务集成**：使用模板方法替换现有缓存逻辑
5. **性能优化**：根据统计数据调整缓存策略

## 📈 项目优势

通过这次统一重构，CacheKV 项目获得了：

- **学习成本降低**：一致的代码风格和文档结构
- **集成难度减少**：标准化的使用模式和最佳实践
- **维护效率提升**：统一的架构设计和代码组织
- **扩展能力增强**：基于 KeyManager 的灵活键管理
- **生产就绪**：完整的功能特性和性能优化

## 🎉 总结

CacheKV 现在是一个功能完整、设计统一、易于使用的缓存库：

- ✅ **核心功能完整**：自动回填、批量操作、标签管理、Key 管理
- ✅ **文档体系完善**：从入门到实战的完整学习路径
- ✅ **代码质量统一**：一致的风格和最佳实践展示
- ✅ **实际应用就绪**：丰富的业务场景案例和集成指南
- ✅ **持续演进能力**：基于 KeyManager 的可扩展架构

这个统一的项目结构为用户提供了清晰的学习路径和实践指南，大大降低了使用门槛，提升了开发效率！
