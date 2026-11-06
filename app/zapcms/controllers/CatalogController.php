<?php

namespace zapcms\controllers;

use zapcms\controllers\AdminController;
use zapcms\models\AdminLog;
use zapcms\services\Catalog;
use zapcms\services\Locale;
use zapcms\services\MenuLocation;
use zapcms\services\MenuItem;
use zapcms\services\NodeType;
use zapcms\services\SlugHelper;
use zapcms\services\Translator;
use zap\DB;
use zap\http\Request;
use zap\http\Response;
use zap\view\View;

/*
 * 栏目
 */
class CatalogController extends AdminController
{
    function index(){
        $data = [];
        $menu = Catalog::instance();
        $data['menu'] = $menu;
        $data['catalog_count'] = DB::table('catalog')->count();
        $page_title = '栏目管理';
        $breadcrumbs = [
            ['title' => '控制台', 'url' => url_action('Index')],
            ['title' => '栏目管理'],
        ];
        $data['page_title'] = $page_title;
        $data['breadcrumbs'] = $breadcrumbs;
        View::render("catalog.index",$data);
    }

    public function save()
    {
        $catalog = Request::post('catalog',[]);
        foreach ($catalog as $id=>$row){
            DB::update('catalog',$row,['id'=>$id]);
        }
        Response::json(['code'=>0,'msg'=>'保存成功']);
    }

    public function saveCatalog(){
        $catalog = Request::post('catalog',[]);
        $catalogId = intval(Request::post('catalog_id',0));
        if($catalog['node_type'] == 'link-url'){
            $catalog['slug'] = '--zap-link-url';
        }else{
            $catalog['slug'] = SlugHelper::generate(empty($catalog['slug']) ? $catalog['title'] : $catalog['slug']);
        }

        $menu = Catalog::instance();
        $cid = 0;
        if($catalogId){
            $menu->update($catalog,$catalogId);
            $this->saveCatalogTranslations($catalogId);
            $cid = $catalogId;
        }else{
            $newId = (int) $menu->add($catalog);
            if($newId > 0){
                $this->saveCatalogTranslations($newId);
                $cid = $newId;
            }
        }

        // 同步菜单位置勾选到 menu_items（新增缺失 / 移除未勾选）
        $menuLocations = Request::post('menu_locations', []);
        if ($cid > 0 && is_array($menuLocations)) {
            MenuItem::instance()->syncCatalogLocations($cid, $menuLocations);
        }
        // 栏目标题/URL 变化可能影响菜单回退显示，清菜单缓存
        MenuItem::instance()->clearMenuCache();

        AdminLog::info(
            AdminLog::CATEGORY_CONTENT,
            $catalogId ? '编辑栏目' : '新增栏目',
            '栏目：' . ($catalog['title'] ?? '') . ($catalogId ? ' #' . $catalogId : '')
        );

        Response::json(['code'=>0,'msg'=>'保存成功']);
    }

    /**
     * 保存栏目名称的其它语言译文
     *
     * 栏目同时存在 node / catalog 两条记录且 id 相同，译文统一按 node 维度存放。
     *
     * @param int $catalogId
     */
    protected function saveCatalogTranslations(int $catalogId): void
    {
        if ($catalogId <= 0 || !Locale::enabled()) {
            return;
        }

        $posted = Request::post('translation', []);
        if (!is_array($posted)) {
            return;
        }

        foreach (Locale::available() as $code) {
            if ($code === Locale::defaultLocale()) {
                continue;
            }
            $row = $posted[$code] ?? [];
            if (!is_array($row)) {
                continue;
            }
            Translator::save(Translator::TYPE_NODE, $catalogId, $code, $row);
        }
    }

    public function remove()
    {
        $catalog = Request::post('catalog',[]);
        $menu = Catalog::instance();
        foreach ($catalog as $id=>$row){
            $menu->remove($id);
            // 清理该栏目的多语言译文
            Translator::deleteAll(Translator::TYPE_NODE, intval($id));
        }
        AdminLog::info(AdminLog::CATEGORY_CONTENT, '删除栏目', '共 ' . count($catalog) . ' 个栏目');
        Response::json(['code'=>0,'msg'=>'分类删除成功']);
    }

