<?php
?>
<form>
    <input type="hidden" name="role_id" value="<?php echo e($data['role_id'] ?? 0); ?>" />
    <div class="mb-3">
        <label for="data_name" class="form-label">角色名称</label>
        <input type="text" class="form-control" id="data_name" name="data[name]" value="<?php echo e($data['name']); ?>" placeholder="请输入角色名称" />
    </div>
    <div class="mb-3">
        <label for="data_description" class="form-label">简介</label>
        <input type="text" class="form-control" id="data_description" name="data[description]" value="<?php echo \zapcms\support\HtmlXss::clean($data['description']); ?>" placeholder="请输入角色介绍" />
    </div>
    <hr/>

    <div class="mb-3 overflow-y-auto " style="height: auto">
        <label  class="form-label">权限</label>
        <div class="table-responsive">
            <table class="table table-bordered table-hover text-nowrap">
                <thead class="bg-light sticky-top top-0">
                <tr class="table-secondary">
                    <th scope="col" style="width: 50px">
                        <label>
                            <input class="form-check-input" type="checkbox" onclick="Zap.CheckBox_CheckAll(this,'.sl_perm_list')"/>
                        </label>
                    </th>

                    <th scope="col">权限名称</th>
                    <th scope="col">扩展</th>
                </tr>
                </thead>
                <tbody>
                <?php
                foreach ($permissions as $item){
                    ?>
                    <tr>
                        <td class="w-auto">

                                <input name="perms[]"
                                       value="<?php echo e($item['perm_key']); ?>"
                                       class="form-check-input sl_perm_list" type="checkbox"
                                <?php if(isset($role_permissions[$item['perm_key']])){echo 'checked';} ?>
                                />

                        </td>


                        <td class="w-50">
                                <?php echo e($item['title']); ?>
                                <small class="text-black-50">ID:<?php echo e($item['perm_id']); ?></small>
                                <small class="text-black-50"><?php echo e($item['perm_key']); ?></small>




                        </td>

                        <td >
                            <?php if(!empty($item['extras'])){
                                $extrasPerms = json_decode($item['extras'],true);
                                if(json_last_error() !== JSON_ERROR_NONE){$extrasPerms = [];}
                                ?>
                                <?php
                                foreach ($extrasPerms as $key=>$value){
                                    ?>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" id="extra_<?php echo md5($item['perm_key'].'_'.$key);?>" value="<?php echo e($key); ?>" name="extras[<?php echo e($item['perm_key']); ?>][]"
                                            <?php if(isset($role_permissions[$item['perm_key']][$key])){echo 'checked';} ?>
                                        >
                                        <label class="form-check-label" for="extra_<?php echo md5($item['perm_key'].'_'.$key);?>"><?php echo e($value); ?></label>
                                    </div>
                                    <?php
                                }
                                ?>
                                <small class="text-muted ms-1">（不勾选子项 = 仅拥有该菜单入口权限）</small>
                            <?php }else{ ?>
                                <small class="text-muted">— 拥有全部 —</small>
                            <?php } ?>
                            <?php
                            };
                            ?>
                        </td>

                    </tr>



                </tbody>
            </table>


        </div>
    </div>


    <div class="mb-3 overflow-y-auto " style="height: auto">
        <label  class="form-label">系统菜单</label>
        <div class="table-responsive">
            <table class="table table-hover text-nowrap">
                <thead class="bg-light sticky-top top-0">
                <tr class="table-secondary">
                    <th scope="col" style="width: 50px">
                        <label>
                            <input class="form-check-input" type="checkbox" onclick="Zap.CheckBox_CheckAll(this,'.sl_admin_menus')"/>
                        </label>
                    </th>

                    <th scope="col">菜单名称</th>
                    <th scope="col">链接地址</th>
                </tr>
                </thead>
                <tbody>
                <?php
                foreach ($admin_menus as $item){
                    ?>
                    <tr>
                        <td class="w-auto">

                            <input name="perms[]"
                                   value="admin_menu_<?php echo e($item['id']); ?>"
                                   class="form-check-input sl_admin_menus" type="checkbox"
                                <?php if(isset($role_permissions['admin_menu_'.$item['id']])){echo 'checked';} ?>
                            />

                        </td>


                        <td class="w-50">
                            <div style="padding-left: <?php echo $item['level']*0.9 ?>rem !important;">
                            <i class="<?php echo e($item['icon']); ?>"></i>
                            <?php echo e($item['title']); ?>
                            <small class="text-black-50">ID:<?php echo e($item['id']); ?></small>
                            </div>
                        </td>

                        <td class="text-black-50"><?php echo e($item['link_to']); ?></td>

                    </tr>

                    <?php
                };
                ?>


                </tbody>
            </table>


        </div>
    </div>

</form>
