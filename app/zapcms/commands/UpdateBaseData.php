<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS
 */

namespace zapcms\commands;

use zapcms\support\CreateBaseData;
use zap\console\Command;
use zap\console\Input;
use zap\console\Output;
use zap\DB;

/**
 * Update / export schema and base data.
 *
 * Usage:
 *   php console zapcms:UpdateBaseData -f              # Force update system config tables
 *   php console zapcms:UpdateBaseData -f -d           # Include demo data reset
 *   php console zapcms:UpdateBaseData -e              # Export schema + data → CreateTables.php + CreateBaseData.php
 *   php console zapcms:UpdateBaseData -e /path/dir    # Export to custom directory
 */
class UpdateBaseData extends Command
{
    function execute(Input $input, Output $output): int
    {
        $force  = $this->input->hasParam('f');
        $demo   = $this->input->hasParam('d');
        $export = $this->input->hasParam('e');

        if ($export) {
            return $this->runExport();
        }

        if (!$force && !$demo) {
            $this->out->writeln('This command updates system base data.');
            $this->out->writeln('Existing records with duplicate keys may cause errors.');
            $this->out->writeln('Use -f flag to clear system tables before re-inserting:');
            $this->out->writeln('  php console zapcms:UpdateBaseData -f');
            $this->out->writeln('');
            $this->out->writeln('  php console zapcms:UpdateBaseData -e    Export schema + data classes');
            $this->out->writeln('');
            $this->out->writeln('Use -h for help.');
            return self::SUCCESS;
        }

        if ($force) {
            $this->out->writeln('Clearing existing system data...');
            $this->clearSystemData();
            $this->out->writeln('');
        }

        if ($demo) {
            $this->out->writeln('Clearing demo data...');
            $this->clearDemoData();
            $this->out->writeln('');
        }

        $this->out->writeln('Installing base data...');
        CreateBaseData::install();

        $this->out->writeln('');
        $this->out->writeln('Base data updated successfully.');
        return self::SUCCESS;
    }

    protected function clearSystemData(): void
    {
        $pdo = DB::getPDO();
        $driver = $this->getDriver();

        // Tables to fully truncate (pure system config, safe to replace completely)
        // options included: -f means "reset system configuration to base defaults"
        $truncateTables = [
            'admin_menu',
            'permissions',
            'roles_permissions',
            'admin_roles',
            'options',
        ];

        foreach ($truncateTables as $table) {
            $quoted = $pdo->quoteTable($table);
            $pdo->rawExec("DELETE FROM {$quoted}");
            $this->out->writeln("  Cleared: {$table}");
        }

        // Tables where we only delete known system IDs (preserve user-created records)
        $quoted = $pdo->quoteTable('roles');
        $pdo->rawExec("DELETE FROM {$quoted} WHERE role_id IN (1,2,3,5)");
        $this->out->writeln("  Cleared (system IDs only): roles");

        $quoted = $pdo->quoteTable('node_types');
        $pdo->rawExec("DELETE FROM {$quoted} WHERE type_id IN (1,2,3,4,5)");
        $this->out->writeln("  Cleared (system IDs only): node_types");

        // Reset auto-increment for MySQL / PGSQL
        if ($driver === 'pgsql') {
            foreach (['admin_menu', 'permissions', 'node_types'] as $t) {
                $seq = "{$t}_id_seq";
                $pdo->rawExec("ALTER SEQUENCE {$seq} RESTART WITH 1");
            }
        }
    }

    protected function clearDemoData(): void
    {
        $pdo = DB::getPDO();
        foreach (['node_relation', 'catalog', 'node'] as $table) {
            $quoted = $pdo->quoteTable($table);
            $pdo->rawExec("DELETE FROM {$quoted}");
            $this->out->writeln("  Cleared: {$table}");
        }

        // Remove orphan polymorphic comments pointing to cleared node content
        try {
            $pdo->rawExec("DELETE FROM " . $pdo->quoteTable('comments') . " WHERE object_type = 'node'");
            $this->out->writeln("  Cleared: comments (object_type=node)");
        } catch (\Throwable $e) {
            $this->out->writeln("  Skipped: comments ({$e->getMessage()})");
        }
    }

