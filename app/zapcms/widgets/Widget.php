<?php

namespace zapcms\widgets;

use zapcms\Hook;

/**
 * 小工具基类
 *
 * 一个小工具 = 一个 Widget 子类：
 *   - key()/title()/description()/icon()：注册元信息；
 *   - defaults()：默认配置（JSON 存储于 widgets.config，不含 title）；
 *   - fields()：后台表单字段定义（不含 title，title 由框架统一提供）；
 *   - render()：输出正文 HTML（不含标题外框，外框由 output() 统一包裹）。
 *
 * 扩展方式：
 *   - 插件/主题自行创建 Widget 子类，通过 WidgetManager::register(类名) 注册；
 *   - 或用 Hook::filter('widgets.register', ...) 动态增删注册表。
 */
abstract class Widget
{
    /** @var array 实例数据（widgets 表一行，含 title / config / area 等） */
    protected array $instance = [];

    public function __construct(array $instance = [])
    {
        $this->instance = $instance;
    }

    abstract public static function key(): string;

    abstract public static function title(): string;

    public static function description(): string
    {
        return '';
    }

    public static function icon(): string
    {
        return 'fa-solid fa-puzzle-piece';
    }

    /** 默认配置（不含 title） */
    public static function defaults(): array
    {
        return [];
    }

    /** 后台表单字段定义：['name','label','type'(text|textarea|number|select|checkbox),'options'?,...] */
    public static function fields(): array
    {
        return [];
    }

    public function instance(): array
    {
        return $this->instance;
    }

    /** 实例标题（widgets.title） */
    public function widgetTitle(): string
    {
        return (string) ($this->instance['title'] ?? '');
    }

    /** 读取配置项 */
    public function config(string $name, $default = null)
    {
        $config = $this->instance['config'] ?? [];
        return $config[$name] ?? $default;
    }

    abstract public function render(): string;

    /** 渲染完整输出：标题外框 + 正文，经 widgets.output 过滤器 */
    public function output(): string
    {
        $body = (string) $this->render();
        $key  = static::key();

        if ($this->widgetTitle() !== '') {
            $html = '<div class="widget widget-' . $key . '">'
                . '<h3 class="widget-title">' . e($this->widgetTitle()) . '</h3>'
                . '<div class="widget-body">' . $body . '</div></div>';
        } else {
            $html = '<div class="widget widget-' . $key . '"><div class="widget-body">' . $body . '</div></div>';
        }

        return Hook::applyFilters('widget.output', $html, $key, $this->instance);
    }
}
