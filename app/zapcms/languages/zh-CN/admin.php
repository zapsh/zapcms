<?php

/**
 * 后台界面文案（简体中文）。
 * key 供 __('admin.xxx') 使用；英文等其它语言见同目录对应语言文件。
 */
return [
    // 顶部导航栏
    'visit_site'         => '访问网站',
    'clear_cache'        => '清理缓存',
    'switch_language'    => '切换语言',
    'appearance'         => '主题外观',
    'account_settings'   => '账户设置',
    'system_settings'    => '系统设置',
    'logout'             => '退出登录',
    'theme_light'        => '亮色',
    'theme_dark'         => '暗色',
    'theme_auto'         => '自动',

    // 控制台
    'dashboard'          => '控制台',
    'hello'              => '你好，{name}',
    'customize'          => '自定义首页',
    'empty_dashboard'    => '当前没有显示任何组件，点击右上角「自定义首页」添加。',
    'customize_title'    => '自定义控制台',
    'customize_desc'     => '设置仅对当前管理员生效。勾选显示、取消勾选隐藏，用箭头调整显示顺序。',
    'restore_default'    => '恢复默认',
    'cancel'             => '取消',
    'save'               => '保存',
    'close'              => '关闭',
    'move_up'            => '上移',
    'move_down'          => '下移',
    'loading'            => '加载中...',
    'load_failed'        => '加载失败，请稍后重试',
    'saving'             => '保存中...',
    'saved'              => '已保存',
    'save_failed'        => '保存失败',
    'restored'           => '已恢复',
    'operation_failed'   => '操作失败',
    'request_failed'     => '请求失败，请稍后重试',

    // 组件（标题 / 描述）
    'widget_stats'       => '内容统计',
    'widget_stats_desc'  => '各内容模型的条目数量与快捷入口',
    'widget_pages'       => '单页管理',
    'widget_pages_desc'  => '站点单页列表，可快速进入编辑',
    'widget_website'     => '网站信息',
    'widget_website_desc'=> '语言、模板、上传与运行环境概要',
    'widget_activity'    => '最近操作',
    'widget_activity_desc'=> '后台最近 8 条操作审计记录',
    'widget_system'      => '系统信息',
    'widget_system_desc' => 'CMS / PHP / 数据库 / 服务器详细参数',
    'widget_failed'      => '组件「{title}」加载失败',

    // 内容统计
    'manage'             => '管理 {title}',

    // 单页管理
    'page_count'         => '{count} 个页面',
    'empty_pages'        => '暂无单页内容',
    'title'              => '标题',
    'status'             => '状态',
    'action'             => '操作',
    'published'          => '已发布',
    'draft'              => '草稿',
    'edit'               => '编辑',

    // 网站信息
    'system_language'    => '系统语言',
    'site_theme'         => '站点模版',
    'upload_limit'       => '上传限制',
    'memory_limit'       => '内存限制',
    'php_version'        => 'PHP 版本',
    'database'           => '数据库',

    // 最近操作
    'log_count'          => '{count} 条',
    'empty_activity'     => '暂无操作记录',
    'action_label'       => '操作',
    'admin'              => '管理员',
    'category'           => '分类',
    'level'              => '级别',
    'time'               => '时间',

    // 系统信息
    'collapse'           => '折叠',
    'zapcms_version'     => 'ZapCMS 版本',
    'unsupported'        => '不支持',
    'not_installed'      => '未安装',
    'pdo_drivers'        => 'PDO 驱动',
    'os'                 => '操作系统',
    'sqlite_not_supported' => '当前环境不支持sqlite3',
];
