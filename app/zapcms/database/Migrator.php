<?php

namespace zapcms\database;

use zap\DB;
use zap\db\Schema;
use zap\db\TableSchema;

/**
 * 数据库迁移执行器。
 *
 * 迁移记录保存在 migrations 表（自动创建），按文件名顺序执行未执行过的迁移。
 * 系统升级（ZapPackageManager::applySystemUpdate）与安装流程都会调用它。
 */
class Migrator
{
    /**
     * 迁移记录表名（不含表前缀）。
     */
    public const TABLE = 'migrations';

    /**
     * 迁移文件目录。
     */
    protected string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?: base_path('app/zapcms/migrations');
    }

    /**
     * 迁移目录。
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * 扫描迁移文件。
     *
     * @return array<string, string> [迁移名 => 文件路径]，按名称升序
     */
    public function files(): array
    {
        if (!is_dir($this->path)) {
            return [];
        }

        $files = [];

        foreach (glob($this->path . '/*.php') ?: [] as $file) {
            $files[basename($file, '.php')] = $file;
        }

        ksort($files, SORT_STRING);

        return $files;
    }

    /**
     * 已执行的迁移名。
     *
     * @return string[]
     */
    public function executed(): array
    {
        if (!Schema::hasTable(self::TABLE)) {
            return [];
        }

        $rows = DB::table(self::TABLE)->orderBy('id asc')->get();

        return array_column($rows, 'migration');
    }

    /**
     * 待执行的迁移。
     *
     * @return array<string, string>
     */
    public function pending(): array
    {
        $executed = $this->executed();
        $pending  = [];

        foreach ($this->files() as $name => $file) {
            if (!in_array($name, $executed, true)) {
                $pending[$name] = $file;
            }
        }

        return $pending;
    }

    /**
     * 迁移状态。
     *
     * @return array{files: string[], executed: string[], pending: string[]}
     */
    public function status(): array
    {
        return [
            'files'    => array_keys($this->files()),
            'executed' => $this->executed(),
            'pending'  => array_keys($this->pending()),
        ];
    }

    /**
     * 执行所有未执行的迁移。
     *
     * 单条迁移失败时立即停止，已成功的记录保留，避免下次重复执行。
     *
     * @param callable|null $log 日志回调，接收一行文本
     *
     * @return array{ran: string[], error: string|null, batch: int}
     */
    public function run(?callable $log = null): array
    {
        $this->ensureRepository();

        $batch = $this->nextBatch();
        $ran   = [];
        $error = null;

        foreach ($this->pending() as $name => $file) {
            try {
                $migration = $this->resolve($file);
                $migration->up();
                $this->record($name, $batch);

                $ran[] = $name;

                if ($log !== null) {
                    $log("[OK] {$name}");
                }
            } catch (\Throwable $e) {
                $error = $name . ': ' . $e->getMessage();

                if ($log !== null) {
                    $log("[FAIL] {$error}");
                }

                break;
            }
        }

        return ['ran' => $ran, 'error' => $error, 'batch' => $batch];
    }

    /**
     * 把当前所有迁移文件标记为已执行（不执行）。
     *
     * 用于全新安装：安装向导已按最新结构建表，历史迁移无需再跑。
     *
     * @return int 新标记的数量
     */
    public function markAllAsRun(): int
    {
        $this->ensureRepository();

        $executed = $this->executed();
        $batch    = $this->nextBatch();
        $count    = 0;

        foreach (array_keys($this->files()) as $name) {
            if (in_array($name, $executed, true)) {
                continue;
            }

            $this->record($name, $batch);
            $count++;
        }

        return $count;
    }

    /**
     * 确保迁移记录表存在。
     */
    public function ensureRepository(): void
    {
        if (Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::create(self::TABLE, function (TableSchema $table) {
            $table->integer('id')->autoIncrement();
            $table->varchar('migration', 191);
            $table->integer('batch')->default('1');
            $table->integer('executed_at')->default('0');

            $table->addPrimaryKey('id');
            $table->addIndex('idx_migration', ['migration']);

            $table->setTableEngine(TableSchema::ENGINE_INNODB);
        });
    }

    /**
     * 加载迁移文件，必须返回 Migration 实例。
     */
    protected function resolve(string $file): Migration
    {
        $migration = require $file;

        if (!$migration instanceof Migration) {
            throw new \RuntimeException('迁移文件必须返回 Migration 实例');
        }

        return $migration;
    }

    /**
     * 写入一条执行记录。
     */
    protected function record(string $name, int $batch): void
    {
        DB::table(self::TABLE)->insert([
            'migration'   => $name,
            'batch'       => $batch,
            'executed_at' => time(),
        ]);
    }

    /**
     * 下一批 batch 号。
     */
    protected function nextBatch(): int
    {
        if (!Schema::hasTable(self::TABLE)) {
            return 1;
        }

        $rows = DB::table(self::TABLE)->select('batch')->orderBy('batch desc')->limit(1)->get();

        return (int)($rows[0]['batch'] ?? 0) + 1;
    }
}
