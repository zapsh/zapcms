<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS - All Rights Reserved
 * @author Allen
 * @email zapcms@zap.cn
 *
 * 模块 autoload 脚本示例：
 * 模块注册（写入 options 表）后，后台每次启动都会加载本文件，
 * 可在此注册 hooks、过滤器或扩展后台功能。
 */

defined('IN_ZAPCMS_ADMIN') or die('No permission to access');

// 示例：注册后台 hook（需 ZapCMS >= 1.0）
// \zapcms\AdminHook::on('admin_menu_after', function () {
//     // 不要写死 /z-admin/，url_action() 会自动拼上当前后台前缀
//     echo '<li><a href="' . url_action('mod/shop/product') . '">产品管理</a></li>';
// });
//
// 更推荐的做法：在 mod.json 的 admin_menu 中声明菜单，
// 模块启用后由 zapcms\support\ModMenu 自动同步到后台菜单，无需写代码。
