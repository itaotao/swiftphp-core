# SwiftPHP框架（融合ThinkPHP易用性+Webman高性能）

核心目标：SwiftPHP 框架以「低学习成本、高开发效率」为易用性核心（对齐ThinkPHP），以「常驻内存、事件驱动、低损耗」为性能核心（对齐Webman），同时兼顾PHP开发者传统使用习惯，避免过度封装导致的学习成本，也避免极简设计导致的开发效率下降，最终实现“上手即会、运行即快”的目标。

核心原则：
1.  易用性优先：沿用ThinkPHP经典目录结构、API风格、辅助函数，降低开发者迁移成本；
2.  性能不妥协：基于Workerman（Webman底层）实现常驻内存、事件驱动，摒弃PHP-FPM依赖；
3.  轻量可扩展：核心内核极简，冗余功能可按需加载，兼顾性能与灵活性；
4.  原生兼容：支持Composer生态，兼容PHP原生语法，无需学习新的开发范式。

# 一、架构设计（核心：融合两者优势，规避短板）

## 1.1 整体架构选型

核心底层：基于Workerman（Webman同款底层），实现常驻内存、事件驱动、多进程模型，替代PHP-FPM，从根源解决传统框架重复初始化的性能损耗；
上层封装：参考ThinkPHP的MVC架构、核心API、目录结构，降低开发者上手成本，避免Webman“过于极简”导致的开发繁琐问题；
运行模式：支持两种启动方式（满足不同场景）——
- 常驻模式（默认）：用于生产环境，多进程+事件驱动，常驻内存，高性能；
- 调试模式（开发环境）：单次请求加载，自动重载代码，无需重启服务，对齐ThinkPHP开发体验。

## 1.2 目录结构设计（沿用ThinkPHP习惯，适配Webman常驻特性）

采用ThinkPHP经典目录结构，减少开发者适应成本，同时新增常驻相关目录，适配性能需求：

```plain text
SwiftPHP/
├── app/                  # 应用目录（同ThinkPHP）
│   ├── controller/       # 控制器（支持ThinkPHP风格注解路由）
│   ├── model/            # 模型（简化ORM，兼容原生SQL）
│   ├── view/             # 视图（支持blade/think-template）
│   ├── middleware/       # 中间件（同ThinkPHP，支持全局/局部）
│   ├── config/           # 应用配置（同ThinkPHP，支持环境变量）
│   └── common.php        # 应用公共函数
├── config/               # 框架全局配置（区分应用配置，同ThinkPHP）
│   ├── app.php           # 应用基础配置
│   ├── server.php        # 常驻服务配置（新增，适配Workerman）
│   ├── database.php      # 数据库配置
│   └── route.php         # 路由配置（支持注解+配置两种方式）
├── core/                 # 框架核心（极简设计，参考Webman）
│   ├── Server/           # 常驻服务核心（基于Workerman封装）
│   ├── Routing/          # 路由解析（融合ThinkPHP注解+Webman高效解析）
│   ├── Container/        # 容器（轻量版，参考ThinkPHP，无冗余）
│   ├── Request/          # 请求处理（兼容ThinkPHP请求对象，优化I/O）
│   └── Response/         # 响应处理（极简封装，减少损耗）
├── extend/               # 扩展目录（同ThinkPHP，支持自定义扩展）
├── public/               # 入口目录（开发环境入口，生产环境无需）
│   └── index.php         # 调试模式入口（单次请求）
├── runtime/              # 运行时目录（缓存、日志，同ThinkPHP）
├── vendor/               # Composer依赖（兼容Webman/ThinkPHP生态）
├── start.php             # 常驻服务启动入口（新增，生产环境启动）
└── composer.json         # 依赖配置（兼容Composer）
```

说明：目录结构核心是“ThinkPHP用户无感知”，同时通过start.php、core/Server等目录，实现Webman的常驻特性，避免开发者额外学习新的目录规范。

## 1.3 核心运行流程（兼顾易用性与性能）

