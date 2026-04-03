<?php

namespace SwiftPHP\Scaffold\Commands;

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
        parent::configure();
        $this
            ->setName($this->commandName)
            ->setDescription('Create a new console command class')
            ->addArgument('name', InputArgument::REQUIRED, 'The command name (e.g., Demo)')
            ->addOption('command', 'c', InputOption::VALUE_REQUIRED, 'The console command signature');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');

        if ($this->validateName($name, $io)) {
            return Command::FAILURE;
        }

        $commandSignature = $input->getOption('command');

        if ($commandSignature !== null && !preg_match('/^[a-z][a-z0-9:_]*$/', $commandSignature)) {
            $io->error('Command signature must start with a letter and contain only lowercase letters, numbers, colons, and underscores');
            return Command::FAILURE;
        }

        $className = $this->toPascalCase($name);
        $directory = $this->buildPath($this->basePath, 'app', 'command');
        $this->ensureDirectory($directory);

        $filePath = $this->buildPath($directory, $className . 'Command.php');

        if (file_exists($filePath)) {
            $io->error("Command {$className}Command already exists!");
            return Command::FAILURE;
        }

        $signature = $commandSignature ?: 'app:' . $this->toCamelCase($name);

        $content = <<<PHP
<?php

namespace App\\Command;

use Symfony\\Component\\Console\\Attribute\\AsCommand;
use Symfony\\Component\\Console\\Command\\Command;
use Symfony\\Component\\Console\\Input\\InputInterface;
use Symfony\\Component\\Console\\Input\\InputOption;
use Symfony\\Component\\Console\\Input\\InputArgument;
use Symfony\\Component\\Console\\Output\\OutputInterface;
use Symfony\\Component\\Console\\Style\\SymfonyStyle;

#[AsCommand(
    name: '{$signature}',
    description: 'Add a description for your command',
)]
class {$className}Command extends Command
{
    protected function configure(): void
    {
        \$this
            ->addOption('option', 'o', InputOption::VALUE_NONE, 'Option description')
            ->addArgument('argument', InputArgument::OPTIONAL, 'Argument description');
    }

    protected function execute(InputInterface \$input, OutputInterface \$output): int
    {
        \$io = new SymfonyStyle(\$input, \$output);

        \$io->success('Command executed successfully!');

        return Command::SUCCESS;
    }
}
PHP;

        if (file_put_contents($filePath, $content) === false) {
            $io->error("Failed to create command file");
            return Command::FAILURE;
        }

        $io->success("Command {$className}Command created successfully!");
        $io->text("Path: {$filePath}");
        $io->table(
            ['Info', 'Value'],
            [
                ['Signature', $signature],
                ['Run Command', 'php think ' . $signature]
            ]
        );

        return Command::SUCCESS;
    }
}
