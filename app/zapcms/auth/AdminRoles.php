<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS - All Rights Reserved
 * @author Allen
 * @email zapcms@zap.cn
 * @date 2023/12/27 上午11:01
 * @lastModified 2023/12/20 下午6:28
 *
 */

namespace zapcms\auth;

use zap\db\Model;

class AdminRoles extends Model
{

    public static function tableName(): string
    {
        return 'admin_roles';
    }

    public static function primaryKey(): array
    {
        return ['admin_id', 'role_id'];
    }


}