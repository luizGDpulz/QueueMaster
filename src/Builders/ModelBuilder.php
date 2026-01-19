<?php

namespace QueueMaster\Builders;

use QueueMaster\Core\Database;
use QueueMaster\Utils\Logger;

/**
 * ModelBuilder - CLI Tool to Generate Model Classes
 * 
 * Generates model classes from database table schema.
 * Creates models with basic CRUD methods and table metadata.
 * 
 * Usage:
 *   php scripts/cli-model-generator.php <table_name>
 * 
 * Generated model includes:
 * - Table name and primary key
 * - find($id) - Find by primary key
 * - all() - Get all records
 * - create($data) - Insert new record
 * - update($id, $data) - Update existing record
 * - delete($id) - Delete record
 */
class ModelBuilder
{
    private Database $db;
    private string $outputDir;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->outputDir = __DIR__ . '/../Models';

        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
    }

    /**
     * Generate model class for a table
     * 
     * @param string $tableName Table name
     * @return bool Success status
     */
    public function generate(string $tableName): bool
    {
        try {
            Logger::info("ModelBuilder: Generating model for table '$tableName'");

            $schema = $this->getTableSchema($tableName);
            
            if (empty($schema)) {
                throw new \RuntimeException("Table '$tableName' not found or has no columns");
            }

            $this->generateModelFile($tableName, $schema);

            Logger::info("ModelBuilder: Successfully generated model for '$tableName'");
            return true;

        } catch (\Exception $e) {
            Logger::error("ModelBuilder: Failed to generate model for '$tableName'", [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Read table schema from information_schema
     * 
     * @param string $tableName Table name
     * @return array Column schema information
     */
    public function getTableSchema(string $tableName): array
    {
        $dbName = $_ENV['DB_NAME'] ?? 'queue_system';
        
        $sql = "
            SELECT 
                COLUMN_NAME as name,
                DATA_TYPE as type,
                IS_NULLABLE as nullable,
                COLUMN_KEY as key,
                COLUMN_DEFAULT as default_value,
                EXTRA as extra
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
            ORDER BY ORDINAL_POSITION
        ";

        return $this->db->query($sql, [$dbName, $tableName]);
    }

    /**
     * Generate model file from schema
     * 
     * @param string $tableName Table name
     * @param array $schema Column schema
     * @return void
     */
    public function generateModelFile(string $tableName, array $schema): void
    {
        $className = $this->tableNameToClassName($tableName);
        $primaryKey = $this->findPrimaryKey($schema);
        
        $template = $this->buildModelTemplate(
            $className,
            $tableName,
            $primaryKey,
            $schema
        );

        $filename = $this->outputDir . '/' . $className . '.php';
        file_put_contents($filename, $template);

        Logger::debug("ModelBuilder: Created file '$filename'");
    }

    /**
     * Convert table name to ClassName (singular, PascalCase)
     * 
     * @param string $tableName Table name
     * @return string Class name
     */
    private function tableNameToClassName(string $tableName): string
    {
        // Remove trailing 's' for plural tables (simple singularization)
        if (substr($tableName, -3) === 'ies') {
            $tableName = substr($tableName, 0, -3) . 'y';
        } elseif (substr($tableName, -1) === 's' && substr($tableName, -2) !== 'ss') {
            $tableName = substr($tableName, 0, -1);
        }

        // Convert underscores to PascalCase
        return str_replace('_', '', ucwords($tableName, '_'));
    }

    /**
     * Find primary key column from schema
     * 
     * @param array $schema Column schema
     * @return string Primary key column name
     */
    private function findPrimaryKey(array $schema): string
    {
        foreach ($schema as $column) {
            if ($column['key'] === 'PRI') {
                return $column['name'];
            }
        }
        return 'id';
    }

    /**
     * Build model class template
     * 
     * @param string $className Class name
     * @param string $tableName Table name
     * @param string $primaryKey Primary key column
     * @param array $schema Column schema
     * @return string PHP class code
     */
    private function buildModelTemplate(
        string $className,
        string $tableName,
        string $primaryKey,
        array $schema
    ): string {
        $columnList = $this->formatColumnList($schema);
        
        return <<<PHP
<?php

namespace QueueMaster\Models;

use QueueMaster\Builders\QueryBuilder;
use QueueMaster\Core\Database;

/**
 * $className Model - Generated from '$tableName' table
 * 
 * Auto-generated model class with basic CRUD operations.
 * Feel free to extend with custom methods and relationships.
 */
class $className
{
    protected static string \$table = '$tableName';
    protected static string \$primaryKey = '$primaryKey';

    /**
     * Find record by primary key
     * 
     * @param int \$id Primary key value
     * @return array|null Record data or null
     */
    public static function find(int \$id): ?array
    {
        \$qb = new QueryBuilder();
        return \$qb->select(self::\$table)
            ->where(self::\$primaryKey, '=', \$id)
            ->first();
    }

    /**
     * Get all records
     * 
     * @param array \$conditions Optional WHERE conditions ['column' => 'value']
     * @param string \$orderBy Optional ORDER BY column
     * @param string \$direction Sort direction (ASC|DESC)
     * @param int|null \$limit Optional LIMIT
     * @return array Records array
     */
    public static function all(
        array \$conditions = [],
        string \$orderBy = '',
        string \$direction = 'ASC',
        ?int \$limit = null
    ): array {
        \$qb = new QueryBuilder();
        \$qb->select(self::\$table);

        foreach (\$conditions as \$column => \$value) {
            \$qb->where(\$column, '=', \$value);
        }

        if (!empty(\$orderBy)) {
            \$qb->orderBy(\$orderBy, \$direction);
        }

        if (\$limit !== null) {
            \$qb->limit(\$limit);
        }

        return \$qb->get();
    }

    /**
     * Create new record
     * 
     * @param array \$data Column => value pairs
     * @return int Inserted record ID
     */
    public static function create(array \$data): int
    {
        \$qb = new QueryBuilder();
        \$qb->select(self::\$table);
        return \$qb->insert(\$data);
    }

    /**
     * Update existing record
     * 
     * @param int \$id Primary key value
     * @param array \$data Column => value pairs to update
     * @return int Number of affected rows
     */
    public static function update(int \$id, array \$data): int
    {
        \$qb = new QueryBuilder();
        return \$qb->select(self::\$table)
            ->where(self::\$primaryKey, '=', \$id)
            ->update(\$data);
    }

    /**
     * Delete record
     * 
     * @param int \$id Primary key value
     * @return int Number of affected rows
     */
    public static function delete(int \$id): int
    {
        \$qb = new QueryBuilder();
        return \$qb->select(self::\$table)
            ->where(self::\$primaryKey, '=', \$id)
            ->delete();
    }

    /**
     * Validate data before create/update
     * 
     * Override this method to add custom validation logic
     * 
     * @param array \$data Data to validate
     * @return array Validation errors (empty if valid)
     */
    public static function validate(array \$data): array
    {
        \$errors = [];
        
        // Add your validation rules here
        // Example:
        // if (empty(\$data['name'])) {
        //     \$errors['name'] = 'Name is required';
        // }
        
        return \$errors;
    }

    /**
     * Table columns:
$columnList
     */
}

PHP;
    }

    /**
     * Format column list for documentation
     * 
     * @param array $schema Column schema
     * @return string Formatted column list
     */
    private function formatColumnList(array $schema): string
    {
        $lines = [];
        foreach ($schema as $column) {
            $nullable = $column['nullable'] === 'YES' ? 'NULL' : 'NOT NULL';
            $key = $column['key'] ? " [{$column['key']}]" : '';
            $lines[] = "     * - {$column['name']}: {$column['type']} {$nullable}{$key}";
        }
        return implode("\n", $lines);
    }
}
