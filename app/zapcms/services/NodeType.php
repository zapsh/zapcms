<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS - All Rights Reserved
 * @author Allen
 * @email zapcms@zap.cn
 * @date 2023/12/27 上午11:27
 * @lastModified 2023/10/28 下午1:54
 *
 */

namespace zapcms\services;


use zap\DB;
use zap\facades\Cache;
use zapcms\node\AbstractNodeType;

/**
 * 内容类型
 */
class NodeType
{
    protected static ?array $nodeTypes = null;

    /** 请求级缓存：字段、字段分组（按 type_name） */
    protected static array $fieldsCache = [];
    protected static array $groupsCache = [];

    /**
     * 清空全部请求级缓存（模型增删改后调用）
     */
    public static function flush(): void
    {
        static::$nodeTypes   = null;
        static::$fieldsCache = [];
        static::$groupsCache = [];
        static::$configCache = [];
    }

    /** 只清某个模型的字段/分组缓存 */
    protected static function flushFields(string $type_name): void
    {
        unset(static::$fieldsCache[$type_name], static::$groupsCache[$type_name]);
    }

    /**
     * 虚拟内容类型：不产生真实内容，仅作为内部机制的占位类型。
     * 不在「内容模型」等面向内容管理的列表中展示。
     * （link-url 已随菜单体系统一到 menu_items 移除，目前无虚拟类型，机制保留备用）
     */
    public const VIRTUAL_TYPES = [];

    public static function isVirtual(string $type_name): bool
    {
        return in_array($type_name, static::VIRTUAL_TYPES, true);
    }

    public static function getNodeTypes(): array
    {
        if(is_null(static::$nodeTypes)){
            static::$nodeTypes = [];
            $resultSet = DB::table('node_types')->where('status',1)
                ->orderBy('sort_order DESC')
                ->get(FETCH_ASSOC);
            foreach ($resultSet as $row){
                static::$nodeTypes[$row['type_name']] = $row;
            }
        }
        return static::$nodeTypes;
    }

    public static function getKeyPair(string $key = 'type_name', string $value = 'title')
    {
        return DB::table('node_types')->select([$key,$value])
            ->where('status',1)
            ->orderBy('sort_order DESC')
            ->get(FETCH_KEY_PAIR);
    }


    public static function getNodeType(string $type_name, ?string $key = null)
    {
        if(is_null(static::$nodeTypes)){
            static::getNodeTypes();
        }
        // 模型不存在时返回 null，避免访问不存在的键触发 Undefined array key 警告
        if(!isset(static::$nodeTypes[$type_name])){
            return null;
        }
        if (is_null($key)) {
            return self::$nodeTypes[$type_name];
        }
        return self::$nodeTypes[$type_name][$key] ?? null;
    }


    public static function getTitle(string $type_name)
    {
       return static::getNodeType($type_name,'title');
    }

    /**
     * 获取模型的自定义处理类。
     *
     * 归一化规则（兼容历史脏数据）：
     *  - 折叠多余反斜杠（历史数据存过 "zap\\node\\Xxx" 这种双反斜杠）；
     *  - 旧命名空间 zap\node\ 前缀迁移为 zapcms\node\；
     *  - 校验类存在且继承 AbstractNodeType，否则回落基类。
     * 返回空配置（''）同样回落基类。
     */
    public static function getClass(string $type_name)
    {
        $class = trim((string) static::getNodeType($type_name, 'node_type'));
        if ($class === '') {
            return AbstractNodeType::class;
        }

        $class = str_replace('\\\\', '\\', $class);
        $class = ltrim($class, '\\');
        // 旧命名空间迁移：zap\node\... → zapcms\node\...
        if (stripos($class, 'zap\\node\\') === 0) {
            $class = 'zapcms\\' . substr($class, 4);
        }

        if (!class_exists($class) || !is_subclass_of($class, AbstractNodeType::class)) {
            return AbstractNodeType::class;
        }
        return $class;
    }

    public static function getID(string $type_name)
    {
        return static::getNodeType($type_name,'type_id');
    }