    // ═══════════════════════════════════════════════════════════
    //  Export mode — generates TWO class files
    // ═══════════════════════════════════════════════════════════

    /**
     * Export both table structure (CreateTables) and data (CreateBaseData).
     */
    protected function runExport(): int
    {
        $exportPath = $this->input->getParam('e');
        if ($exportPath === true || $exportPath === null) {
            $dir = ROOT_PATH . '/app/zapcms/support';
        } else {
            $dir = rtrim($exportPath, '/\\');
        }

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // 1. Generate CreateTables.php — table structure
        $tablesCode = $this->buildCreateTablesCode();
        $tablesFile = $dir . '/CreateTables.php';
        file_put_contents($tablesFile, $tablesCode);
        $this->out->writeln('Generated: ' . $tablesFile);

        // 2. Generate CreateBaseData.php — base data
        $dataCode = $this->buildCreateBaseDataCode();
        $dataFile = $dir . '/CreateBaseData.php';
        file_put_contents($dataFile, $dataCode);
        $this->out->writeln('Generated: ' . $dataFile);

        $this->out->writeln('');
        $this->out->writeln('Export complete. Two class files created:');
        $this->out->writeln('  ' . $tablesFile);
        $this->out->writeln('  ' . $dataFile);
        return self::SUCCESS;
    }

    // ──────────────── CreateTables (schema) generation ────────────────

