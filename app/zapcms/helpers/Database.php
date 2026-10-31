<?php
/*
 * Copyright (c) 2025.  ZAP.CN  - ZAP CMS - All Rights Reserved
 * @author Allen
 * @email zapcms@zap.cn
 * @date 2025/5/7 16:56
 * @lastModified 2025/5/7 16:56
 *
 */

namespace zapcms\helpers;

use zap\DB;

class Database
{
    /** 单次写入批量插入的行数 */
    const BATCH_SIZE = 500;

    /** 默认保留的备份文件数 */
    const MAX_BACKUPS = 7;

    /** 恢复时每次执行的行数 */
    const RESTORE_CHUNK = 200;

    /**
     * 获取数据库连接信息
     * @return array{conn_name: string, dbname: string, driver: string}
     */
    protected static function getConnectionInfo(): array
    {
        $conn_name = config('database.default');
        $connConfig = config("database.connections.{$conn_name}");
        $dbname = $connConfig['dbname'] ?? $connConfig['database'] ?? $connConfig['path'] ?? 'zapcms';
        $driver = $connConfig['driver'] ?? 'mysql';
        if ($driver === 'sqlite') {
            $dbname = basename($dbname, '.sqlite') ?: 'zapcms';
        }
        return compact('conn_name', 'dbname', 'driver');
    }

    /**
     * 获取数据库表列表
     * @param string $driver
     * @return array
     */
    protected static function getTables(string $driver): array
    {
        if ($driver === 'sqlite') {
            $tableRows = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
            return array_column($tableRows, 'name');
        } elseif ($driver === 'pgsql') {
            $tableRows = DB::select(
                "SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname NOT IN ('pg_catalog','information_schema')"
            );
            return array_column($tableRows, 'tablename');
        } else {
            $tableRows = DB::select("SHOW TABLES");
            return array_map('current', $tableRows);
        }
    }

    /**
     * 获取建表语句
     * @param string $driver
     * @param string $table
     * @return string
     */
    protected static function getCreateTableSQL(string $driver, string $table): string
    {
        if ($driver === 'sqlite') {
            $rows = DB::select("SELECT sql FROM sqlite_master WHERE type='table' AND name=:name", ['name' => $table]);
            return $rows[0]['sql'] ?? '';
        } elseif ($driver === 'pgsql') {
            $rows = DB::select("SELECT 'CREATE TABLE ' || quote_ident(:table) || ' (' || "
                . "string_agg(column_def, ', ') || ');' AS ddl "
                . "FROM (SELECT column_name || ' ' || data_type || "
                . "CASE WHEN character_maximum_length IS NOT NULL THEN '(' || character_maximum_length || ')' ELSE '' END "
                . "|| CASE WHEN is_nullable='NO' THEN ' NOT NULL' ELSE '' END AS column_def "
                . "FROM information_schema.columns WHERE table_name=:table ORDER BY ordinal_position) t",
                ['table' => $table]);
            return $rows[0]['ddl'] ?? '';
        } else {
            $rows = DB::select("SHOW CREATE TABLE `{$table}`");
            return $rows[0]['Create Table'] ?? '';
        }
    }

    /**
     * 获取表的行数
     * @param string $table
     * @return int
     */
    protected static function getTableRowCount(string $table): int
    {
        $rows = DB::select("SELECT COUNT(*) AS cnt FROM `{$table}`");
        return (int)($rows[0]['cnt'] ?? 0);
    }

    /**
     * 转义 SQL 值
     * @param mixed $value
     * @return string
     */
    protected static function escapeSQL($value): string
    {
        if (is_null($value)) {
            return 'NULL';
        }
        if (is_numeric($value) && !is_string($value)) {
            return (string)$value;
        }
        return "'" . addslashes((string)$value) . "'";
    }

    /**
     * 准备备份目录
     * @return string 备份目录路径
     */
    protected static function prepareBackupDir(): string
    {
        $backupDir = var_path('backups/sql');
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        return $backupDir;
    }

