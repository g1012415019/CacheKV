<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Asfop\CacheKV\CacheKVFactory;
use Asfop\CacheKV\CacheKVServiceProvider;
use Asfop\CacheKV\CacheTemplates;
use Asfop\CacheKV\Services\ProductService;
use Asfop\CacheKV\Services\UserService;

echo "=== CacheKV 模板管理解决方案 ===\n\n";

// 配置缓存系统
CacheKVFactory::setDefaultConfig([
    'default' => 'array',
    'stores' => [
        'array' => [
            'driver' => new \Asfop\CacheKV\Cache\Drivers\ArrayDriver(),
            'ttl' => 3600
        ]
    ],
    'key_manager' => [
        'app_prefix' => 'shop',
        'env_prefix' => 'prod',
        'version' => 'v1',
        'templates' => [
            // 用户相关模板 - 使用常量值作为键
            'user_profile' => 'user:profile:{id}',
            'user_settings' => 'user:settings:{id}',
            'user_cart' => 'user:cart:{user_id}',
            'user_wishlist' => 'user:wishlist:{user_id}',
            'user_orders' => 'user:orders:{user_id}:page:{page}',
            
            // 产品相关模板
            'product_detail' => 'product:detail:{id}',
            'product_reviews' => 'product:reviews:{id}:page:{page}',
            'product_stock' => 'product:stock:{id}',
            'product_related' => 'product:related:{id}:limit:{limit}',
            
            // 订单相关模板
            'order_detail' => 'order:detail:{id}',
            'order_items' => 'order:items:{id}',
            
            // 系统相关模板
            'system_config' => 'system:config:{key}',
        ]
    ]
]);

// 注册服务提供者
CacheKVServiceProvider::register();

echo "✅ 缓存系统配置完成\n\n";

// ========================================
// 方案对比演示
// ========================================

echo "❌ 旧方式（硬编码模板名称）：\n";
echo "--------------------------------\n";
echo "// 问题：模板名称硬编码，难以维护\n";
echo "CacheKVFacade::getByTemplate('product_detail', ['id' => 123], \$callback);\n";
echo "CacheKVFacade::getByTemplate('product_detail', ['id' => 456], \$callback);\n";
echo "CacheKVFacade::getByTemplate('product_detail', ['id' => 789], \$callback);\n\n";

echo "✅ 新方式1（使用常量类）：\n";
echo "--------------------------------\n";
echo "// 优势：统一管理，易于维护\n";
echo "CacheKVFacade::getByTemplate(CacheTemplates::PRODUCT_DETAIL, ['id' => 123], \$callback);\n";
echo "CacheKVFacade::getByTemplate(CacheTemplates::PRODUCT_DETAIL, ['id' => 456], \$callback);\n";
echo "CacheKVFacade::getByTemplate(CacheTemplates::PRODUCT_DETAIL, ['id' => 789], \$callback);\n\n";

echo "✅ 新方式2（使用服务类）：\n";
echo "--------------------------------\n";
echo "// 优势：业务逻辑封装，更加简洁\n";
echo "\$productService = new ProductService();\n";
echo "\$productService->getProductDetail(123);\n";
echo "\$productService->getProductDetail(456);\n";
echo "\$productService->getProductDetail(789);\n\n";

// ========================================
// 实际使用演示
// ========================================

echo "🚀 实际使用演示：\n";
echo "================================\n";

// 1. 使用常量类
echo "1. 使用模板常量类：\n";

// 查看所有可用模板
$allTemplates = CacheTemplates::getAllTemplates();
echo "可用模板数量: " . count($allTemplates) . "\n";

// 查看模板分组
$templateGroups = CacheTemplates::getTemplateGroups();
echo "模板分组: " . implode(', ', array_keys($templateGroups)) . "\n";

// 检查模板是否存在
$exists = CacheTemplates::exists(CacheTemplates::PRODUCT_DETAIL);
echo "产品详情模板存在: " . ($exists ? '是' : '否') . "\n\n";

// 2. 使用服务类
echo "2. 使用业务服务类：\n";

$productService = new ProductService();
$userService = new UserService();

// 获取产品详情
$product = $productService->getProductDetail(100);
echo "产品详情: " . json_encode($product, JSON_UNESCAPED_UNICODE) . "\n";