#### 生产环境（常驻模式）：
1.  启动服务：执行php start.php start，框架初始化（加载配置、路由、类文件），常驻内存；
2.  接收请求：Workerman监听端口，接收HTTP请求（无Nginx→FPM转发损耗）；
3.  路由解析：融合ThinkPHP注解路由（如@Route("/index")）和Webman高效解析逻辑，快速匹配控制器；
4.  请求处理：调用中间件、控制器方法，复用内存中的配置、数据库连接，无重复初始化；
5.  响应返回：极简响应封装，直接返回结果，无需销毁进程/连接；
6.  循环处理：单进程持续监听请求，I/O等待时自动切换至其他请求（事件驱动）。

#### 开发环境（调试模式）：
1.  启动入口：访问public/index.php，单次请求加载框架（无需常驻）；
2.  自动重载：修改代码后，无需重启服务，刷新页面即可生效（对齐ThinkPHP开发体验）；
3.  流程同上，仅新增“请求结束后销毁资源”步骤，方便调试。

# 二、核心模块开发（分模块实现，兼顾易用与性能）

## 2.1 常驻服务核心模块（复用Webman底层，简化封装）

核心目标：基于Workerman封装，隐藏底层复杂度，让开发者像启动ThinkPHP一样简单启动常驻服务。

#### 具体实现：
1.  封装Server类（core/Server/SwiftServer.php）：继承Workerman的Worker类，简化配置，默认集成HTTP服务、多进程配置；
   - 内置默认配置（可通过config/server.php覆盖）：进程数=CPU核心数、监听端口=8787、超时时间=60s；
   - 提供简洁API：start()、stop()、reload()，对应php start.php start/stop/reload，与Webman命令一致，但无需开发者了解Workerman细节。

2.  启动入口（start.php）：极简封装，开发者无需编写复杂的启动逻辑，直接执行即可：

```php
<?php
require __DIR__ . '/vendor/autoload.php';
// 加载框架核心
$server = new SwiftPHP\Core\Server\SwiftServer();
// 启动服务（自动读取config/server.php配置）
$server->start();
```

3.  内存复用优化：框架启动时，一次性加载config目录、路由、核心类、全局辅助函数，请求处理时直接复用，避免ThinkPHP每次请求重新加载的损耗；同时支持配置“预加载文件”，开发者可自定义需要常驻内存的类/文件。

## 2.2 路由模块（融合ThinkPHP易用性+Webman高效性）

核心目标：支持ThinkPHP注解路由（降低学习成本），复用Webman路由解析逻辑（提升性能），同时兼容配置式路由。

#### 具体实现：
1.  注解路由（优先支持，同ThinkPHP）：
   - 封装注解解析类（core/Routing/Annotation.php），支持@Route、@Get、@Post等注解，语法与ThinkPHP完全一致；
   - 示例（控制器）：

```php
<?php
namespace app\controller;
use SwiftPHP\Core\Routing\Annotation\Route;

class IndexController
{
    // 注解路由，同ThinkPHP写法
    /**
     * @Route("/index", method="get")
     */
    public function index()
    {
        return "Hello SwiftPHP";
    }
}
```

2.  路由解析优化：
   - 框架启动时，一次性解析所有控制器的注解路由，生成路由映射表，常驻内存；
   - 复用Webman的路由匹配逻辑（哈希表匹配，O(1)复杂度），比ThinkPHP的路由解析更快；
   - 支持路由参数、路由分组、中间件绑定，语法与ThinkPHP完全一致，开发者无感知切换。

3.  兼容配置式路由（config/route.php）：沿用ThinkPHP的路由配置语法，满足习惯配置式路由的开发者：

```php
// config/route.php
return [
    'GET /test' => 'app\controller\IndexController@test',
    'POST /submit' => 'app\controller\FormController@submit',
];
```

## 2.3 控制器与请求/响应模块（对齐ThinkPHP，优化性能）

核心目标：控制器写法与ThinkPHP完全一致，请求/响应对象简化封装，减少性能损耗，同时保留常用API。

#### 具体实现：
1.  控制器封装（core/Controller.php）：
   - 继承基础控制器，提供assign()、fetch()、json()等ThinkPHP常用方法，无需额外学习；
   - 示例（与ThinkPHP写法一致）：

```php
<?php
namespace app\controller;
use SwiftPHP\Core\Controller;

class IndexController extends Controller
{
    public function index()
    {
        // 同ThinkPHP的模板赋值、渲染
        $this->assign('name', 'SwiftPHP');
        return $this->fetch('index');
        
        // 同ThinkPHP的JSON响应
        // return $this->json(['code' => 200, 'msg' => 'success']);
    }
}
```

