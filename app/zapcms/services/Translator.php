<?php

namespace zapcms\services;

use zap\DB;

/**
 * 内容翻译存储服务。
 *
 * 采用子表方案：主表 node / catalog 保留「默认语言」的原文，
 * 其余语言写入 translations 子表（object_type + object_id + locale 唯一）。
 *
 * 读取时按需覆盖：非默认语言优先取译文，取不到则回退到主表原文。
 */
class Translator
{
    /** 对象类型：内容节点（同时覆盖 node_type=catalog 的栏目，两者 id 相同） */
    const TYPE_NODE = 'node';
    /** 对象类型：栏目表记录 */
    const TYPE_CATALOG = 'catalog';

    /**
     * 翻译表名（逻辑名，不带表前缀）
     */
    public static function table(): string
    {
        return 'translations';
    }

    /**
     * 可翻译字段列表
     *
     * @return string[]
     */
    public static function fields(): array
    {
        return ['title', 'slug', 'excerpt', 'content', 'keywords', 'description'];
    }

    /**
     * 读取某条译文（不存在返回空数组）
     */
    public static function get(string $objectType, int $objectId, string $locale): array
    {
        if ($objectId <= 0) {
            return [];
        }
        $row = DB::table(static::table())
            ->where('object_type', $objectType)
            ->where('object_id', $objectId)
            ->where('locale', $locale)
            ->fetch(FETCH_ASSOC);

        return $row ? static::normalizeRow((array) $row) : [];
    }

    /**
     * 读取某个对象的全部译文（locale => row）
     */
    public static function getAll(string $objectType, int $objectId): array
    {
        if ($objectId <= 0) {
            return [];
        }
        $rows = DB::table(static::table())
            ->where('object_type', $objectType)
            ->where('object_id', $objectId)
            ->fetchAll(FETCH_ASSOC);

        $result = [];
        foreach ($rows as $row) {
            $row = static::normalizeRow($row);
            $result[$row['locale']] = $row;
        }
        return $result;
    }

    /**
     * 保存（写入或更新）某条译文
     *
     * 所有字段都为空时会删除该条记录，避免留下空壳。
     *
     * @param array $data 只允许 fields() 内的键
     */
    public static function save(string $objectType, int $objectId, string $locale, array $data): void
    {
        if ($objectId <= 0) {
            return;
        }

        $row = ['update_time' => time()];
        foreach (static::fields() as $field) {
            $row[$field] = array_key_exists($field, $data) ? trim((string) $data[$field]) : '';
        }

        // 只保留主键外的有效字段用于判空
        $isEmpty = true;
        foreach (static::fields() as $field) {
            if ($row[$field] !== '') {
                $isEmpty = false;
                break;
            }
        }

        if ($isEmpty) {
            static::delete($objectType, $objectId, $locale);
            return;
        }

        $exist = static::get($objectType, $objectId, $locale);

        if ($exist) {
            DB::table(static::table())
                ->where('object_type', $objectType)
                ->where('object_id', $objectId)
                ->where('locale', $locale)
                ->update($row);
            return;
        }

        DB::table(static::table())->insert(array_merge([
            'object_type' => $objectType,
            'object_id'   => $objectId,
            'locale'      => $locale,
        ], $row));
    }

    /**
     * 删除某条译文
     */
    public static function delete(string $objectType, int $objectId, string $locale): void
    {
        DB::table(static::table())
            ->where('object_type', $objectType)
            ->where('object_id', $objectId)
            ->where('locale', $locale)
            ->delete();
    }

    /**
     * 删除某对象的全部译文（删除内容时调用）
     */
    public static function deleteAll(string $objectType, int $objectId): void
    {
        if ($objectId <= 0) {
            return;
        }
        DB::table(static::table())
            ->where('object_type', $objectType)
            ->where('object_id', $objectId)
            ->delete();
    }

    /**
     * 是否存在非空译文
     */
    public static function hasTranslation(string $objectType, int $objectId, string $locale): bool
    {
        $row = static::get($objectType, $objectId, $locale);
        return ($row['title'] ?? '') !== '' || ($row['content'] ?? '') !== '';
    }

    /**
     * 按 slug + 语言反查译文
     *
     * 用于多语言 URL：/en/xxx.html 里的 xxx 优先匹配译文里的 slug。
     *
     * @return array|null 命中行（含 object_id）
     */
    public static function findBySlug(string $slug, string $locale, string $objectType = self::TYPE_NODE): ?array
    {
        if ($slug === '') {
            return null;
        }
        // 该表主键是 (object_type, object_id, locale)，没有自增 id 列
        $row = DB::table(static::table())
            ->where('object_type', $objectType)
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->fetch(FETCH_ASSOC);

        return $row ? static::normalizeRow($row) : null;
    }

