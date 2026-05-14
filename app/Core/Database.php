<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

/**
 * PDO singleton. Use prepared statements only.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host = Config::get('database.host', '127.0.0.1');
        $port = Config::get('database.port', '3306');
        $name = Config::get('database.database', 'itportal');
        $user = Config::get('database.username', 'root');
        $pass = Config::get('database.password', '');
        $charset = Config::get('database.charset', 'utf8mb4');

        $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=$charset";

        try {
            self::$pdo = new PDO($dsn, (string) $user, (string) $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            throw new \RuntimeException('Database connection failed: ' . $e->getMessage(), 0, $e);
        }
        return self::$pdo;
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }
}