    protected function buildCreateTablesCode(): string
    {
        $tables = $this->getAllTables();

        $lines = [];
        $lines[] = '<?php';
        $lines[] = '/*';
        $lines[] = ' * Auto-generated by: php console zapcms:UpdateBaseData -e';
        $lines[] = ' * Generated at: ' . date('Y-m-d H:i:s');
        $lines[] = ' *';
        $lines[] = ' * Table structure for ALL database tables.';
        $lines[] = ' * To regenerate: php console zapcms:UpdateBaseData -e';
        $lines[] = ' */';
        $lines[] = '';
        $lines[] = 'namespace zapcms\support;';
        $lines[] = '';
        $lines[] = 'use zap\\db\\Schema;';
        $lines[] = 'use zap\\db\\TableSchema;';
        $lines[] = '';
        $lines[] = 'class CreateTables';
        $lines[] = '{';
        $lines[] = '    public function createSchema()';
        $lines[] = '    {';

        foreach ($tables as $tableInfo) {
            $lines[] = '';
            $lines[] = $this->buildTableCreateCode($tableInfo['full'], $tableInfo['logical']);
        }

        $lines[] = '    }';
        $lines[] = '';
        $lines[] = '    public function installBaseData()';
        $lines[] = '    {';
        $lines[] = '        CreateBaseData::install();';
        $lines[] = '    }';
        $lines[] = '';
        $lines[] = '    public function installDemoData()';
        $lines[] = '    {';
        $lines[] = '        ';
        $lines[] = '    }';
        $lines[] = '}';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Build a single Schema::create() block for one table.
     */
    protected function buildTableCreateCode(string $fullName, string $logicalName): string
    {
        $columns = $this->getColumnsDetail($fullName);
        $indexes = $this->getIndexes($fullName);

        if (empty($columns)) {
            return "        // Table `{$logicalName}` — no columns found, skipped";
        }

        $out = [];
        $out[] = "        Schema::dropIfExists('{$logicalName}');";
        $out[] = "        Schema::create('{$logicalName}',function(TableSchema \$table){";

        // 被索引或主键引用的列：MySQL 不允许 TEXT 列参与索引（无前缀长度）。
        // SQLite 不保留 varchar 长度（统一存为 TEXT），因此导出时必须显式降级为 varchar。
        $keyColumns = [];
        foreach ($columns as $col) {
            if (!empty($col['primary'])) {
                $keyColumns[$col['name']] = true;
            }
        }
        foreach ($indexes as $idx) {
            $idxCols = is_array($idx['columns']) ? $idx['columns'] : [$idx['column'] ?? $idx['columns']];
            foreach ($idxCols as $c) {
                $keyColumns[$c] = true;
            }
        }

        // Emit columns
        foreach ($columns as $col) {
            $out[] = '            ' . $this->generateColumnLine($col, $keyColumns);
        }

        // Blank line before indexes
        $out[] = '';

        // Emit primary key (use addPrimary for single-column PKs)
        $pkColumns = [];
        foreach ($columns as $col) {
            if (!empty($col['primary'])) {
                $pkColumns[] = $col['name'];
            }
        }
        if (!empty($pkColumns)) {
            if (count($pkColumns) === 1) {
                $out[] = "            \$table->addPrimaryKey('{$pkColumns[0]}');";
            } else {
                $pkList = "'" . implode("', '", $pkColumns) . "'";
                $out[] = "            \$table->addPrimaryKey([{$pkList}]);";
            }
        }

        // Emit indexes (skip PK duplicates; non_unique=0 → unique, else → regular index)
        $pkColSet = array_flip($pkColumns);
        foreach ($indexes as $idx) {
            // Skip PK indexes (already handled)
            if (!empty($idx['primary'])) {
                continue;
            }
            // Skip single-column indexes that match a PK column
            $idxCols = is_array($idx['columns']) ? $idx['columns'] : [$idx['column'] ?? $idx['columns']];
            if (count($idxCols) === 1 && isset($pkColSet[$idxCols[0]])) {
                continue;
            }
            $colList = "'" . implode("', '", $idxCols) . "'";
            $idxName = $this->buildIndexName($idxCols);
            $out[] = "            \$table->addIndex('{$idxName}',[{$colList}]);";
        }

        $out[] = '';
        $out[] = "            \$table->setTableEngine(TableSchema::ENGINE_INNODB);";
        $out[] = "        });";

        $this->out->writeln("  Schema: {$logicalName} (" . count($columns) . " columns)");

        return implode("\n", $out);
    }

    /**
     * Generate a single column line like: $table->varchar('title',255)->nullable()->default(null);
     *
     * @param array $keyColumns 被索引 / 主键引用的列名集合（键为列名）
     */
    protected function generateColumnLine(array $col, array $keyColumns = []): string
    {
        $name     = $col['name'];
        $method   = $col['method'];
        $length   = $col['length'] ?? null;
        $nullable = $col['nullable'] ?? false;
        $default  = $col['default'] ?? null;
        $autoInc  = !empty($col['auto_increment']);

        // MySQL 限制：TEXT 列不能建索引（无前缀长度），也不能带默认值。
        // 导出时对此类列自动降级为 varchar，保证生成的 DDL 在 MySQL 下可执行。
        if ($method === 'text' && (isset($keyColumns[$name]) || $default !== null)) {
            $method = 'varchar';
            $length = 255;
            if (is_string($default)) {
                $length = max($length, mb_strlen($default) + 10);
            }
        }

        // Build method call with arguments
        if ($length !== null) {
            $code = "\$table->{$method}('{$name}',{$length})";
        } else {
            $code = "\$table->{$method}('{$name}')";
        }

        // Modifiers
        $modifiers = [];

        if ($autoInc) {
            $modifiers[] = 'autoIncrement()';
        }

        // nullable + default: emit together
        if ($nullable && $default === null) {
            $modifiers[] = 'nullable()';
        } elseif ($nullable && $default !== null) {
            $modifiers[] = 'nullable()';
            $modifiers[] = 'default(' . $this->formatPhpValue($default) . ')';
        } elseif (!$nullable && $default !== null) {
            $modifiers[] = 'default(' . $this->formatPhpValue($default) . ')';
        }

        foreach ($modifiers as $m) {
            $code .= '->' . $m;
        }

        return $code . ';';
    }

    // ──────────────── CreateBaseData (data) generation ────────────────

    protected function buildCreateBaseDataCode(): string
    {
        $tables = $this->getAllTables();

        $lines = [];
        $lines[] = '<?php';
        $lines[] = '/*';
        $lines[] = ' * Auto-generated by: php console zapcms:UpdateBaseData -e';
        $lines[] = ' * Generated at: ' . date('Y-m-d H:i:s');
        $lines[] = ' *';
        $lines[] = ' * Contains base data for ALL database tables.';
        $lines[] = ' * To regenerate: php console zapcms:UpdateBaseData -e';
        $lines[] = ' */';
        $lines[] = '';
        $lines[] = 'namespace zapcms\support;';
        $lines[] = '';
        $lines[] = 'use zap\\db\\AlertTable;';
        $lines[] = 'use zap\\db\\Schema;';
        $lines[] = '';
        $lines[] = 'class CreateBaseData';
        $lines[] = '{';
        $lines[] = '    public static function install()';
        $lines[] = '    {';

        foreach ($tables as $tableInfo) {
            $lines[] = '';
            $lines[] = $this->exportTableData($tableInfo['full'], $tableInfo['logical']);
        }

        $lines[] = '    }';
        $lines[] = '}';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Export one table's data as Schema::alter() + batchInsert() code block.
     */
    protected function exportTableData(string $fullName, string $logicalName): string
    {
        // Runtime/sensitive tables must never be exported into base data:
        // admin_logs contains IPs, user agents and operation traces.
        if (in_array($logicalName, ['admin_logs'], true)) {
            $this->out->writeln("  Data:    {$logicalName} (skipped: runtime data)");
            return "        // Table `{$logicalName}` — skipped (runtime data)";
        }

        $pdo = DB::getPDO();
        $quoted = $pdo->quoteTable($logicalName);

        try {
            $columns = $this->getTableColumnNames($fullName);
            $rows = $pdo->query("SELECT * FROM {$quoted}")->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return "        // Table `{$logicalName}` — skipped: {$e->getMessage()}";
        }

        if (empty($rows)) {
            return "        // Table `{$logicalName}` — no data";
        }

        $out = [];

        $out[] = "        Schema::alter('{$logicalName}',function (AlertTable \$table){";
        $out[] = "            \$table->batchInsert([";

        foreach ($rows as $row) {
            $vals = [];
            foreach ($columns as $col) {
                $quotedCol = "'{$col}'";
                $vals[] = "{$quotedCol} => " . $this->formatPhpValue($row[$col] ?? null);
            }
            $out[] = "                [" . implode(", ", $vals) . "],";
        }

        $out[] = "            ], true);";
        $out[] = "        });";

        $this->out->writeln("  Data:    {$logicalName} (" . count($rows) . " rows)");

        return implode("\n", $out);
    }

    // ──────────────── Table discovery ────────────────

    /**
     * Get all user tables from the database (across MySQL/SQLite/PostgreSQL).
     */
    protected function getAllTables(): array
    {
        $pdo = DB::getPDO();
        $driver = $this->getDriver();

        if ($driver === 'sqlite') {
            $rows = $pdo->query(
                "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
            )->fetchAll(\PDO::FETCH_ASSOC);
            $tableNames = array_column($rows, 'name');
        } elseif ($driver === 'pgsql') {
            $rows = $pdo->query(
                "SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname NOT IN ('pg_catalog','information_schema') ORDER BY tablename"
            )->fetchAll(\PDO::FETCH_ASSOC);
            $tableNames = array_column($rows, 'tablename');
        } else {
            // mysql / mariadb
            $rows = $pdo->query("SHOW TABLES")->fetchAll(\PDO::FETCH_NUM);
            $tableNames = array_column($rows, 0);
        }

        $result = [];
        foreach ($tableNames as $fullName) {
            $logicalName = $pdo->unPrefixTable($fullName);
            $result[] = [
                'full'    => $fullName,
                'logical' => $logicalName,
            ];
        }

        return $result;
    }

    // ──────────────── Column detail (for schema generation) ────────────────

    /**
     * Get detailed column info: name, type, length, nullable, default, auto_increment, primary.
     */
    protected function getColumnsDetail(string $fullTableName): array
    {
        $pdo    = DB::getPDO();
        $driver = $this->getDriver();

        if ($driver === 'sqlite') {
            return $this->getColumnsDetailSqlite($pdo, $fullTableName);
        }

        if ($driver === 'pgsql') {
            return $this->getColumnsDetailPgsql($pdo, $fullTableName);
        }

        // mysql / mariadb
        return $this->getColumnsDetailMysql($pdo, $fullTableName);
    }

    protected function getColumnsDetailMysql($pdo, string $fullName): array
    {
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$fullName}`");
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $cols = [];
        foreach ($rows as $r) {
            $parsed = $this->parseMysqlType($r['Type']);
            $cols[] = [
                'name'           => $r['Field'],
                'type'           => $parsed['type'],
                'method'         => $parsed['method'],
                'length'         => $parsed['length'],
                'nullable'       => ($r['Null'] === 'YES'),
                'default'        => $this->parseDefault($r['Default'], $parsed['type']),
                'auto_increment' => (stripos($r['Extra'], 'auto_increment') !== false),
                'primary'        => ($r['Key'] === 'PRI'),
            ];
        }
        return $cols;
    }

    protected function getColumnsDetailSqlite($pdo, string $fullName): array
    {
        $stmt = $pdo->query("PRAGMA table_info('{$fullName}')");
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // PRAGMA table_info only tells us which columns are part of the PK,
        // NOT whether AUTOINCREMENT was declared. Parse sqlite_master instead.
        $hasAutoincrement = false;
        try {
            $createStmt = $pdo->query(
                "SELECT sql FROM sqlite_master WHERE type='table' AND name='{$fullName}'"
            )->fetchColumn();
            if ($createStmt && stripos($createStmt, 'AUTOINCREMENT') !== false) {
                $hasAutoincrement = true;
            }
        } catch (\Throwable $e) {
            // fallback: assume no explicit AUTOINCREMENT
        }

        $cols = [];
        foreach ($rows as $r) {
            $parsed = $this->parseSqliteType($r['type']);
            $nullOk = ($r['notnull'] == 0);

            // AUTOINCREMENT only exists on a SINGLE INTEGER PRIMARY KEY column.
            // Composite PKs and non-integer PKs never have AUTOINCREMENT.
            $autoInc = false;
            if ($hasAutoincrement && !empty($r['pk']) && $parsed['type'] === 'int') {
                $autoInc = ($r['pk'] == 1);
            }

            $cols[] = [
                'name'           => $r['name'],
                'type'           => $parsed['type'],
                'method'         => $parsed['method'],
                'length'         => $parsed['length'],
                'nullable'       => $nullOk,
                'default'        => $this->parseDefault($r['dflt_value'], $parsed['type']),
                'auto_increment' => $autoInc,
                'primary'        => !empty($r['pk']),
            ];
        }
        return $cols;
    }

    protected function getColumnsDetailPgsql($pdo, string $fullName): array
    {
        $stmt = $pdo->query(
            "SELECT c.column_name, c.udt_name, c.character_maximum_length, c.is_nullable, c.column_default,"
            . " (pk.constraint_type = 'PRIMARY KEY') AS is_pk,"
            . " c.is_identity"
            . " FROM information_schema.columns c"
            . " LEFT JOIN ("
            . "   SELECT ku.column_name, tc.constraint_type"
            . "   FROM information_schema.table_constraints tc"
            . "   JOIN information_schema.key_column_usage ku"
            . "     ON ku.constraint_name = tc.constraint_name"
            . "   WHERE tc.table_name = '{$fullName}' AND tc.constraint_type = 'PRIMARY KEY'"
            . " ) pk ON c.column_name = pk.column_name"
            . " WHERE c.table_name = '{$fullName}'"
            . " ORDER BY c.ordinal_position"
        );
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $cols = [];
        foreach ($rows as $r) {
            $parsed = $this->parsePgsqlType($r['udt_name'], $r['character_maximum_length']);
            $cols[] = [
                'name'           => $r['column_name'],
                'type'           => $parsed['type'],
                'method'         => $parsed['method'],
                'length'         => $parsed['length'],
                'nullable'       => ($r['is_nullable'] === 'YES'),
                'default'        => $this->parseDefault($r['column_default'], $parsed['type']),
                'auto_increment' => ($r['is_identity'] === 'YES'),
                'primary'        => !empty($r['is_pk']),
            ];
        }
        return $cols;
    }

    // ──────────────── Index info ────────────────

    protected function getIndexes(string $fullTableName): array
    {
        $pdo    = DB::getPDO();
        $driver = $this->getDriver();

        if ($driver === 'sqlite') {
            return $this->getIndexesSqlite($pdo, $fullTableName);
        }

        if ($driver === 'pgsql') {
            return $this->getIndexesPgsql($pdo, $fullTableName);
        }

        return $this->getIndexesMysql($pdo, $fullTableName);
    }

    protected function getIndexesMysql($pdo, string $fullName): array
    {
        try {
            $stmt = $pdo->query("SHOW INDEX FROM `{$fullName}`");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }

        $grouped = [];
        foreach ($rows as $r) {
            $keyName = $r['Key_name'];
            if (!isset($grouped[$keyName])) {
                $grouped[$keyName] = [
                    'name'    => $keyName,
                    'unique'  => ($r['Non_unique'] == 0),
                    'primary' => ($keyName === 'PRIMARY'),
                    'columns' => [],
                ];
            }
            $grouped[$keyName]['columns'][] = $r['Column_name'];
        }

        return array_values($grouped);
    }

    protected function getIndexesSqlite($pdo, string $fullName): array
    {
        try {
            $idxList = $pdo->query("PRAGMA index_list('{$fullName}')")->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }

        $result = [];
        foreach ($idxList as $idx) {
            try {
                $infoRows = $pdo->query("PRAGMA index_info('{$idx['name']}')")->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {
                continue;
            }

            $columns = array_column($infoRows, 'name');
            $isPk = (stripos($idx['name'], 'sqlite_autoindex') !== false);

            $result[] = [
                'name'    => $idx['name'],
                'unique'  => !empty($idx['unique']),
                'primary' => $isPk,
                'columns' => $columns,
            ];
        }

        return $result;
    }

    protected function getIndexesPgsql($pdo, string $fullName): array
    {
        try {
            $stmt = $pdo->query(
                "SELECT i.relname AS index_name,"
                . " ix.indisunique AS is_unique,"
                . " ix.indisprimary AS is_primary"
                . " FROM pg_class t"
                . " JOIN pg_index ix ON t.oid = ix.indrelid"
                . " JOIN pg_class i ON i.oid = ix.indexrelid"
                . " WHERE t.relname = '{$fullName}'"
                . " ORDER BY i.relname"
            );
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }

        // For column names, use a second query
        // Simplification: just return index names with approximate info
        $result = [];
        foreach ($rows as $r) {
            $result[] = [
                'name'    => $r['index_name'],
                'unique'  => !empty($r['is_unique']),
                'primary' => !empty($r['is_primary']),
                'columns' => [$r['index_name']], // Pg: we approximate; columns require additional query
            ];
        }

        return $result;
    }

    // ──────────────── Column name only (for data export) ────────────────

    protected function getTableColumnNames(string $fullTableName): array
    {
        $pdo    = DB::getPDO();
        $driver = $this->getDriver();

        if ($driver === 'sqlite') {
            $stmt = $pdo->query("PRAGMA table_info('{$fullTableName}')");
            $cols = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $col) {
                $cols[] = $col['name'];
            }
            return $cols;
        }

        if ($driver === 'pgsql') {
            $stmt = $pdo->query(
                "SELECT column_name FROM information_schema.columns"
                . " WHERE table_name = '{$fullTableName}'"
                . " ORDER BY ordinal_position"
            );
            $cols = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $col) {
                $cols[] = $col['column_name'];
            }
            return $cols;
        }

        // mysql / mariadb
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$fullTableName}`");
        $cols = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $col) {
            $cols[] = $col['Field'];
        }
        return $cols;
    }

