<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS
 */

namespace app\controllers;

use zapcms\services\BreadCrumb;
use zapcms\services\Catalog;
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

    public function __construct()
    {
        BreadCrumb::instance()->add('首页',base_url('/'));
    }

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
    private function resolveCatalogTemplate(): string
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
        if ($mime !== '') {
            if ($mime !== 'page') {
                $candidates[] = $mime . '_list';
            }
            $candidates[] = $mime;
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
        $template = $this->resolveCatalogTemplate();

        pageState()->catalogPaths = \zapcms\services\Translator::applyBatch(
            (array) Catalog::instance()->getCatalogPathById(pageState()->nodeId),
            null,
            \zapcms\services\Translator::TYPE_NODE,
            ['title', 'slug']
        );
        $lastKey = array_key_last(pageState()->catalogPaths );
        foreach (pageState()->catalogPaths as $key => $catalog){
            BreadCrumb::instance()->add($catalog['title'],smart_node_url(['node_type' => 'category', 'slug' => $catalog['slug'] ?? '', 'id' => $catalog['id'] ?? 0]),$key === $lastKey);
        }

        // 侧边栏菜单：使用后台「外观 > 菜单」的 MenuItem（left 位置），只显示当前栏目所在分支
        pageState()->subCatalogList = pageState()->getSidebarMenu('left');
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
                $query = DB::table('node_relation','nr')->leftJoin(['node','n'],'nr.node_id=n.id')
                    ->select('n.id','n.title','n.slug','n.image','n.pub_time','n.node_type')
                    ->where('nr.catalog_id',pageState()->nodeId)
                    ->where('n.node_type', pageState()->nodeMimeType);
                // FAQ 模型需要额外查询 content 字段    
                if(pageState()->nodeMimeType === 'faq'){
                    $query->addSelect('n.content');
                }
                $view->page = $page->setTotal($query->count('n.id'));
                $query->limit($page->getLimit())->offset($page->getOffset());
                $nodes = $query->get(FETCH_ASSOC);
                // 列表节点套用当前语言译文（只需标题/别名/摘要，不取正文避免列表查询过重）；
                // 多语言设置为「无译文不显示」时自动剔除未翻译条目
                $nodes = \zapcms\services\Translator::applyList($nodes, null, \zapcms\services\Translator::TYPE_NODE, ['title', 'slug', 'excerpt']);
                $node_list_columns = \zapcms\services\NodeType::getConfig(pageState()->nodeMimeType,'list_columns',[]);
                // 批量加载 node_meta（如产品价格 price）附加到列表节点，避免 N+1 查询
                $view->nodes = NodeMeta::attachList($nodes, $node_list_columns);
                $view->page = $page;
                //// 模板页面没有相关联的栏目菜单时，读取左侧导航栏目菜单作为兜底（侧边栏位于页面左侧）
                if (empty(pageState()->subCatalogList) && !empty($view->nodes)) {
                    pageState()->subCatalogList = pageState()->getSidebarMenu('left');
                }
            }

        }catch (ViewNotFoundException $exception){
            $view = View::make(pageState()->nodeType);
        }
        $view->display();
    }


}