<?php

namespace SwiftPHP\Scaffold\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputOption;

class MakeValidate extends BaseCommand
{
    protected $commandName = 'make:validate';

    protected function configure(): void
    {
        parent::configure();
        $this
            ->setName($this->commandName)
            ->setDescription('Create a new validation class')
            ->addArgument('name', InputArgument::REQUIRED, 'The validation class name (e.g., User)')
            ->addOption('scene', 's', InputOption::VALUE_NONE, 'Include validation scene methods');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');
        $withScene = $input->getOption('scene');

        if ($this->validateName($name, $io)) {
            return Command::FAILURE;
        }

        $className = $this->toPascalCase($name);
        if (str_ends_with(strtolower($className), 'validate')) {
            $className = substr($className, 0, -8);
        }
        $directory = $this->buildPath($this->basePath, 'app', 'validate');
        $this->ensureDirectory($directory);

        $filePath = $this->buildPath($directory, $className . 'Validate.php');

        if (file_exists($filePath)) {
            $io->error("Validation class {$className}Validate already exists!");
            return Command::FAILURE;
        }

        $sceneMethods = $withScene ? $this->generateSceneMethods() : '';

        $content = <<<PHP
<?php

namespace App\Validate;

use SwiftPHP\Validate\Validate;

class {$className}Validate extends Validate
{
    protected \$rules = [
        // 'field' => 'require|email|max:255',
    ];

    protected \$messages = [
        // 'field.require' => 'The field is required',
        // 'field.email' => 'The field must be a valid email',
    ];{$sceneMethods}
}
PHP;

        if (file_put_contents($filePath, $content) === false) {
            $io->error("Failed to create validation file");
            return Command::FAILURE;
        }

        $io->success("Validation class {$className}Validate created successfully!");
        $io->text("Path: {$filePath}");
        $io->table(
            ['Rule', 'Description'],
            [
                ['require', 'Field is required'],
                ['email', 'Must be valid email'],
                ['number', 'Must be a number'],
                ['integer', 'Must be an integer'],
                ['float', 'Must be a float'],
                ['boolean', 'Must be true or false'],
                ['array', 'Must be an array'],
                ['date', 'Must be a valid date'],
                ['alpha', 'Must be alphabetic'],
                ['alphaNum', 'Must be alphanumeric'],
                ['alphaDash', 'Must be alphanumeric, underscore, dash'],
                ['url', 'Must be a valid URL'],
                ['ip', 'Must be a valid IP'],
                ['in:a,b,c', 'Must be one of the values'],
                ['notIn:a,b,c', 'Must not be one of the values'],
                ['between:1,10', 'Must be between 1 and 10'],
                ['length:1,10', 'Length must be between 1 and 10'],
                ['max:255', 'Maximum length/value is 255'],
                ['min:1', 'Minimum length/value is 1'],
                ['regex:/^.../', 'Must match the regex pattern'],
            ]
        );

        return Command::SUCCESS;
    }

    protected function generateSceneMethods(): string
    {
        return <<<'PHP'

    protected $scene = [];

    public function scene(string $name): bool
    {
        if (!isset($this->scene[$name])) {
            return false;
        }

        $this->only = $this->scene[$name];
        return $this->check($this->data);
    }

    public static function registerScenes(): array
    {
        return [
            // 'create' => ['field1', 'field2'],
            // 'update' => ['field1', 'field2'],
        ];
    }
PHP;
    }
}