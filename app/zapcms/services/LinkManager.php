<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS - All Rights Reserved
 * @author Allen
 * @email zapcms@zap.cn
 *
 * 链接/广告位管理：基于系统 options 表实现，不新增数据表。
 *
 * 数据结构（扁平数组选项）：
 *   ad.groups.N.{code,name,type,status,sort}          分组（type: slide 轮播 / link 友链）
 *   ad.items.<code>.N.{title,img_path,link,target,desc,status,sort,start_time,end_time}
 *                                                      每个分组下的条目
 */

namespace zapcms\services;

class LinkManager
{
    public const TYPE_SLIDE = 'slide';
    public const TYPE_LINK  = 'link';

    /** 分组数组基名 */
    private const GROUPS_BASE = 'ad.groups';
    /** 条目数组基名前缀（后接分组 code） */
    private const ITEMS_BASE = 'ad.items';

    /** 允许的分组类型 */
    public static function types(): array
    {
        return [
            self::TYPE_SLIDE => '图片轮播',
            self::TYPE_LINK  => '文字/图标链接',
        ];
    }

    // ─────────────── 分组 ───────────────

    /** 全部分组（含停用），按 sort 升序 */
    public static function groups(): array
    {
        $groups = option_get_array(self::GROUPS_BASE, []);
        usort($groups, static fn($a, $b) => ((int)($a['sort'] ?? 0)) <=> ((int)($b['sort'] ?? 0)));
        return $groups;
    }

    /** 按 code 取单个分组 */
    public static function group(string $code): ?array
    {
        foreach (self::groups() as $g) {
            if (($g['code'] ?? '') === $code) {
                return $g;
            }
        }
        return null;
    }

    /** 前台用：启用的分组 */
    public static function enabledGroups(?string $type = null): array
    {
        return array_values(array_filter(self::groups(), static function ($g) use ($type) {
            if ((string)($g['status'] ?? 1) !== '1') {
                return false;
            }
            return $type === null || ($g['type'] ?? '') === $type;
        }));
    }

    /** 保存全部分组（先清后写，保证收缩时无残留） */
    public static function saveGroups(array $groups): void
    {
        option_del_array(self::GROUPS_BASE);
        if ($groups !== []) {
            option_set_array(self::GROUPS_BASE, array_values($groups));
        }
    }

    /** 新增一个分组，返回新分组行；code 重复或非法时返回 null */
    public static function addGroup(string $name, string $type, int $status = 1, string $code = ''): ?array
    {
        $name = trim($name);
        if ($name === '' || !isset(self::types()[$type])) {
            return null;
        }
        $groups = self::groups();
        $codes = array_column($groups, 'code');
        $code = strtolower(trim($code));
        if ($code !== '') {
            // 自定义 code：仅允许字母数字下划线，且不能重复
            if (!preg_match('/^[a-z0-9_]{1,40}$/', $code) || in_array($code, $codes, true)) {
                return null;
            }
        } else {
            // 未指定则用 g + 时间戳 + 序号生成唯一 code
            $base = 'g' . substr((string)time(), -6);
            $code = $base;
            $i = 1;
            while (in_array($code, $codes, true)) {
                $code = $base . '_' . $i++;
            }
        }
        $group = [
            'code'   => $code,
            'name'   => $name,
            'type'   => $type,
            'status' => $status,
            'sort'   => count($groups) + 1,
        ];
        $groups[] = $group;
        self::saveGroups($groups);
        return $group;
    }

    /** 更新分组可编辑字段（不允许改 code，避免条目失联） */
    public static function updateGroup(string $code, array $fields): void
    {
        $groups = self::groups();
        foreach ($groups as &$g) {
            if (($g['code'] ?? '') === $code) {
                foreach (['name', 'type', 'status', 'sort'] as $k) {
                    if (array_key_exists($k, $fields)) {
                        $g[$k] = $fields[$k];
                    }
                }
                break;
            }
        }
        unset($g);
        self::saveGroups($groups);
    }

    /** 删除分组及其下所有条目 */
    public static function removeGroup(string $code): void
    {
        $groups = array_values(array_filter(self::groups(), static fn($g) => ($g['code'] ?? '') !== $code));
        self::saveGroups($groups);
        option_del_array(self::ITEMS_BASE . '.' . $code);
    }

    // ─────────────── 条目 ───────────────

    /** 取某分组的全部条目（含停用/未生效），按 sort 升序 */
    public static function items(string $code): array
    {
        $items = option_get_array(self::ITEMS_BASE . '.' . $code, []);
        usort($items, static fn($a, $b) => ((int)($a['sort'] ?? 0)) <=> ((int)($b['sort'] ?? 0)));
        return $items;
    }

    /**
     * 前台取某分组的有效条目：过滤停用、过期、未开始，统一字段并归一 target。
     * 分组不存在或停用时返回空数组（调用方可自行回退默认图）。
     */
    public static function activeItems(string $code): array
    {
        $group = self::group($code);
        if ($group === null || (string)($group['status'] ?? 1) !== '1') {
            return [];
        }
        $now = time();
        $out = [];
        foreach (self::items($code) as $it) {
            if ((string)($it['status'] ?? 1) !== '1') {
                continue;
            }
            $start = (int)($it['start_time'] ?? 0);
            $end   = (int)($it['end_time'] ?? 0);
            if ($start > 0 && $start > $now) {
                continue;
            }
            if ($end > 0 && $end < $now) {
                continue;
            }
            $out[] = [
                'title'    => (string)($it['title'] ?? ''),
                'img_path' => (string)($it['img_path'] ?? ''),
                'link'     => (string)($it['link'] ?? ''),
                'target'   => (($it['target'] ?? '_self') === '_blank') ? '_blank' : '_self',
                'desc'     => (string)($it['desc'] ?? ''),
            ];
        }
        return $out;
    }

    /** 保存某分组的全部条目（先清后写） */
    public static function saveItems(string $code, array $items): void
    {
        option_del_array(self::ITEMS_BASE . '.' . $code);
        if ($items !== []) {
            option_set_array(self::ITEMS_BASE . '.' . $code, array_values($items));
        }
    }

    /** 批量替换分组与条目（后台整页提交时一次写入） */
    public static function saveAll(array $groups, array $itemsByGroup): void
    {
        self::saveGroups($groups);
        $validCodes = array_column($groups, 'code');
        // 清理已删除分组的条目
        foreach (self::groupCodes() as $oldCode) {
            if (!in_array($oldCode, $validCodes, true)) {
                option_del_array(self::ITEMS_BASE . '.' . $oldCode);
            }
        }
        foreach ($validCodes as $code) {
            self::saveItems($code, $itemsByGroup[$code] ?? []);
        }
    }

    /** 现存已有条目的分组 code 列表 */
    public static function groupCodes(): array
    {
        $rows = \zapcms\services\Option::getKeys(self::ITEMS_BASE, 'REGEXP');
        $codes = [];
        foreach ($rows as $name) {
            if (preg_match('/^ad\.items\.([^\.]+)\./', (string)$name, $m)) {
                $codes[$m[1]] = $m[1];
            }
        }
        return array_values($codes);
    }
}
