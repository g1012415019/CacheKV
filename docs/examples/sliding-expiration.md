# 滑动过期机制详解

## 场景描述

滑动过期（Sliding Expiration）是一种智能的缓存过期策略：当缓存项被访问时，自动延长其过期时间。这种机制特别适合那些被频繁访问的热点数据，可以确保活跃数据始终保持在缓存中，而不活跃的数据则会自然过期。

## 传统固定过期的问题

### ❌ 固定过期时间的局限性
```php
// 传统方式：固定过期时间
$cache->set('hot_user:123', $userData, 3600); // 1小时后过期

// 问题场景：
// - 第59分钟：用户频繁访问，数据很热
// - 第60分钟：缓存过期，需要重新从数据库加载
// - 第61分钟：又要重新缓存，造成不必要的数据库压力
```

### 问题分析
- **热点数据过期**：频繁访问的数据仍然会过期
- **性能波动**：缓存过期时刻会出现性能抖动
- **资源浪费**：重复加载活跃数据
- **用户体验差**：热点数据的访问延迟不稳定

## CacheKV 的滑动过期机制

### ✅ 智能的滑动过期
```php
use Asfop\CacheKV\CacheKV;
use Asfop\CacheKV\Cache\KeyManager;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;

$keyManager = new KeyManager([
    'app_prefix' => 'sliding',
    'env_prefix' => 'prod',
    'version' => 'v1'
]);

$cache = new CacheKV(new ArrayDriver(), 3600, $keyManager);

// 启用滑动过期的数据获取
$user = $cache->getByTemplate('user', ['id' => 123], function() {
    return getUserFromDatabase(123);
}, 3600, true); // 最后一个参数启用滑动过期

// 每次访问都会自动延长过期时间！
// 第1次访问：过期时间 = 当前时间 + 3600秒
// 第30分钟访问：过期时间 = 当前时间 + 3600秒（重新计算）
// 第50分钟访问：过期时间 = 当前时间 + 3600秒（重新计算）
```

## 完整滑动过期实现示例