    // ──────────────── Type parsing ────────────────

    /**
     * Parse MySQL type string like "varchar(255)", "int(11)", "text", "decimal(10,2)".
     */
    protected function parseMysqlType(string $type): array
    {
        $type = trim($type);
        if (preg_match('/^(\w+)\s*\((.+)\)$/', $type, $m)) {
            $baseType = strtolower($m[1]);
            $params   = $m[2];

            // Extract first numeric parameter as length
            if (preg_match('/^(\d+)/', $params, $pm)) {
                $length = (int) $pm[1];
            } else {
                $length = null;
            }
        } else {
            $baseType = strtolower($type);
            $length   = null;
        }

        return $this->mapBaseType($baseType, $length);
    }

    protected function parseSqliteType(?string $type): array
    {
        $type = strtolower(trim($type ?? ''));
        $length = null;

        if (preg_match('/^(\w+)\s*\(\s*(\d+)\s*\)$/i', $type, $m)) {
            $baseType = strtolower($m[1]);
            $length   = (int) $m[2];
        } else {
            $baseType = $type;
        }

        return $this->mapBaseType($baseType, $length);
    }

    protected function parsePgsqlType(string $udtName, $charMaxLength): array
    {
        $baseType = strtolower($udtName);
        $length   = $charMaxLength ? (int) $charMaxLength : null;

        return $this->mapBaseType($baseType, $length);
    }

