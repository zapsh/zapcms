<?php
/*
 * Copyright (c) 2025.  ZAP.CN  - ZAP CMS - All Rights Reserved
 * @author Allen
 * @email zapcms@zap.cn
 * @date 2025/5/6 15:25
 * @lastModified 2025/5/6 15:25
 *
 */

namespace zapcms\support;

use zap\DB;

class SysInfo
{
    /**
     * 获取全部数据表名
     *
     * 统一返回「纯表名字符串数组」，无论哪种数据库驱动都一致，
     * 避免调用方再去判断是数组还是标量。
     *
     * @return string[]
     */
    public static function getDatabaseTableNames(): array
    {
        if (DB::connection()->driver === 'sqlite') {
            $tables = DB::select(
                "SELECT name FROM sqlite_master WHERE type ='table' AND name NOT LIKE 'sqlite_%'",
                [],
                FETCH_COLUMN
            );
        } else {
            $tables = DB::select("SHOW TABLES", [], FETCH_COLUMN);
        }

        return array_values(array_filter(array_map('strval', (array) $tables)));
    }
}