<?php

namespace SwiftPHP\Scaffold\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputOption;

class MakeCommand extends BaseCommand
{
    protected $commandName = 'make:command';

    protected function configure(): void
    {
        $this
            ->setName($this->commandName)
            ->setDescription('Create a new command class')
            ->addArgument('name', InputArgument::REQUIRED, 'The command name (e.g., SendEmail)')
            ->addOption('command', 'c', InputOption::VALUE_REQUIRED, 'The command signature (e.g., app:demo)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');
        $command = $input->getOption('command');

        if ($this->validateName($name, $io)) {
            return Command::FAILURE;
        }

        $className = $this->toPascalCase($name);
        $directory = $this->buildPath($this->basePath, 'app', 'command');
        $this->ensureDirectory($directory);

        $filePath = $this->buildPath($directory, $className . '.php');

        if (file_exists($filePath)) {
            $io->error("Command {$className} already exists!");
            return Command::FAILURE;
        }

        $content = $this->generateContent($className, $command);

        file_put_contents($filePath, $content);
        $io->success("Command {$className} created successfully!");

        return Command::SUCCESS;
    }

    protected function generateContent(string $className, ?string $command): string
    {
        $signature = $command ?: strtolower(preg_replace('/([a-z])([A-Z])/', '$1:$2', $className));

        return <<<PHP
<?php

namespace App\Command;

use SwiftPHP\Command\Command;
use SwiftPHP\Request\Request;
use SwiftPHP\Response\Response;

class {$className} extends Command
{
    protected \$signature = '{$signature}';
    protected \$description = '{$className} command';

    public function handle(): int
    {
        \$this->info('{$className} command executed successfully!');
        return 0;
    }
}
PHP;
    }
}
