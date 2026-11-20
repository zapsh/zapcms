<?php

namespace zapcms\widgets;

use zapcms\services\MenuLocation;

/**
 * 导航菜单小工具：输出某个菜单位置（复用 catalog_menu）
 */
class MenuWidget extends Widget
{
    public static function key(): string
    {
        return 'menu';
    }

    public static function title(): string
    {
        return '导航菜单';
    }

    public static function description(): string
    {
        return '输出一个菜单位置的导航';
    }

    public static function icon(): string
    {
        return 'fa-solid fa-bars';
    }

    public static function defaults(): array
    {
        return ['location_key' => 'left', 'ul_class' => 'nav flex-column'];
    }

    public static function fields(): array
    {
        return [
            ['name' => 'location_key', 'label' => '菜单位置', 'type' => 'select', 'options' => MenuLocation::instance()->options()],
            ['name' => 'ul_class', 'label' => '列表 class', 'type' => 'text', 'placeholder' => 'nav flex-column'],
        ];
    }

    public function render(): string
    {
        $key = (string) $this->config('location_key', '');
        if ($key === '') {
            return '<p class="text-muted mb-0">未选择菜单位置</p>';
        }

        return catalog_menu($key, [
            'ul_class' => (string) $this->config('ul_class', 'nav flex-column'),
        ]);
    }
}
