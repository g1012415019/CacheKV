# 实战案例

本文档提供了 CacheKV 在真实业务场景中的应用案例。

## 案例 1：电商用户系统

### 业务场景
- 用户信息查询频繁
- 用户权限需要实时验证
- 用户设置变更后需要清除相关缓存

### 实现方案

```php
// 缓存模板定义
class CacheTemplates {
    const USER = 'user_profile';
    const USER_PERMISSIONS = 'user_permissions';
    const USER_SETTINGS = 'user_settings';
    const USER_CART = 'user_cart';
}

// 用户缓存辅助类
class UserCacheHelper {
    private static $cache;
    
    private static function getCache() {
        if (!self::$cache) {
            self::$cache = CacheKVFactory::store();
        }
        return self::$cache;
    }
    
    // 获取用户基本信息
    public static function getUser($userId) {
        return self::getCache()->getByTemplate(CacheTemplates::USER, ['id' => $userId], function() use ($userId) {
            $user = DB::table('users')->find($userId);
            if (!$user) {
                return null;
            }
            
            return [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'created_at' => $user->created_at,
            ];
        }, 3600); // 缓存1小时
    }
    
    // 获取用户权限
    public static function getUserPermissions($userId) {
        return self::getCache()->getByTemplate(CacheTemplates::USER_PERMISSIONS, ['user_id' => $userId], function() use ($userId) {
            return DB::table('user_permissions')
                ->where('user_id', $userId)
                ->pluck('permission')
                ->toArray();
        }, 1800); // 缓存30分钟
    }
    
    // 获取用户设置
    public static function getUserSettings($userId) {
        return self::getCache()->getByTemplate(CacheTemplates::USER_SETTINGS, ['user_id' => $userId], function() use ($userId) {
            $settings = DB::table('user_settings')
                ->where('user_id', $userId)
                ->get()
                ->keyBy('key')
                ->map(function($item) {
                    return $item->value;
                })
                ->toArray();
                
            return $settings ?: [];
        }, 7200); // 缓存2小时
    }
    
    // 获取用户购物车
    public static function getUserCart($userId) {
        return self::getCache()->getByTemplate(CacheTemplates::USER_CART, ['user_id' => $userId], function() use ($userId) {
            return DB::table('cart_items')
                ->where('user_id', $userId)
                ->join('products', 'cart_items.product_id', '=', 'products.id')
                ->select('cart_items.*', 'products.name', 'products.price', 'products.image')
                ->get()
                ->toArray();
        }, 600); // 缓存10分钟
    }
    
    // 批量获取用户信息
    public static function getUsers($userIds) {
        $cache = self::getCache();
        $keyManager = CacheKVFactory::getKeyManager();
        
        $userKeys = array_map(function($id) use ($keyManager) {
            return $keyManager->make(CacheTemplates::USER, ['id' => $id]);
        }, $userIds);
        
        return $cache->getMultiple($userKeys, function($missingKeys) {
            $missingUserIds = array_map(function($key) {
                preg_match('/user_profile:(\d+)$/', $key, $matches);
                return (int)$matches[1];
            }, $missingKeys);
            
            $users = DB::table('users')
                ->whereIn('id', $missingUserIds)
                ->get()
                ->keyBy('id')
                ->toArray();
            
            $result = [];
            foreach ($users as $user) {
                $key = CacheKVFactory::getKeyManager()->make(CacheTemplates::USER, ['id' => $user->id]);
                $result[$key] = [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                ];
            }
            
            return $result;
        });
    }
    
    // 清除用户相关的所有缓存
    public static function clearUserCache($userId) {
        $cache = self::getCache();
        $cache->deleteByTemplate(CacheTemplates::USER, ['id' => $userId]);
        $cache->deleteByTemplate(CacheTemplates::USER_PERMISSIONS, ['user_id' => $userId]);
        $cache->deleteByTemplate(CacheTemplates::USER_SETTINGS, ['user_id' => $userId]);
        $cache->deleteByTemplate(CacheTemplates::USER_CART, ['user_id' => $userId]);
    }
}

// 业务使用
class UserController {
    public function show($userId) {
        $user = UserCacheHelper::getUser($userId);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }
        
        $permissions = UserCacheHelper::getUserPermissions($userId);
        $settings = UserCacheHelper::getUserSettings($userId);
        
        return response()->json([
            'user' => $user,
            'permissions' => $permissions,
            'settings' => $settings,
        ]);
    }
    
    public function update($userId, Request $request) {
        // 更新用户信息
        DB::table('users')->where('id', $userId)->update($request->only(['username', 'email', 'avatar']));
        
        // 清除相关缓存
        UserCacheHelper::clearUserCache($userId);
        
        return response()->json(['message' => 'User updated successfully']);
    }
}
```