2.  请求对象（core/Request/Request.php）：
   - 兼容ThinkPHP的请求API（如input()、get()、post()、param()），但底层复用Webman的请求解析逻辑，更高效；
   - 常驻内存优化：请求对象每次请求重新实例化，但复用内存中的配置（如请求头、全局参数），避免重复解析。

3.  响应对象（core/Response/Response.php）：
   - 极简封装，减少冗余逻辑，支持文本、JSON、模板、重定向等常用响应类型；
   - 优化I/O：直接输出响应内容，无需经过复杂的中间层转发，比ThinkPHP响应更快。

## 2.4 数据库模块（融合ThinkPHP易用性+Webman连接池）

核心目标：ORM语法对齐ThinkPHP（简单易上手），底层使用连接池（Webman核心优势），避免重复创建数据库连接，提升性能。

#### 具体实现：
1.  ORM封装（core/Model/Model.php）：
   - 沿用ThinkPHP的ORM语法，支持find()、select()、where()、save()等常用方法，开发者无需学习新ORM；
   - 简化ORM内核，去除ThinkPHP中冗余的关联查询、复杂验证逻辑，保留核心功能，降低性能损耗；
   - 示例（与ThinkPHP写法一致）：

```php
<?php
namespace app\model;
use SwiftPHP\Core\Model\Model;

class UserModel extends Model
{
    protected $table = 'user'; // 同ThinkPHP的表名指定
    
    // 同ThinkPHP的查询写法
    public function getUser($id)
    {
        return $this->where('id', $id)->find();
    }
}
```

2.  连接池实现（core/Database/ConnectionPool.php）：
   - 基于Webman的连接池逻辑，封装MySQL/Redis连接池，框架启动时创建固定数量的连接，常驻内存；
   - 请求处理时，从连接池获取连接，使用完毕后归还，避免每次请求创建/销毁连接的损耗；
   - 配置兼容ThinkPHP的database.php，开发者只需修改配置，无需修改代码：

```php
// config/database.php
return [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'type' => 'mysql',
            'host' => '127.0.0.1',
            'database' => 'test',
            'username' => 'root',
            'password' => '123456',
            'pool_size' => 10, // 新增连接池大小配置
        ],
    ],
];
```

## 2.5 中间件与容器（轻量封装，兼顾易用与性能）

#### 1.  中间件（同ThinkPHP，简化底层）：
   - 沿用ThinkPHP的中间件语法，支持全局中间件、控制器中间件、路由中间件；
   - 简化中间件执行逻辑，去除冗余的反射、依赖注入，提升执行效率；
   - 示例（与ThinkPHP写法一致）：

```php
<?php
namespace app\middleware;

class CheckLogin
{
    public function handle($request, \Closure $next)
    {
        if (!$request->param('token')) {
            return json(['code' => 401, 'msg' => '未登录']);
        }
        return $next($request);
    }
}
```

#### 2.  容器（轻量版，参考ThinkPHP，无冗余）：
   - 封装轻量容器（core/Container/Container.php），支持依赖注入、单例模式，语法与ThinkPHP一致；
   - 去除Webman中复杂的服务治理、自动代理逻辑，保留核心的依赖解析功能，降低内存占用；
   - 示例（与ThinkPHP写法一致）：

```php
// 绑定实例
Container::set('db', function() {
    return new \SwiftPHP\Core\Database\Mysql();
});
// 获取实例
$db = Container::get('db');
```

## 2.6 辅助函数（完全对齐ThinkPHP，提升开发效率）

核心目标：保留ThinkPHP常用辅助函数，让开发者无需修改习惯，快速上手。

#### 具体实现：
1.  内置常用辅助函数（如config()、input()、json()、dump()、cache()等），语法与ThinkPHP完全一致；
2.  辅助函数常驻内存，框架启动时一次性加载，避免每次请求重新定义；
3.  支持自定义辅助函数（app/common.php），与ThinkPHP的自定义方式一致。

# 三、性能优化（核心对齐Webman，不牺牲易用性）

## 3.1 常驻内存优化（核心性能点）

1.  预加载机制：框架启动时，预加载核心类、配置文件、路由映射表、辅助函数，请求时直接复用，无重复加载；
2.  避免重复初始化：数据库连接、Redis连接、容器实例等，仅初始化一次，常驻内存，请求时直接调用；
3.  内存回收：请求处理完毕后，仅回收请求相关的临时变量，核心资源（配置、连接、类）不回收，减少内存开销。

