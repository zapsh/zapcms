<?php
/**
 * 商城模块 - 卸载脚本
 *
 * 默认保留数据：只删除后台菜单与模块登记，产品/订单数据表原样保留，
 * 便于重新注册后继续使用原有数据。
 *
 * 需要彻底清理数据时，由 ZapPackageManager::runPluginUninstallScript($name, false)
 * 或 Mod::uninstall(false) 传入 keepData=false（后台“卸载”弹窗中可勾选/取消）。
 */

use zap\db\Schema;

// 兼容多种传参方式：局部变量（require 作用域）/ 全局标志 / 默认保留
$keepData = isset($keepData)
    ? (bool)$keepData
    : (bool)($GLOBALS['zap_mod_uninstall_keep_data'] ?? true);

if (!$keepData) {
    Schema::dropIfExists('shop_order_item');
    Schema::dropIfExists('shop_order');
    Schema::dropIfExists('shop_product');
}

return true;