    /**
     * Map a database base type to TableSchema method.
     */
    protected function mapBaseType(string $baseType, $length): array
    {
        $intTypes = ['int', 'integer', 'bigint', 'mediumint', 'smallint', 'tinyint', 'int2', 'int4', 'int8', 'serial', 'bigserial', 'smallserial'];
        $varcharTypes = ['varchar', 'char', 'character', 'character varying', 'nvarchar', 'nchar', 'tinytext'];
        $textTypes = ['text', 'mediumtext', 'longtext', 'clob'];
        $decimalTypes = ['float', 'double', 'decimal', 'numeric', 'real', 'float4', 'float8', 'double precision', 'money'];
        $boolTypes = ['boolean', 'bool', 'bit'];

        // Map multi-column decimal types: keep length=null; we don't split (10,2) for simplicity
        if (in_array($baseType, $intTypes, true)) {
            return ['type' => 'int', 'method' => 'integer', 'length' => null];
        }

        if (in_array($baseType, $varcharTypes, true)) {
            $len = $length ?? 255;
            return ['type' => 'varchar', 'method' => 'varchar', 'length' => ($len > 0 ? $len : 255)];
        }

        if (in_array($baseType, $textTypes, true)) {
            return ['type' => 'text', 'method' => 'text', 'length' => null];
        }

        if (in_array($baseType, $decimalTypes, true)) {
            return ['type' => 'decimal', 'method' => 'decimal', 'length' => null];
        }

        if (in_array($baseType, $boolTypes, true)) {
            return ['type' => 'boolean', 'method' => 'boolean', 'length' => null];
        }

        // Fallback for date/time types (CMS uses int timestamps)
        if (in_array($baseType, ['datetime', 'timestamp', 'date', 'time', 'year', 'timestamptz', 'timetz'], true)) {
            return ['type' => 'int', 'method' => 'integer', 'length' => null];
        }

        if (in_array($baseType, ['json', 'jsonb'], true)) {
            return ['type' => 'text', 'method' => 'text', 'length' => null];
        }

        if (in_array($baseType, ['blob', 'longblob', 'mediumblob', 'tinyblob', 'bytea', 'binary', 'varbinary'], true)) {
            return ['type' => 'blob', 'method' => 'blob', 'length' => null];
        }

        if (in_array($baseType, ['enum', 'set'], true)) {
            return ['type' => 'varchar', 'method' => 'varchar', 'length' => 255];
        }

        // Unknown → text fallback
        return ['type' => 'text', 'method' => 'text', 'length' => null];
    }

