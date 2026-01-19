<?php

namespace QueueMaster\Builders;

use QueueMaster\Core\Database;

/**
 * QueryBuilder - Fluent Query Builder with Prepared Statements
 * 
 * Provides a minimal fluent interface for building SQL queries with
 * automatic parameter binding for security. Uses Database class for
 * all query execution with prepared statements.
 */
class QueryBuilder
{
    private Database $db;
    private string $table = '';
    private array $wheres = [];
    private array $bindings = [];
    private ?string $orderBy = null;
    private ?int $limit = null;
    private ?int $offset = null;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Start a SELECT query
     * 
     * @param string $table Table name
     * @return self
     */
    public function select(string $table): self
    {
        $this->table = $table;
        $this->reset();
        return $this;
    }

    /**
     * Add WHERE clause
     * 
     * @param string $column Column name
     * @param string $operator Comparison operator
     * @param mixed $value Value to compare
     * @return self
     */
    public function where(string $column, string $operator, $value): self
    {
        $this->wheres[] = [
            'type' => 'AND',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
        ];
        return $this;
    }

    /**
     * Add OR WHERE clause
     * 
     * @param string $column Column name
     * @param string $operator Comparison operator
     * @param mixed $value Value to compare
     * @return self
     */
    public function orWhere(string $column, string $operator, $value): self
    {
        $this->wheres[] = [
            'type' => 'OR',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
        ];
        return $this;
    }

    /**
     * Add ORDER BY clause
     * 
     * @param string $column Column name
     * @param string $direction Sort direction (ASC|DESC)
     * @return self
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'])) {
            $direction = 'ASC';
        }
        $this->orderBy = "$column $direction";
        return $this;
    }

    /**
     * Add LIMIT clause
     * 
     * @param int $limit Maximum rows to return
     * @return self
     */
    public function limit(int $limit): self
    {
        $this->limit = max(1, $limit);
        return $this;
    }

    /**
     * Add OFFSET clause
     * 
     * @param int $offset Number of rows to skip
     * @return self
     */
    public function offset(int $offset): self
    {
        $this->offset = max(0, $offset);
        return $this;
    }

    /**
     * Execute SELECT query and return all results
     * 
     * @return array Results array
     */
    public function get(): array
    {
        $sql = $this->buildSelectSql();
        return $this->db->query($sql, $this->bindings);
    }

    /**
     * Execute SELECT query and return first result
     * 
     * @return array|null First result or null
     */
    public function first(): ?array
    {
        $this->limit(1);
        $results = $this->get();
        return $results[0] ?? null;
    }

    /**
     * Execute COUNT query and return count
     * 
     * @return int Row count
     */
    public function count(): int
    {
        $sql = "SELECT COUNT(*) as total FROM {$this->table}";
        
        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . $this->buildWhereClause();
        }
        
        $result = $this->db->query($sql, $this->bindings);
        return (int)($result[0]['total'] ?? 0);
    }

    /**
     * Insert a new record
     * 
     * @param array $data Associative array of column => value
     * @return int Last insert ID
     */
    public function insert(array $data): int
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('Insert data cannot be empty');
        }

        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        
        $sql = "INSERT INTO {$this->table} (" 
            . implode(', ', $columns) . ") VALUES (" 
            . implode(', ', $placeholders) . ")";
        
        $this->db->execute($sql, array_values($data));
        return (int)$this->db->lastInsertId();
    }

    /**
     * Update existing records
     * 
     * @param array $data Associative array of column => value
     * @return int Number of affected rows
     */
    public function update(array $data): int
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('Update data cannot be empty');
        }

        if (empty($this->wheres)) {
            throw new \InvalidArgumentException('Update requires WHERE clause for safety');
        }

        $sets = [];
        $values = [];
        
        foreach ($data as $column => $value) {
            $sets[] = "$column = ?";
            $values[] = $value;
        }
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets);
        $sql .= ' WHERE ' . $this->buildWhereClause();
        
        $allBindings = array_merge($values, $this->bindings);
        
        return $this->db->execute($sql, $allBindings);
    }

    /**
     * Delete records
     * 
     * @return int Number of affected rows
     */
    public function delete(): int
    {
        if (empty($this->wheres)) {
            throw new \InvalidArgumentException('Delete requires WHERE clause for safety');
        }

        $sql = "DELETE FROM {$this->table} WHERE " . $this->buildWhereClause();
        return $this->db->execute($sql, $this->bindings);
    }

    /**
     * Build complete SELECT SQL statement
     * 
     * @return string SQL query
     */
    private function buildSelectSql(): string
    {
        $sql = "SELECT * FROM {$this->table}";
        
        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . $this->buildWhereClause();
        }
        
        if ($this->orderBy !== null) {
            $sql .= " ORDER BY {$this->orderBy}";
        }
        
        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }
        
        if ($this->offset !== null) {
            $sql .= " OFFSET {$this->offset}";
        }
        
        return $sql;
    }

    /**
     * Build WHERE clause and populate bindings
     * 
     * @return string WHERE clause (without "WHERE" keyword)
     */
    private function buildWhereClause(): string
    {
        $this->bindings = [];
        $clauses = [];
        
        foreach ($this->wheres as $index => $where) {
            $clause = '';
            
            if ($index > 0) {
                $clause .= ' ' . $where['type'] . ' ';
            }
            
            $clause .= "{$where['column']} {$where['operator']} ?";
            $clauses[] = $clause;
            $this->bindings[] = $where['value'];
        }
        
        return implode('', $clauses);
    }

    /**
     * Reset query builder state
     * 
     * @return void
     */
    private function reset(): void
    {
        $this->wheres = [];
        $this->bindings = [];
        $this->orderBy = null;
        $this->limit = null;
        $this->offset = null;
    }
}
