<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS
 */

namespace app\controllers;

use zapcms\services\NodeMeta;
use zap\DB;
use zap\exception\ViewNotFoundException;
use zap\helpers\Pagination;
use zap\http\Controller;
use zap\view\View;
use app\controllers\RenderTakeover;


/**
 * 
 * 栏目
 * 
 * Class CatalogController
 * @package app\controllers
 * 
 */
class CatalogController extends Controller
{
    use RenderTakeover;

    public function _invoke(string $method, $params = [])
    {
        if(method_exists($this,$method)){
            $this->$method();
        }else{
            $this->index();
        }
    }

    /**
     * 解析栏目页模板，支持主题按分类 slug / id 建自定义布局：
     *
     *   catalog-{slug}.php   （如 themes/basic/catalog-about-us.php，slug 仅允许 ASCII 字母数字 - _）
     *   catalog-{id}.php     （中文 slug 或 slug 会变时更稳定，如 catalog-28.php）
     *   {mime}_list / {mime} （现有按内容模型的模板）
     *   catalog.php          （通用栏目模板）
     *
     * PHP 与 Twig 主题都支持（theme_file_is_exists 会试 .php / .twig）。
     */
    /**
     * 解析栏目列表显示样式：优先取栏目 node_meta 的 list_style（带请求级缓存）；
     * 未单独设置时按内容类型给默认 —— 文章 list，产品 card。
     */
    private function resolveListStyle(): string
    {
        $catId = (int) (pageState()->nodeId ?? 0);
        if ($catId > 0) {
            $catStyle = trim((string) (NodeMeta::get($catId, 'list_style') ?? ''));
            if ($catStyle !== '') {
                return $catStyle;
            }
        }
        // 未单独设置：按内容类型默认（产品卡片，文章/其它列表）
        $mime = (string) (pageState()->nodeMimeType ?? '');
        return $mime === 'product' ? 'card' : 'list';
    }

    private function resolveCatalogTemplate(string $style = ''): string
    {
        $node = pageState()->node ?: [];
        $mime = (string) (pageState()->nodeMimeType ?: '');
        $id   = (int) ($node['id'] ?? 0);
        $slug = strtolower((string) ($node['slug'] ?? ''));
        $safe = trim(substr(preg_replace('/[^a-z0-9_-]+/', '-', $slug) ?? '', 0, 80), '-');

        $candidates = [];
        if ($safe !== '') {
            $candidates[] = 'catalog-' . $safe;
        }
        if ($id > 0) {
            $candidates[] = 'catalog-' . $id;
        }

        if ($mime === 'page') {
            // 页面型栏目的正文在节点自身（node.content），不是下级节点列表。
            // 不能被「列表显示样式」强制切到 catalog.php，否则会因没有 $nodes 显示“暂无内容”。
            // 主题仍可用 catalog-{slug} / catalog-{id} 自定义模板覆盖。
            $candidates[] = 'page';
        } else {
            // 列表显示样式：非「卡片」的样式统一走样式化通用模板 catalog.php（覆盖按内容模型的模板）；
            // 卡片样式沿用主题/内容模型默认模板，保持原有产品列表等布局不被覆盖
            if ($style !== '' && $style !== 'card' && theme_file_is_exists('catalog')) {
                return 'catalog';
            }
            if ($mime !== '') {
                $candidates[] = $mime . '_list';
                $candidates[] = $mime;
            }
        }
        $candidates[] = 'catalog';

        foreach ($candidates as $template) {
            if (theme_file_is_exists($template)) {
                return $template;
            }
        }
        return 'catalog';
    }