## 案例 2：商品信息缓存系统

### 业务场景
- 商品信息查询量大
- 价格变动频繁
- 库存实时性要求高
- 需要按分类批量清除缓存

### 实现方案

```php
// 缓存模板定义
class CacheTemplates {
    const PRODUCT = 'product_info';
    const PRODUCT_PRICE = 'product_price';
    const PRODUCT_INVENTORY = 'product_inventory';
    const CATEGORY = 'category_info';
    const PRODUCT_REVIEWS = 'product_reviews';
}

// 商品缓存辅助类
class ProductCacheHelper {
    private static $cache;
    
    private static function getCache() {
        if (!self::$cache) {
            self::$cache = CacheKVFactory::store();
        }
        return self::$cache;
    }
    
    // 获取商品基本信息
    public static function getProduct($productId) {
        return self::getCache()->getByTemplate(CacheTemplates::PRODUCT, ['id' => $productId], function() use ($productId) {
            $product = DB::table('products')
                ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                ->where('products.id', $productId)
                ->select('products.*', 'categories.name as category_name')
                ->first();
                
            if (!$product) {
                return null;
            }
            
            return [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'category_id' => $product->category_id,
                'category_name' => $product->category_name,
                'images' => json_decode($product->images, true),
                'attributes' => json_decode($product->attributes, true),
                'created_at' => $product->created_at,
            ];
        }, 3600); // 缓存1小时
    }
    
    // 获取商品价格（变动频繁，缓存时间短）
    public static function getProductPrice($productId) {
        return self::getCache()->getByTemplate(CacheTemplates::PRODUCT_PRICE, ['id' => $productId], function() use ($productId) {
            $price = DB::table('product_prices')
                ->where('product_id', $productId)
                ->where('is_active', true)
                ->orderBy('created_at', 'desc')
                ->first();
                
            return $price ? [
                'original_price' => $price->original_price,
                'sale_price' => $price->sale_price,
                'discount_rate' => $price->discount_rate,
                'start_time' => $price->start_time,
                'end_time' => $price->end_time,
            ] : null;
        }, 300); // 缓存5分钟
    }
    
    // 获取商品库存（实时性要求高）
    public static function getProductInventory($productId) {
        return self::getCache()->getByTemplate(CacheTemplates::PRODUCT_INVENTORY, ['id' => $productId], function() use ($productId) {
            $inventory = DB::table('product_inventory')
                ->where('product_id', $productId)
                ->first();
                
            return $inventory ? [
                'stock' => $inventory->stock,
                'reserved' => $inventory->reserved,
                'available' => $inventory->stock - $inventory->reserved,
                'updated_at' => $inventory->updated_at,
            ] : null;
        }, 60); // 缓存1分钟
    }
    
    // 获取商品评价统计
    public static function getProductReviews($productId) {
        return self::getCache()->getByTemplate(CacheTemplates::PRODUCT_REVIEWS, ['id' => $productId], function() use ($productId) {
            $reviews = DB::table('product_reviews')
                ->where('product_id', $productId)
                ->where('status', 'approved')
                ->selectRaw('
                    COUNT(*) as total_count,
                    AVG(rating) as average_rating,
                    SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
                    SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
                    SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
                    SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
                    SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
                ')
                ->first();
                
            return [
                'total_count' => $reviews->total_count,
                'average_rating' => round($reviews->average_rating, 1),
                'rating_distribution' => [
                    5 => $reviews->five_star,
                    4 => $reviews->four_star,
                    3 => $reviews->three_star,
                    2 => $reviews->two_star,
                    1 => $reviews->one_star,
                ],
            ];
        }, 1800); // 缓存30分钟
    }
    
    // 获取完整的商品信息
    public static function getFullProduct($productId) {
        $product = self::getProduct($productId);
        if (!$product) {
            return null;
        }
        
        $price = self::getProductPrice($productId);
        $inventory = self::getProductInventory($productId);
        $reviews = self::getProductReviews($productId);
        
        return array_merge($product, [
            'price' => $price,
            'inventory' => $inventory,
            'reviews' => $reviews,
        ]);
    }
    
    // 批量获取商品信息
    public static function getProducts($productIds) {
        $cache = self::getCache();
        $keyManager = CacheKVFactory::getKeyManager();
        
        $productKeys = array_map(function($id) use ($keyManager) {
            return $keyManager->make(CacheTemplates::PRODUCT, ['id' => $id]);
        }, $productIds);
        
        return $cache->getMultiple($productKeys, function($missingKeys) {
            $missingProductIds = array_map(function($key) {
                preg_match('/product_info:(\d+)$/', $key, $matches);
                return (int)$matches[1];
            }, $missingKeys);
            
            $products = DB::table('products')
                ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                ->whereIn('products.id', $missingProductIds)
                ->select('products.*', 'categories.name as category_name')
                ->get()
                ->keyBy('id');
            
            $result = [];
            foreach ($products as $product) {
                $key = CacheKVFactory::getKeyManager()->make(CacheTemplates::PRODUCT, ['id' => $product->id]);
                $result[$key] = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'category_id' => $product->category_id,
                    'category_name' => $product->category_name,
                    'images' => json_decode($product->images, true),
                ];
            }
            
            return $result;
        });
    }
    
    // 设置带标签的商品缓存
    public static function setProductWithTags($productId, $productData) {
        $cache = self::getCache();
        $categoryId = $productData['category_id'];
        
        $cache->setByTemplateWithTag(
            CacheTemplates::PRODUCT,
            ['id' => $productId],
            $productData,
            ['products', "product_{$productId}", "category_{$categoryId}"]
        );
    }
    
    // 清除商品相关缓存
    public static function clearProductCache($productId) {
        $cache = self::getCache();
        $cache->deleteByTemplate(CacheTemplates::PRODUCT, ['id' => $productId]);
        $cache->deleteByTemplate(CacheTemplates::PRODUCT_PRICE, ['id' => $productId]);
        $cache->deleteByTemplate(CacheTemplates::PRODUCT_INVENTORY, ['id' => $productId]);
        $cache->deleteByTemplate(CacheTemplates::PRODUCT_REVIEWS, ['id' => $productId]);
    }
    
    // 按分类清除商品缓存
    public static function clearCategoryProducts($categoryId) {
        $cache = self::getCache();
        $cache->clearTag("category_{$categoryId}");
    }
}

// 业务使用
class ProductController {
    public function show($productId) {
        $product = ProductCacheHelper::getFullProduct($productId);
        if (!$product) {
            return response()->json(['error' => 'Product not found'], 404);
        }
        
        return response()->json(['product' => $product]);
    }
    
    public function updatePrice($productId, Request $request) {
        // 更新价格
        DB::table('product_prices')->insert([
            'product_id' => $productId,
            'original_price' => $request->original_price,
            'sale_price' => $request->sale_price,
            'is_active' => true,
            'created_at' => now(),
        ]);
        
        // 只清除价格缓存，保留其他缓存
        $cache = CacheKVFactory::store();
        $cache->deleteByTemplate(CacheTemplates::PRODUCT_PRICE, ['id' => $productId]);
        
        return response()->json(['message' => 'Price updated successfully']);
    }
}
```

