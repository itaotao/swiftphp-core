<?php

namespace SwiftPHP\Scaffold\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class NewProject extends BaseCommand
{
    protected $commandName = 'new';

    protected function configure(): void
    {
        parent::configure();
        $this
            ->setName($this->commandName)
            ->setDescription('Create a new SwiftPHP project')
            ->addArgument('name', InputArgument::REQUIRED, 'The project name (e.g., myapp)')
            ->addOption('dir', 'd', InputOption::VALUE_REQUIRED, 'Target directory for the project');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');
        $targetDir = $input->getOption('dir');

        if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            $io->error('Project name must start with a letter and contain only lowercase letters, numbers, and underscores');
            return Command::FAILURE;
        }

        $projectPath = $targetDir
            ? rtrim($targetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name
            : getcwd() . DIRECTORY_SEPARATOR . $name;

        if (is_dir($projectPath)) {
            $io->error("Directory {$projectPath} already exists!");
            return Command::FAILURE;
        }

        $io->title("Creating SwiftPHP Project: {$name}");
        $io->text("Target: {$projectPath}");

        try {
            $this->createDirectoryStructure($projectPath, $name, $io);
            $this->createEntryFiles($projectPath, $name);
            $this->createConfigFiles($projectPath, $name);
            $this->createAppFiles($projectPath, $name);
            $this->createEnvFile($projectPath);
            $this->createComposerFile($projectPath, $name);

            $io->success("Project {$name} created successfully!");
            $io->newLine();
            $io->text("Next steps:");
            $io->text("  cd {$name}");
            $io->text("  composer install");
            $io->text("  php start.php");

        } catch (\Exception $e) {
            $io->error("Failed to create project: " . $e->getMessage());
            if (is_dir($projectPath)) {
                $this->removeDirectory($projectPath);
            }
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    protected function createDirectoryStructure(string $projectPath, string $projectName, SymfonyStyle $io): void
    {
        $io->section('Creating directory structure...');

        $dirs = [
            'app/controller',
            'app/model',
            'app/middleware',
            'app/validate',
            'app/lang/zh-cn',
            'app/lang/en',
            'config',
            'public',
            'route',
            'runtime/log',
            'runtime/cache',
            'bin',
        ];

        foreach ($dirs as $dir) {
            $fullPath = $projectPath . DIRECTORY_SEPARATOR . $dir;
            if (!mkdir($fullPath, 0755, true)) {
                throw new \RuntimeException("Failed to create directory: {$fullPath}");
            }
            $io->text("  ✓ {$dir}");
        }
    }

    protected function createEntryFiles(string $projectPath, string $projectName): void
    {
        $publicIndex = <<<'PHP'
<?php

define('SWIFTPHP_START_TIME', microtime(true));
define('SWIFTPHP_START_MEM', memory_get_usage());

require dirname(__DIR__) . '/vendor/autoload.php';

require dirname(__DIR__) . '/app/common.php';

require dirname(__DIR__) . '/core/Container/Container.php';
require dirname(__DIR__) . '/core/Request/Request.php';
require dirname(__DIR__) . '/core/Response/Response.php';
require dirname(__DIR__) . '/core/Controller/Controller.php';
require dirname(__DIR__) . '/core/Routing/Router.php';
require dirname(__DIR__) . '/core/I18n/I18n.php';

$request = new \SwiftPHP\Request\Request();
$request->setMethod($_SERVER['REQUEST_METHOD'] ?? 'GET')
    ->setPath(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH))
    ->setHeader(getallheaders() ?: [])
    ->setGet($_GET)
    ->setPost($_POST)
    ->setBody(file_get_contents('php://input'));

\SwiftPHP\I18n\I18n::init(config('i18n') ?: []);
$detectedLocale = \SwiftPHP\I18n\I18n::detectLocale($request->get(), getallheaders() ?: []);
\SwiftPHP\I18n\I18n::setLocale($detectedLocale);

class_alias(\SwiftPHP\Request\Request::class, 'Request');
class_alias(\SwiftPHP\Response\Response::class, 'Response');
class_alias(\SwiftPHP\Controller\Controller::class, 'Controller');
class_alias(\SwiftPHP\Container\Container::class, 'Container');
class_alias(\SwiftPHP\I18n\I18n::class, 'I18n');

$router = new \SwiftPHP\Routing\Router();
$response = $router->dispatch($request);

echo $response->send();
PHP;

        file_put_contents($projectPath . '/public/index.php', $publicIndex);

        $startPhp = <<<'PHP'
<?php

require_once __DIR__ . '/vendor/autoload.php';

use SwiftPHP\Server\SwiftServer;

$server = new SwiftServer();
$server->run();
PHP;

        file_put_contents($projectPath . '/start.php', $startPhp);

        $startBat = <<<'BAT'
@echo off
php start.php
pause
BAT;

        file_put_contents($projectPath . '/start.bat', $startBat);

        $htaccess = <<<'HTACCESS'
<IfModule mod_rewrite.c>
    Options +FollowSymLinks
    RewriteEngine On

    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^(.*)$ index.php/$1 [QSA,PT,L]
</IfModule>
HTACCESS;

        file_put_contents($projectPath . '/public/.htaccess', $htaccess);
    }

    protected function createConfigFiles(string $projectPath, string $projectName): void
    {
        $appConfig = <<<'PHP'
<?php

return [
    'app_name' => 'SwiftPHP',
    'app_version' => '1.0.0',
    'locale' => env('APP_LOCALE', 'zh-cn'),
    'debug' => env('APP_DEBUG', true),
];
PHP;

        file_put_contents($projectPath . '/config/app.php', $appConfig);

        $databaseConfig = <<<'PHP'
<?php

return [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'type' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'swiftphp',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8mb4',
            'prefix' => '',
            'pool_size' => 10,
        ],
    ],
];
PHP;

