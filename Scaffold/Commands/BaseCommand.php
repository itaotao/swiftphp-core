<?php

namespace SwiftPHP\Scaffold\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class BaseCommand extends Command
{
    protected $basePath;

    public function __construct(string $basePath)
    {
        parent::__construct();
        $this->basePath = $basePath;
    }

    protected function validateName(string $name, SymfonyStyle $io): int
    {
        if (empty($name)) {
            $io->error('Name cannot be empty');
            return 1;
        }

        if (!preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $name)) {
            $io->error('Name must start with a letter and contain only letters and numbers');
            return 1;
        }

        $reservedWords = [
            'abstract', 'and', 'array', 'as', 'break', 'callable', 'case', 'catch',
            'class', 'clone', 'const', 'continue', 'declare', 'default', 'die', 'do',
            'echo', 'else', 'elseif', 'empty', 'enddeclare', 'endfor', 'endforeach',
            'endif', 'endswitch', 'endwhile', 'eval', 'exit', 'extends', 'final',
            'finally', 'for', 'foreach', 'function', 'global', 'goto', 'if',
            'implements', 'include', 'include_once', 'instanceof', 'insteadof',
            'interface', 'isset', 'list', 'namespace', 'new', 'or', 'print',
            'private', 'protected', 'public', 'require', 'require_once', 'return',
            'static', 'switch', 'throw', 'trait', 'try', 'unset', 'use', 'var',
            'while', 'with', 'yield', 'int', 'float', 'string', 'bool', 'void', 'null', 'true', 'false'
        ];

        if (in_array(strtolower($name), $reservedWords)) {
            $io->error("'{$name}' is a PHP reserved word and cannot be used as a class name");
            return 1;
        }

        return 0;
    }

    protected function ensureDirectory(string $path): void
    {
        $path = rtrim($path, DIRECTORY_SEPARATOR);
        if (!is_dir($path)) {
            if (!mkdir($path, 0755, true)) {
                throw new \RuntimeException("Failed to create directory: {$path}");
            }
        }
        if (!is_writable($path)) {
            throw new \RuntimeException("Directory is not writable: {$path}");
        }
    }

    protected function buildPath(string ...$parts): string
    {
        return implode(DIRECTORY_SEPARATOR, $parts);
    }

    protected function toPascalCase(string $name): string
    {
        $name = str_replace(['-', '_'], ' ', $name);
        $name = ucwords($name);
        return str_replace(' ', '', $name);
    }

    protected function toCamelCase(string $name): string
    {
        return lcfirst($this->toPascalCase($name));
    }

    protected function toSnakeCase(string $name): string
    {
        $name = preg_replace('/([A-Z])/', '_\1', $name);
        return strtolower(ltrim($name, '_'));
    }

    protected function getNamespace(string $path): string
    {
        $path = str_replace('/', '\\', $path);
        return ltrim($path, '\\');
    }
}