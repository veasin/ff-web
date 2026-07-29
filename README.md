# ff-web

ff 的 Web 中间件集合——CORS、CSRF、Gzip、JSON、日志、限流、静态文件服务、认证（Basic/Token/JWT/API Key）。

```
composer require veasin/ff-web
```

---

## user($data, $storage) — 用户凭证管理

统一凭证生命周期管理：记录、读取、检测、销毁用户凭证。支持多个独立 storage（default / session / token / jwt）。

```php
use function ff\web\{user};

user();                              // 从默认 storage 读取当前用户
user($userData);                     // 记录用户到默认 storage，返回 token
user(true);                          // 检测默认 storage 是否有凭证
user(false);                         // 销毁默认 storage 的凭证
user($userData, 'session');          // 指定 storage 写入
user(null, 'jwt');                   // 从 jwt storage 读取
```

容器配置——storage 需注册 `#user.{name}`，包含五个回调：

| 键 | 签名 | 用途 |
|---|---|---|
| `token` | `fn(): ?string` | 读取当前请求携带的凭证 |
| `read` | `fn(string $token): ?array` | 用 token 恢复用户数据 |
| `check` | `fn(string $token): bool` | 检测 token 是否有效 |
| `write` | `fn(object\|array $user): string` | 持久化用户数据，返回新 token |
| `destroy` | `fn(string $token): void` | 销毁凭证 |

```php
// 默认 storage：基于 session
container('#user.default', user\session());

// 自定义 storage：基于 token
container('#user.token', user\token(
    fn(string $token): ?array => lookupUserByToken($token)
));

// 自定义 storage：基于 JWT
container('#user.jwt', user\jwt('your-secret-key', ttl: 3600));
```

---

## user\session() — Session 凭证 storage

基于 PHP 原生 session 的凭证存储，零外部依赖。`token` 返回 `session_id()`，数据存储在 `$_SESSION['#user']`。

```php
use function ff\web\user\session;

container('#user.default', user\session());
```

---

## user\token($provider, $ttl, $prefix) — Token 凭证 storage

通过 `$provider` 回调接入 Redis/APCu/DB 等任意存储后端。自动从 `Authorization: Bearer` 头提取 token，生成 64 位随机 hex token。

```php
use function ff\web\user\token;

// Redis 后端
container('#user.default', user\token(
    fn(string $op, string $key, ...$a) => match($op){
        'get' => redis($key),
        'set' => redis($key, $a[0], $a[1] ?? 86400),
        'del' => redis($key, null),
    },
));
```

参数：
- **`$provider`**: `callable` 必填 存储回调，签名：
  - `'get'` → `fn(string $key): ?string` — 读
  - `'set'` → `fn(string $key, string $data, ?int $ttl): void` — 写
  - `'del'` → `fn(string $key): void` — 删
- **`$ttl`**: `int` 默认 `86400` token 过期秒数
- **`$prefix`**: `string` 默认 `'ff_ut:'` token 存储 key 前缀

---

## user\jwt($secret, $ttl, $algo) — JWT 凭证 storage

纯 PHP 实现 HS256 JWT，零外部依赖。自动从 `Authorization: Bearer` 头提取 token，payload 内嵌 `user` 数据和 `exp`/`iat` 声明。

```php
use function ff\web\user\jwt;

container('#user.default', user\jwt('my-secret-key'));
container('#user.default', user\jwt('my-secret-key', ttl: 3600, algo: 'HS256'));
```

参数：
- **`$secret`**: `string` 必填 HMAC 签名密钥
- **`$ttl`**: `int` 默认 `86400` token 过期秒数
- **`$algo`**: `string` 默认 `'HS256'` 算法，当前仅支持 `HS256`

---

所有预制中间件返回 `callable`，直接传入 `middleware()` 或 `hump()` 使用：

```php
use function ff\web\middleware\{cors, serve, json};
use function ff\web\middleware\auth\{basic, token, jwt, apikey};
use function ff\web\middleware\{csrf, error, gzip, log as mw_log, rate};

middleware(cors(), basic(), log(), $handler);
```

**认证相关**

## basic(prefix, realm) — HTTP Basic 认证

从 `Authorization: Basic` 头提取用户名密码，调用验证器。验证器返回值直接存入 user（可返回用户对象而非 `true`），支持密码含冒号。

```php
use function ff\web\middleware\auth\basic;
container('#web/auth/validators', [fn($user, $pass) => $user]);//设置验证器
middleware(basic(), $handler);//使用中间件
container('#web/auth/user');//获取认证用户
```

参数：
- **`$prefix`**: `string` 默认 `'#web/auth'` 容器键前缀
- **`$realm`**: `?string` 默认 `null` WWW-Authenticate realm，null 时使用 i18n 翻译

容器配置：
- **`{prefix}/validators`**: `array` - 验证器数组，接收 `($user, $pass)` 返回用户信息
- **`{prefix}/user`**: `mixed` - 认证通过后写入用户信息

---

## token(prefix, headerName) — Token 认证

