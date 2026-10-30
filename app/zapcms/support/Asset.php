<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS - All Rights Reserved
 * @author Allen
 * @email zapcms@zap.cn
 * @date 2023/12/27 上午11:10
 * @lastModified 2025/8/5 下午4:30
 */

namespace zapcms\support;

/**
 * 前端资源管理
 *
 * 管理 CSS / JS 的注册与加载，所有第三方库的定义集中维护于此。
 */
class Asset
{
    /**
     * 库定义映射（懒初始化）
     */
    protected static ?array $libraries = null;

    // ============================================================
    //   公共方法
    // ============================================================

    /**
     * 注册 CSS 样式
     */
    public static function css(string $url, string $position = ASSETS_HEAD): void
    {
        register_styles($url, $position);
    }

    /**
     * 注册 JS 脚本
     */
    public static function js(string $url, string $position = ASSETS_HEAD): void
    {
        register_scripts($url, $position);
    }

    /**
     * 按名称加载第三方库
     *
     * @param string $name 库名称（如 jqueryvalidation、summernote 等）
     */
    public static function library(string $name): void
    {
        if (static::$libraries === null) {
            static::bootLibraries();
        }

        $name = preg_replace('/[^A-Za-z0-9]/', '', $name);

        if (isset(static::$libraries[$name])) {
            (static::$libraries[$name])();
        }
    }

    // ============================================================
    //   库注册（懒初始化）
    // ============================================================

    protected static function bootLibraries(): void
    {
        static::$libraries = [
            // ---- 基础库 ----
            'jquery' => static function (): void {
                register_scripts(base_url('/assets/jquery/jquery-3.6.4.min.js'), ASSETS_HEAD);
            },

            'bootstrap' => static function (): void {
                register_styles(base_url('/assets/bootstrap/5.3.1/css/bootstrap.min.css'));
                register_scripts(base_url('/assets/bootstrap/5.3.1/js/bootstrap.bundle.min.js'), ASSETS_BODY);
            },

            // ---- 表单相关 ----
            'jqueryvalidation' => static function (): void {
                register_scripts(base_url('/assets/jqueryvalidation/jquery.validate.min.js'));
                register_scripts(base_url('/assets/jqueryvalidation/additional-methods.min.js'));
            },

            'datetimepicker' => static function (): void {
                register_styles(base_url('/assets/datetimepicker/jquery.datetimepicker.min.css'), ASSETS_HEAD);
                register_scripts(base_url('/assets/datetimepicker/jquery.datetimepicker.full.js'), ASSETS_HEAD);
            },

            // ---- 编辑器 ----
            'summernote' => static function (): void {
                register_styles(base_url('/assets/plugins/summernote/summernote-lite.min.css'));
                register_styles(<<<'CSS'
.note-editor .note-toolbar .note-color-all .note-dropdown-menu, .note-popover .popover-content .note-color-all .note-dropdown-menu {
    min-width: 343px;
}
.note-toolbar .dropdown-toggle::after {
    content: none;
}
pre {
    display: block;
    padding: 9.5px;
    margin: 0 0 10px;
    font-size: 13px;
    line-height: 1.42857143;
    color: #333;
    word-break: break-all;
    word-wrap: break-word;
    background-color: #f5f5f5;
    border: 1px solid #ccc;
    border-radius: 4px;
}
CSS, ASSETS_HEAD_TEXT);
                register_scripts(base_url('/assets/plugins/summernote/summernote-lite.min.js'));
                register_scripts(base_url('/assets/plugins/snfinder/summernote-ext-snfinder.js'));
                register_scripts(base_url('/assets/plugins/summernote/lang/summernote-zh-CN.js'));
            },

            'trumbowyg' => static function (): void {
                register_styles(base_url('/assets/trumbowyg/ui/trumbowyg.min.css'));
                register_scripts(base_url('/assets/trumbowyg/trumbowyg.min.js'));
            },

            // ---- 上传 ----
            'dropzone' => static function (): void {
                register_scripts(base_url('/assets/plugins/dropzone/dropzone-min.js'));
                register_styles(base_url('/assets/plugins/dropzone/basic.css'));
                register_styles(base_url('/assets/plugins/dropzone/dropzone.css'));
            },

            // ---- 模板引擎 ----
            'arttemplate' => static function (): void {
                register_scripts(base_url('/assets/art-template.min.js'));
            },
        ];
    }
}
