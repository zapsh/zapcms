<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS - All Rights Reserved
 * @author Allen
 * @email zapcms@zap.cn
 * @date 2023/12/27 上午11:26
 * @lastModified 2023/12/14 上午11:55
 *
 */

namespace zapcms\services;


use zap\DB;

class Option
{
    public static function update(string $option_name, string $option_value, $sort_order = null, $autoload = null){
        $data = ['option_value'=>$option_value];
        is_null($sort_order) ?: $data['sort_order'] = $sort_order;
        is_null($autoload) ?: $data['autoload'] = $autoload;
        return DB::update('options',$data,['option_name'=>$option_name]);
    }

    /**
     * 插入或更新（upsert）：依赖 options.option_name 唯一索引（uk_option_name），
     * 命中冲突时按 $duplicate 更新。底层走 ZPDO::upsert：
     *  - MySQL/MariaDB：INSERT ... ON DUPLICATE KEY UPDATE
     *  - SQLite 3.24+  ：INSERT ... ON CONFLICT(option_name) DO UPDATE SET col=excluded.col
     *  - PostgreSQL    ：INSERT ... ON CONFLICT(option_name) DO UPDATE SET
     *
     * 相比 REPLACE INTO，不会删除旧行再插入（避免 rowid 变化、外键 ON DELETE 触发、
     * 触发器误触发等问题），也不会因缺少唯一键而产生重复行（如多条 mod.installed.tools）。
     *
     * @return int 受影响行数
     */
    public static function upsert(string $option_name, string $option_value, $sort_order = null, $autoload = null)
    {
        $data = [
            'option_name'  => $option_name,
            'option_value' => $option_value,
        ];
        is_null($sort_order) ?: $data['sort_order'] = $sort_order;
        is_null($autoload) ?: $data['autoload']    = $autoload;

        // 冲突时更新的值：option_value 必更新，sort_order / autoload 仅在显式传入时更新
        $duplicate = ['option_value' => $option_value];
        is_null($sort_order) ?: $duplicate['sort_order'] = $sort_order;
        is_null($autoload) ?: $duplicate['autoload']    = $autoload;

        return DB::upsert('options', $data, $duplicate, ['option_name']);
    }

    public static function replace(string $option_name, string $option_value, $sort_order = null, $autoload = null){
        return self::upsert($option_name, $option_value, $sort_order, $autoload);
    }

    public static function add(string $option_name, string $option_value, $sort_order = null, $autoload = null){
        return self::upsert($option_name, $option_value, $sort_order, $autoload);
    }

    public static function addArray(array $options, $sort_order = null, $autoload = null){
        foreach ($options as $option_name => $option_value){
            self::upsert($option_name,$option_value,$sort_order,$autoload);
        }
    }

    public static function remove(string $option_name){
        return DB::delete('options','option_name=:name',['name'=>$option_name]);
    }

    /**
     * 批量删除多个 option（按 option_name 列表）
     *
     * @param string[] $names
     * @return int 受影响行数
     */
    public static function removeMany(array $names): int
    {
        $names = array_values(array_filter($names, fn($n) => is_string($n) && $n !== ''));
        if (empty($names)) {
            return 0;
        }
        $placeholders = [];
        $params       = [];
        foreach ($names as $i => $name) {
            $key            = 'name' . $i;
            $placeholders[] = ':' . $key;
            $params[$key]   = $name;
        }
        return DB::delete('options', 'option_name IN (' . implode(',', $placeholders) . ')', $params);
    }

    /**
     * 按正则删除 option（如删除某前缀下的全部键：^mod.installed）
     *
     * @return int 受影响行数
     */
    public static function removeByRegex(string $pattern): int
    {
        return DB::delete('options', 'option_name REGEXP :pat', ['pat' => $pattern]);
    }


    public static function get(string $option_name, $default = null){
        $rows = DB::select('select option_value from {options} where option_name=:option_name',
            ['option_name'=>$option_name]);
        if(empty($rows)){
            return $default;
        }
        return $rows[0]['option_value'];
    }

    public static function getArray(string|array $option_name, string $type = '='): array
    {
        if(is_array($option_name) && $type == 'REGEXP'){
            $option_name = join('|',array_filter($option_name,'is_scalar'));
            $option_name = "^({$option_name}).*";
        }else if(is_string($option_name) && $type == 'REGEXP'){
            $option_name = "^({$option_name}).*";
        }

        $query = DB::table('options')->select('option_name,option_value')
            ->orderBy('sort_order desc');
        if (is_array($option_name) && ($type === '=' || $type === '')) {
            // 数组 + 等值 = IN 查询。Query::where() 不支持数组绑定，直接传数组
            // 会生成 `option_name = ?` 并绑定数组，触发 "Array to string conversion"
            $query->whereIn('option_name', $option_name);
        } else {
            $query->where('option_name',$type,$option_name);
        }
        $options = $query->fetchAll(FETCH_KEY_PAIR);
        if(empty($options)){
            return [];
        }
        return $options;
    }

    public static function getKeys(string|array $option_name, string $type = '='): array
    {
        if(is_array($option_name) && $type == 'REGEXP'){
            // 过滤掉非标量元素，避免误传多维数组时触发 Array to string conversion
            $option_name = join('|',array_filter($option_name,'is_scalar'));
            $option_name = "^({$option_name}).*";
        }else if(is_string($option_name) && $type == 'REGEXP'){
            $option_name = "^({$option_name}).*";
        }

        $query = DB::table('options')->select('option_name')
            ->orderBy('sort_order desc');

        if (is_array($option_name) && ($type === '=' || $type === '')) {
            $query->whereIn('option_name', $option_name);
        } else {
            $query->where('option_name',$type,$option_name);
        }
        $options = $query->fetchAll(FETCH_COLUMN);
        if(empty($options)){
            return [];
        }
        return $options;
    }
}