<?php

declare(strict_types=1);

namespace TaskQueue\Support;

use PDO;

class Database
{
    /**
     * Create a configured SQLite PDO instance with concurrency-friendly settings.
     */
    public static function createSqlitePdo(string $path): PDO
    {
        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Optional timeout attribute (seconds) if supported
        if (defined('PDO::ATTR_TIMEOUT')) {
            $pdo->setAttribute(PDO::ATTR_TIMEOUT, 5);
        }

        // Apply PRAGMAs for concurrency and reasonable durability
        try {
            $pdo->exec('PRAGMA busy_timeout=5000');
            $pdo->exec('PRAGMA journal_mode=WAL');
            $pdo->exec('PRAGMA synchronous=NORMAL');
        } catch (\Throwable $e) {
            // Ignore if driver/PRAGMA unsupported
        }

        return $pdo;
    }
}