## 3.2 事件驱动与多进程优化

1.  多进程配置：默认进程数=CPU核心数，开发者可通过config/server.php修改，充分利用CPU资源；
2.  事件驱动：复用Workerman的事件循环（支持libevent），单进程可处理上万并发连接，I/O等待时自动切换请求，提升并发能力；
3.  进程隔离：每个Worker进程独立处理请求，避免进程间干扰，提升稳定性。

## 3.3 冗余代码剔除（极简内核）

1.  内核精简：仅保留核心模块（路由、请求、响应、数据库、容器、中间件），去除ThinkPHP中冗余的模块（如日志、验证、权限等），可通过Composer按需安装；
2.  避免过度封装：核心逻辑直接调用，减少多层抽象，缩短代码执行路径（如路由解析、请求处理仅需3-5步）；
3.  禁用不必要的反射：反射会增加性能损耗，仅在必要场景（如依赖注入）使用，替代方案为直接实例化。

## 3.4 调试模式与生产模式分离

1.  调试模式：单次请求加载，自动重载代码，开启错误提示、日志记录，方便开发调试，性能略低；
2.  生产模式：常驻内存，关闭错误提示、冗余日志，开启连接池、事件驱动，最大化性能；
3.  自动切换：通过环境变量（APP_ENV）自动切换模式，开发者无需手动修改配置。

# 四、易用性优化（核心对齐ThinkPHP，降低学习成本）

## 4.1 开发体验对齐ThinkPHP

1.  目录结构、API语法、辅助函数、配置方式完全对齐ThinkPHP，ThinkPHP开发者可无缝迁移；
2.  调试体验：支持断点调试、错误提示、日志记录，与ThinkPHP调试方式一致；
3.  文档支持：编写与ThinkPHP风格一致的文档，开发者可快速查阅API、使用方法。

## 4.2 简化部署流程

1.  开发环境：无需配置复杂的Nginx、PHP-FPM，直接访问public/index.php即可启动，与ThinkPHP部署一致；
2.  生产环境：仅需执行php start.php start，即可启动常驻服务，支持后台运行、自动重启，部署流程比Webman更简单；
3.  兼容传统部署：支持Nginx反向代理（可选），满足传统PHP部署习惯，同时保留常驻内存的性能优势。

## 4.3 生态兼容（复用Composer，降低扩展成本）

1.  兼容ThinkPHP生态：可直接安装ThinkPHP的扩展（如think-validate、think-captcha等），无需修改代码；
2.  兼容Webman生态：可安装Webman的扩展（如webman/redis、webman/cache等），提升性能；
3.  支持Composer自动加载：沿用Composer的自动加载机制，开发者可快速引入第三方依赖。


# 五、预期效果（对比ThinkPHP、Webman）

|对比维度|SwiftPHP|ThinkPHP|Webman|
|---|---|---|---|
|学习成本|低（与ThinkPHP一致）|低|中（需学习Workerman）|
|QPS（纯接口）|4万+（接近Webman）|~2000|4.5万+|
|开发效率|高（与ThinkPHP一致）|高|中（需手动处理更多细节）|
|生态兼容性|高（兼容两者生态）|高|中（仅兼容自身生态）|
|部署难度|低（兼容两种部署方式）|低|中（需配置常驻服务）|

总结：SwiftPHP 最终实现“ThinkPHP的易用性+Webman的高性能”，让开发者以极低的学习成本，开发出高性能的PHP应用，同时兼容现有生态，降低迁移和扩展成本。

# 六、测试路由
1. GET  /              - 首页
2. GET  /test          - 测试首页
3. POST /test/validate - 验证器测试
4. GET  /test/cache    - 缓存测试
5. GET  /session/test   - Session测试
6. GET  /cookie/test    - Cookie测试
7. GET  /api            - API首页
8. POST /api/jwt/login  - JWT登录
9. GET  /db             - QueryBuilder首页
10. GET  /db/select      - 查询测试
11. GET  /user           - 用户列表(分页)
12. POST /user/register  - 用户注册
13. POST /user/login     - 用户登录
14. GET  /user/me        - 当前用户信息