    public function form()
    {

        $data['pid'] = intval(Request::get('pid',0));
        $data['cid'] = intval(Request::get('cid',0));
        if($data['pid']){
            $data['parent'] = Catalog::instance()->get($data['pid']);
        }

        $data['catalog'] = $data['cid'] == 0 ? [] : Catalog::instance()->get($data['cid']);

        // 前台菜单位置选项（含自定义位置）+ 本栏目已归属的位置
        $data['locations'] = Catalog::getLocationOptions();
        $locMap = MenuItem::instance()->catalogLocationMap();
        $data['catalog_locations'] = $data['cid'] > 0 ? ($locMap[$data['cid']] ?? []) : [];

        // 已保存的栏目译文（locale => row）
        $data['translations'] = !empty($data['catalog']['id']) && Locale::enabled()
            ? Translator::getAll(Translator::TYPE_NODE, (int) $data['catalog']['id'])
            : [];

        View::render('catalog.form',$data);
    }

    /**
     * 搜索可链接的内容（栏目 + 节点）
     *
     * @return void JSON
     */
    public function searchLinkTarget()
    {
        $keyword = trim((string) Request::get('keyword', ''));
        $limit   = min(50, max(1, intval(Request::get('limit', 30))));

        // 搜索栏目
        $catalogs = [];
        Catalog::instance()->forEachAll(function ($row) use (&$catalogs, $keyword) {
            if ($keyword !== '' && stripos($row['title'], $keyword) === false) {
                return;
            }
            $catalogs[] = [
                'id'         => (int) $row['id'],
                'title'      => $row['title'],
                'slug'       => $row['slug'] ?? '',
                'node_type'  => $row['node_type'] ?: 'catalog',
                'mime_type'  => $row['mime_type'] ?? '',
                'kind'       => 'catalog',
                'path_label' => str_repeat('— ', max(0, (int) $row['level'] - 1)) . $row['title'],
            ];
        });

        // 搜索节点（排除 catalog 类型，因为 catalog 已在上方列出）
        $nodeQuery = \zapcms\models\Node::createQuery()
            ->whereNotIn('node_type', ['catalog'])
            ->select('id', 'title', 'slug', 'node_type', 'mime_type', 'status');
        if ($keyword !== '') {
            $nodeQuery->where('title', 'LIKE', "%{$keyword}%");
        }
        $nodes = $nodeQuery->orderBy('id DESC')->limit($limit)->get(FETCH_ASSOC);
        $nodes = $nodes ?: [];
        foreach ($nodes as &$node) {
            $node['kind']       = 'node';
            $node['path_label'] = $node['title'];
        }
        unset($node);

        Response::json([
            'code'      => 0,
            'catalogs'  => $catalogs,
            'nodes'     => $nodes,
            'total'     => count($catalogs) + count($nodes),
        ]);
    }

    /**
     * 栏目树懒加载：按父级取直接子节点（用于后台"栏目管理"可展开树）。
     *
     * @return void JSON {nodes:[{id,pid,title,icon,slug,link_to,node_type,node_type_title,sort_order,has_children,locations:[{key,title}]}]}
     */
    public function children()
    {
        $parentId = intval(Request::get('parent_id', 0));

        $rows = Catalog::instance()->getChildren($parentId);

        // 位置标题 + 栏目归属映射（用于行内位置徽标，点击直达菜单构建器）
        $locTitles = [];
        foreach (MenuLocation::instance()->all() as $loc) {
            $locTitles[$loc['key']] = $loc['title'];
        }
        $catLocMap = MenuItem::instance()->catalogLocationMap();

        $nodes = [];
        foreach ($rows as $row) {
            $locs = array_values(array_intersect(
                array_keys($locTitles),
                $catLocMap[(int) $row['id']] ?? []
            ));
            $nodes[] = [
                'id'             => (int) $row['id'],
                'pid'            => (int) ($row['pid'] ?? 0),
                'title'          => $row['title'] ?? '',
                'icon'           => $row['icon'] ?? '',
                'slug'           => $row['slug'] ?? '',
                'link_to'        => $row['link_to'] ?? '',
                'node_type'      => $row['node_type'] ?: 'catalog',
                'node_type_title'=> NodeType::getTitle($row['node_type'] ?: 'catalog'),
                'sort_order'     => (int) ($row['sort_order'] ?? 0),
                'has_children'   => !empty($row['has_children']),
                'locations'      => array_map(function ($k) use ($locTitles) {
                    return ['key' => $k, 'title' => $locTitles[$k] ?? $k];
                }, $locs),
            ];
        }

        Response::json(['code' => 0, 'nodes' => $nodes, 'total' => count($nodes)]);
    }
}