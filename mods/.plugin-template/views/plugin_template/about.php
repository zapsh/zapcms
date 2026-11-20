<?php
defined('IN_ZAPCMS') or die('No permission to access');
/*
 * 插件“使用说明”页面片段（admin.pages 里 view 类型，最常用、无需控制器）
 */
?>
<h6 class="fw-semibold">后台设置页（无左侧菜单）怎么用</h6>
<p class="text-muted">像支付、短信这类“后台服务”插件，其实不需要在左侧导航建菜单。本模板演示的做法：</p>
<ol class="small text-muted">
    <li>在 <code>plugin.json</code> / <code>mod.json</code> 写 <code>admin.pages</code> 数组，每个元素是一项后台页面。</li>
    <li>页面类型支持三种：
        <ul>
            <li><code>view</code>：直接渲染 <code>views/...</code> 片段（最简单，无需控制器）。</li>
            <li><code>action</code>：分发到模块控制器 <code>\mods\{name}\controllers\{C}Controller::{a}()</code>（其它功能用这个）。</li>
            <li><code>url</code>：外链 / 独立路由，在新窗口打开。</li>
        </ul>
    </li>
    <li>插件列表里会出现“设置”按钮，点进去就是这里的宿主页（左侧子导航 + 右侧内容区）。</li>
</ol>
<p class="text-muted small mb-0">这样左侧导航始终干净，插件的所有后台入口都收拢在列表的“设置”里。</p>