```php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Asfop\CacheKV\CacheKV;
use Asfop\CacheKV\Cache\KeyManager;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;

echo "=== 滑动过期机制详解 ===\n\n";

// 1. 系统配置
$keyManager = new KeyManager([
    'app_prefix' => 'sliding',
    'env_prefix' => 'prod',
    'version' => 'v1',
    'templates' => [
        // 用户相关（适合滑动过期）
        'user_session' => 'user:session:{id}',
        'user_profile' => 'user:profile:{id}',
        'user_preferences' => 'user:preferences:{id}',
        
        // 内容相关（适合滑动过期）
        'hot_post' => 'post:hot:{id}',
        'trending_topic' => 'topic:trending:{id}',
        
        // 统计相关（不适合滑动过期）
        'daily_stats' => 'stats:daily:{date}',
        'hourly_metrics' => 'metrics:hourly:{hour}',
        
        // API缓存（适合滑动过期）
        'api_user_info' => 'api:user:{provider}:{id}',
        'api_weather' => 'api:weather:{city}',
    ]
]);

$cache = new CacheKV(new ArrayDriver(), 3600, $keyManager);

// 2. 模拟数据源
function getUserFromDatabase($userId) {
    echo "📊 从数据库加载用户 {$userId}\n";
    usleep(200000); // 0.2秒延迟
    
    return [
        'id' => $userId,
        'name' => "User {$userId}",
        'email' => "user{$userId}@example.com",
        'last_login' => date('Y-m-d H:i:s'),
        'login_count' => rand(10, 1000)
    ];
}

function getHotPostFromDatabase($postId) {
    echo "📊 从数据库加载热门文章 {$postId}\n";
    usleep(300000); // 0.3秒延迟
    
    return [
        'id' => $postId,
        'title' => "Hot Post {$postId}",
        'content' => "This is a trending post with ID {$postId}",
        'views' => rand(1000, 10000),
        'likes' => rand(100, 1000),
        'created_at' => date('Y-m-d H:i:s', time() - rand(0, 86400))
    ];
}

function getWeatherFromAPI($city) {
    echo "🌐 从API获取天气信息: {$city}\n";
    usleep(1500000); // 1.5秒延迟
    
    return [
        'city' => $city,
        'temperature' => rand(-10, 40),
        'condition' => ['Sunny', 'Cloudy', 'Rainy'][rand(0, 2)],
        'humidity' => rand(30, 90),
        'updated_at' => date('Y-m-d H:i:s')
    ];
}

// 3. 滑动过期服务类
class SlidingExpirationService
{
    private $cache;
    private $keyManager;
    private $accessLog = [];
    
    public function __construct($cache, $keyManager)
    {
        $this->cache = $cache;
        $this->keyManager = $keyManager;
    }
    
    /**
     * 用户会话管理（典型的滑动过期场景）
     */
    public function getUserSession($userId)
    {
        $this->logAccess('user_session', $userId);
        
        // 用户会话使用滑动过期：每次访问都延长30分钟
        return $this->cache->getByTemplate('user_session', ['id' => $userId], function() use ($userId) {
            return [
                'user_id' => $userId,
                'session_id' => 'sess_' . uniqid(),
                'created_at' => date('Y-m-d H:i:s'),
                'last_activity' => date('Y-m-d H:i:s'),
                'ip_address' => '192.168.1.' . rand(1, 254)
            ];
        }, 1800); // 30分钟滑动过期
    }
    
    /**
     * 用户资料（滑动过期 - 活跃用户的资料保持热缓存）
     */
    public function getUserProfile($userId)
    {
        $this->logAccess('user_profile', $userId);
        
        return $this->cache->getByTemplate('user_profile', ['id' => $userId], function() use ($userId) {
            return getUserFromDatabase($userId);
        }, 3600); // 1小时滑动过期
    }
    
    /**
     * 热门文章（滑动过期 - 热门内容保持缓存）
     */
    public function getHotPost($postId)
    {
        $this->logAccess('hot_post', $postId);
        
        return $this->cache->getByTemplate('hot_post', ['id' => $postId], function() use ($postId) {
            return getHotPostFromDatabase($postId);
        }, 7200); // 2小时滑动过期
    }
    
    /**
     * 天气信息（滑动过期 - 频繁查询的城市保持新鲜）
     */
    public function getWeather($city)
    {
        $this->logAccess('api_weather', $city);
        
        return $this->cache->getByTemplate('api_weather', ['city' => $city], function() use ($city) {
            return getWeatherFromAPI($city);
        }, 1800); // 30分钟滑动过期
    }
    
    /**
     * 每日统计（固定过期 - 不适合滑动过期）
     */
    public function getDailyStats($date)
    {
        $this->logAccess('daily_stats', $date);
        
        // 统计数据使用固定过期，不应该因为访问而延长
        return $this->cache->getByTemplate('daily_stats', ['date' => $date], function() use ($date) {
            echo "📊 计算 {$date} 的统计数据\n";
            usleep(500000); // 0.5秒
            
            return [
                'date' => $date,
                'total_users' => rand(1000, 5000),
                'total_posts' => rand(100, 500),
                'total_views' => rand(10000, 50000),
                'calculated_at' => date('Y-m-d H:i:s')
            ];
        }, 86400, false); // 24小时固定过期（不滑动）
    }
    
    /**
     * 模拟用户活动模式
     */
    public function simulateUserActivity($userId, $activityPattern = 'normal')
    {
        echo "\n👤 模拟用户 {$userId} 的 {$activityPattern} 活动模式\n";
        
        switch ($activityPattern) {
            case 'high_frequency':
                // 高频用户：每5秒访问一次，持续1分钟
                echo "高频访问模式：每5秒访问一次\n";
                for ($i = 0; $i < 12; $i++) {
                    echo "第 " . ($i + 1) . " 次访问 (+" . ($i * 5) . "s): ";
                    $profile = $this->getUserProfile($userId);
                    echo "用户 {$profile['name']} 资料已缓存\n";
                    
                    if ($i < 11) sleep(5); // 最后一次不等待
                }
                break;
                
            case 'normal':
                // 正常用户：间隔访问
                $intervals = [10, 30, 60, 120]; // 10秒、30秒、1分钟、2分钟
                foreach ($intervals as $i => $interval) {
                    echo "第 " . ($i + 1) . " 次访问 (+{$interval}s): ";
                    $profile = $this->getUserProfile($userId);
                    echo "用户 {$profile['name']} 资料已缓存\n";
                    
                    if ($i < count($intervals) - 1) {
                        echo "等待 {$interval} 秒...\n";
                        sleep($interval);
                    }
                }
                break;
                
            case 'inactive':
                // 不活跃用户：只访问一次
                echo "不活跃用户：只访问一次\n";
                $profile = $this->getUserProfile($userId);
                echo "用户 {$profile['name']} 资料已缓存\n";
                break;
        }
    }
    
    /**
     * 热点内容访问模拟
     */
    public function simulateHotContent($postId, $accessCount = 10)
    {
        echo "\n🔥 模拟热点文章 {$postId} 的访问 ({$accessCount} 次)\n";
        
        for ($i = 0; $i < $accessCount; $i++) {
            $post = $this->getHotPost($postId);
            echo "第 " . ($i + 1) . " 次访问: {$post['title']} (浏览量: {$post['views']})\n";
            
            // 模拟随机访问间隔
            if ($i < $accessCount - 1) {
                $interval = rand(1, 10);
                sleep($interval);
            }
        }
    }
    
    /**
     * 天气查询模拟（不同城市的访问频率）
     */
    public function simulateWeatherQueries()
    {
        echo "\n🌤️  模拟天气查询\n";
        
        $cities = [
            'Beijing' => 15,    // 热门城市：15次查询
            'Shanghai' => 12,   // 热门城市：12次查询
            'Guangzhou' => 8,   // 中等城市：8次查询
            'Shenzhen' => 5,    // 中等城市：5次查询
            'Hangzhou' => 2     // 冷门城市：2次查询
        ];
        
        foreach ($cities as $city => $queryCount) {
            echo "\n{$city} 城市查询 {$queryCount} 次:\n";
            
            for ($i = 0; $i < $queryCount; $i++) {
                $weather = $this->getWeather($city);
                echo "  第 " . ($i + 1) . " 次: {$city} {$weather['temperature']}°C {$weather['condition']}\n";
                
                if ($i < $queryCount - 1) {
                    sleep(rand(2, 8)); // 随机间隔2-8秒
                }
            }
        }
    }
    
    /**
     * 记录访问日志
     */
    private function logAccess($type, $id)
    {
        $key = "{$type}:{$id}";
        if (!isset($this->accessLog[$key])) {
            $this->accessLog[$key] = [];
        }
        
        $this->accessLog[$key][] = [
            'timestamp' => time(),
            'datetime' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * 获取访问统计
     */
    public function getAccessStats()
    {
        $stats = [];
        
        foreach ($this->accessLog as $key => $accesses) {
            $stats[$key] = [
                'total_accesses' => count($accesses),
                'first_access' => $accesses[0]['datetime'],
                'last_access' => end($accesses)['datetime'],
                'access_frequency' => $this->calculateFrequency($accesses)
            ];
        }
        
        return $stats;
    }
    
    /**
     * 计算访问频率
     */
    private function calculateFrequency($accesses)
    {
        if (count($accesses) < 2) {
            return 0;
        }
        
        $firstTime = $accesses[0]['timestamp'];
        $lastTime = end($accesses)['timestamp'];
        $duration = $lastTime - $firstTime;
        
        if ($duration == 0) {
            return count($accesses);
        }
        
        return round(count($accesses) / ($duration / 60), 2); // 每分钟访问次数
    }
    
    /**
     * 检查缓存状态
     */
    public function checkCacheStatus()
    {
        echo "\n📊 检查缓存状态\n";
        echo "================\n";
        
        $checkItems = [
            ['user_profile', ['id' => 1]],
            ['user_profile', ['id' => 2]],
            ['user_profile', ['id' => 3]],
            ['hot_post', ['id' => 1]],
            ['api_weather', ['city' => 'Beijing']],
            ['api_weather', ['city' => 'Shanghai']],
            ['api_weather', ['city' => 'Hangzhou']],
            ['daily_stats', ['date' => date('Y-m-d')]]
        ];
        
        foreach ($checkItems as [$template, $params]) {
            $exists = $this->cache->hasByTemplate($template, $params);
            $key = $this->keyManager->make($template, $params);
            echo "  - {$key}: " . ($exists ? "✅ 已缓存" : "❌ 未缓存") . "\n";
        }
    }
}

// 4. 实际使用演示
echo "1. 初始化滑动过期服务\n";
echo "======================\n";
$slidingService = new SlidingExpirationService($cache, $keyManager);

echo "\n2. 用户会话管理演示\n";
echo "==================\n";

// 模拟用户登录和持续活动
$session1 = $slidingService->getUserSession(1);
echo "用户1会话: " . json_encode($session1) . "\n";

// 模拟会话保持活跃
echo "\n模拟用户1持续活动（每10秒访问一次）:\n";
for ($i = 0; $i < 5; $i++) {
    sleep(10);
    $session = $slidingService->getUserSession(1);
    echo "第 " . ($i + 2) . " 次访问: 会话保持活跃\n";
}

echo "\n3. 不同用户活动模式对比\n";
echo "========================\n";

// 高频用户
$slidingService->simulateUserActivity(1, 'high_frequency');

// 正常用户
$slidingService->simulateUserActivity(2, 'normal');

// 不活跃用户
$slidingService->simulateUserActivity(3, 'inactive');

echo "\n4. 热点内容访问演示\n";
echo "==================\n";

$slidingService->simulateHotContent(1, 8);

echo "\n5. 天气查询频率演示\n";
echo "==================\n";

$slidingService->simulateWeatherQueries();

echo "\n6. 固定过期 vs 滑动过期对比\n";
echo "============================\n";

// 获取今天的统计（固定过期）
$todayStats = $slidingService->getDailyStats(date('Y-m-d'));
echo "今日统计（固定过期）: " . json_encode($todayStats) . "\n";

// 多次访问统计数据（不会延长过期时间）
echo "\n多次访问统计数据（固定过期不会延长）:\n";
for ($i = 0; $i < 3; $i++) {
    $stats = $slidingService->getDailyStats(date('Y-m-d'));
    echo "第 " . ($i + 1) . " 次访问统计数据\n";
    sleep(5);
}

echo "\n7. 访问统计分析\n";
echo "===============\n";

$accessStats = $slidingService->getAccessStats();
foreach ($accessStats as $key => $stat) {
    echo "资源 {$key}:\n";
    echo "  - 总访问次数: {$stat['total_accesses']}\n";
    echo "  - 首次访问: {$stat['first_access']}\n";
    echo "  - 最后访问: {$stat['last_access']}\n";
    echo "  - 访问频率: {$stat['access_frequency']} 次/分钟\n\n";
}

echo "\n8. 缓存状态检查\n";
echo "===============\n";

$slidingService->checkCacheStatus();

echo "\n9. 缓存统计\n";
echo "===========\n";

$cacheStats = $cache->getStats();
echo "滑动过期缓存统计:\n";
echo "  - 命中次数: {$cacheStats['hits']}\n";
echo "  - 未命中次数: {$cacheStats['misses']}\n";
echo "  - 命中率: {$cacheStats['hit_rate']}%\n";

echo "\n=== 滑动过期机制演示完成 ===\n";
```

