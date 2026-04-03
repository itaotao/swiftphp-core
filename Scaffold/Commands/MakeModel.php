<?php

namespace SwiftPHP\Scaffold\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputOption;

class MakeModel extends BaseCommand
{
    protected $commandName = 'make:model';

    protected function configure(): void
    {
        parent::configure();
        $this
            ->setName($this->commandName)
            ->setDescription('Create a new model class')
            ->addArgument('name', InputArgument::REQUIRED, 'The model name (e.g., User)')
            ->addOption('table', 't', InputOption::VALUE_REQUIRED, 'Specify the database table name')
            ->addOption('pk', 'p', InputOption::VALUE_REQUIRED, 'Specify the primary key field')
            ->addOption('fillable', 'f', InputOption::VALUE_NONE, 'Include fillable properties');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');

        if ($this->validateName($name, $io)) {
            return Command::FAILURE;
        }

        $table = $input->getOption('table');
        $pk = $input->getOption('pk');
        $fillable = $input->getOption('fillable');

        if ($table !== null && !preg_match('/^[a-z_][a-z0-9_]*$/', $table)) {
            $io->error('Table name must start with a letter or underscore and contain only lowercase letters, numbers, and underscores');
            return Command::FAILURE;
        }

        if ($pk !== null && !preg_match('/^[a-z_][a-z0-9_]*$/', $pk)) {
            $io->error('Primary key must start with a letter or underscore and contain only lowercase letters, numbers, and underscores');
            return Command::FAILURE;
        }

        $className = $this->toPascalCase($name);
        $directory = $this->buildPath($this->basePath, 'app', 'model');
        $this->ensureDirectory($directory);

        $filePath = $this->buildPath($directory, $className . '.php');

        if (file_exists($filePath)) {
            $io->error("Model {$className} already exists!");
            return Command::FAILURE;
        }

        $tableName = $table ?: $this->toSnakeCase($className);
        $primaryKey = $pk ?: 'id';
        $fillableProperties = $fillable ? $this->generateFillable() : '';

        $content = <<<PHP
<?php

namespace App\Model;

use SwiftPHP\Model\Model;

class {$className} extends Model
{
    protected \$table = '{$tableName}';
    protected \$primaryKey = '{$primaryKey}';{$fillableProperties}
}
PHP;

        if (file_put_contents($filePath, $content) === false) {
            $io->error("Failed to create model file");
            return Command::FAILURE;
        }

        $io->success("Model {$className} created successfully!");
        $io->text("Path: {$filePath}");
        $io->table(
            ['Property', 'Value'],
            [
                ['Table', $tableName],
                ['Primary Key', $primaryKey]
            ]
        );

        return Command::SUCCESS;
    }

    protected function generateFillable(): string
    {
        return <<<'PHP'

    protected $fillable = [
        // 'field_name',
    ];
PHP;
    }
}