        file_put_contents($projectPath . '/config/database.php', $databaseConfig);

        $routeFile = <<<'PHP'
<?php

return [
    'GET /' => 'App\Controller\IndexController@index',
    'GET /hello' => 'App\Controller\IndexController@hello',

    'GET /api/v1/users' => 'App\Controller\UserController@index',
    'GET /api/v1/users/{id}' => [
        'uses' => 'App\Controller\UserController@show',
        'where' => ['id' => '[0-9]+'],
    ],
    'GET /api/v1/posts' => [
        'uses' => 'App\Controller\PostController@index',
    ],
    'GET /api/v1/posts/{id}' => [
        'uses' => 'App\Controller\PostController@show',
        'where' => ['id' => '[0-9]+'],
    ],

    'GET /admin/dashboard' => [
        'uses' => 'App\Controller\AdminController@dashboard',
        'middleware' => ['admin'],
        'as' => 'admin.dashboard',
    ],
    'GET /admin/users' => [
        'uses' => 'App\Controller\AdminController@users',
        'middleware' => ['admin'],
        'as' => 'admin.users',
    ],
];
PHP;

        file_put_contents($projectPath . '/route/route.php', $routeFile);

        $middlewareConfig = <<<'PHP'
<?php

return [
    'global' => [\App\Middleware\Cors::class],

    'groups' => [
        'admin' => [\App\Middleware\Auth::class],
        'api' => [\App\Middleware\ApiAuth::class],
    ],

    'prefix' => [
        '/admin' => 'admin',
        '/api' => 'api',
    ],

    'only' => [],

    'except' => [],

    'cache' => false,
];
PHP;

        file_put_contents($projectPath . '/config/middleware.php', $middlewareConfig);

        $i18nConfig = <<<'PHP'
<?php

return [
    'default_locale' => 'zh-cn',
    'supported_locales' => ['zh-cn', 'en'],
    'locale_detect_order' => ['cookie', 'header', 'param', 'default'],
    'fallback_locale' => 'en',
    'translation_file_pattern' => 'lang/{locale}/{name}.php',
];
PHP;

        file_put_contents($projectPath . '/config/i18n.php', $i18nConfig);

        $sessionConfig = <<<'PHP'
<?php

return [
    'type' => 'file',
    'expire' => 3600,
    'prefix' => 'swiftphp_',
    'path' => '',
    'domain' => '',
    'secure' => false,
    'httponly' => true,
];
PHP;

        file_put_contents($projectPath . '/config/session.php', $sessionConfig);

        $cookieConfig = <<<'PHP'
<?php

return [
    'prefix' => 'swiftphp_',
    'expire' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => false,
    'httponly' => true,
];
PHP;

        file_put_contents($projectPath . '/config/cookie.php', $cookieConfig);