    /**
     * 轮转清理旧备份（保留最近 N 个）
     * @param string $backupDir
     * @param string $dbname
     * @param string $extension
     */
    protected static function rotateBackups(string $backupDir, string $dbname, string $extension): void
    {
        $pattern = $backupDir . '/' . preg_quote($dbname, '/') . '_*\d{14}\.' . preg_quote($extension, '/');
        $files = glob($pattern);
        if ($files === false || count($files) <= static::MAX_BACKUPS) {
            return;
        }
        // 按文件名排序（时间戳在文件名中，自然排序即可）
        sort($files, SORT_STRING);
        $toDelete = array_slice($files, 0, count($files) - static::MAX_BACKUPS);
        foreach ($toDelete as $file) {
            @unlink($file);
        }
    }

    /**
     * 写入表结构到备份文件
     * @param resource $fp
     * @param string $driver
     * @param string $table
     */
    protected static function writeTableStructure($fp, string $driver, string $table): void
    {
        $structure = static::getCreateTableSQL($driver, $table);
        if (empty($structure)) {
            return;
        }
        fwrite($fp, "-- --------------------------------------------------------\n");
        fwrite($fp, "--  Table structure for `{$table}`\n");
        fwrite($fp, "-- --------------------------------------------------------\n");
        fwrite($fp, "DROP TABLE IF EXISTS `{$table}`;\n");
        fwrite($fp, $structure . ";\n\n");
    }

    /**
     * 分块写入表数据到备份文件
     * @param resource $fp
     * @param string $table
     * @return int 导出的行数
     */
    protected static function writeTableData($fp, string $table): int
    {
        $totalRows = static::getTableRowCount($table);
        if ($totalRows === 0) {
            return 0;
        }

        fwrite($fp, "-- --------------------------------------------------------\n");
        fwrite($fp, "--  Data for table `{$table}` (~{$totalRows} rows)\n");
        fwrite($fp, "-- --------------------------------------------------------\n");

        $exported = 0;
        $batch = [];
        $offset = 0;

        while ($offset < $totalRows) {
            $rows = DB::select("SELECT * FROM `{$table}` LIMIT " . static::BATCH_SIZE . " OFFSET {$offset}");
            foreach ($rows as $row) {
                $values = array_map([static::class, 'escapeSQL'], array_values($row));
                $batch[] = "INSERT INTO `{$table}` VALUES (" . implode(', ', $values) . ");";
                $exported++;
            }
            if (!empty($batch)) {
                fwrite($fp, implode("\n", $batch) . "\n");
                $batch = [];
            }
            $offset += static::BATCH_SIZE;
        }

        // 每批已在循环内落盘并清空 $batch，此处无需再写一茬（原代码为死分支）

        fwrite($fp, "\n");
        return $exported;
    }

    /**
     * 生成备份元数据
     * @param array $tables
     * @param array $rowCounts
     * @return array
     */
    protected static function buildMetadata(array $tables, array $rowCounts): array
    {
        return [
            'version'      => '1.0',
            'generated_at' => date('Y-m-d H:i:s'),
            'tables'       => $tables,
            'row_counts'   => $rowCounts,
            'total_tables' => count($tables),
            'total_rows'   => array_sum($rowCounts),
        ];
    }

    // ==================== 公开 API ====================

