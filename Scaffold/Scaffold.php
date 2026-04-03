<?php

namespace SwiftPHP\Scaffold;

use Symfony\Component\Console\Application;
use SwiftPHP\Scaffold\Commands\MakeController;
use SwiftPHP\Scaffold\Commands\MakeModel;
use SwiftPHP\Scaffold\Commands\MakeMiddleware;
use SwiftPHP\Scaffold\Commands\MakeValidate;
use SwiftPHP\Scaffold\Commands\MakeCommand;
use SwiftPHP\Scaffold\Commands\NewProject;
use SwiftPHP\Scaffold\Commands\ListCommands;

class Scaffold
{
    protected static $instance;
    protected $app;
    protected $basePath;

    private function __construct()
    {
        $this->basePath = getcwd();
        $this->app = new Application('SwiftPHP', '1.0.2');
        $this->registerCommands();
    }

    public static function getInstance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    protected function registerCommands(): void
    {
        $this->app->add(new NewProject($this->basePath));
        $this->app->add(new MakeController($this->basePath));
        $this->app->add(new MakeModel($this->basePath));
        $this->app->add(new MakeMiddleware($this->basePath));
        $this->app->add(new MakeValidate($this->basePath));
        $this->app->add(new MakeCommand($this->basePath));
        $this->app->add(new ListCommands());
    }

    public function run(array $args = []): void
    {
        if (empty($args)) {
            $args = $_SERVER['argv'] ?? ['swiftphp'];
        }

        if (count($args) === 1) {
            $args[] = 'list';
        }

        try {
            $input = new \Symfony\Component\Console\Input\ArgvInput($args);
            $output = new \Symfony\Component\Console\Output\ConsoleOutput();
            $this->app->run($input, $output);
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }
}