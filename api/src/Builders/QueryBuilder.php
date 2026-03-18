<?php

namespace QueueMaster\Builders;

use QueueMaster\Core\Database;

/**
 * QueryBuilder - Fluent Query Builder with Prepared Statements
 *
 * Keeps values parameterized and validates identifiers used in table/column
 * expressions to avoid SQL injection while still supporting the most common
 * query patterns needed by the application.
 */
class QueryBuilder
{
    private Database $db;
    private string $table = '';
    private array $selects = ['*'];
    private array $joins = [];
    private array $wheres = [];
    private array $bindings = [];
    private array $orderBys = [];
    private array $groupBys = [];
    private ?int $limit = null;
    private ?int $offset = null;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Start a SELECT query.
     *
     * @param string $table Table name, optionally with alias
     * @param string|array $columns Selected columns
     * @return self
     */
    public function select(string $table, string|array $columns = '*'): self
    {
        $this->reset();
        $this->table = $this->normalizeTableReference($table);
        $this->selects = $this->normalizeColumns($columns);

        return $this;
    }

    /**
     * Add columns to the SELECT list.
     *
     * @param string|array $columns
     * @return self
     */
    public function addSelect(string|array $columns): self
    {
        $this->selects = array_merge($this->selects, $this->normalizeColumns($columns));
        return $this;
    }

    /**
     * Add INNER JOIN.
     *
     * @return self
     */
    public function join(string $table, string $first, string $operator, string $second): self
    {
        return $this->addJoin('INNER', $table, $first, $operator, $second);
    }

