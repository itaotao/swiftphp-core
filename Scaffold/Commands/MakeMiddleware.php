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
        parent::configure();
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

        $content = <<<PHP
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

        if (file_put_contents($filePath, $content) === false) {
            $io->error("Failed to create middleware file");
            return Command::FAILURE;
        }

        $io->success("Middleware {$className} created successfully!");
        $io->text("Path: {$filePath}");

        if ($isGlobal) {
            $io->note("To enable as global middleware, add to config/middleware.php:");
            $io->text("  '{$className}' => \\App\\Middleware\\{$className}::class,");
        }

        return Command::SUCCESS;
    }
}