从请求头或 URL 查询参数 `?token=` 提取 token，调用验证器。

```php
use function ff\web\middleware\auth\token;
container('#web/auth/validators', [fn($token) => $user]);//设置验证器
middleware(token(), $handler);//使用中间件，从 Authorization 头提取
middleware(token('#web/auth', 'X-Auth-Token'), $handler);//自定义请求头
```

参数：
- **`$prefix`**: `string` 默认 `'#web/auth'` 容器键前缀
- **`$headerName`**: `string` 默认 `'Authorization'` 请求头名称（未取到时 fallback 查询参数 `token`）

容器配置：
- **`{prefix}/validators`**: `array` - 验证器数组，接收 `($token)` 返回用户信息
- **`{prefix}/user`**: `mixed` - 认证通过后写入用户信息

---

## jwt(prefix, algo) — JWT 认证

从 `Authorization: Bearer <token>` 提取 JWT，HMAC 验证签名后解码 payload。

```php
use function ff\web\middleware\auth\jwt;
container('#web/auth/secret', 'your-secret-key');//设置 HMAC 签名密钥
container('#web/auth/validators', [fn($payload) => $user]);//设置验证器
middleware(jwt(), $handler);//使用中间件
container('#web/auth/payload');//获取解码后的 JWT payload
container('#web/auth/user');//获取认证用户
```

参数：
- **`$prefix`**: `string` 默认 `'#web/auth'` 容器键前缀
- **`$algo`**: `string` 默认 `'HS256'` 签名算法，支持 `HS256`、`HS512`

容器配置：
- **`{prefix}/secret`**: `string` - HMAC 签名密钥
- **`{prefix}/validators`**: `array` - 验证器数组，接收 `($payload)` 返回用户信息
- **`{prefix}/user`**: `mixed` - 认证通过后写入用户信息
- **`{prefix}/payload`**: `array` - 自动写入解码后的 JWT payload

---

## apikey(prefix, headerName, queryName) — API Key 认证

从请求头或 URL 查询参数提取 API Key，调用验证器。

```php
use function ff\web\middleware\auth\apikey;
container('#web/auth/validators', [fn($apiKey) => $user]);//设置验证器
middleware(apikey(), $handler);//使用中间件
```

参数：
- **`$prefix`**: `string` 默认 `'#web/auth'` 容器键前缀
- **`$headerName`**: `string` 默认 `'X-API-Key'` 请求头名称
- **`$queryName`**: `string` 默认 `'api_key'` URL 查询参数名

容器配置：
- **`{prefix}/validators`**: `array` - 验证器数组，接收 `($apiKey)` 返回用户信息
- **`{prefix}/user`**: `mixed` - 认证通过后写入用户信息

---

**通用中间件**

## cors(options) — CORS 跨域

为响应添加 CORS 头，`OPTIONS` 预检请求直接返回空响应。

```php
middleware(cors(), $handler);//基础使用
middleware(cors(['origin' => 'https://example.com']), $handler);//自定义配置
```

**$options**：
- **`origin`**: `string|array` 默认 `'*'` 允许的源，数组时随机选取
- **`methods`**: `string` 默认 `'GET,POST,PUT,DELETE,OPTIONS'` 允许的 HTTP 方法
- **`headers`**: `string` 默认 `'Content-Type,Authorization,X-CSRF-Token'` 允许的请求头
- **`credentials`**: `bool` 默认 `false` 是否允许发送凭证
- **`max-age`**: `int` 默认 `86400` 预检缓存时间（秒）

---

## csrf(verify) — CSRF 防护

- **`verify: false`**（默认）：生成 token 注入响应（数组加 `_token` 字段，对象加 `token` 属性）
- **`verify: true`**：校验请求中的 `_token` 或 `X-CSRF-Token` 头，不匹配返回 419

```php
middleware(csrf(), $handler);//生成 token
middleware(csrf(verify: true), $handler);//验证 token
```

容器配置：
- **`#web/csrf/token`**: `string` - 存储/读取当前会话的 CSRF token

---

## error(statusMap) — 异常处理

捕获所有 `\Throwable` 异常，根据异常类型映射状态码和 HTTP 状态描述（reason phrase）。不输出 body，错误信息通过 `output(..., ['message' => ...])` 写入 HTTP 响应头（如 `HTTP/1.1 400 Invalid argument`）。未匹配的异常返回 `500` 且状态描述为空。

```php
middleware(error(), $handler);                                            // 默认 500，状态描述为空
middleware(error([\InvalidArgumentException::class => 400]), $handler);   // int：仅状态码，状态描述为空
middleware(error([\RuntimeException::class => [500, '#ff.error.msg']]), $handler);// [code, msg]：i18n(msg) 作为 HTTP 状态描述
middleware(error([\DomainException::class => [422, '{message}']]), $handler);// {message} 替换为 $e->getMessage()
```

