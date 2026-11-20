<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS - All Rights Reserved
 * @author Allen
 * @email zapcms@zap.cn
 * @date 2023/12/27 上午11:23
 * @lastModified 2023/12/20 下午6:27
 *
 */

namespace zapcms\models;

use zapcms\services\Auth;
use zap\DB;
use zap\db\Model;

class Admin extends Model
{
    const STATUS_ACTIVATED = 'activated';
    const STATUS_DISABLED = 'disabled';

    public static function tableName(): string
    {
        return 'admin';
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

    public static function getProfile($id = null){
        $id = $id ?? Auth::user('id');
        return DB::table(static::tableName())->where('id',$id)->fetch(FETCH_ASSOC);
    }

    /**
     * @return string
     */
    public function getPrimaryKey(): string
    {
        return 'id';
    }

}