// 获取产品评论
$reviews = $productService->getProductReviews(100, 1);
echo "产品评论: " . json_encode($reviews, JSON_UNESCAPED_UNICODE) . "\n";

// 获取用户档案
$user = $userService->getUserProfile(200);
echo "用户档案: " . json_encode($user, JSON_UNESCAPED_UNICODE) . "\n";

// 获取用户购物车
$cart = $userService->getUserCart(200);
echo "用户购物车: " . json_encode($cart, JSON_UNESCAPED_UNICODE) . "\n\n";

// 3. 批量操作
echo "3. 批量操作：\n";

$productIds = [101, 102, 103];
$products = $productService->getMultipleProductDetails($productIds);
echo "批量产品数量: " . count($products) . "\n";

$userIds = [201, 202, 203];
$users = $userService->getMultipleUserProfiles($userIds);
echo "批量用户数量: " . count($users) . "\n\n";

// 4. 缓存更新
echo "4. 缓存更新操作：\n";

// 更新产品信息（自动清除相关缓存）
$updateResult = $productService->updateProduct(100, ['name' => 'Updated Product']);
echo "产品更新结果: " . ($updateResult ? '成功' : '失败') . "\n";

// 更新用户档案（自动清除相关缓存）
$updateResult = $userService->updateUserProfile(200, ['name' => 'Updated User']);
echo "用户更新结果: " . ($updateResult ? '成功' : '失败') . "\n\n";

// ========================================
// 高级功能演示
// ========================================

echo "🔧 高级功能演示：\n";
echo "================================\n";

// 1. 带标签的缓存
echo "1. 带标签的缓存：\n";
$productService->setProductDetailWithTag(300, [
    'id' => 300,
    'name' => 'Tagged Product',
    'price' => 999
]);
echo "设置带标签的产品缓存完成\n";

// 2. 短期缓存（库存信息）
echo "2. 短期缓存（库存信息）：\n";
$stock = $productService->getProductStock(100);
echo "产品库存: " . json_encode($stock, JSON_UNESCAPED_UNICODE) . "\n";

// 3. 相关产品推荐
echo "3. 相关产品推荐：\n";
$relatedProducts = $productService->getRelatedProducts(100, 5);
echo "相关产品数量: " . count($relatedProducts) . "\n\n";

// ========================================
// 配置管理演示
// ========================================

echo "⚙️ 配置管理演示：\n";
echo "================================\n";

// 模拟模板名称变更场景
echo "模拟场景：需要将 'product_detail' 改为 'product_info'\n";
echo "旧方式：需要在所有使用的地方手动修改\n";
echo "新方式：只需要修改 CacheTemplates 常量类中的一个地方\n\n";

echo "// 在 CacheTemplates 类中修改：\n";
echo "// const PRODUCT_DETAIL = 'product_info'; // 从 'product_detail' 改为 'product_info'\n";
echo "// 所有使用 CacheTemplates::PRODUCT_DETAIL 的地方自动生效\n\n";

// ========================================
// 最佳实践建议
// ========================================

echo "💡 最佳实践建议：\n";
echo "================================\n";
echo "1. 使用 CacheTemplates 常量类管理所有模板名称\n";
echo "2. 创建业务服务类封装缓存逻辑\n";
echo "3. 使用继承 CacheServiceBase 的方式统一缓存操作\n";
echo "4. 为不同业务模块创建独立的服务类\n";
echo "5. 在服务类中处理缓存失效和更新逻辑\n";
echo "6. 使用标签管理相关联的缓存项\n";
echo "7. 根据数据特性设置合适的缓存时间\n\n";

echo "🎯 解决的问题：\n";
echo "================================\n";
echo "✅ 消除硬编码模板名称\n";
echo "✅ 统一管理缓存模板\n";
echo "✅ 提高代码可维护性\n";
echo "✅ 减少模板名称修改的影响范围\n";
echo "✅ 封装业务逻辑，简化使用\n";
echo "✅ 提供类型安全和IDE支持\n";
echo "✅ 支持批量操作和高级功能\n\n";

echo "=== 模板管理解决方案演示完成 ===\n";