    /**
     * 获取内容模型的动态字段配置（按排序返回）
     */
    public static function getFields(string $type_name): array
    {
        if (array_key_exists($type_name, static::$fieldsCache)) {
            return static::$fieldsCache[$type_name];
        }
        $typeId = static::getID($type_name);
        if (empty($typeId)) {
            return static::$fieldsCache[$type_name] = [];
        }
        return static::$fieldsCache[$type_name] = DB::table('node_type_field')
            ->where('node_type_id', (int)$typeId)
            ->orderBy('sort_order', 'ASC')->orderBy('field_id', 'ASC')
            ->get(FETCH_ASSOC) ?: [];
    }

    /**
     * 获取某类型配置的自定义字段名列表
     */
    public static function getFieldNames(string $type_name): array
    {
        $fields = static::getFields($type_name);
        return array_map(function ($f) { return $f['field_name']; }, $fields);
    }

    /**
     * 分组配置在 options 表中的键前缀（按内容模型区分）
     */
    protected const GROUP_OPTION_PREFIX = 'node_field_group.';

    /**
     * 获取内容模型的字段分组列表（按 sort_order 排序）
     *
     * 返回结构：[ ['name' => '规格参数', 'sort_order' => 0], ... ]
     */
    public static function getFieldGroups(string $type_name): array
    {
        if (array_key_exists($type_name, static::$groupsCache)) {
            return static::$groupsCache[$type_name];
        }
        $row = DB::table('options')->where('option_name', static::GROUP_OPTION_PREFIX . $type_name)->first();
        $groups = [];
        if (!empty($row['option_value'])) {
            $decoded = json_decode((string)$row['option_value'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $g) {
                    if (is_string($g)) {
                        $groups[] = ['name' => trim($g), 'sort_order' => 0];
                    } elseif (is_array($g) && !empty($g['name'])) {
                        $groups[] = [
                            'name'       => trim((string)$g['name']),
                            'sort_order' => (int)($g['sort_order'] ?? 0),
                        ];
                    }
                }
            }
        }
        usort($groups, fn($a, $b) => $a['sort_order'] <=> $b['sort_order']);
        return static::$groupsCache[$type_name] = $groups;
    }

    /**
     * 保存内容模型的字段分组列表（全量替换，存 options 表）
     *
     * @param string $type_name 内容模型标识（如 product）
     * @param array  $names     分组名称列表
     */
    public static function saveFieldGroups(string $type_name, array $names): void
    {
        $groups = [];
        $seq = 0;
        foreach ($names as $name) {
            $name = trim((string)$name);
            if ($name === '') {
                continue;
            }
            $groups[] = ['name' => $name, 'sort_order' => $seq++];
        }

        $optionName = static::GROUP_OPTION_PREFIX . $type_name;
        $json = $groups ? json_encode($groups, JSON_UNESCAPED_UNICODE) : '';

        if (DB::table('options')->where('option_name', $optionName)->exists()) {
            DB::table('options')->where('option_name', $optionName)->update(['option_value' => $json]);
        } else {
            DB::table('options')->insert([
                'option_name'  => $optionName,
                'option_value' => $json,
                'sort_order'   => 0,
                'autoload'     => 0,
            ]);
        }
        static::flushFields($type_name);
    }

    /**
     * 删除内容模型的字段分组配置
     */
    public static function removeFieldGroups(string $type_name): void
    {
        DB::table('options')->where('option_name', static::GROUP_OPTION_PREFIX . $type_name)->delete();
        static::flushFields($type_name);
    }

    /**
     * 内容模型显示配置在 options 表中的键前缀（按内容模型区分）
     */
    protected const CONFIG_OPTION_PREFIX = 'node_type_config.';

    /**
     * 内容模型显示配置默认值
     */
    public const CONFIG_DEFAULTS = [
        'list_per_page'      => 12,   // 列表分页显示数量
        'list_image_width'   => 270,  // 列表图片宽度
        'list_image_height'  => 210,  // 列表图片高度
        'detail_image_width' => 750,  // 详情页图片宽度
        'detail_image_height'=> 480,  // 详情页图片高度
        'list_columns'       => [],   // 后台列表默认展示的自定义字段（node_meta 键）
    ];

