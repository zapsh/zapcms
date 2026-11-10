<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS - All Rights Reserved
 * @author Allen
 * @email zapcms@zap.cn
 * @date 2023/12/27 上午11:26
 * @lastModified 2023/12/27 上午11:25
 *
 */

namespace zapcms\models;

use zap\DB;
use zap\db\Model;
use zap\db\Query;

class Node extends Model
{

    const STATUS_PUBLISH = 'publish'; //已发布
    const STATUS_DRAFT = 'draft'; //草稿
    const STATUS_SOFT_DELETE = 'soft_delete'; //软删除
    const STATUS_TRASH = 'trash'; //回收站

    public static function tableName(): string
    {
        return 'node';
    }

    public static function primaryKey()
    {
        return 'id';
    }

    public function getPubTimeToDate(){
        if($this->hasAttribute('pub_time')){
            return date(Z_DATE_TIME,$this->getAttribute('pub_time'));
        }
        return date(Z_DATE_TIME);
    }

    /**
     * 自定义字段（node_meta）缓存
     * @var array
     */
    protected $meta = [];

    /**
     * 是否已加载自定义字段
     * @var bool
     */
    protected $metaLoaded = false;

    /**
     * 读取自定义字段值
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get_node_meta($key, $default = ''){
        $this->loadMeta();
        return $this->meta[$key] ?? $default;
    }

    /**
     * 加载当前节点的全部自定义字段（node_meta），返回 meta_name => meta_value 数组
     * @return array
     */
    public function loadMeta(): array
    {
        if($this->metaLoaded || empty($this->id)){
            return $this->meta;
        }
        foreach (DB::table('node_meta')->where('object_id', (int)$this->id)->get(FETCH_ASSOC) as $row){
            $this->meta[$row['meta_name']] = $row['meta_value'];
        }
        $this->metaLoaded = true;
        return $this->meta;
    }

    /**
     * 保存自定义字段（先删除该节点全部 meta 再插入，空值不保存）
     * @param array $meta meta_name => meta_value
     */
    public function saveMeta(array $meta): void
    {
        if(empty($this->id)){
            return;
        }
        $this->deleteMeta();
        foreach ($meta as $name => $value){
            if($value === '' || $value === null){
                continue;
            }
            DB::table('node_meta')->insert([
                'object_id'  => (int)$this->id,
                'meta_name'  => (string)$name,
                'meta_value' => is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value,
            ]);
            $this->meta[$name] = $value;
        }
    }

    /**
     * 删除当前节点的全部自定义字段
     */
    public function deleteMeta(): void
    {
        if(empty($this->id)){
            return;
        }
        DB::table('node_meta')->where('object_id', (int)$this->id)->delete();
        $this->meta = [];
    }

    public static function getStatusTitle($status): string
    {
        switch ($status){
            case self::STATUS_PUBLISH:
                return '已发布';
            case self::STATUS_DRAFT:
                return '草稿';
            case self::STATUS_SOFT_DELETE:
                return '软删除';
            default:
                return '无';
        }
    }

    public static function getTypeTitle($nodeType): string
    {
        return \zapcms\services\NodeType::getTitle($nodeType) ?: $nodeType;
    }

    public function getStatus(): array
    {
        return [
            self::STATUS_PUBLISH => '已发布',
            self::STATUS_DRAFT => '草稿',
            self::STATUS_SOFT_DELETE => '已删除',
            self::STATUS_TRASH => '回收站'
        ];
    }


    public function getAllTypesCount(){
        $resultNodeTypes = static::createQuery()
            ->select('node_type,count(id) as total')
            ->whereNotIn('node_type', ['category', 'tag'])
            ->groupBy('node_type')
            ->get(FETCH_KEY_PAIR);
        return $resultNodeTypes;
    }

    public function getPages($columns = '*'){
        $query = Node::where('node_type','page')->select($columns);
        $query->orWhere(function(Query $query){
            $query->where('node_type','category')->where('mime_type','page');
        });
        return $query->get(FETCH_ASSOC);
    }






}