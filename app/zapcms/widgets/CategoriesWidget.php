<?php

namespace zapcms\widgets;

use zapcms\services\Catalog;

/**
 * 栏目列表小工具
 */
class CategoriesWidget extends Widget
{
    public static function key(): string
    {
        return 'categories';
    }

    public static function title(): string
    {
        return '栏目列表';
    }

    public static function description(): string
    {
        return '列出某父栏目下的子栏目';
    }

    public static function icon(): string
    {
        return 'fa-solid fa-folder-tree';
    }

    public static function defaults(): array
    {
        return ['parent_id' => 0];
    }

    public static function fields(): array
    {
        return [
            ['name' => 'parent_id', 'label' => '父栏目 ID', 'type' => 'number', 'min' => 0, 'placeholder' => '0 表示顶级栏目'],
        ];
    }

    public function render(): string
    {
        $parentId = (int) $this->config('parent_id', 0);
        $rows = Catalog::instance()->getChildren($parentId);

        if (empty($rows)) {
            return '<p class="text-muted mb-0">暂无栏目</p>';
        }

        $html = '<ul class="widget-list list-unstyled mb-0">';
        foreach ($rows as $row) {
            $url = resolve_link_url([
                'link_type'   => 'catalog',
                'link_to'     => $row['slug'] ?? '',
                'link_object' => 0,
            ]);
            $html .= '<li><a href="' . e($url) . '">' . e($row['title']) . '</a></li>';
        }
        $html .= '</ul>';

        return $html;
    }
}
