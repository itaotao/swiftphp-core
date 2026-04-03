<?php

namespace SwiftPHP\Scaffold\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputOption;

class MakeMiddleware extends BaseCommand
{
    protected $commandName = 'make:middleware';

    protected function configure(): void
    {
        $this
            ->setName($this->commandName)
            ->setDescription('Create a new middleware class')
            ->addArgument('name', InputArgument::REQUIRED, 'The middleware name (e.g., Auth)')
            ->addOption('global', 'g', InputOption::VALUE_NONE, 'Register as global middleware');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');
        $isGlobal = $input->getOption('global');

        if ($this->validateName($name, $io)) {
            return Command::FAILURE;
        }

        $className = $this->toPascalCase($name);
        $directory = $this->buildPath($this->basePath, 'app', 'middleware');
        $this->ensureDirectory($directory);

        $filePath = $this->buildPath($directory, $className . '.php');

        if (file_exists($filePath)) {
            $io->error("Middleware {$className} already exists!");
            return Command::FAILURE;
        }

        $content = $this->generateContent($className);

        file_put_contents($filePath, $content);
        $io->success("Middleware {$className} created successfully!");

        // 如果是全局中间件，注册到配置文件中
        if ($isGlobal) {
            $this->registerGlobalMiddleware($className, $io);
        }

        return Command::SUCCESS;
    }

    protected function generateContent(string $className): string
    {
        return <<<PHP
<?php

namespace App\Middleware;

use SwiftPHP\Middleware\Middleware;
use SwiftPHP\Request\Request;
use SwiftPHP\Response\Response;

class {$className} extends Middleware
{
    public function handle(Request \$request, callable \$next): Response
    {
        // Add your middleware logic here

        return \$next(\$request);
    }
}
PHP;
    }

    protected function registerGlobalMiddleware(string $className, SymfonyStyle $io): void
    {
        $configPath = $this->buildPath($this->basePath, 'config', 'middleware.php');
        
        if (!file_exists($configPath)) {
            $io->warning("Middleware config file not found. Please register {$className} manually.");
            return;
        }

        $content = file_get_contents($configPath);
        $middlewareClass = "App\\Middleware\\{$className}";

        // 检查是否已存在
        if (strpos($content, $middlewareClass) !== false) {
            $io->note("Middleware {$className} is already registered.");
            return;
        }

        // 在 'global' 数组中添加新的中间件
        $pattern = "/('global'\s*=>\s*\[)([^\]]*)/";
        $replacement = "\$1\$2        '{$middlewareClass}',\n        ";
        
        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, $replacement, $content);
            file_put_contents($configPath, $content);
            $io->success("Middleware {$className} registered as global middleware.");
        } else {
            $io->warning("Could not register middleware automatically. Please register {$className} manually in config/middleware.php");
        }
    }
}