## 滑动过期的适用场景

### ✅ 适合滑动过期的场景

1. **用户会话管理**
   ```php
   // 用户活跃时自动延长会话
   $session = $cache->getByTemplate('user_session', ['id' => $userId], $callback, 1800);
   ```

2. **热点内容缓存**
   ```php
   // 热门文章保持缓存，冷门文章自然过期
   $post = $cache->getByTemplate('hot_post', ['id' => $postId], $callback, 3600);
   ```

3. **API响应缓存**
   ```php
   // 频繁查询的API保持新鲜
   $apiData = $cache->getByTemplate('api_weather', ['city' => $city], $callback, 1800);
   ```

4. **用户个性化数据**
   ```php
   // 活跃用户的偏好设置保持缓存
   $preferences = $cache->getByTemplate('user_preferences', ['id' => $userId], $callback, 7200);
   ```

### ❌ 不适合滑动过期的场景

1. **统计报表数据**
   ```php
   // 统计数据应该定时更新，不应因访问而延长
   $stats = $cache->getByTemplate('daily_stats', ['date' => $date], $callback, 86400, false);
   ```

2. **定时任务结果**
   ```php
   // 定时任务的结果有固定的生命周期
   $cronResult = $cache->getByTemplate('cron_result', ['job' => $jobName], $callback, 3600, false);
   ```

