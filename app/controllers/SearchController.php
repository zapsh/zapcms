<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS
 */

namespace app\controllers;

use app\PageState;
use zapcms\services\BreadCrumb;
use zapcms\services\Catalog;
use zapcms\models\Node;
use zap\helpers\Pagination;
use zap\http\Controller;

class SearchController extends Controller
{
    use RenderTakeover;

    public function __construct()
    {
        BreadCrumb::instance()->add('首页',base_url('/'));
        BreadCrumb::instance()->add('搜索','#',true);
    }

    function index(){
        $keyword = trim(req()->get('q'));

        // 搜索类型（product/article/faq/catalog，可为空）
        $searchType = trim((string)req()->get('node_type'));

        // 模块接管整页搜索结果渲染（约定式 search_render / search_render_{type}）；
        // 命中即接管并结束渲染，置于默认查询之前，接管时不必重复查询。
        if ($this->renderSearch($keyword, $searchType)) {
            return;
        }

        // 分页数量从内容模型显示配置读取（按请求 node_type 区分，默认 12）
        $listPerPage = (int)\zapcms\services\NodeType::getConfig($searchType !== '' ? $searchType : 'article', 'list_per_page', 12);
        $page = new Pagination(intval(req()->get('page',1)), max(1, $listPerPage), req()->get());
        // 修复分页链接：以搜索页 /search 为基准，保留 q/node_type 等查询参数，
        // 强制使用 ?page=N 形式，避免 path 为空时生成 "/N" 这类错误链接。
        $queryParams = req()->get();
        unset($queryParams['page']);
        $page->withPath(append_url_suffix(locale_site_url('/search')) . '?' . ($queryParams ? http_build_query($queryParams) . '&' : '') . 'page={page}');
        pageState()->subCatalogList = pageState()->getSearchSidebarMenu();
        $query = Node::where('title','LIKE',"%{$keyword}%")
            ->where('status',Node::STATUS_PUBLISH)
            ->where('node_type','IN',['product','article','faq','catalog']);
        // set total
        $page->setTotal($query->count());
        // limit
        $query->limit($page->getLimit(),$page->getOffset());
        view('search',[
            'data_list'=> \zapcms\services\Translator::applyBatch(
                $query->get(FETCH_ASSOC),
                null,
                \zapcms\services\Translator::TYPE_NODE,
                ['title', 'slug', 'excerpt']
            ),
            'page' => $page,
            'query' => $keyword,
        ]);
    }
}