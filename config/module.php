<?php
/**
 * 模块路由配置
 *
 * prefixes: URL 前缀 → 模块目录名
 *   配置后即可用无 /mod/ 的简洁 URL，例如：
 *     'shop' => 'shopv2'     →  /shop/product/view/123 → mods/shopv2/
 *     'cart' => 'shopv2'     →  /cart/checkout        → mods/shopv2/
 *     'mall' => 'shopv2'     →  /mall/order/create    → mods/shopv2/
 *
 * 多个前缀可指向同一个模块目录。
 * 不在此列表中的路径不会触发模块分发，完全不影响 CMS 路由。
 */
return [
    'prefixes' => [
        // 'shop' => 'shopv2',
    ],
];