3. **临时验证码**
   ```php
   // 验证码必须在固定时间内过期
   $verifyCode = $cache->getByTemplate('verify_code', ['phone' => $phone], $callback, 300, false);
   ```

## 滑动过期的配置策略

### 1. 基于访问频率的TTL
```php
class SmartSlidingExpiration
{
    public function getWithSmartTTL($template, $params, $callback)
    {
        $accessCount = $this->getAccessCount($template, $params);
        
        // 根据访问频率调整TTL
        if ($accessCount > 100) {
            $ttl = 7200; // 高频访问：2小时
        } elseif ($accessCount > 10) {
            $ttl = 3600; // 中频访问：1小时
        } else {
            $ttl = 1800; // 低频访问：30分钟
        }
        
        return $this->cache->getByTemplate($template, $params, $callback, $ttl);
    }
}
```

### 2. 时间段相关的滑动策略
```php
public function getWithTimeBasedSliding($template, $params, $callback)
{
    $hour = date('H');
    
    // 工作时间：短TTL，高活跃度
    if ($hour >= 9 && $hour <= 18) {
        $ttl = 1800; // 30分钟
    } else {
        $ttl = 7200; // 非工作时间：2小时
    }
    
    return $this->cache->getByTemplate($template, $params, $callback, $ttl);
}
```

