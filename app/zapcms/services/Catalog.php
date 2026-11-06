<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS - All Rights Reserved
 * @author Allen
 * @email zapcms@zap.cn
 * @date 2023/12/27 上午11:26
 * @lastModified 2023/12/27 上午11:26
 *
 */

namespace zapcms\services;

use zapcms\models\Node;
use zap\DB;
use zap\traits\SingletonTrait;

class Catalog extends Category
{

    use SingletonTrait;

    const POSITION_TOP = 1;
    const POSITION_LEFT = 2;
    const POSITION_RIGHT = 3;
    const POSITION_BOTTOM = 4;

    const POSITION_ALL = "1,2,3,4";

    /**
     * 历史整型位置 → 位置 key 映射（兼容旧数据；新数据直接存 key）
     */
    protected static array $POSITION_KEY_MAP = [
        1 => 'top',
        2 => 'left',
        3 => 'right',
        4 => 'footer',
    ];

    protected static array $POSITION_LIST  = [
        1 => '顶部导航',
        2 => '左侧导航',
        3 => '右侧导航',
        4 => '底部导航',
    ];
    public static function getPositions(): array
    {
        return static::$POSITION_LIST;
    }

    public static function positionTitle($position){
        return static::$POSITION_LIST[$position];
    }

    /**
     * 获取前台菜单位置选项 [key => title]
     *
     * 优先从 MenuLocation 表读取（支持管理员自定义位置），
     * 表不存在或为空时回退到内置的顶部/左侧/右侧/底部映射。
     */
    public static function getLocationOptions(): array
    {
        if (class_exists(\zapcms\services\MenuLocation::class)) {
            $opts = \zapcms\services\MenuLocation::instance()->options();
            if (!empty($opts)) {
                return $opts;
            }
        }
        $map = [];
        foreach (self::$POSITION_KEY_MAP as $k => $key) {
            $map[$key] = self::$POSITION_LIST[$k] ?? $key;
        }
        return $map;
    }

    public function __construct()
    {
        parent::__construct('catalog');
    }

    public function add($data): int
    {
        $node = Node::create([
            'title'=>$data['title'],
            'slug'=>$data['slug'],
            'author_id'=>0,
            'node_type'=>'catalog',
            'mime_type'=>$data['node_type'],
            'status'=>Node::STATUS_PUBLISH,
            'pub_time'=>time(),
            'update_time'=>time(),
            'add_time'=>time(),
        ]);
        if($node->id){
            $data[$this->primaryKey] = $node->id;
        }
        $data['created_at'] = time();
        return parent::add($data);
    }

    public function update($data, $id)
    {
        Node::updateAll([
            'title'=>$data['title'],
            'slug'=>$data['slug'],
//            'node_type'=>$data['node_type'],
            'node_type'=>'catalog',
            'mime_type'=>$data['node_type'],
            'update_time'=>time(),
        ],['id'=>$id]);
        return parent::update($data, $id);
    }

    public function remove($id)
    {
        Node::delete($id);
        DB::table('node_meta')->where('object_id',$id)->delete();
        return parent::remove($id);
    }


    public function getCatalogPathById($catalogId){
        $catalog = $this->get($catalogId);
        // 栏目不存在或 path 为空时直接返回空数组，避免访问 null 下标
        if(empty($catalog) || empty($catalog['path'])){
            return [];
        }
        $ids = array_filter(explode(',',$catalog['path']));
        if(empty($ids)){
            return [];
        }
        return $this->getAll([
            [$this->primaryKey,'IN',$ids]
        ]);
    }

    public function getSubCatalogList($catalogId){
        // 排除父级自身：path LIKE '5,%' 会匹配到 path='5,'，加 _ 确保至少还有一个后代
        return $this->getAll([
            [$this->pathColumn,'LIKE',"{$catalogId},%_"]
        ]);
    }

    /**
     * 获取指定显示位置的菜单（用于模板在任意区域输出导航）
     *
     * 数据来源：menu_items 表（WordPress 式菜单组成），按位置 key 取嵌套树。
     * 兼容：若该位置在 menu_items 中无任何项，则回退到旧逻辑（栏目树按 show_position 过滤）。
     *
     * @param int|string|array $position 支持：整型枚举(POSITION_LEFT)、位置 key('left')、key 数组
     * @return array 嵌套菜单树，每项含 id/title/url/target/children
     */
    public function getPositionMenu($position = self::POSITION_LEFT): array
    {
        $keys = $this->normalizePositionKeys($position);
        if (empty($keys)) {
            return [];
        }

        // 主数据源：menu_items（任一位置有数据即采用，前台走缓存）
        if (class_exists(\zapcms\services\MenuItem::class)) {
            $merged = \zapcms\services\MenuItem::instance()->cachedMergedTree($keys);
            if (!empty($merged)) {
                return $merged;
            }
        }

        // 回退：旧栏目树按 show_position 过滤
        $menu = [];
        foreach ($this->getTreeArray() as $tree){
            if(!$this->isPositionEnabled($tree, $keys)){
                continue;
            }
            if(!empty($tree['children'])){
                $tree['children'] = array_values(array_filter($tree['children'], function ($child) use ($keys){
                    return $this->isPositionEnabled($child, $keys);
                }));
            }
            $menu[] = $tree;
        }
        return $menu;
    }

    /**
     * 将整型枚举 / key / 数组归一化为位置 key 列表
     */
    protected function normalizePositionKeys($position): array
    {
        $raw = is_array($position) ? $position : [$position];
        $keys = [];
        foreach ($raw as $p) {
            if (is_numeric($p)) {
                $mapped = self::$POSITION_KEY_MAP[(int) $p] ?? null;
                if ($mapped !== null) {
                    $keys[] = $mapped;
                }
            } elseif (is_string($p) && $p !== '') {
                $keys[] = $p;
            }
        }
        return array_values(array_unique($keys));
    }

    /**
     * 判断栏目 show_position 是否包含指定位置 key
     */
    private function isPositionEnabled(array $catalog, array $keys): bool
    {
        $positions = array_filter(explode(',', $catalog['show_position'] ?? ''), 'strlen');
        foreach ($positions as $pos) {
            if (in_array(trim($pos), $keys, true)) {
                return true;
            }
        }
        return false;
    }

}