`int` 值只设置状态码，HTTP 状态描述为空。`[int, string]` 中 string 为 i18n 键或模板，支持上下文占位符：`{status}`、`{code}`、`{message}`、`{file}`、`{line}`。其中 `{message}` 直接替换为 `$e->getMessage()`，无需预先注册翻译。

未配置消息时自动回退到 `container("#web/error/$code")` 查找，存在则过 i18n，不存在则状态描述为空：

```php
container('#web/error/400', 'myapp.bad_request');
container('i18n.myapp.bad_request', ['错误的请求', 'en_US' => 'Bad request']);
```

参数：
- **`$statusMap`**: `array` 默认 `[]` 异常类名映射，值为 `int`（仅状态码）或 `[int, ?string]`（状态码 + i18n 键或模板，支持 `{message}` 等占位符）

---

## gzip(level) — Gzip 响应压缩

检查客户端 `Accept-Encoding: gzip`，压缩后内容比原内容小时启用压缩。自动跳过 `null`、`array`、`OPTIONS` 请求。

```php
middleware(gzip(), $handler);//默认压缩级别 6
middleware(gzip(9), $handler);//最高压缩级别
```

参数：
- **`$level`**: `int` 默认 `6` 压缩级别 1-9

---

## json(pretty) — JSON 格式化

将返回值转为 JSON 输出（Content-Type 由 ff 的 json type handler 自动设置）。

```php
middleware(json(), $handler);//默认输出
middleware(json(pretty: true), $handler);//格式化输出
```

参数：
- **`$pretty`**: `bool` 默认 `false` 是否格式化输出

---

## log(level) — 请求日志

记录请求方法、URI、状态码、耗时（ms）、内存（KB）。

```php
middleware(log(), $handler);//默认 info 级别
middleware(log('warning'), $handler);//自定义级别
```

参数：
- **`$level`**: `string` 默认 `'info'` 日志级别

容器配置：
- **`#out.op`**: `array` - 读取输出操作的元数据，`[1]['code']` 为响应状态码

---

## rate(maxRequests, windowSeconds, key) — 接口限流

基于滑动窗口的 IP + 路由级别限流，默认使用 APCu 存储。

```php
middleware(rate(), $handler);//60次/分钟
middleware(rate(100, 60), $handler);//100次/分钟
middleware(rate(30, 60, 'api'), $handler);//自定义 key 前缀
```

参数：
- **`$maxRequests`**: `int` 默认 `60` 时间窗口内最大请求数
- **`$windowSeconds`**: `int` 默认 `60` 时间窗口大小（秒）
- **`$key`**: `string` 默认 `'rate'` 限流键名前缀

容器配置：
- **`#web/rate/storage`**: `callable` - 自定义存储，签名 `fn($key) => [...]`（读取）或 `fn($key, $value, $ttl) => 1`（写入）

```php
container('#web/rate/storage', fn($key) => [...]);//读取
container('#web/rate/storage', fn($key, $value, $ttl) => 1);//写入
```

---

## serve(root, cache) — 静态文件服务

根据 URI 在指定目录查找静态文件，自动设置 MIME 类型。目录自动追加 `index.html`。

```php
middleware(serve('/var/www/public'), $handler);//基础使用（无缓存头）
middleware(serve('/var/www/public', false), $handler);//强制不缓存
middleware(serve('/var/www/public', 31536000), $handler);//自定义 max-age
middleware(serve('/var/www/public', 'etag'), $handler);//ETag 条件缓存
middleware(serve('/var/www/public', 'modified'), $handler);//Last-Modified 条件缓存
middleware(serve('/var/www/public', ['control' => 'etag,modified', 'age' => 86400]), $handler);//组合策略
```

参数：
- **`$root`**: `string` 必填 静态文件根目录
- **`$cache`**: `null|false|int|string|array` 默认 `null` 缓存策略
  - `null`（默认）— 不输出 `Cache-Control`，浏览器自行决定
  - `false` — `Cache-Control: no-cache, no-store, must-revalidate`
  - `3600` / `86400` — `Cache-Control: public, max-age=N`
  - `'etag'` — ETag 条件缓存（`filemtime+filesize` 快速计算），命中返回 304
  - `'modified'` — Last-Modified 条件缓存，命中返回 304
  - `['control' => 'etag,modified', 'age' => 3600]` — 组合策略，`control` 逗号分隔，`age` 为 max-age 秒数

优先级：显式传参 > `container('#web/static/cache')` > 默认无缓存

内置 MIME 类型支持：`html`、`htm`、`txt`、`css`、`js`、`json`、`png`、`jpg`、`jpeg`、`gif`、`svg`、`ico`、`woff`、`woff2`、`ttf`、`zip`、`xml`

容器配置：
- **`#web/static/mimes`**: `array` - 扩展 MIME 类型
- **`#web/static/cache`**: `null|false|int|string|array` - 全局默认缓存策略，参数未传时回退到此配置

```php
container('#web/static/mimes', ['webp' => 'image/webp']);//扩展 MIME 类型
container('#web/static/cache', 86400);//全局默认缓存 1 天
```
