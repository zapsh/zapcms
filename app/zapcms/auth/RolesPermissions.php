<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS - All Rights Reserved
 * @author        Allen
 * @email        zapcms@zap.cn
 * @date        2023/12/27 上午11:01
 * @lastModified        2023/12/27 上午11:01
 *
 */

namespace zapcms\auth;

use zap\db\Model;

class RolesPermissions extends Model
{

    public static function tableName(): string
    {
        return 'roles_permissions';
    }

    public static function primaryKey(): array
    {
        return ['role_id','perm_id'];
    }

    public static function getByRoleId($id): array
    {
        $rows = static::find(['role_id'=>$id])->select('perm_key,extras')
            ->fetchAll(FETCH_ASSOC);
        $role_permissions = [];
        foreach ($rows as $row){
            if(!empty($row['extras'])){
                $role_permissions[$row['perm_key']] = array_fill_keys(explode(',',$row['extras']),true);
            }else{
                $role_permissions[$row['perm_key']] = [];
            }

        }
        return $role_permissions;
    }

}