    function index(){
        pageState()->getCatalog();
        $listStyle = $this->resolveListStyle();
        $template = $this->resolveCatalogTemplate($listStyle);

        // 混合列表子选项：mode（image 左图右文 / text 纯文本无图）与 feature（on 首条特推 / off 无特推）
        // 优先取栏目 node_meta，否则内置默认（左图右文 / 首条特推）
        $listMixedMode = 'image';
        $listMixedFeature = 'on';
        if ($listStyle === 'mixed') {
            $catMode = trim((string) (NodeMeta::get((int) pageState()->nodeId, 'list_style_mixed_mode') ?? ''));
            $listMixedMode = $catMode !== '' ? $catMode : 'image';
            $catFeat = trim((string) (NodeMeta::get((int) pageState()->nodeId, 'list_style_mixed_feature') ?? ''));
            $listMixedFeature = $catFeat !== '' ? $catFeat : 'on';
        }

        // 面包屑 / 栏目路径 / 侧栏导航均由主题模板通过 breadcrumb()/category_nav() 惰性获取（见 Nav 服务）
        try{
            if(pageState()->nodeMimeType==='page') {
                if ($this->renderPage(pageState()->nodeMimeType)) {
                    return;
                }
                view($template, ['node' => pageState()->node]);
                return;
            }else{
                if ($this->renderCatalog(pageState()->nodeMimeType)) {
                    return;
                }

                // 分页数量从内容模型显示配置读取（默认 12）
                $listPerPage = (int)\zapcms\services\NodeType::getConfig(pageState()->nodeMimeType, 'list_per_page', 12);
                $page = new Pagination(intval(req()->get('page',1)), max(1, $listPerPage), req()->get());
                // 修复分页链接：以当前栏目完整 URL 为基准（如 /products 或 /catalog/products），
                // 保留除 page 外的查询参数，并强制使用 ?page=N 形式。
                // 否则 URL 无查询参数时 path 为空，Pagination::url() 会生成 "/N" 这类错误链接；
                // 且 /products/N 路径形式无法被路由解析为 page 参数。
                $queryParams = req()->get();
                unset($queryParams['page']);
                $pagerBase = smart_node_url(pageState()->node ?: []);
                if ($pagerBase === '') {
                    $pagerBase = home_url();
                }
                $page->withPath($pagerBase . '?' . ($queryParams ? http_build_query($queryParams) . '&' : '') . 'page={page}');
                $view = View::make($template);
                $view->listStyle = $listStyle !== '' ? $listStyle : 'card';
                $view->listMixedMode = $listMixedMode;
                $view->listMixedFeature = $listMixedFeature;
                $query = DB::table('node_relation','nr')->leftJoin(['node','n'],'nr.node_id=n.id')
                    ->select('n.id','n.title','n.slug','n.image','n.pub_time','n.node_type','n.excerpt','n.description')
                    ->where('nr.catalog_id',pageState()->nodeId)
                    ->where('n.node_type', pageState()->nodeMimeType);
                // 图文列表模式需要正文 content 用于自动补足简介（当页面描述过短时）
                $needsContent = in_array($listStyle, ['list_image', 'mixed'], true);
                if (pageState()->nodeMimeType === 'faq' || $needsContent) {
                    $query->addSelect('n.content');
                }
                // 置顶优先
                $query->addSelect('n.is_top');
                $query->orderByRaw('n.is_top DESC');
                $query->orderBy('n.pub_time','DESC');
                $view->page = $page->setTotal($query->count('n.id'));
                $query->limit($page->getLimit())->offset($page->getOffset());
                $nodes = $query->get(FETCH_ASSOC);
                // 列表节点套用当前语言译文（只需标题/别名/摘要，不取正文避免列表查询过重）；
                // 多语言设置为「无译文不显示」时自动剔除未翻译条目
                $nodes = \zapcms\services\Translator::applyList($nodes, null, \zapcms\services\Translator::TYPE_NODE, ['title', 'slug', 'excerpt']);
                // 图文列表：简介过短时从正文提取纯文本补足，保证列表有足够的简短描述
                if ($needsContent) {
                    foreach ($nodes as &$n) {
                        $desc = trim(strip_tags((string)($n['description'] ?? '')));
                        if (mb_strlen($desc) < 60) {
                            $body = trim(strip_tags(html_entity_decode((string)($n['content'] ?? ''), ENT_QUOTES, 'UTF-8')));
                            $body = preg_replace('/\s+/u', ' ', $body) ?? '';
                            $n['list_excerpt'] = mb_substr($desc . ($desc !== '' && $body !== '' ? ' ' : '') . $body, 0, 300);
                        } else {
                            $n['list_excerpt'] = mb_substr($desc, 0, 300);
                        }
                    }
                    unset($n);
                }
                $node_list_columns = \zapcms\services\NodeType::getConfig(pageState()->nodeMimeType,'list_columns',[]);
                // 批量加载 node_meta（如产品价格 price）附加到列表节点，避免 N+1 查询
                $nodes = NodeMeta::attachList($nodes, $node_list_columns);
                // 列表卡片展示标签（图文列表右下角的 chips），批量读取避免逐行查询
                $tagTitlesByNode = \zapcms\services\Tag::getNodeTagTitlesBatch(array_column($nodes, 'id'));
                foreach ($nodes as &$n) {
                    $n['tags'] = $tagTitlesByNode[(int) ($n['id'] ?? 0)] ?? [];
                }
                unset($n);
                $view->nodes = $nodes;
                $view->page = $page;
            }

        }catch (ViewNotFoundException $exception){
            $view = View::make(pageState()->nodeType);
        }
        $view->display();
    }


}