    // ──────────────── Default value parsing ────────────────

    protected function parseDefault($rawDefault, string $dbType)
    {
        if ($rawDefault === null) {
            return null;
        }

        $raw = trim((string) $rawDefault);

        // SQLite: defaults are raw values; strings may be quoted with ' or "
        if (preg_match('/^"(.*)"$/s', $raw, $m)) {
            $raw = $m[1];
        }

        if (preg_match("/^'(.*)'$/s", $raw, $m)) {
            return $m[1];
        }

        // MySQL: CURRENT_TIMESTAMP etc.
        if (stripos($raw, 'CURRENT_TIMESTAMP') !== false) {
            return 'CURRENT_TIMESTAMP';
        }

        // Numeric
        if (is_numeric($raw)) {
            if (strpos($raw, '.') !== false) {
                return (float) $raw;
            }
            return (int) $raw;
        }

        // NULL literal
        if (strtoupper($raw) === 'NULL') {
            return null;
        }

        // PgSQL: default may include type cast suffix like 'value'::character varying
        if (preg_match("/^'(.*)'::/s", $raw, $m)) {
            return $m[1];
        }

        // PgSQL: nextval('...'::regclass) → skip as auto_increment handles it
        if (stripos($raw, 'nextval') !== false) {
            return null;
        }

        return $raw;
    }

