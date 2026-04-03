<?php

namespace SwiftPHP\Scaffold\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ListCommands extends Command
{
    protected function configure(): void
    {
        $this->setName('list');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('SwiftPHP Scaffold Commands');
        $io->text('Available make commands for generating code scaffolding:');
        $io->newLine();

        $commands = [
            ['new', 'Create a new project', 'php swiftphp new myapp'],
            ['make:controller', 'Create a new controller', 'php swiftphp make:controller User'],
            ['make:model', 'Create a new model', 'php swiftphp make:model User'],
            ['make:middleware', 'Create a new middleware', 'php swiftphp make:middleware Auth'],
            ['make:validate', 'Create a new validation class', 'php swiftphp make:validate User'],
            ['make:command', 'Create a new console command', 'php swiftphp make:command Demo'],
        ];

        $io->table(['Command', 'Description', 'Usage'], $commands);

        $io->section('Controller Options');
        $io->text('  --rest, -r    Create a RESTful controller');
        $io->text('  --api, -a     Create an API controller with validation');
        $io->newLine();

        $io->section('Model Options');
        $io->text('  --table, -t   Specify database table name');
        $io->text('  --pk, -p      Specify primary key field');
        $io->text('  --fillable, -f   Include fillable properties');
        $io->newLine();

        $io->section('Middleware Options');
        $io->text('  --global, -g  Show global middleware registration info');
        $io->newLine();

        $io->section('Validate Options');
        $io->text('  --scene, -s   Include validation scene methods');
        $io->newLine();

        $io->section('Command Options');
        $io->text('  --command, -c Specify command signature');
        $io->newLine();

        $io->section('Examples');
        $io->text('  php swiftphp new myapp              # Create a new project');
        $io->text('  php swiftphp new myapp --dir=/path   # Create in specific directory');
        $io->text('  php swiftphp make:controller User');
        $io->text('  php swiftphp make:controller User --rest');
        $io->text('  php swiftphp make:model User --table=users --fillable');
        $io->text('  php swiftphp make:middleware Auth --global');
        $io->text('  php swiftphp make:validate User --scene');
        $io->text('  php swiftphp make:command Demo --command=app:demo');

        return Command::SUCCESS;
    }
}