    /**
     * 获取内容模型显示配置
     *
     * @param string|null  $type_name 内容模型标识（如 article / product）；null/空表示栏目未绑定模型，返回默认值
     * @param string|null $key       配置键；null 返回全部配置（含默认值）
     * @param mixed       $default   键不存在时的默认值
     * @return mixed
     */
    /**
     * 请求级配置缓存：同一内容模型一次请求只读一次。
     * 底层 Cache 关闭或未命中时，避免同一请求内重复查询 options 表。
     */
    protected static array $configCache = [];

    public static function getConfig(?string $type_name, ?string $key = null, $default = null)
    {
        // type_name 为空（栏目未绑定内容模型）时直接返回默认值，避免 TypeError 与无意义查询
        if (empty($type_name)) {
            return is_null($key) ? static::CONFIG_DEFAULTS : (static::CONFIG_DEFAULTS[$key] ?? $default);
        }
        $cacheKey = static::CONFIG_OPTION_PREFIX . $type_name;
        if (!array_key_exists($cacheKey, static::$configCache)) {
            $ttl = (int)config('cache.ttl', 0);
            if ($ttl <= 0) {
                $ttl = 10000;
            }
            static::$configCache[$cacheKey] = Cache::get($cacheKey, function () use ($cacheKey) {
                $row = DB::table('options')->where('option_name', $cacheKey)->first();
                $config = static::CONFIG_DEFAULTS;
                if (!empty($row['option_value'])) {
                    $decoded = json_decode((string)$row['option_value'], true);
                    if (is_array($decoded)) {
                        $config = array_merge($config, $decoded);
                    }
                }
                return $config;
            }, $ttl);
        }
        $config = static::$configCache[$cacheKey];
        return is_null($key) ? $config : ($config[$key] ?? $default);
    }

    /**
     * 保存内容模型显示配置（基于现有配置合并非空值，存 options 表）
     */
    public static function saveConfig(string $type_name, array $config): void
    {
        $optionName = static::CONFIG_OPTION_PREFIX . $type_name;
        // 先取现有配置（含默认值兜底），再合并非空值，避免空提交冲掉已保存项
        $existing = static::getConfig($type_name);
        $new = array_merge($existing, array_filter($config, function ($v) {
            return $v !== null && $v !== '';
        }));

        if (DB::table('options')->where('option_name', $optionName)->exists()) {
            DB::table('options')->where('option_name', $optionName)->update(['option_value' => json_encode($new, JSON_UNESCAPED_UNICODE)]);
        } else {
            DB::table('options')->insert([
                'option_name'  => $optionName,
                'option_value' => json_encode($new, JSON_UNESCAPED_UNICODE),
                'sort_order'   => 0,
                'autoload'     => 0,
            ]);
        }

        // 使配置缓存失效，下次 getConfig 重新读取
        Cache::delete($optionName);
        unset(static::$configCache[$optionName]);
    }

    /**
     * 删除内容模型的显示配置
     */
    public static function removeConfig(string $type_name): void
    {
        $optionName = static::CONFIG_OPTION_PREFIX . $type_name;
        DB::table('options')->where('option_name', $optionName)->delete();

        // 使配置缓存失效
        Cache::delete($optionName);
        unset(static::$configCache[$optionName]);
    }

    /**
     * 按分组返回字段：['分组名' => [字段...]]，'' 表示默认分组（未分组字段）
     *
     * 顺序：默认分组 → 已配置分组（按 sort_order）→ 字段中未配置但存在的分组（兜底）
     */
    public static function getFieldsGrouped(string $type_name): array
    {
        $fields = static::getFields($type_name);
        $byGroup = [];
        foreach ($fields as $f) {
            $g = trim((string)($f['group_name'] ?? ''));
            $byGroup[$g][] = $f;
        }

        $result = [];
        if (!empty($byGroup[''])) {
            $result[''] = $byGroup[''];
            unset($byGroup['']);
        }
        foreach (static::getFieldGroups($type_name) as $g) {
            $name = $g['name'];
            if (isset($byGroup[$name])) {
                $result[$name] = $byGroup[$name];
                unset($byGroup[$name]);
            }
        }
        foreach ($byGroup as $name => $fs) {
            $result[$name] = $fs;
        }
        return $result;
    }

}