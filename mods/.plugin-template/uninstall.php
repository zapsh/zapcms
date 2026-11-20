<?php
/**
 * 插件卸载脚本
 * 插件卸载时自动执行
 */

// 删除自定义数据表
\zap\DB::exec('DROP TABLE IF EXISTS {example_plugin_data}');

// 移除配置项
\zapcms\services\Option::remove('plugin.example.config');

// 移除后台菜单项（如果有注册的话）
// \zapcms\services\AdminMenu::removeModuleItem('example-plugin');
