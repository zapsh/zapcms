<?php
/*
 * 插件后台子控制器（演示“其它控制器功能”如何挂到宿主页）
 *
 * 在插件 manifest 的 admin.pages 中声明 "action": "admin/logs" 即可挂载到宿主页子导航。
 *
 * 重要：模块目录名必须是合法 PHP 命名空间（连字符需改为下划线）。
 * 本模板目录名为 example-plugin（含连字符），复制后请改名为如 example_plugin，
 * 并同步修改本文件命名空间 \mods\example_plugin\controllers，否则 class_exists 失败，
 * 宿主页会给出友好提示（不会致命错误）。
 */
namespace mods\exampleplugin\controllers;

use zap\view\View;

class AdminController
{
    /**
     * 对应 admin.pages 中 "action": "admin/logs"
     * 返回片段 HTML（不要调用 $this->layout，外壳由宿主页负责）
     */
    public function Logs()
    {
        return View::render('plugin_template.logs', ['plugin' => 'example-plugin'], true);
    }
}