    /**
     * 备份数据库表结构及数据
     * @param array|null $onlyTables 指定备份的表，null 表示全部
     * @param bool       $compress   是否 gzip 压缩
     * @param callable|null $progressCallback 进度回调 function(string $phase, string $table, int $done, int $total): void
     * @return string|false  成功返回备份文件路径，失败返回 false
     */
    public static function backup(?array $onlyTables = null, bool $compress = false, ?callable $progressCallback = null)
    {
        ['conn_name' => $conn_name, 'dbname' => $dbname, 'driver' => $driver] = static::getConnectionInfo();

        try {
            $tables = static::getTables($driver);

            // 过滤指定表
            if ($onlyTables !== null) {
                $tables = array_intersect($tables, $onlyTables);
            }
            if (empty($tables)) {
                throw new \RuntimeException('没有找到可备份的数据表');
            }

            $backupDir = static::prepareBackupDir();
            $timestamp = date('YmdHis');
            $baseName  = $dbname . '_' . $timestamp;
            $sqlFile   = $backupDir . '/' . $baseName . '.sql';

            $fp = fopen($sqlFile, 'w');
            if (!$fp) {
                throw new \RuntimeException("无法创建备份文件: {$sqlFile}");
            }

            // 文件头
            fwrite($fp, "-- ZAP CMS Database Backup\n");
            fwrite($fp, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
            fwrite($fp, "-- Database: {$dbname}\n");
            fwrite($fp, "-- Driver: {$driver}\n");
            fwrite($fp, "-- Tables: " . implode(', ', $tables) . "\n");
            fwrite($fp, "-- --------------------------------------------------------\n\n");
            fwrite($fp, "SET FOREIGN_KEY_CHECKS=0;\n");
            fwrite($fp, "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

            $totalTables = count($tables);
            $rowCounts   = [];

            foreach ($tables as $index => $table) {
                // 结构备份
                if ($progressCallback) {
                    $progressCallback('structure', $table, $index + 1, $totalTables);
                }
                static::writeTableStructure($fp, $driver, $table);

                // 数据备份（分块）
                if ($progressCallback) {
                    $progressCallback('data', $table, $index + 1, $totalTables);
                }
                $rowCounts[$table] = static::writeTableData($fp, $table);
            }

            fwrite($fp, "SET FOREIGN_KEY_CHECKS=1;\n");
            fclose($fp);

            // 写入元数据
            $meta = static::buildMetadata($tables, $rowCounts);
            file_put_contents($backupDir . '/' . $baseName . '.json', json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $finalFile = $sqlFile;

            // Gzip 压缩
            if ($compress) {
                $gzFile = $sqlFile . '.gz';
                $fpIn   = fopen($sqlFile, 'rb');
                $fpOut  = gzopen($gzFile, 'wb9');
                if ($fpIn && $fpOut) {
                    stream_copy_to_stream($fpIn, $fpOut);
                    gzclose($fpOut);
                    fclose($fpIn);
                    @unlink($sqlFile);
                    $finalFile = $gzFile;
                }
            }

            // 轮转清理旧备份
            $ext = $compress ? 'sql.gz' : 'sql';
            static::rotateBackups($backupDir, $dbname, $ext);

            return $finalFile;
        } catch (\Exception $e) {
            trigger_error('数据库备份失败: ' . $e->getMessage(), E_USER_WARNING);
            return false;
        }
    }

    /**
     * 仅备份数据（不含表结构）
     * @param array|null $onlyTables 指定备份的表
     * @param bool       $compress   是否 gzip 压缩
     * @return string|false
     */
    public static function backupData(?array $onlyTables = null, bool $compress = false)
    {
        ['conn_name' => $conn_name, 'dbname' => $dbname, 'driver' => $driver] = static::getConnectionInfo();

        try {
            $tables = static::getTables($driver);
            if ($onlyTables !== null) {
                $tables = array_intersect($tables, $onlyTables);
            }
            if (empty($tables)) {
                throw new \RuntimeException('没有找到可备份的数据表');
            }

            $backupDir = static::prepareBackupDir();
            $timestamp = date('YmdHis');
            $baseName  = $dbname . '_data_' . $timestamp;
            $sqlFile   = $backupDir . '/' . $baseName . '.sql';

            $fp = fopen($sqlFile, 'w');
            if (!$fp) {
                throw new \RuntimeException("无法创建备份文件: {$sqlFile}");
            }

            fwrite($fp, "-- ZAP CMS Data-Only Backup\n");
            fwrite($fp, "-- Generated: " . date('Y-m-d H:i:s') . "\n\n");
            fwrite($fp, "SET FOREIGN_KEY_CHECKS=0;\n\n");

            foreach ($tables as $table) {
                fwrite($fp, "TRUNCATE TABLE `{$table}`;\n");
                static::writeTableData($fp, $table);
            }

            fwrite($fp, "SET FOREIGN_KEY_CHECKS=1;\n");
            fclose($fp);

            $finalFile = $sqlFile;
            if ($compress) {
                $gzFile = $sqlFile . '.gz';
                $fpIn   = fopen($sqlFile, 'rb');
                $fpOut  = gzopen($gzFile, 'wb9');
                if ($fpIn && $fpOut) {
                    stream_copy_to_stream($fpIn, $fpOut);
                    gzclose($fpOut);
                    fclose($fpIn);
                    @unlink($sqlFile);
                    $finalFile = $gzFile;
                }
            }

            return $finalFile;
        } catch (\Exception $e) {
            trigger_error('数据备份失败: ' . $e->getMessage(), E_USER_WARNING);
            return false;
        }
    }

    /**
     * 还原数据库（从 SQL 备份文件）
     * @param string $filePath 备份 SQL 文件路径（支持 .sql 或 .sql.gz）
     * @param callable|null $progressCallback function(int $lineNum, int $totalLines): void
     * @return bool
     */
    public static function restore(string $filePath, ?callable $progressCallback = null): bool
    {
        if (!file_exists($filePath)) {
            trigger_error("备份文件不存在: {$filePath}", E_USER_WARNING);
            return false;
        }

        try {
            // 解压 gzip
            $isGzip = (pathinfo($filePath, PATHINFO_EXTENSION) === 'gz');
            if ($isGzip) {
                $fh = gzopen($filePath, 'rb');
                $sql = '';
                while (!gzeof($fh)) {
                    $sql .= gzread($fh, 8192);
                }
                gzclose($fh);
            } else {
                $sql = file_get_contents($filePath);
            }

            if (empty($sql)) {
                throw new \RuntimeException("备份文件内容为空");
            }

            // 解析 SQL 语句
            $statements = static::parseSQL($sql);

            $totalLines = count($statements);
            if ($progressCallback) {
                $progressCallback(0, $totalLines);
            }

            foreach (array_chunk($statements, static::RESTORE_CHUNK) as $chunkIndex => $chunk) {
                DB::beginTransaction();
                try {
                    foreach ($chunk as $i => $stmt) {
                        DB::statement($stmt);
                        if ($progressCallback) {
                            $progressCallback($chunkIndex * static::RESTORE_CHUNK + $i + 1, $totalLines);
                        }
                    }
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    throw $e;
                }
            }

            return true;
        } catch (\Exception $e) {
            trigger_error('数据库还原失败: ' . $e->getMessage(), E_USER_WARNING);
            if (isset($chunk)) {
                try { DB::rollBack(); } catch (\Exception $ignore) {}
            }
            return false;
        }
    }

    /**
     * 仅还原数据（跳过建表/删表语句）
     * @param string $filePath 备份文件路径
     * @param callable|null $progressCallback
     * @return bool
     */
    public static function restoreData(string $filePath, ?callable $progressCallback = null): bool
    {
        if (!file_exists($filePath)) {
            trigger_error("备份文件不存在: {$filePath}", E_USER_WARNING);
            return false;
        }

        try {
            $isGzip = (pathinfo($filePath, PATHINFO_EXTENSION) === 'gz');
            if ($isGzip) {
                $fh = gzopen($filePath, 'rb');
                $sql = '';
                while (!gzeof($fh)) {
                    $sql .= gzread($fh, 8192);
                }
                gzclose($fh);
            } else {
                $sql = file_get_contents($filePath);
            }

            if (empty($sql)) {
                throw new \RuntimeException("备份文件内容为空");
            }

            // 只保留 INSERT 和 TRUNCATE 语句
            $statements = static::parseSQL($sql);
            $dataStatements = array_filter($statements, function ($stmt) {
                $trimmed = ltrim($stmt);
                return stripos($trimmed, 'INSERT INTO') === 0
                    || stripos($trimmed, 'TRUNCATE TABLE') === 0
                    || stripos($trimmed, 'SET FOREIGN_KEY_CHECKS') === 0;
            });
            $statements = array_values($dataStatements);

            $totalLines = count($statements);
            if ($progressCallback) {
                $progressCallback(0, $totalLines);
            }

            foreach (array_chunk($statements, static::RESTORE_CHUNK) as $chunkIndex => $chunk) {
                DB::beginTransaction();
                try {
                    foreach ($chunk as $i => $stmt) {
                        DB::statement($stmt);
                        if ($progressCallback) {
                            $progressCallback($chunkIndex * static::RESTORE_CHUNK + $i + 1, $totalLines);
                        }
                    }
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    throw $e;
                }
            }

            return true;
        } catch (\Exception $e) {
            trigger_error('数据还原失败: ' . $e->getMessage(), E_USER_WARNING);
            if (isset($chunk)) {
                try { DB::rollBack(); } catch (\Exception $ignore) {}
            }
            return false;
        }
    }

    /**
     * 解析 SQL 文件中的语句列表
     * @param string $sql
     * @return array
     */
    protected static function parseSQL(string $sql): array
    {
        $statements = [];
        $current    = '';
        $inString   = false;
        $stringChar = '';
        $len        = strlen($sql);

        for ($i = 0; $i < $len; $i++) {
            $char = $sql[$i];

            if ($inString) {
                $current .= $char;
                if ($char === $stringChar && ($i === 0 || $sql[$i - 1] !== '\\')) {
                    $inString = false;
                }
                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $inString   = true;
                $stringChar = $char;
                $current   .= $char;
                continue;
            }

            if ($char === ';') {
                $trimmed = trim($current);
                if (!empty($trimmed)) {
                    $statements[] = $trimmed;
                }
                $current = '';
                continue;
            }

            $current .= $char;
        }

        $trimmed = trim($current);
        if (!empty($trimmed)) {
            $statements[] = $trimmed;
        }

        return $statements;
    }

    /**
     * 列出所有备份文件
     * @return array [{file, size, time, tables, total_rows}, ...]
     */
    public static function listBackups(): array
    {
        ['dbname' => $dbname] = static::getConnectionInfo();
        $backupDir = var_path('backups/sql');

        if (!is_dir($backupDir)) {
            return [];
        }

        $pattern = $backupDir . '/' . preg_quote($dbname, '/') . '_*.*';
        $files   = glob($pattern) ?: [];
        $results = [];

        $sqlPattern = '/^' . preg_quote($dbname . '_', '/') . '(\d{14})\.(sql(?:\.gz)?)$/';
        foreach ($files as $file) {
            $baseName = basename($file);
            if (!preg_match($sqlPattern, $baseName, $m)) {
                continue;
            }

            $jsonFile = $backupDir . '/' . $dbname . '_' . $m[1] . '.json';
            $meta     = [];
            if (file_exists($jsonFile)) {
                $meta = json_decode(file_get_contents($jsonFile), true) ?: [];
            }

            $results[] = [
                'file'       => $file,
                'filename'   => $baseName,
                'size'       => filesize($file),
                'size_human' => static::formatBytes(filesize($file)),
                'time'       => date('Y-m-d H:i:s', filemtime($file)),
                'tables'     => $meta['tables'] ?? [],
                'total_rows' => $meta['total_rows'] ?? 0,
                'compressed' => (pathinfo($file, PATHINFO_EXTENSION) === 'gz'),
            ];
        }

        // 按时间倒序
        usort($results, function ($a, $b) {
            return strcmp($b['time'], $a['time']);
        });

        return $results;
    }

    /**
     * 删除指定备份文件
     * @param string $filename 文件名（不含路径）
     * @return bool
     */
    public static function deleteBackup(string $filename): bool
    {
        $backupDir = var_path('backups/sql');
        $sqlFile   = $backupDir . '/' . $filename;
        $deleted   = false;

        if (file_exists($sqlFile)) {
            $deleted = unlink($sqlFile);
        }

        // 同时删除对应元数据
        $jsonFile = preg_replace('/\.sql(\.gz)?$/', '.json', $sqlFile);
        if (file_exists($jsonFile)) {
            unlink($jsonFile);
        }

        return $deleted;
    }

    /**
     * 格式化字节数
     * @param int $bytes
     * @param int $precision
     * @return string
     */
    protected static function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow   = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow   = min($pow, count($units) - 1);
        return round($bytes / (1024 ** $pow), $precision) . ' ' . $units[$pow];
    }
}