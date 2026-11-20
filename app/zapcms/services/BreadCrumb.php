<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS - All Rights Reserved
 * @author Allen
 * @email zapcms@zap.cn
 * @date 2023/12/27 上午11:28
 * @lastModified 2026/08/10
 */

namespace zapcms\services;

use zap\traits\SingletonTrait;

/**
 * Breadcrumb navigation builder (singleton, chainable).
 *
 * Usage:
 *   BreadCrumb::instance()
 *       ->add('首页', base_url('/'))
 *       ->add('栏目', site_url('/news'))
 *       ->add('详情', activated: true);
 *
 *   // in view:
 *   <?= BreadCrumb::instance() ?>
 *   <?php foreach (BreadCrumb::instance()->toArray() as $item): ?> ... <?php endforeach; ?>
 */
class BreadCrumb
{
    use SingletonTrait;

    /** @var list<array{title: string, url: string, active: bool}> */
    protected array $items = [];

    /** ─────────────── Mutation ─────────────── */

    /**
     * Add a breadcrumb item (returns self for chaining).
     *
     * @param string      $title      Display text
     * @param string|null $url        Link URL (null → '#'); considered decorative on active items
     * @param bool        $activated  true  = current page (rendered as <span>, not <a>)
     */
    public function add(string $title, ?string $url = null, bool $activated = false)
    {
        $this->items[] = ['title' => $title, 'url' => $url ?? '#', 'active' => $activated];
        return $this;
    }

    /** Remove the most recently added item. */
    public function pop()
    {
        array_pop($this->items);
        return $this;
    }

    /** Remove all items. */
    public function clear()
    {
        $this->items = [];
        return $this;
    }

    /** ─────────────── Query ─────────────── */

    public function count(): int    { return count($this->items); }
    public function isEmpty(): bool { return empty($this->items); }

    /**
     * Return items as plain array (for custom rendering).
     *
     * @return list<array{title: string, url: string, active: bool}>
     */
    public function toArray(): array
    {
        return $this->items;
    }

    /** ─────────────── Rendering ─────────────── */

    /**
     * Render Bootstrap 5 breadcrumb HTML.
     *
     * @param bool $echo   true = echo;  false = return string
     */
    public function display(bool $echo = true): ?string
    {
        if ($this->isEmpty()) {
            return null;
        }

        $divider = "--bs-breadcrumb-divider: url('data:image/svg+xml,"
            . "%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%228%22 height=%228%22"
            . "%3E%3Cpath d=%22M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z%22"
            . " fill=%22%236c757d%22/%3E%3C/svg%3E');";

        $html  = "<nav style=\"{$divider}\" aria-label=\"breadcrumb\">";
        $html .= '<ol class="breadcrumb mb-0">';

        $last = array_key_last($this->items);
        foreach ($this->items as $i => $item) {
            $title = htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8');
            $url   = htmlspecialchars($item['url'],   ENT_QUOTES, 'UTF-8');
            $isLast = ($i === $last) || $item['active'];

            if ($isLast) {
                // Current page — <span>, no link
                $html .= "<li class=\"breadcrumb-item active\" aria-current=\"page\">";
                $html .= "<span class=\"text-secondary\">{$title}</span></li>";
            } else {
                $html .= "<li class=\"breadcrumb-item\">";
                $html .= "<a href=\"{$url}\">{$title}</a></li>";
            }
        }

        $html .= '</ol></nav>';

        if ($echo) {
            echo $html;
            return null;
        }
        return $html;
    }

    /**
     * So that <?= BreadCrumb::instance() ?> also works.
     */
    public function __toString(): string
    {
        return $this->display(false) ?? '';
    }
}
