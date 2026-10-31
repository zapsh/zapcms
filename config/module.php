<?php
/**
 * 模块路由配置 —— 由系统根据已启用模块的 mod.json "route" 自动生成。
 * 生成时间：2026-10-31 07:58:23
 *
 * 请勿手改本文件：模块安装 / 卸载 / 启用 / 禁用 / 更新时会重新生成。
 * 需要手动新增或覆盖前缀映射时，请创建 config/module.overrides.php：
 *
 *   <?php
 *   return ['mall' => 'shopv2', 'cart' => 'shopv2'];
 *
 * prefixes 语义：URL 前缀 => 模块目录名。
 */

$prefixes = array (
  'mall' => 'shop',
  'shop' => 'shop',
);

// 站点级手动覆盖（可选），优先级高于自动生成结果
$overrideFile = __DIR__ . '/module.overrides.php';
if (is_file($overrideFile)) {
    $overrides = include $overrideFile;
    if (is_array($overrides)) {
        $prefixes = array_merge($prefixes, $overrides);
    }
}

return ['prefixes' => $prefixes];