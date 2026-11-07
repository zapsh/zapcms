<?php

namespace zapcms\widgets;

use zapcms\models\Node;

/**
 * 最新内容小工具
 */
class RecentPostsWidget extends Widget
{
    public static function key(): string
    {
        return 'recent_posts';
    }

    public static function title(): string
    {
        return '最新内容';
    }

    public static function description(): string
    {
        return '按发布时间列出最近内容';
    }

    public static function icon(): string
    {
        return 'fa-solid fa-clock-rotate-left';
    }

    public static function defaults(): array
    {
        return ['limit' => 5, 'node_type' => ''];
    }

    public static function fields(): array
    {
        return [
            ['name' => 'limit', 'label' => '显示数量', 'type' => 'number', 'min' => 1, 'max' => 20],
            ['name' => 'node_type', 'label' => '内容模型（可选）', 'type' => 'text', 'placeholder' => '如 article，留空为全部'],
        ];
    }

    public function render(): string
    {
        $limit = (int) $this->config('limit', 5);
        $limit = max(1, min(20, $limit));

        $nodeType = trim((string) $this->config('node_type', ''));
        $query = Node::createQuery()
            ->select('id', 'title', 'slug', 'node_type', 'add_time', 'pub_time')
            ->where('status', 1)
            ->orderBy('id DESC')
            ->limit($limit);
        if ($nodeType !== '') {
            $query->where('node_type', $nodeType);
        }
        $rows = $query->get(FETCH_ASSOC) ?: [];

        if (empty($rows)) {
            return '<p class="text-muted mb-0">暂无内容</p>';
        }

        $html = '<ul class="widget-list list-unstyled mb-0">';
        foreach ($rows as $row) {
            $html .= '<li><a href="' . e(smart_node_url($row)) . '">' . e($row['title']) . '</a></li>';
        }
        $html .= '</ul>';

        return $html;
    }
}
