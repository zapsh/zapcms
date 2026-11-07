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

    function index(){
        pageState()->getCatalog();

        pageState()->catalogPaths = \zapcms\services\Translator::applyBatch(
            (array) Catalog::instance()->getCatalogPathById(pageState()->nodeId),
            null,
            \zapcms\services\Translator::TYPE_NODE,
            ['title', 'slug']
        );
        $lastKey = array_key_last(pageState()->catalogPaths );
        foreach (pageState()->catalogPaths as $key => $catalog){
            BreadCrumb::instance()->add($catalog['title'],smart_node_url(['node_type' => 'catalog', 'slug' => $catalog['slug'] ?? '', 'id' => $catalog['id'] ?? 0]),$key === $lastKey);
        }

        // 侧边栏菜单：使用后台「外观 > 菜单」的 MenuItem（left 位置），含译文与激活链路标记
        pageState()->subCatalogList = pageState()->getNavMenu('left');
        try{
            if(pageState()->nodeMimeType==='page') {
                view('page', ['node' => pageState()->node]);
                return;
            }else{
                // 模块接管整页列表渲染（约定式 catalog_render_{mime} 及旧式
                // catalog_product_list_render）；命中即接管并结束渲染。
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
                // 根据栏目 mimeType 选择不同的模板文件，若不存在则使用默认的模板文件
                $view = View::make( theme_file_is_exists(pageState()->nodeMimeType . '_list') ? pageState()->nodeMimeType.'_list' : pageState()->nodeMimeType);
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
                // 列表节点套用当前语言译文（只需标题/别名/摘要，不取正文避免列表查询过重）
                $nodes = \zapcms\services\Translator::applyBatch($nodes, null, \zapcms\services\Translator::TYPE_NODE, ['title', 'slug', 'excerpt']);
                $node_list_columns = \zapcms\services\NodeType::getConfig(pageState()->nodeMimeType,'list_columns',[]);
                // 批量加载 node_meta（如产品价格 price）附加到列表节点，避免 N+1 查询
                $view->nodes = NodeMeta::attachList($nodes, $node_list_columns);
                $view->page = $page;
                //// 模板页面没有相关联的栏目菜单时，读取左侧导航栏目菜单作为兜底（侧边栏位于页面左侧）
                if (empty(pageState()->subCatalogList) && !empty($view->nodes)) {
                    pageState()->subCatalogList = pageState()->getNavMenu('left');
                }
            }

        }catch (ViewNotFoundException $exception){
            $view = View::make(pageState()->nodeType);
        }
        $view->display();
    }


}