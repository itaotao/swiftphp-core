<?php

namespace SwiftPHP\Database;

use PDO;
use Exception;

class ConnectionPool
{
    protected static $connections = [];
    protected static $config = [];
    protected static $pools = [];

    public static function init(array $config): void
    {
        self::$config = $config;
    }

    public static function getConnection(string $name = 'mysql')
    {
        if (isset(self::$connections[$name])) {
            return self::$connections[$name];
        }

        $connection = self::createConnection($name);
        self::$connections[$name] = $connection;
        return $connection;
    }

    protected static function createConnection(string $name): PDO
    {
        $connections = self::$config['connections'] ?? [];
        $config = $connections[$name] ?? $connections['mysql'] ?? null;

        if (!$config) {
            throw new Exception("Database config not found: {$name}");
        }

        $dsn = sprintf(
            '%s:host=%s;port=%d;dbname=%s;charset=%s',
            $config['type'] ?? 'mysql',
            $config['host'] ?? '127.0.0.1',
            $config['port'] ?? 3306,
            $config['database'] ?? 'test',
            $config['charset'] ?? 'utf8mb4'
        );

        try {
            $pdo = new PDO(
                $dsn,
                $config['username'] ?? 'root',
                $config['password'] ?? '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_STRINGIFY_FETCHES => false,
                ]
            );
            return $pdo;
        } catch (Exception $e) {
            throw new Exception("Connection failed: " . $e->getMessage());
        }
    }

    public static function query(string $sql, array $params = [])
    {
        $default = self::$config['default'] ?? 'mysql';
        $pdo = self::getConnection($default);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function select(string $sql, array $params = []): array
    {
        $stmt = self::query($sql, $params);
        return $stmt->fetchAll();
    }

    public static function find(string $sql, array $params = [])
    {
        $stmt = self::query($sql, $params);
        return $stmt->fetch();
    }

    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::query($sql, $params);
        return $stmt->rowCount();
    }

    public static function lastInsertId(): string
    {
        $default = self::$config['default'] ?? 'mysql';
        return self::$connections[$default]->lastInsertId();
    }

    public static function beginTransaction(): bool
    {
        $default = self::$config['default'] ?? 'mysql';
        return self::getConnection($default)->beginTransaction();
    }

    public static function commit(): bool
    {
        $default = self::$config['default'] ?? 'mysql';
        return self::getConnection($default)->commit();
    }

    public static function rollBack(): bool
    {
        $default = self::$config['default'] ?? 'mysql';
        return self::getConnection($default)->rollBack();
    }

    public static function close(): void
    {
        foreach (self::$connections as $connection) {
            $connection = null;
        }
        self::$connections = [];
    }
}
