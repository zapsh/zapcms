<?php
/**
 * 插件更新脚本
 * 插件更新时自动执行
 *
 * 可用的变量：
 * - $name: 插件名称
 * - $fromVersion: 旧版本号
 * - $toVersion: 新版本号
 */

// 根据版本执行不同的更新操作
// if (version_compare($fromVersion, '1.1.0', '<')) {
//     // 执行 1.1.0 版本的更新操作
//     \zapcms\services\Option::add('plugin.example.new_config', 'new_value');
// }