        $cacheConfig = <<<'PHP'
<?php

return [
    'default' => 'file',
    'stores' => [
        'file' => [
            'type' => 'file',
            'path' => 'runtime/cache/',
            'expire' => 0,
        ],
        'redis' => [
            'type' => 'redis',
            'host' => '127.0.0.1',
            'port' => 6379,
            'password' => '',
            'select' => 0,
            'timeout' => 0,
            'expire' => 0,
            'persistent' => false,
        ],
    ],
];
PHP;

        file_put_contents($projectPath . '/config/cache.php', $cacheConfig);

        $logConfig = <<<'PHP'
<?php

return [
    'default' => 'file',
    'channels' => [
        'file' => [
            'type' => 'file',
            'path' => 'runtime/log/',
            'level' => ['debug', 'info', 'warning', 'error'],
            'max_files' => 30,
        ],
    ],
];
PHP;

        file_put_contents($projectPath . '/config/log.php', $logConfig);

        $serverConfig = <<<'PHP'
<?php

return [
    'host' => '0.0.0.0',
    'port' => 8080,
    'workers' => 4,
    'reloadable' => true,
    'reuse_port' => false,
    'transport' => 'tcp',
    'context' => [],
];
PHP;

        file_put_contents($projectPath . '/config/server.php', $serverConfig);
    }

    protected function createAppFiles(string $projectPath, string $projectName): void
    {
        $commonFile = <<<'PHP'
<?php

function config(string $name = null, $default = null)
{
    static $config = [];
    static $loaded = [];

    if ($name === null) {
        return $config;
    }

    if (strpos($name, '.') === false) {
        if (!isset($loaded[$name])) {
            $file = dirname(__DIR__) . '/config/' . $name . '.php';
            if (file_exists($file)) {
                $config[$name] = include $file;
            } else {
                $config[$name] = [];
            }
            $loaded[$name] = true;
        }
        return $config[$name] ?? $default;
    }

    $keys = explode('.', $name);
    $first = array_shift($keys);

    if (!isset($loaded[$first])) {
        $file = dirname(__DIR__) . '/config/' . $first . '.php';
        if (file_exists($file)) {
            $config[$first] = include $file;
        } else {
            $config[$first] = [];
        }
        $loaded[$first] = true;
    }

    $value = $config[$first] ?? [];
    foreach ($keys as $key) {
        if (!is_array($value) || !isset($value[$key])) {
            return $default;
        }
        $value = $value[$key];
    }

    return $value;
}

function env(string $key, $default = null)
{
    static $env = [];
    static $loaded = false;

    if (!$loaded) {
        $envFile = dirname(__DIR__) . '/.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                    list($k, $v) = explode('=', $line, 2);
                    $env[trim($k)] = trim($v);
                }
            }
        }
        $loaded = true;
    }

    return $env[$key] ?? $default;
}

function app(string $class = null)
{
    static $container = [];

    if ($class === null) {
        return $container;
    }

    if (!isset($container[$class])) {
        $container[$class] = new $class();
    }

    return $container[$class];
}
PHP;

        file_put_contents($projectPath . '/app/common.php', $commonFile);

        $routeFile = <<<'PHP'
<?php

use SwiftPHP\Routing\Router;

$router = new Router();

$router->get('/', 'IndexController@index');
$router->get('/hello', 'IndexController@hello');

return $router;
PHP;

        file_put_contents($projectPath . '/route/route.php', $routeFile);

        $indexController = <<<'PHP'
<?php

namespace App\Controller;

use SwiftPHP\Controller\Controller;
use SwiftPHP\Request\Request;
use SwiftPHP\Response\Response;

class IndexController extends Controller
{
    public function index(?Request $request = null): Response
    {
        return $this->json([
            'code' => 200,
            'msg' => 'Welcome to SwiftPHP Framework',
            'data' => [
                'version' => '1.0.0',
                'framework' => 'SwiftPHP',
                'description' => 'ThinkPHP ease of use + Webman high performance',
            ]
        ]);
    }

    public function hello(?Request $request = null): Response
    {
        $name = $request->param('name', 'World');
        return $this->json([
            'code' => 200,
            'msg' => 'success',
            'data' => [
                'message' => "Hello, {$name}!"
            ]
        ]);
    }
}
PHP;

        file_put_contents($projectPath . '/app/controller/IndexController.php', $indexController);

        $userController = <<<'PHP'
