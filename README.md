# EAPM-PHP
#### **a agent for elastic-apm**

## 安装
> **`composer require laokiea/eapm-php`**
  
## 基本用法
> 示例代码在最后

#### 初始化agent
```php
$agent = new EApmPhp\EApmComposer();
```

#### APM相关配置
> SERVER_URL：**APM地址**
> 
> SECRET_TOKEN：提交请求所需要的**token**
> 
> SERVICE_NAME: 使用APM的服务名称，例如**Frontend**

**其他相关配置查看** [APM接入文档](https://bluecity.feishu.cn/docs/doccnDTjBZaEirrtTHhYU1Czarc)

###### 代码
```php
$configure = new \EApmPhp\EApmConfigure(
    "apmserverurlhere",
    "tokenhere",
    "Frontend"
);
$agent = new EApmPhp\EApmComposer();
$agent->setConfigure($configure);
```

#### 服务相关配置
##### 设置debug模式
```php
//设置debug模式，一些错误会直接输出或者记录到日志中
//可以查看[日志](#日志)小节
$agent->setAppConfig("debug", true);
```
##### 设置用户uid
```php
// 设置用户id可以在APM面板上直接粘贴搜索
$agent->setUserId(1000);
```
##### 设置采样率
```php
// 设置采样率可以控制日志记录的数量
$agent->setSampleRate(0.5);
```

##### 接入
```php
$agent->EApmUse();
```
>EApmUse可以接受一个匿名函数，当作中间件传入agent中，以Frontend为例：
>经典的匿名函数，可以改成以下
```php
$invoke_func = function() use ($_INVOKING_FILE_) {
    require_once($_INVOKING_FILE_);
};
$agent->EApmUse($invoke_func);
```
>这样可以在入口处直接调用agent现有的中间件，当然你可以在真正使用到的地方再调用 `$agent->EApmUse();`

##### 启动一个Transaction(会话)
>Transaction(会话)代表着一系列Http/DB/Grpc等操作的集合
```php
$transaction = $agent->startNewTransaction("POST /avatar/{uid}/upload", "request");
```
>startNewTransaction方法接受name和type参数，这两个参数标示着一次会话的特征，比如在一次HTTP请求中，逻辑是处理用户上传头像，那么name和type可以是

>**name**: `POST /avatar/{uid}/upload`(也可以直接用中文:`用户上传头像`，好处是在面板上可以一目了然)

>⚠️如果请求地址中有ID，token等不同值的参数，比如uid，那么用{uid}代替，其他类型的参数也一样，这样在APM面板上不会产生很多的会话名称。

>**type**: `http.request`

##### 结束一个会话
```php
$transaction->end();
```
>**会话**（包括其他的**Even**t，比如**Span,Error**）可以手动结束，像上面这样，但是使用中我们可以不写这行代码，agent会自动帮我们处理

##### 发送所有的会话信息
```php
$agent->eventsPush();
```
>和结束会话类似，我们不需要手动操作，agent会自动帮我们发送。

## 添加Span
>Span和会话类似，也是代表一种操作，只不过更具体。下面具体Http/DB等操作添加Span为例

### DB Span
> 当我们操作数据库时，实际都是数据在网络环境中的传输，往往一个TCP请求会影响整个HTTP服务的性能，所以DB的操作非常值得去记录追踪

#### 添加Mysql Span
##### 代码
```php
$mysqlSpan = $agent->startNewSpan("SELECT", "db.mysql", "blued.adm", $transaction);

$result = $mysqlSpan->startMysqlTypeSpan(getMysqlInstance(), "select * from adm where type = 1 and status = 1");

var_dump($result);
```
> 首先调用`startNewSpan`实例化一个任意类型的Span，接受四个参数：
> **name**：和transaction的name类似

> **type**：和transaction的type类似

> **sub_type**：多一个子类型，更加具体的描述操作

> **parent**: 父节点，大部分情况是上面**实例化的transaction**，但是也可以是其他的**span**

> ⚠️**对于Mysql操作，上面参数的值有以下建议:**
> 1. **name**传入`SELECT/UPDATE/DELETE`操作名
> 2. **type**以点分隔符，记录操作的数据库类型,例如`db.mysql`
> 3. **sub_type**以点分隔符，记录数据库及表名，例如`blued.adm`

>`startMysqlTypeSpan`方法表示进行具体的mysql操作，这里agent做了一个封装
>可以传入实例化后的`mysqli/PDO`对象以及需要执行的sql语句，函数会将执行后的结果返回。

>⚠️需要注意的是，传入`mysqli`实例之前，必须已经调用`select_db`方法

#### 添加Redis Span
##### 代码
```php
$redisSpan = $agent->startNewSpan("ZREVRANGE", "db.redis", "hermes", $transaction);
$result = $redisSpan->startRedisTypeSpan(getRedisInstance(), "ZREVRANGE", "u:9336644:sessions", 0, 199, true);
var_dump($result);

$redisSpan = $agent->startNewSpan("HGET", "db.redis", "hermes", $transaction);
$result = $redisSpan->startRedisTypeSpan(getRedisInstance(), "HGET", "u:9336644:got_cursor", "2_15261526-9225711");
var_dump($result);
```
>与上面创建Mysql Span类似，也是先实例化一个任意类型的Span，然后利用封装好的`startRedisTypeSpan`方法完成命令执行。

> ⚠️**对于Redis操作，上面参数的值有以下建议:**
> 1. **name**传入`ZREVRANGE/HGET`命令名
> 2. **type**以点分隔符，记录操作的数据库类型,例如`db.redis`
> 3. **sub_type**以点分隔符，记录db实例名，例如`hermes`
> 4. `startRedisTypeSpan`的参数从第三个开始，和执行命令的参数保持一致即可。

#### HTTP Span
> 见分布式追踪章节

#### 消息队列操作
> 还有一种常见的数据息在网络间传播的操作就是消息队列的生产和消费
##### 代码
```php
$publishSpan = $agent->startNewSpan("Publish", "kafka.nezha", "topic-publish", $transaction);
// 这里是你的生产代码
// xxxx
$publishSpan->setMessageQueueSpanContext("publish data body");

$consumeSpan = $agent->startNewSpan("Consume", "rabbitMQ", "topic-consume", $transaction);
// 这里是你的消费代码
// xxxx
$consumeSpan->setMessageQueueSpanContext("consume data body");

// 如果是消费，这里是你的处理消息的代码
// xxxx
```
>如上，先创建一个任意类型的Span，和db及http Span不同的是，agent没有封装生产消费的逻辑，但是消息队列操作的Span可以调用setMessageQueueSpanContext方法设置context

> ⚠️**对于消息队列的操作，上面参数的值有以下建议:**
> 1. **name**传入`Publish/Consume`表示生产还是消费
> 2. **type**以点分隔符，记录操作的消息队列种类, 可以带上额外的实例名，例如`kafka.nezha`, `rabbitMQ`
> 3. **sub_type**表示消费的queue/Topic等，例如`pns-sender-topic`

>setMessageQueueSpanContext方法**接受生产或者消费到的消息体作为参数**(如果生产或者消费多条消息，**可以传空字符串或者最后一条消息的内容**)

## 分布式追踪
> 一个完整的HTTP请求，可能会经过多个服务。
> 例如在Frontend里调用user-profile,广告，用户关系等微服务，那么可以在以上的微服务中也接入eapm-php,达到分布式追踪的效果。
> **agent会自动处理分布式追踪中的trace信息**。
> 当然如果微服务里没有接入agent，也没关系，agent也会记录该次请求。

>**还是以代码为示例：**

#### 假设Frontend里有代码如下：
```php
$httpSpan = $agent->startNewSpan("GET user_profile", "request.microservice", "user_profile", $transaction);
    
$response = $httpSpan->startHttpTypeSpan("GET", "http://localhost:8812/test_distribute.php", []);
```
>与上面添加DB Span类似，添加HTTP Span也是同样的做法，先利用`startNewSpan`实例化一个任意类型的Span，然后利用封装好的`startHttpTypeSpan`方法来完成微服务调用。

> ⚠️**对于HTTP操作，上面参数的值有以下建议:**
> 1. **name**传入`请求方法 微服务名`,例如`GET user_profile`
> 2. **type**以点分隔符，记录操作的类型，例如调用微服务则是`request.microservice`
> 3. **sub_type**以点分隔符，记录微服务名，例如`user_profile`

>⚠️对于`startHttpTypeSpan`方法的参数，说明如下：
>前两个分别是请求类型和地址(完整的地址，最好以https/http打头)
>第三个参数，可以支持多个参数
>1. timeout,超时时间
>2. verify，验证https证书，默认为false
>3. headers，请求所带的headers头，格式为key-value数组
>4. json,POST请求所带的json body。
```php
// 一个示例
[
    "timeout" => 1.0,
    "verify" => false,
    "headers" => [
        "Content-Type" => "application/json",
    ],
    "json" => [
        "app" => "blued",
    ],
]
```

#### 假设User-Profile微服务里有代码如下：
```php
$configure = new \EApmPhp\EApmConfigure(
    "apmserverurlhere",
    "tokenhere",
    "eapm-php-user-profile"
);

$agent = new EApmPhp\EApmComposer();
$agent->setConfigure($configure);
$agent->setAppConfig("debug", true);
$agent->setUserId(1001);
$agent->EApmUse();

$transaction = $agent->startNewTransaction("GET /user/{uid}/info", "request");
$mysqlSpan = $agent->startNewSpan("SELECT", "db.mysql", "blued.adm", $transaction);
$result = $mysqlSpan->startMysqlTypeSpan(getMysqlInstance(), "select * from adm where type = 1 and status = 1");
```

>可以看到上面的代码其实就是基本实例的完整版。没有额外的处理，agent会自动处理分布式追踪，效果在APM服务里就像这样：

>⚠️ 我们并没有调用end方法和eventPush方法，agent会自动处理。

>在APM面板上的效果：
![image](https://user-images.githubusercontent.com/13516246/93850123-0a114800-fce0-11ea-9362-de5b6c02ec87.png)
>可以看到两个服务串成一个完整的会话，微服务里的db操作也可以清晰的看到


## Error捕获
#### 代码
```php
$agent->captureError(new Error("test error"), $transaction);
$agent->captureError(new Exception("test exception"), $transaction);
```
> agent可以手动捕获Throwable类型的任意错误和异常，并发给APM server
> 在APM面板上可以看到详细的错误信息

## 日志
>当设置debug参数为true时，agent会自动记录一些运行时错误到/data/logs/EAPM-PHP目录下
>也可以手动记录日志
```php
$agent->getLogger()->logWarn("log warning info");
$agent->getLogger()->logError("log error info");
```

## 示例代码
```php
<?php

require_once "./vendor/autoload.php";

$configure = new \EApmPhp\EApmConfigure(
    "apmserverurlhere",
    "tokenhere",
    "eapm-php-project"
);

$agent = new EApmPhp\EApmComposer();
$agent->setConfigure($configure);

$agent->setAppConfig("debug", true);
$agent->setUserId(1000);
$agent->setSampleRate(0.5);

// start
$agent->EApmUse(functoion(){
    echo "trace start.";
});

$transaction = $agent->startNewTransaction("POST /user/{uid}/avatar/upload", "http.request");

// mysql span
$mysqlSpan = $agent->startNewSpan("SELECT", "db.mysql", "blued.adm", $transaction);
// getMysqlInstance 返回一个数据库实例
$result = $mysqlSpan->startMysqlTypeSpan(getMysqlInstance(), "select * from adm where type = 1 and status = 1");

// redis span
$redisSpan = $agent->startNewSpan("ZREVRANGE", "db.redis", "hermes", $transaction);
// getRedisInstance返回一个redis实例
$result = $redisSpan->startRedisTypeSpan(getRedisInstance(), "ZREVRANGE", "u:9336644:sessions", 0, 199, true);

// http span
try {
    $httpSpan = $agent->startNewSpan("GET user_profile", "request.microservice", "user_profile", $transaction);
    // 请求地址需要替换
    $response = $httpSpan->startHttpTypeSpan("GET", "http://localhost:8812/test_distribute.php", []);
} catch (\Exception $e) {
    $agent->getLogger()->logError((string)$e);
    $agent->captureError($e, $httpSpan);
}
```