    /**
     * Add LEFT JOIN.
     *
     * @return self
     */
    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->addJoin('LEFT', $table, $first, $operator, $second);
    }

    /**
     * Add RIGHT JOIN.
     *
     * @return self
     */
    public function rightJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->addJoin('RIGHT', $table, $first, $operator, $second);
    }

    /**
     * Add WHERE clause.
     *
     * @param string $column Column name
     * @param string $operator Comparison operator
     * @param mixed $value Value to compare
     * @return self
     */
    public function where(string $column, string $operator, mixed $value): self
    {
        $this->wheres[] = [
            'type' => 'AND',
            'kind' => 'basic',
            'column' => $this->normalizeColumnReference($column),
            'operator' => $this->normalizeOperator($operator),
            'value' => $value,
        ];
        return $this;
    }

    /**
     * Add OR WHERE clause.
     *
     * @param string $column Column name
     * @param string $operator Comparison operator
     * @param mixed $value Value to compare
     * @return self
     */
    public function orWhere(string $column, string $operator, mixed $value): self
    {
        $this->wheres[] = [
            'type' => 'OR',
            'kind' => 'basic',
            'column' => $this->normalizeColumnReference($column),
            'operator' => $this->normalizeOperator($operator),
            'value' => $value,
        ];
        return $this;
    }

    /**
     * Add WHERE IN clause.
     *
     * @return self
     */
    public function whereIn(string $column, array $values): self
    {
        $values = array_values($values);

        $this->wheres[] = [
            'type' => 'AND',
            'kind' => 'in',
            'column' => $this->normalizeColumnReference($column),
            'values' => $values,
        ];
        return $this;
    }

    /**
     * Add OR WHERE IN clause.
     *
     * @return self
     */
    public function orWhereIn(string $column, array $values): self
    {
        $values = array_values($values);

        $this->wheres[] = [
            'type' => 'OR',
            'kind' => 'in',
            'column' => $this->normalizeColumnReference($column),
            'values' => $values,
        ];
        return $this;
    }

    /**
     * Add WHERE NULL clause.
     *
     * @return self
     */
    public function whereNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'AND',
            'kind' => 'null',
            'column' => $this->normalizeColumnReference($column),
            'not' => false,
        ];
        return $this;
    }

    /**
     * Add WHERE NOT NULL clause.
     *
     * @return self
     */
    public function whereNotNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'AND',
            'kind' => 'null',
            'column' => $this->normalizeColumnReference($column),
            'not' => true,
        ];
        return $this;
    }

    /**
     * Add GROUP BY clause.
     *
     * @param string|array $columns
     * @return self
     */
    public function groupBy(string|array $columns): self
    {
        foreach ((array)$columns as $column) {
            $this->groupBys[] = $this->normalizeColumnReference($column);
        }

        return $this;
    }

    /**
     * Add ORDER BY clause.
     *
     * @param string $column Column name
     * @param string $direction Sort direction (ASC|DESC)
     * @return self
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            $direction = 'ASC';
        }

        $this->orderBys[] = $this->normalizeColumnReference($column) . " $direction";
        return $this;
    }

    /**
     * Add LIMIT clause.
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
     * Add OFFSET clause.
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
     * Execute SELECT query and return all results.
     *
     * @return array Results array
     */
    public function get(): array
    {
        $sql = $this->buildSelectSql();
        return $this->db->query($sql, $this->bindings);
    }

    /**
     * Execute SELECT query and return first result.
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
     * Execute COUNT query and return count.
     *
     * @return int Row count
     */
    public function count(): int
    {
        $sql = "SELECT COUNT(*) as total FROM {$this->table}";
        $sql .= $this->buildJoinClause();

        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . $this->buildWhereClause();
        }

        $result = $this->db->query($sql, $this->bindings);
        return (int)($result[0]['total'] ?? 0);
    }

    /**
     * Insert a new record.
     *
     * @param array $data Associative array of column => value
     * @return int Last insert ID
     */
    public function insert(array $data): int
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('Insert data cannot be empty');
        }

        $columns = array_map([$this, 'normalizeInsertUpdateColumn'], array_keys($data));
        $placeholders = array_fill(0, count($columns), '?');

        $sql = "INSERT INTO {$this->table} ("
            . implode(', ', $columns) . ') VALUES ('
            . implode(', ', $placeholders) . ')';

        $this->db->execute($sql, array_values($data));
        return (int)$this->db->lastInsertId();
    }

    /**
     * Update existing records.
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
            $safeColumn = $this->normalizeInsertUpdateColumn((string)$column);
            $sets[] = "$safeColumn = ?";
            $values[] = $value;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets);
        $sql .= ' WHERE ' . $this->buildWhereClause();

        $allBindings = array_merge($values, $this->bindings);

        return $this->db->execute($sql, $allBindings);
    }

    /**
     * Delete records.
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
     * Build complete SELECT SQL statement.
     *
     * @return string SQL query
     */
    private function buildSelectSql(): string
    {
        $sql = 'SELECT ' . implode(', ', $this->selects) . " FROM {$this->table}";
        $sql .= $this->buildJoinClause();

        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . $this->buildWhereClause();
        }

        if (!empty($this->groupBys)) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBys);
        }

        if (!empty($this->orderBys)) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orderBys);
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
     * Build JOIN clauses.
     *
     * @return string
     */
    private function buildJoinClause(): string
    {
        if (empty($this->joins)) {
            return '';
        }

        return ' ' . implode(' ', $this->joins);
    }

    /**
     * Build WHERE clause and populate bindings.
     *
     * @return string WHERE clause (without "WHERE" keyword)
     */
    private function buildWhereClause(): string
    {
        $this->bindings = [];
        $clauses = [];

        foreach ($this->wheres as $index => $where) {
            $clause = $index > 0 ? ' ' . $where['type'] . ' ' : '';

            switch ($where['kind']) {
                case 'in':
                    if (empty($where['values'])) {
                        $clause .= '1 = 0';
                    } else {
                        $placeholders = implode(', ', array_fill(0, count($where['values']), '?'));
                        $clause .= "{$where['column']} IN ($placeholders)";
                        array_push($this->bindings, ...$where['values']);
                    }
                    break;

                case 'null':
                    $clause .= "{$where['column']} IS " . ($where['not'] ? 'NOT NULL' : 'NULL');
                    break;

                case 'basic':
                default:
                    $clause .= "{$where['column']} {$where['operator']} ?";
                    $this->bindings[] = $where['value'];
                    break;
            }

            $clauses[] = $clause;
        }

        return implode('', $clauses);
    }

    /**
     * Add join definition after validating identifiers.
     *
     * @return self
     */
    private function addJoin(string $type, string $table, string $first, string $operator, string $second): self
    {
        $operator = $this->normalizeJoinOperator($operator);
        $this->joins[] = sprintf(
            '%s JOIN %s ON %s %s %s',
            $type,
            $this->normalizeTableReference($table),
            $this->normalizeColumnReference($first),
            $operator,
            $this->normalizeColumnReference($second)
        );

        return $this;
    }

    /**
     * Normalize selected columns.
     *
     * @param string|array $columns
     * @return array
     */
    private function normalizeColumns(string|array $columns): array
    {
        $list = is_array($columns) ? $columns : [$columns];

        if (empty($list)) {
            return ['*'];
        }

        return array_map(function (string $column): string {
            $column = trim($column);
            if ($column === '*') {
                return '*';
            }

            if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\.\*$/', $column)) {
                return $column;
            }

            return $this->normalizeColumnReferenceWithAlias($column);
        }, $list);
    }

    /**
     * Normalize table references like "queues" or "queues q".
     */
    private function normalizeTableReference(string $table): string
    {
        $table = trim($table);
        if ($table === '') {
            throw new \InvalidArgumentException('Table reference cannot be empty');
        }

        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)(?:\s+(?:AS\s+)?([A-Za-z_][A-Za-z0-9_]*))?$/i', $table, $matches)) {
            $normalized = $matches[1];
            if (!empty($matches[2])) {
                $normalized .= ' ' . $matches[2];
            }
            return $normalized;
        }

        throw new \InvalidArgumentException("Unsafe table reference: {$table}");
    }

    /**
     * Normalize column references with optional alias.
     */
    private function normalizeColumnReferenceWithAlias(string $column): string
    {
        if (preg_match('/^(.+?)\s+(?:AS\s+)?([A-Za-z_][A-Za-z0-9_]*)$/i', $column, $matches)) {
            return $this->normalizeColumnReference(trim($matches[1])) . ' AS ' . $matches[2];
        }

        return $this->normalizeColumnReference($column);
    }

    /**
     * Normalize a column reference, allowing qualified identifiers.
     */
    private function normalizeColumnReference(string $column): string
    {
        $column = trim($column);
        if ($column === '') {
            throw new \InvalidArgumentException('Column reference cannot be empty');
        }

        $parts = explode('.', $column);
        foreach ($parts as $part) {
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $part)) {
                throw new \InvalidArgumentException("Unsafe column reference: {$column}");
            }
        }

        return implode('.', $parts);
    }

    /**
     * Normalize insert/update column names.
     */
    private function normalizeInsertUpdateColumn(string $column): string
    {
        return $this->normalizeColumnReference($column);
    }

    /**
     * Normalize comparison operators.
     */
    private function normalizeOperator(string $operator): string
    {
        $operator = strtoupper(trim($operator));
        $allowed = ['=', '!=', '<>', '>', '>=', '<', '<=', 'LIKE'];

        if (!in_array($operator, $allowed, true)) {
            throw new \InvalidArgumentException("Unsafe operator: {$operator}");
        }

        return $operator;
    }

    /**
     * Normalize join operators.
     */
    private function normalizeJoinOperator(string $operator): string
    {
        $operator = strtoupper(trim($operator));
        $allowed = ['=', '!=', '<>', '>', '>=', '<', '<='];

        if (!in_array($operator, $allowed, true)) {
            throw new \InvalidArgumentException("Unsafe join operator: {$operator}");
        }

        return $operator;
    }

    /**
     * Reset query builder state.
     */
    private function reset(): void
    {
        $this->wheres = [];
        $this->bindings = [];
        $this->joins = [];
        $this->selects = ['*'];
        $this->orderBys = [];
        $this->groupBys = [];
        $this->limit = null;
        $this->offset = null;
    }
}
