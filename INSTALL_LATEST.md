# 安装最新版本说明

## 🚨 Packagist同步延迟

由于Packagist同步GitHub标签可能有延迟，如果`composer require asfop1/cache-kv`安装的还是旧版本，请使用以下方法安装最新版本：

## 方法1：指定具体版本

```bash
composer require asfop1/cache-kv:1.0.5
```

## 方法2：直接从GitHub安装

```bash
composer config repositories.cache-kv vcs https://github.com/asfop1/CacheKV.git
composer require asfop1/cache-kv:^1.0.5
```

## 方法3：在composer.json中指定

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/asfop1/CacheKV.git"
        }
    ],
    "require": {
        "asfop1/cache-kv": "^1.0.5"
    }
}
```

然后运行：
```bash
composer install
```

## 验证安装版本

安装后可以检查版本：

```bash
composer show asfop1/cache-kv
```

应该显示版本为 `1.0.5`。

## 最新功能

v1.0.5 版本包含：
- ✅ 统计功能配置化支持
- ✅ 性能大幅优化（Pipeline批量操作）
- ✅ 简化代码实现
- ✅ 支持自定义统计前缀和TTL

## Packagist同步后

一旦Packagist同步了最新版本，就可以正常使用：

```bash
composer require asfop1/cache-kv:^1.0.5
```

---

**注意**：这个文件是临时的，Packagist同步后会删除。
