<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS - All Rights Reserved
 * @author Allen
 * @email zapcms@zap.cn
 * @date 2023/12/27 上午11:29
 * @lastModified 2023/12/27 上午11:10
 *
 */

namespace zapcms\services;

use zap\db\Model;

class AdminLogs extends Model
{

    public static function tableName(): string
    {
        return 'admin_logs';
    }

    public static function primaryKey()
    {
        return 'id';
    }
}