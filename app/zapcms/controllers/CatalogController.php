<?php

namespace zapcms\controllers;

use zapcms\controllers\AdminController;
use zapcms\models\AdminLog;
use zapcms\services\Catalog;
use zapcms\services\Locale;
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

        // 菜单归属统一在「外观 > 菜单」构建器里维护，栏目保存不再同步菜单位置；
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
     * 快速新建分区/栏目（内容表单内联新建，无需离开编辑页）
     *
     * POST: title（必填）、slug（可选，留空自动生成）、pid（可选，默认 0 顶级）、
     *       node_type（可选，默认 catalog；内容表单会传入当前内容模型）
     */
    public function quickCreate()
    {
        $title = trim((string) Request::post('title', ''));
        if ($title === '') {
            Response::json(['code' => 1, 'msg' => '请填写分区名称']);
            return;
        }

        $slug = trim((string) Request::post('slug', ''));
        $slug = SlugHelper::generate($slug !== '' ? $slug : $title);
        // 栏目 slug 影响路由，避免与已有栏目重复
        if ($slug !== '' && DB::table('catalog')->where('slug', $slug)->exists()) {
            $slug = $slug . '-' . time();
        }

        $nodeType = trim((string) Request::post('node_type', ''));
        if ($nodeType === '') {
            $nodeType = 'catalog';
        }

        $catalog = [
            'title'      => $title,
            'slug'       => $slug,
            'node_type'  => $nodeType,
            'pid'        => intval(Request::post('pid', 0)),
            'sort_order' => intval(Request::post('sort_order', 0)),
        ];

        $newId = (int) Catalog::instance()->add($catalog);
        if ($newId <= 0) {
            Response::json(['code' => 1, 'msg' => '创建失败，请稍后重试']);
            return;
        }

        $row = Catalog::instance()->get($newId) ?: [];
        AdminLog::info(AdminLog::CATEGORY_CONTENT, '新增栏目', '栏目：' . $title . ' #' . $newId);

        Response::json([
            'code'    => 0,
            'msg'     => '创建成功',
            'catalog' => [
                'id'    => $newId,
                'title' => $title,
                'level' => (int) ($row['level'] ?? 1),
                'pid'   => (int) ($row['pid'] ?? 0),
            ],
        ]);
    }

    /**
     * 返回内容表单「分类」卡片的勾选列表 HTML（用于快速新建后原地刷新该卡片）
     *
     * GET: node_type（可选，按内容模型过滤栏目）
     */
    public function catalogOptions()
    {
        $nodeType = trim((string) Request::get('node_type', ''));
        $conditions = $nodeType !== '' ? ['node_type' => $nodeType] : [];
        $catalogList = Catalog::instance()->getTreeArray($conditions, ['columns' => ['title', 'level']]);
        $nodeRelations = [];

        ob_start();
        include base_path('app/zapcms/node/views/_catalog_options.php');
        $html = ob_get_clean();

        Response::json(['code' => 0, 'html' => $html]);
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
        $keyword  = trim((string) Request::get('keyword', ''));
        $limit    = min(50, max(1, intval(Request::get('limit', 30))));
        $nodeType = trim((string) Request::get('node_type', '')); // 按内容模型类型过滤栏目

        // 搜索栏目：条件下推 SQL + LIMIT，避免上万分类时全表拉取再在 PHP 里过滤
        $conditions = $nodeType !== '' ? ['node_type' => $nodeType] : [];
        $catalogs   = [];
        foreach (Catalog::instance()->search($keyword, $limit, $conditions, '', ['slug', 'node_type']) as $row) {
            $catalogs[] = [
                'id'         => (int) $row['id'],
                'title'      => $row['title'],
                'slug'       => $row['slug'] ?? '',
                'node_type'  => $row['node_type'] ?? 'catalog',
                'mime_type'  => $row['mime_type'] ?? '',
                'kind'       => 'catalog',
                'path_label' => str_repeat('— ', max(0, (int) ($row['level'] ?? 1) - 1)) . $row['title'],
            ];
        }

        // 搜索节点（排除 catalog 类型，因为 catalog 已在上方列出）
        $nodeQuery = \zapcms\models\Node::createQuery()
            ->whereNotIn('node_type', ['catalog'])
            ->select('id', 'title', 'slug', 'node_type', 'mime_type', 'status');
        if ($keyword !== '') {
            $nodeQuery->where('title', 'LIKE', "%{$keyword}%");
        }
        // 按节点类型过滤（如菜单「单页」面板传 node_type=page）
        if ($nodeType !== '') {
            $nodeQuery->where('node_type', '=', $nodeType);
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
     * 父级下拉的分类搜索（AJAX）：后台栏目表单在分类上万时按需搜索，不再渲染整棵树
     *
     * @return void JSON {options:[{id,title,level,path}]}
     */
    public function options()
    {
        $keyword     = trim((string) Request::get('keyword', ''));
        $limit       = min(100, max(1, intval(Request::get('limit', 30))));
        $excludePath = (string) Request::get('exclude_path', '');

        $rows = Catalog::instance()->search($keyword, $limit, [], $excludePath);
        $options = [];
        foreach ($rows as $row) {
            $options[] = [
                'id'    => (int) $row['id'],
                'title' => (string) ($row['title'] ?? ''),
                'level' => (int) ($row['level'] ?? 1),
                'path'  => (string) ($row['path'] ?? ''),
            ];
        }
        Response::json(['code' => 0, 'options' => $options]);
    }

    /**
     * 栏目树懒加载：按父级取直接子节点（用于后台"栏目管理"可展开树）。
     *
     * @return void JSON {nodes:[{id,pid,title,icon,slug,link_to,node_type,node_type_title,sort_order,has_children}]}
     */
    public function children()
    {
        $parentId = intval(Request::get('parent_id', 0));

        $rows = Catalog::instance()->getChildren($parentId);

        $nodes = [];
        foreach ($rows as $row) {
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
            ];
        }

        Response::json(['code' => 0, 'nodes' => $nodes, 'total' => count($nodes)]);
    }
}