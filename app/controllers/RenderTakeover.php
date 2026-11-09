<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS
 */

namespace app\controllers;

use zap\view\View;
use zapcms\Hook;

/**
 * 内容模型页面「统一接管」trait。
 *
 * 约定式钩子（按内容模型类型命名），让 mod 在启动时注册过滤器即可接管/增强
 * 任意内容模型的页面输出，无需修改核心 controller：
 *
 *   node_render            主钩子（全类型，最高优先级）
 *   node_render_{mime}     按模型类型接管，如 node_render_product / node_render_article
 *   node_{mime}_render     旧式别名（向后兼容 mall 等现有模块，如 node_product_render）
 *
 * 列表页同理：
 *   catalog_render_{mime}      按模型类型接管栏目列表
 *   catalog_product_list_render 旧式别名（向后兼容）
 *
 * 搜索结果页同理（SearchController）：
 *   search_render              主钩子（全类型）
 *   search_render_{node_type}  按搜索类型接管，如 search_render_product
 *
 * 首页同理（IndexController）：
 *   home_render  接管站点首页
 *
 * 404 页面同理（NotFoundController）：
 *   not_found_render  接管 404 页面
 *
 * 回调可返回：
 *   - \zap\view\View 实例（推荐，自动套用主题模板/layout）
 *   - 非空字符串（直接输出，需自行拼装整页）
 *   - callable（返回上述任意类型）
 * 命中即视为接管，统一 emit 后结束渲染。
 */
trait RenderTakeover
{
    /**
     * 详情页统一接管入口
     */
    protected function renderNode(array $node, ?string $mime = null): void
    {
        $mime = $mime ?: ($node['node_type'] ?? 'page');

        $payload = [$node, $mime, $this];
        foreach (["node_render", "node_render_{$mime}", "node_{$mime}_render"] as $tag) {
            if ($this->tryTakeover($tag, $payload)) {
                return;
            }
        }

        // 默认主题模板（未知模型类型回退到通用 node 模板）
        $template = $mime === 'page' ? 'page' : ($mime === 'faq' ? 'faq-page' : $mime);
        if (!function_exists('theme_file_is_exists') || !theme_file_is_exists($template)) {
            $template = 'node';
        }
        view($template, ['node' => $node]);
    }

    /**
     * 列表页统一接管入口（命中即接管整页列表渲染）。
     * @return bool 是否已接管（true 时调用方应 return）
     */
    protected function renderCatalog(string $mime): bool
    {
        $payload = [pageState()->node ?: [], $mime, $this];
        if ($this->tryTakeover("catalog_render_{$mime}", $payload)) {
            return true;
        }
        return false;
    }

    protected function renderPage(string $mime): bool
    {
        $payload = [pageState()->node ?: [], $mime, $this];
        if ($this->tryTakeover('page_render', $payload)) {
            return true;
        }
        return false;
    }

    /**
     * 搜索结果页统一接管入口（命中即接管整页渲染）。
     * 约定式钩子：
     *   search_render              主钩子（全类型）
     *   search_render_{node_type}  按搜索类型接管，如 search_render_product
     * 回调签名：function ($_, $keyword, $searchType, $ctrl)
     * @return bool 是否已接管（true 时调用方应 return）
     */
    protected function renderSearch(string $keyword = '', string $searchType = ''): bool
    {
        $payload = [$keyword, $searchType, $this];
        $tags = ['search_render'];
        if ($searchType !== '') {
            $tags[] = "search_render_{$searchType}";
        }
        foreach ($tags as $tag) {
            if ($this->tryTakeover($tag, $payload)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 首页统一接管入口（命中即接管整页渲染）。
     * 约定式钩子：
     *   home_render  接管站点首页（IndexController）
     * 回调签名：function ($_, $ctrl)
     * @return bool 是否已接管（true 时调用方应 return）
     */
    protected function renderHome(): bool
    {
        if ($this->tryTakeover('home_render', [$this])) {
            return true;
        }
        return false;
    }

    /**
     * 404 页面统一接管入口（命中即接管整页渲染）。
     * 约定式钩子：
     *   not_found_render  接管 404 页面（NotFoundController）
     * 回调签名：function ($_, $uri, $ctrl)
     * @return bool 是否已接管（true 时调用方应 return）
     */
    protected function render404(): bool
    {
        $uri = isset($_SERVER['REQUEST_URI'])
            ? (string) urldecode(strtok($_SERVER['REQUEST_URI'], '?') ?: '/')
            : '/';
        if ($this->tryTakeover('not_found_render', [$uri, $this])) {
            return true;
        }
        return false;
    }

    /**
     * 触发过滤器式接管：回调返回 View / 非空字符串 / callable 即视为接管
     */
    protected function tryTakeover(string $tag, array $payload): bool
    {
        $override = Hook::applyFilters($tag, null, ...$payload);
        if ($this->isRenderable($override)) {
            $this->emit($override);
            return true;
        }
        return false;
    }

    protected function isRenderable($v): bool
    {
        if ($v instanceof View) {
            return true;
        }
        if (class_exists('\\zap\\http\\Response', false) && $v instanceof \zap\http\Response) {
            return true;
        }
        if (is_string($v) && trim($v) !== '') {
            return true;
        }
        if (is_callable($v)) {
            return $this->isRenderable($v());
        }
        return false;
    }

    protected function emit($v): void
    {
        if ($v instanceof View) {
            $v->display();
            return;
        }
        if (class_exists('\\zap\\http\\Response', false) && $v instanceof \zap\http\Response) {
            $v->send();
            return;
        }
        if (is_callable($v)) {
            $this->emit($v());
            return;
        }
        echo $v;
    }
}
