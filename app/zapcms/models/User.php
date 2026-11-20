<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS - All Rights Reserved
 * @author Allen
 * @email zapcms@zap.cn
 * @date 2023/12/27 上午11:28
 * @lastModified 2023/12/20 下午6:35
 *
 */

namespace zapcms\models;

use zap\db\Model;

class User extends Model
{

    const STATUS_ACTIVATED = 'activated';
    const STATUS_DISABLED = 'disabled';


    public static function tableName(): string
    {
        return 'admin';
    }

    public static function primaryKey()
    {
        return 'id';
    }

    public static function getStatusTitle($status): string
    {
        switch ($status){
            case self::STATUS_ACTIVATED:
                return '可用';
            default:
                return '禁用';
        }
    }

    public static function getStatus(): array
    {
        return [
            self::STATUS_ACTIVATED => '可用',
            self::STATUS_DISABLED => '禁用',

        ];
    }


}