### 3. 用户类型相关的策略
```php
public function getUserDataWithSliding($userId, $userType = 'normal')
{
    $ttl = match($userType) {
        'vip' => 7200,      // VIP用户：2小时
        'premium' => 3600,  // 高级用户：1小时
        'normal' => 1800,   // 普通用户：30分钟
        default => 900      // 游客：15分钟
    };
    
    return $this->cache->getByTemplate('user_profile', ['id' => $userId], function() use ($userId) {
        return getUserFromDatabase($userId);
    }, $ttl);
}
```

## 性能优化建议

### 1. 监控滑动效果
```php
public function monitorSlidingEffectiveness()
{
    $stats = $this->cache->getStats();
    
    // 滑动过期应该提高缓存命中率
    if ($stats['hit_rate'] > 80) {
        echo "✅ 滑动过期效果良好\n";
    } else {
        echo "⚠️  滑动过期效果需要优化\n";
    }
}
```

### 2. 内存使用控制
```php
public function controlMemoryUsage()
{
    // 设置最大滑动次数，防止数据永不过期
    $maxSlides = 10;
    $slideCount = $this->getSlideCount($key);
    
    if ($slideCount >= $maxSlides) {
        // 强制过期，重新加载
        $this->cache->forget($key);
    }
}
```

### 3. 智能预热
```php
public function preloadHotData()
{
    $hotKeys = $this->getHotKeys(); // 获取热点数据键
    
    foreach ($hotKeys as $key) {
        // 预热热点数据，启用滑动过期
        $this->cache->get($key, $this->getDataLoader($key), 3600);
    }
}
```

## 总结

滑动过期机制的核心价值：

- **智能缓存管理**：热点数据自动保持，冷数据自然淘汰
- **性能优化**：减少热点数据的重复加载
- **用户体验提升**：活跃用户享受更稳定的响应时间
- **资源利用优化**：缓存空间更多用于活跃数据
- **自适应特性**：根据访问模式自动调整缓存策略

通过合理使用滑动过期机制，可以显著提升缓存系统的智能化程度和整体性能。