    // ──────────────── Helpers ────────────────

    /**
     * Get the current database driver name.
     */
    /**
     * Generate a clean short index name from column list.
     * e.g. ['object_id'] → 'idx_object_id'
     *      ['category_id','status'] → 'idx_category_id_status'
     */
    protected function buildIndexName(array $columns): string
    {
        return 'idx_' . implode('_', $columns);
    }

    protected function getDriver(): string
    {
        return DB::getPDO()->driver;
    }

    /**
     * Format a PHP value for code generation.
     *
     * - NULL  → NULL
     * - int   → 123
     * - float → 1.5
     * - bool  → true / false
     * - string → single-quoted with proper escaping
     */
    protected function formatPhpValue($val): string
    {
        if ($val === null) {
            return 'NULL';
        }

        if (is_int($val) || is_float($val)) {
            return (string) $val;
        }

        if (is_bool($val)) {
            return $val ? 'true' : 'false';
        }

        // String — single quotes, escape backslash and single quote
        $escaped = str_replace(
            ["\\", "'"],
            ["\\\\", "\\'"],
            (string) $val
        );
        return "'{$escaped}'";
    }

    // ─────────────────────── Help ───────────────────────

    public function help(): int
    {
        $this->out->writeln("Update/resync/export base data defined in zapcms\support\CreateBaseData");
        $this->out->writeln("");
        $this->out->writeln("Options:");
        $this->out->writeln("  -f\tForce mode: clear known system config tables, then reinstall");
        $this->out->writeln("  -d\tDemo mode: also clear and reinstall demo content tables");
        $this->out->writeln("  -e\tExport mode: dump ALL DB tables → CreateTables.php + CreateBaseData.php");
        $this->out->writeln("    \t  php console zapcms:UpdateBaseData -e");
        $this->out->writeln("    \t  php console zapcms:UpdateBaseData -e /path/dir");
        $this->out->writeln("");
        $this->out->writeln("Usage:");
        $this->out->writeln("  php console zapcms:UpdateBaseData -f              # Update system config");
        $this->out->writeln("  php console zapcms:UpdateBaseData -f -d           # Update config + demo data");
        $this->out->writeln("  php console zapcms:UpdateBaseData -e              # Export schema + data classes");
        $this->out->writeln("  php console zapcms:UpdateBaseData -e /my/dir      # Export to custom directory");
        return self::SUCCESS;
    }
}
