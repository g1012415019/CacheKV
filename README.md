# 介绍

在一对一关联情况下的数据缓存的一种尝试，如获取用户的基础信息，用户的im信息，用户的相册信息，用户的设备信息等
使用案例 代码在tests目录下

### 获取多个用户的基础信息
```php
$userHasOne = new UserHasOne();
dd($userHasOne->getInfoList([32,33],['info']));
// 32 => array:2 [ 
//    "info" => array:3 [
//      "id" => 32
//      "nick" => "mimi"
//      "mobile" => "13681985439"
//    ]
//  ]
//  33 => array:2 [
//    "info" => array:3 [
//      "id" => 33
//      "nick" => "QWERTYU"
//      "mobile" => "15688888888"
//    ]
//  ]
//]
```
### 获取多个用户的基础信息和im信息
```php
$userHasOne = new UserHasOne();
dd($userHasOne->getInfoList([32,33],['info','im']));
//array:2 [ 
//  32 => array:2 [ 
//    "info" => array:3 [
//      "id" => 32
//      "nick" => "mimi"
//      "mobile" => "12688888888"
//    ]
//    "im" => array:2 [
//      "id" => 112
//      "im_uuid" => "3"
//    ]
//  ]
//  33 => array:2 [
//    "info" => array:3 [
//      "id" => 33
//      "nick" => "QWERTYU"
//      "mobile" => "11688888888"
//    ]
//    "im" => array:2 [
//      "id" => 231
//      "im_uuid" => "d"
//    ]
//  ]
//]

```
### 当数据发生改变后清空缓存
```php
$userHasOne = new UserHasOne();
dd($userHasOne->forgetCache(34, 'im'));
```