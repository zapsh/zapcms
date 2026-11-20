<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS - All Rights Reserved
 * @author Allen
 * @email zapcms@zap.cn
 * @date 2023/12/27 上午11:27
 * @lastModified 2023/12/20 下午6:35
 *
 */

namespace zapcms\models;

use zap\db\Model;

class NodeRelation extends Model
{

    protected $autoincrement = false;

    public static function tableName(): string
    {
        return 'node_relation';
    }

    public static function primaryKey()
    {
        return ['catalog_id','node_id'];
    }
}