<?php

namespace App\Controller;

use SwiftPHP\Controller\Controller;
use SwiftPHP\Request\Request;
use SwiftPHP\Response\Response;

class UserController extends Controller
{
    public function index(?Request $request = null): Response
    {
        return $this->json([
            'code' => 200,
            'msg' => 'success',
            'data' => [
                'list' => [],
                'total' => 0,
            ]
        ]);
    }

    public function show(?Request $request = null): Response
    {
        $id = $request->param('id', 0);
        return $this->json([
            'code' => 200,
            'msg' => 'success',
            'data' => ['id' => $id]
        ]);
    }

    public function store(?Request $request = null): Response
    {
        $data = $request->post();
        return Response::json([
            'code' => 201,
            'msg' => 'Created successfully',
            'data' => $data
        ], 201);
    }
}
PHP;

        file_put_contents($projectPath . '/app/controller/UserController.php', $userController);

        $userModel = <<<'PHP'
<?php

namespace App\Model;

use SwiftPHP\Model\Model;

class User extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $fillable = [
        'username',
        'email',
        'password',
    ];
}
PHP;

        file_put_contents($projectPath . '/app/model/User.php', $userModel);

        $authMiddleware = <<<'PHP'
<?php

namespace App\Middleware;

use SwiftPHP\Middleware\Middleware;
use SwiftPHP\Request\Request;
use SwiftPHP\Response\Response;

class Auth extends Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        $token = $request->header('Authorization', '');

        if (empty($token)) {
            return Response::json([
                'code' => 401,
                'msg' => 'Unauthorized'
            ], 401);
        }

        return $next($request);
    }
}
PHP;

        file_put_contents($projectPath . '/app/middleware/Auth.php', $authMiddleware);

        $corsMiddleware = <<<'PHP'
<?php

namespace App\Middleware;

use SwiftPHP\Middleware\Middleware;
use SwiftPHP\Request\Request;
use SwiftPHP\Response\Response;

class Cors extends Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);

        $response->withHeader('Access-Control-Allow-Origin', '*');
        $response->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');

        return $response;
    }
}
PHP;

        file_put_contents($projectPath . '/app/middleware/Cors.php', $corsMiddleware);

        $zhLang = <<<'PHP'
<?php

return [
    'welcome' => '欢迎使用 SwiftPHP',
    'success' => '操作成功',
    'error' => '操作失败',
    'unauthorized' => '未授权',
    'not_found' => '资源不存在',
    'validation_failed' => '验证失败',
];
PHP;

        file_put_contents($projectPath . '/app/lang/zh-cn/common.php', $zhLang);

        $enLang = <<<'PHP'
<?php

return [
    'welcome' => 'Welcome to SwiftPHP',
    'success' => 'Success',
    'error' => 'Error',
    'unauthorized' => 'Unauthorized',
    'not_found' => 'Not Found',
    'validation_failed' => 'Validation Failed',
];
PHP;

        file_put_contents($projectPath . '/app/lang/en/common.php', $enLang);
    }

    protected function createEnvFile(string $projectPath): void
    {
        $envContent = <<<'ENV'
APP_NAME=SwiftPHP
APP_VERSION=1.0.0
APP_LOCALE=zh-cn
APP_DEBUG=true

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=swiftphp
DB_USERNAME=root
DB_PASSWORD=

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=

CACHE_DRIVER=file
SESSION_DRIVER=file
ENV;

        file_put_contents($projectPath . '/.env', $envContent);
    }

    protected function createComposerFile(string $projectPath, string $projectName): void
    {
        $composerContent = <<<JSON
{
    "name": "swiftphp/{$projectName}",
    "description": "SwiftPHP Framework Project",
    "type": "project",
    "license": "MIT",
    "require": {
        "php": ">=7.4",
        "itaotao/swiftphp-core": "dev-main",
        "workerman/workerman": "^4.1",
        "symfony/http-foundation": "^6.0"
    },
    "autoload": {
        "psr-4": {
            "App\\": "app/"
        },
        "files": [
            "app/common.php"
        ]
    },
    "minimum-stability": "dev",
    "prefer-stable": true,
    "scripts": {
        "start": "php start.php",
        "server": "php start.php"
    }
}
JSON;

        file_put_contents($projectPath . '/composer.json', $composerContent);
    }

    protected function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}