## 案例 3：API 响应缓存

### 业务场景
- 外部 API 调用成本高
- 响应时间较长
- 数据更新频率不高
- 需要处理 API 异常情况

### 实现方案

```php
// 缓存模板定义
class CacheTemplates {
    const API_WEATHER = 'api_weather';
    const API_EXCHANGE_RATE = 'api_exchange_rate';
    const API_NEWS = 'api_news';
    const API_STOCK_PRICE = 'api_stock_price';
}

// API 缓存辅助类
class ApiCacheHelper {
    private static $cache;
    
    private static function getCache() {
        if (!self::$cache) {
            self::$cache = CacheKVFactory::store();
        }
        return self::$cache;
    }
    
    // 获取天气信息
    public static function getWeather($city) {
        return self::getCache()->getByTemplate(CacheTemplates::API_WEATHER, ['city' => $city], function() use ($city) {
            try {
                $response = Http::timeout(10)->get('https://api.weather.com/v1/current', [
                    'key' => config('services.weather.api_key'),
                    'q' => $city,
                ]);
                
                if ($response->successful()) {
                    $data = $response->json();
                    return [
                        'city' => $city,
                        'temperature' => $data['current']['temp_c'],
                        'condition' => $data['current']['condition']['text'],
                        'humidity' => $data['current']['humidity'],
                        'wind_speed' => $data['current']['wind_kph'],
                        'updated_at' => now()->toISOString(),
                    ];
                }
                
                throw new \Exception('Weather API request failed');
                
            } catch (\Exception $e) {
                Log::error("Weather API error for city {$city}: " . $e->getMessage());
                
                // 返回默认数据或抛出异常
                return [
                    'city' => $city,
                    'error' => 'Weather data temporarily unavailable',
                    'updated_at' => now()->toISOString(),
                ];
            }
        }, 1800); // 缓存30分钟
    }
    
    // 获取汇率信息
    public static function getExchangeRate($fromCurrency, $toCurrency) {
        $cacheKey = "{$fromCurrency}_{$toCurrency}";
        
        return self::getCache()->getByTemplate(CacheTemplates::API_EXCHANGE_RATE, ['pair' => $cacheKey], function() use ($fromCurrency, $toCurrency) {
            try {
                $response = Http::timeout(10)->get('https://api.exchangerate.com/v4/latest/' . $fromCurrency);
                
                if ($response->successful()) {
                    $data = $response->json();
                    $rate = $data['rates'][$toCurrency] ?? null;
                    
                    if ($rate) {
                        return [
                            'from' => $fromCurrency,
                            'to' => $toCurrency,
                            'rate' => $rate,
                            'date' => $data['date'],
                            'updated_at' => now()->toISOString(),
                        ];
                    }
                }
                
                throw new \Exception('Exchange rate API request failed');
                
            } catch (\Exception $e) {
                Log::error("Exchange rate API error for {$fromCurrency} to {$toCurrency}: " . $e->getMessage());
                return null;
            }
        }, 3600); // 缓存1小时
    }
    
    // 获取新闻列表
    public static function getNews($category = 'general', $limit = 10) {
        return self::getCache()->getByTemplate(CacheTemplates::API_NEWS, ['category' => $category, 'limit' => $limit], function() use ($category, $limit) {
            try {
                $response = Http::timeout(15)->get('https://newsapi.org/v2/top-headlines', [
                    'apiKey' => config('services.news.api_key'),
                    'category' => $category,
                    'pageSize' => $limit,
                    'country' => 'us',
                ]);
                
                if ($response->successful()) {
                    $data = $response->json();
                    
                    return [
                        'category' => $category,
                        'total_results' => $data['totalResults'],
                        'articles' => array_map(function($article) {
                            return [
                                'title' => $article['title'],
                                'description' => $article['description'],
                                'url' => $article['url'],
                                'image' => $article['urlToImage'],
                                'published_at' => $article['publishedAt'],
                                'source' => $article['source']['name'],
                            ];
                        }, $data['articles']),
                        'updated_at' => now()->toISOString(),
                    ];
                }
                
                throw new \Exception('News API request failed');
                
            } catch (\Exception $e) {
                Log::error("News API error for category {$category}: " . $e->getMessage());
                return [
                    'category' => $category,
                    'error' => 'News data temporarily unavailable',
                    'articles' => [],
                    'updated_at' => now()->toISOString(),
                ];
            }
        }, 900); // 缓存15分钟
    }
    
    // 获取股票价格
    public static function getStockPrice($symbol) {
        return self::getCache()->getByTemplate(CacheTemplates::API_STOCK_PRICE, ['symbol' => $symbol], function() use ($symbol) {
            try {
                $response = Http::timeout(10)->get('https://api.alphavantage.co/query', [
                    'function' => 'GLOBAL_QUOTE',
                    'symbol' => $symbol,
                    'apikey' => config('services.alphavantage.api_key'),
                ]);
                
                if ($response->successful()) {
                    $data = $response->json();
                    $quote = $data['Global Quote'] ?? null;
                    
                    if ($quote) {
                        return [
                            'symbol' => $symbol,
                            'price' => floatval($quote['05. price']),
                            'change' => floatval($quote['09. change']),
                            'change_percent' => $quote['10. change percent'],
                            'volume' => intval($quote['06. volume']),
                            'updated_at' => now()->toISOString(),
                        ];
                    }
                }
                
                throw new \Exception('Stock API request failed');
                
            } catch (\Exception $e) {
                Log::error("Stock API error for symbol {$symbol}: " . $e->getMessage());
                return null;
            }
        }, 300); // 缓存5分钟
    }
    
    // 批量获取股票价格
    public static function getStockPrices($symbols) {
        $results = [];
        
        foreach ($symbols as $symbol) {
            $price = self::getStockPrice($symbol);
            if ($price) {
                $results[$symbol] = $price;
            }
        }
        
        return $results;
    }
    
    // 预热缓存
    public static function warmupCache() {
        $popularCities = ['New York', 'London', 'Tokyo', 'Beijing'];
        $popularCurrencies = [
            ['USD', 'EUR'],
            ['USD', 'GBP'],
            ['USD', 'JPY'],
            ['EUR', 'GBP'],
        ];
        $popularStocks = ['AAPL', 'GOOGL', 'MSFT', 'TSLA'];
        
        // 预热天气缓存
        foreach ($popularCities as $city) {
            self::getWeather($city);
        }
        
        // 预热汇率缓存
        foreach ($popularCurrencies as [$from, $to]) {
            self::getExchangeRate($from, $to);
        }
        
        // 预热股票缓存
        foreach ($popularStocks as $symbol) {
            self::getStockPrice($symbol);
        }
        
        Log::info('API cache warmup completed');
    }
}

// 业务使用
class ApiController {
    public function weather($city) {
        $weather = ApiCacheHelper::getWeather($city);
        return response()->json(['weather' => $weather]);
    }
    
    public function exchangeRate($from, $to) {
        $rate = ApiCacheHelper::getExchangeRate($from, $to);
        if (!$rate) {
            return response()->json(['error' => 'Exchange rate not available'], 404);
        }
        
        return response()->json(['exchange_rate' => $rate]);
    }
    
    public function news($category = 'general') {
        $news = ApiCacheHelper::getNews($category);
        return response()->json(['news' => $news]);
    }
    
    public function stocks(Request $request) {
        $symbols = $request->input('symbols', []);
        $stocks = ApiCacheHelper::getStockPrices($symbols);
        
        return response()->json(['stocks' => $stocks]);
    }
}

// 定时任务：预热缓存
class WarmupCacheCommand extends Command {
    protected $signature = 'cache:warmup';
    protected $description = 'Warmup API cache';
    
    public function handle() {
        $this->info('Starting cache warmup...');
        ApiCacheHelper::warmupCache();
        $this->info('Cache warmup completed!');
    }
}
```

这些实战案例展示了 CacheKV 在不同业务场景中的应用，包括：

1. **用户系统**：多层缓存、权限验证、批量操作
2. **商品系统**：分层缓存、标签管理、实时性处理
3. **API 缓存**：外部依赖、异常处理、预热策略

每个案例都体现了 CacheKV 的核心优势：简化缓存操作、提升性能、增强可维护性。
