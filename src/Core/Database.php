<?php

namespace QueueMaster\Core;

use PDO;
use PDOException;

/**
 * Database - PDO Wrapper for MySQL/MariaDB
 * 
 * Provides secure database access with prepared statements.
 * Implements singleton pattern for connection reuse.
 */
class Database
{
    private static ?PDO $instance = null;
    private PDO $connection;

    /**
     * Private constructor to enforce singleton pattern
     */
    private function __construct()
    {
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $port = $_ENV['DB_PORT'] ?? '3306';
        $dbname = $_ENV['DB_NAME'] ?? 'queue_system';
        $username = $_ENV['DB_USER'] ?? 'root';
        $password = $_ENV['DB_PASS'] ?? '';

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

        try {
            $this->connection = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false, // Use real prepared statements for security
                PDO::ATTR_PERSISTENT => false, // Disable persistent connections for better control
            ]);
        } catch (PDOException $e) {
            // Log error securely (don't expose credentials)
            error_log("Database connection failed: " . $e->getMessage());
            throw new \RuntimeException("Database connection failed", 500);
        }
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get PDO connection
     */
    public function getConnection(): PDO
    {
        return $this->connection;
    }

    /**
     * Execute a query with parameters (SELECT)
     * 
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters to bind
     * @return array Results
     */
    public function query(string $sql, array $params = []): array
    {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Query failed: " . $e->getMessage());
            throw new \RuntimeException("Query execution failed", 500);
        }
    }

    /**
     * Execute a command (INSERT, UPDATE, DELETE)
     * 
     * @param string $sql SQL command with placeholders
     * @param array $params Parameters to bind
     * @return int Number of affected rows
     */
    public function execute(string $sql, array $params = []): int
    {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Execute failed: " . $e->getMessage());
            throw new \RuntimeException("Command execution failed", 500);
        }
    }

    /**
     * Get last insert ID
     */
    public function lastInsertId(): string
    {
        return $this->connection->lastInsertId();
    }

    /**
     * Begin transaction
     */
    public function beginTransaction(): bool
    {
        return $this->connection->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit(): bool
    {
        return $this->connection->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback(): bool
    {
        return $this->connection->rollBack();
    }

    /**
     * Check if in transaction
     */
    public function inTransaction(): bool
    {
        return $this->connection->inTransaction();
    }

    /**
     * Prepare a statement (for advanced use cases)
     */
    public function prepare(string $sql): \PDOStatement
    {
        return $this->connection->prepare($sql);
    }

    /**
     * Prevent cloning of singleton
     */
    private function __clone()
    {
    }

    /**
     * Prevent unserialization of singleton
     */
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }
}