    /**
     * 把译文覆盖到内容行上
     *
     * 策略：逐字段优先取译文，译文为空时回退主表（默认语言）原文，
     * 保证缺失译文的语言也能正常展示而不是空白页。
     *
     * @param array       $row        主表行（node / catalog）
     * @param string|null $locale     目标语言，默认取当前语言
     * @param string      $objectType 子表里的对象类型
     * @param array|null  $fields     只覆盖指定字段（列表场景避免拉正文），null 表示全部
     */
    public static function apply(array $row, ?string $locale = null, string $objectType = self::TYPE_NODE, ?array $fields = null): array
    {
        if ($row === []) {
            return $row;
        }

        $locale = static::resolveLocale($locale);
        if ($locale === Locale::defaultLocale()) {
            // 默认语言直接读主表，无需查子表
            return $row;
        }

        $objectId = (int) ($row['id'] ?? 0);
        if ($objectId <= 0) {
            return $row;
        }

        return static::applyWith($row, static::get($objectType, $objectId, $locale), $locale, $fields);
    }

    /**
     * 用已取到的译文行覆盖主表行（不查库，供批量/递归场景复用）
     *
     * @param array       $row         主表行
     * @param array       $translation 译文行，空数组表示无译文
     * @param string      $locale      目标语言
     * @param array|null  $fields      只覆盖指定字段
     */
    public static function applyWith(array $row, array $translation, string $locale, ?array $fields = null): array
    {
        if ($row === [] || $translation === []) {
            return $row;
        }

        if ($locale === Locale::defaultLocale()) {
            return $row;
        }

        foreach ($fields ?: static::fields() as $field) {
            $value = $translation[$field] ?? '';
            if ($value !== '') {
                $row[$field] = $value;
            }
        }

        $row['_locale'] = $locale;
        $row['_translated'] = (bool) (($translation['title'] ?? '') !== '' || ($translation['content'] ?? '') !== '');

        return $row;
    }

    /**
     * 批量取译文，返回 object_id => 译文行
     *
     * @param string      $objectType
     * @param array       $objectIds
     * @param string|null $locale 目标语言，默认取当前语言
     * @return array
     */
    public static function getMap(string $objectType, array $objectIds, ?string $locale = null): array
    {
        $locale = static::resolveLocale($locale);
        if ($objectIds === [] || $locale === Locale::defaultLocale()) {
            return [];
        }

        $ids = [];
        foreach ($objectIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        if ($ids === []) {
            return [];
        }

        $rows = DB::table(static::table())
            ->where('object_type', $objectType)
            ->where('object_id', 'IN', array_values($ids))
            ->where('locale', $locale)
            ->fetchAll(FETCH_ASSOC);

        $map = [];
        foreach ((array) $rows as $translation) {
            $translation = static::normalizeRow((array) $translation);
            $map[(int) $translation['object_id']] = $translation;
        }

        return $map;
    }

    /**
     * 批量套用译文（列表场景，一次查询避免 N+1）
     *
     * @param array       $rows       主表行数组
     * @param string|null $locale     目标语言
     * @param string      $objectType 子表里的对象类型
     * @param array|null  $fields     只覆盖指定字段
     * @return array
     */
    public static function applyBatch(array $rows, ?string $locale = null, string $objectType = self::TYPE_NODE, ?array $fields = null): array
    {
        if ($rows === []) {
            return $rows;
        }

        $locale = static::resolveLocale($locale);
        if ($locale === Locale::defaultLocale()) {
            return $rows;
        }

        $ids = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        if ($ids === []) {
            return $rows;
        }

        $map = static::getMap($objectType, array_values($ids), $locale);

        foreach ($rows as $key => $row) {
            $translation = $map[(int) ($row['id'] ?? 0)] ?? null;
            if ($translation === null) {
                continue;
            }
            $rows[$key] = static::applyWith($row, $translation, $locale, $fields);
        }

        return $rows;
    }

    /**
     * 解析目标语言
     */
    protected static function resolveLocale(?string $locale): string
    {
        return $locale !== null ? Locale::normalize($locale) : Locale::current();
    }

    /**
     * 统一行格式（把可能为 null 的字段补成字符串）
     */
    protected static function normalizeRow(array $row): array
    {
        foreach (static::fields() as $field) {
            $row[$field] = (string) ($row[$field] ?? '');
        }
        $row['object_id'] = (int) ($row['object_id'] ?? 0);
        $row['locale'] = (string) ($row['locale'] ?? '');
        return $row;
    }
}
