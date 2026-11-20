<?php

namespace zapcms\node;

use zapcms\services\BreadCrumb;
use zapcms\services\Catalog;
use zapcms\services\Locale;
use zapcms\services\NodeMeta;
use zapcms\services\NodeType;
use zapcms\services\SlugHelper;
use zapcms\services\Translator;
use zapcms\support\HtmlXss;
use zapcms\models\AdminLog;
use zapcms\models\Node;
use zapcms\models\NodeRelation;
use zap\DB;
use zap\db\Query;
use zap\exception\ViewNotFoundException;
use zap\helpers\Pagination;
use zap\http\Request;
use zap\http\Response;
use zap\http\Router;
use zap\util\Arr;
use zap\util\Str;
use zap\view\View;
use zapcms\Hook;

class AbstractNodeType
{
    protected $nodeType;

    protected $catalogId;
    protected $nodeId;

    public $controller;
    public $action;

    protected $title;

    protected $isAjax;

    public $pageHelper;

    public function __construct()
    {
        $this->catalogId = intval(Request::get('cid'));
        $this->isAjax = Request::isAjax();
        BreadCrumb::instance()->add('内容管理',url_action('Node'));
        // View::share('body_class','layout-fixed sidebar-expand-lg sidebar-mini sidebar-collapse bg-body-tertiary app-loaded');
    }

    public function __init(){

    }

    //controller actions
    public function index(){
        $data['title'] = $this->getTitle("%s管理");
        $data['page_title'] = $data['title'];
        $baseType = (string) $this->nodeType;
        if ($baseType === 'default') {
            // 通用内容管理允许通过 ?type=xxx 按模型筛选；无有效值时显示全部模型
            $typeFilter = trim((string) Request::get('type', ''));
            $baseType   = ($typeFilter !== '' && NodeType::getID($typeFilter)) ? $typeFilter : '';
        }
        $conditions = ['where'=>[]];
        if ($baseType !== '') {
            $conditions['where']['n.node_type'] = $baseType;
        } else {
            // 通用内容管理未指定模型时排除虚拟栏目类型
            $conditions['where'][] = ['n.node_type', 'NOT IN', ['category', 'tag']];
        }

        if ($this->catalogId) {
            // 栏目筛选：默认精确匹配；勾选「包含子栏目」时按 path 展开后代
            $catalogIds = [(int) $this->catalogId];
            if (Request::get('with_child')) {
                $cid = (int) $this->catalogId;
                $descRows = DB::table('node')
                    ->where(function ($q) use ($cid) {
                        $q->where('id', $cid)
                          ->orWhere('path', 'LIKE', $cid . ',%')
                          ->orWhere('path', 'LIKE', '%,' . $cid . ',%');
                    })
                    ->select('id')->get(FETCH_ASSOC);
                foreach ($descRows as $r) {
                    $catalogIds[(int) $r['id']] = (int) $r['id'];
                }
            }
            $conditions['where'][] = ['nr.catalog_id', 'IN', array_values($catalogIds)];
        }

        // ── 列表筛选：搜索（标题/别名模糊）+ 状态 ──────────────
        $keyword = trim((string)Request::get('s', ''));
        if ($keyword !== '') {
            // 包一层数组：prepareConditions 用 where(...$value) 展开，闭包须作为单参传入
            $conditions['where'][] = [function ($q) use ($keyword) {
                $q->where('n.title', 'LIKE', "%{$keyword}%")
                  ->orWhere('n.slug', 'LIKE', "%{$keyword}%");
            }];
        }
        // 标签筛选：标签是 node_type='tag' 的节点，关联存于 node_relation.catalog_id
        $tagFilter = trim((string) Request::get('tag', ''));
        if ($tagFilter !== '') {
            $tagRows = DB::table('node')
                ->where('node_type', 'tag')
                ->where('title', $tagFilter)
                ->select('id')->get(FETCH_ASSOC);
            $tagIds = array_map('intval', array_column($tagRows, 'id'));
            $taggedNodeIds = [];
            if ($tagIds !== []) {
                $relRows = DB::table('node_relation')
                    ->whereIn('catalog_id', $tagIds)
                    ->select('node_id')->get(FETCH_ASSOC);
                $taggedNodeIds = array_map('intval', array_column($relRows, 'node_id'));
            }
            $conditions['where'][] = ['n.id', 'IN', $taggedNodeIds !== [] ? $taggedNodeIds : [0]];
        }
        // 状态快捷筛选计数（沿用当前模型/栏目/关键词/标签条件，只切换 status）
        $statusCounts = [];
        foreach ([
            'all'    => null,
            'publish'=> Node::STATUS_PUBLISH,
            'draft'  => Node::STATUS_DRAFT,
            'trash'  => Node::STATUS_TRASH,
        ] as $statusKey => $statusValue) {
            $countConditions = $conditions;
            if ($statusValue !== null) {
                $countConditions['where']['n.status'] = $statusValue;
            }
            $statusCounts[$statusKey] = (int) $this->getTotalRows($countConditions);
        }
        $data['status_counts'] = $statusCounts;

        $statusFilter = (string)Request::get('status', '');
        if (in_array($statusFilter, [Node::STATUS_PUBLISH, Node::STATUS_DRAFT, Node::STATUS_TRASH, Node::STATUS_SOFT_DELETE], true)) {
            $conditions['where']['n.status'] = $statusFilter;
        }

        $conditions = apply_filters('node_total_conditions',$conditions);
        // 后台列表分页数量与前台统一走模型显示配置 list_per_page，允许 ?per_page= 覆盖
        $perPage = max(1, (int)NodeType::getConfig($baseType !== '' ? $baseType : 'article', 'list_per_page', 20));
        $requestedPerPage = (int) Request::get('per_page', 0);
        if (in_array($requestedPerPage, [10, 20, 50, 100], true)) {
            $perPage = $requestedPerPage;
        }
        $page = $this->usePageHelper($this->getTotalRows($conditions), 'page', $perPage);
        // ── 排序：白名单校验 + n. 前缀（cid 时 join 两表都有 id 列，裸写会歧义）──
        $conditions['orderBy'] = 'n.is_top DESC, ' . $this->resolveOrderBy((string)Request::get('sort', ''));
        $conditions['limit'] = [$page->getLimit(),$page->getOffset()];
        $conditions = apply_filters('node_get_all_conditions',$conditions);
        $data['data'] = $this->getAll($conditions);
        // 多语言开启时，列表按当前后台语言套用译文（仅标题/别名/摘要，未翻译字段
        // 回退主表原文；未开启多语言或当前为默认语言时 applyBatch 原样返回、零额外查询）
        $data['data'] = Translator::applyBatch(
            $data['data'],
            null,
            Translator::TYPE_NODE,
            ['title', 'slug', 'excerpt']
        );
        // 批量读取列表内容的所属栏目路径，避免表格里逐行查询栏目（支持「包含子栏目」后的混合结果）
        $data['row_catalog_paths'] = [];
        $nodeIds = array_map('intval', array_column($data['data'], 'id'));
        if ($nodeIds !== []) {
            $relations = DB::table('node_relation', 'nr')
                ->leftJoin(['node', 'c'], 'nr.catalog_id=c.id')
                ->whereIn('nr.node_id', $nodeIds)
                ->orderBy('nr.level', 'ASC')
                ->get(FETCH_ASSOC);
            foreach ($relations as $rel) {
                $nid = (int) $rel['node_id'];
                if (!empty($rel['title'])) {
                    $data['row_catalog_paths'][$nid][] = [
                        'id'    => (int) ($rel['catalog_id'] ?? 0),
                        'title' => (string) $rel['title'],
                    ];
                }
            }
        }

        // 批量读取列表内容的标签标题，挂到每行（供文章/产品列表的标签列使用）
        $tagTitlesByNode = \zapcms\services\Tag::getNodeTagTitlesBatch($nodeIds);
        foreach ($data['data'] as &$row) {
            $row['tags'] = $tagTitlesByNode[(int)$row['id']] ?? [];
        }
        unset($row);

        // 列表默认展示的自定义字段列（node_meta 键，来自显示配置勾选）
        $data['list_columns'] = $this->getListColumns();
        if (!empty($data['list_columns'])) {
            $data['data'] = NodeMeta::attachList(
                $data['data'],
                array_column($data['list_columns'], 'field_name')
            );
        }
        $this->display($data);
    }

    /**
     * 获取列表默认展示的自定义字段（含字段标签），来自内容模型显示配置 list_columns
     * @return array [['field_name'=>..., 'field_label'=>...], ...]
     */
    protected function getListColumns(): array
    {
        $columns = (array)NodeType::getConfig((string)$this->nodeType, 'list_columns', []);
        if (empty($columns)) {
            return [];
        }
        $labelMap = [];
        foreach (NodeType::getFields((string)$this->nodeType) as $f) {
            $labelMap[$f['field_name']] = $f['field_label'] ?? $f['field_name'];
        }
        $result = [];
        foreach ($columns as $name) {
            $name = (string)$name;
            $result[] = [
                'field_name'  => $name,
                'field_label' => $labelMap[$name] ?? $name,
            ];
        }
        return $result;
    }

    public function edit($id = 0){
        $id = intval($id);
        if(!$id){
            $this->redirectTo("Node@{$this->action}",$_GET,$this->getTitle("%s不存在"),'error');
        }

        if(Request::isPost()){
            $node = Request::post('node');
            // 模型专用入口编辑时锁定内容模型，防止请求改写成其它模型
            if ($this->nodeType !== 'default') {
                $node['node_type'] = $this->nodeType;
            }
            // 所有者（author_id）是创建者，编辑时禁止被表单/请求改写，固定为原值
            if ($id > 0) {
                $origAuthor = DB::table('node')->select('author_id')->where('id', $id)->fetchColumn();
                if ($origAuthor !== null) {
                    $node['author_id'] = (int) $origAuthor;
                }
            }
            $catalogArray = Request::post('catalog',[]);
            $node['update_time'] = time();
            $node['pub_time'] = strtotime($node['pub_time']) ?: time();
            $node['content'] = HtmlXss::clean($node['content']);
            // slug：优先使用手动输入，否则从标题生成
            if (!empty($node['slug'])) {
                $node['slug'] = SlugHelper::generate($node['slug']);
            } elseif (!empty($node['title'])) {
                $node['slug'] = SlugHelper::generate($node['title']);
            }
            // slug 唯一性校验
            if (!empty($node['slug'])) {
                $exist = DB::table('node')->where('id','!=',$id)->where('slug',$node['slug'])->fetchColumn();
                if ($exist) {
                    $node['slug'] = $node['slug'] . '-' . $id;
                }
            }
            NodeRelation::delete(['node_id'=>$id]);
            foreach ($catalogArray as $catalog_id=>$level){
                NodeRelation::create(['node_id'=>$id,'catalog_id'=>$catalog_id,'level'=>$level]);
            }
            Node::updateAll($node,['id'=>$id]);
            // 保存自定义字段（node_meta）
            Node::findById($id)->saveMeta(Request::post('meta', []));
            // 保存标签（全量替换）
            \zapcms\services\Tag::saveNodeTags($id, $this->parseTags(Request::post('tags', '')));
            // 保存多语言译文（translations）
            $this->saveTranslations($id);
            // 审计日志：编辑内容
            AdminLog::info(
                AdminLog::CATEGORY_CONTENT,
                '编辑' . $this->title,
                '标题：' . ($node['title'] ?? '') . '（' . $this->nodeType . ' #' . $id . '）'
            );
            // slug 可能被唯一性校验改写（重名补 -id 后缀），一并返回给前端回写，
            // 前端据此就地更新页面，不再整体刷新（见 Zap.saveNodeForm）
            Response::json([
                'code'=>0,
                'msg'=>$this->getTitle("%s修改成功"),
                'id'=>$id,
                'slug'=>$node['slug'] ?? '',
                'update_time'=>$node['update_time'] ?? time(),
                'title'=>$this->getTitle("修改%s"),
            ]);
        }
        $this->nodeId = $id;
        $data['title'] = $this->title;
        $data['sub_title'] = $this->getTitle("修改%s");
        $node = Node::findById($id);
        if (!$node) {
            return $this->add();
        }
        $data['node'] = $node;
        $data['node_relations'] = $this->getNodeRelationships($id);
        // URL 未带 ?cid 时（如单页列表直接 edit/{id}），从当前编辑节点推导所属栏目，
        // 供左侧「栏目导航」高亮与面包屑使用
        if (!$this->catalogId) {
            if ($node->node_type === 'category') {
                // 分类节点（如单页栏目）自身即选中栏目
                $this->catalogId = $id;
            } else {
                $cid = NodeRelation::find(['node_id' => $id])
                    ->select('catalog_id')->orderBy('level desc')->fetchColumn();
                $cid and $this->catalogId = (int) $cid;
            }
        }
        // 分类树按「内容模型」过滤：内容节点取 node_type；分类节点（如单页栏目）自身
        // node_type='category'，其内容模型在 mime_type 上
        $catalogFilterType = $data['node']['node_type'] === 'category'
            ? (string) ($data['node']['mime_type'] ?? '')
            : $data['node']['node_type'];
        $data['catalogList'] = Catalog::instance()->getTreeArray(
            $catalogFilterType !== '' ? ['mime_type' => $catalogFilterType] : [],
            ['columns' => ['title', 'level']]
        );
        $data['nodeTagTitles'] = \zapcms\services\Tag::getNodeTagTitles($id);
        $this->display($data,'form');
    }

    /**
     * 解析提交的标签字符串（逗号/空格/顿号/换行分隔，去重、去空、trim）
     *
     * @param mixed $raw
     * @return array
     */
    protected function parseTags($raw): array
    {
        if (is_array($raw)) {
            $list = $raw;
        } else {
            $list = preg_split('/[\s,，、]+/u', (string)$raw);
        }
        $out = [];
        foreach ($list as $t) {
            $t = trim((string)$t);
            if ($t !== '' && !in_array($t, $out, true)) {
                $out[] = $t;
            }
        }
        return $out;
    }

    public function add()
    {
        if(Request::isPost()){
            $node = Request::post('node');
            $catalogArray = Request::post('catalog',[]);
            // 只有通用发布表单（node/default/add）允许显式指定内容模型；
            // 模型专用入口（如 node/download/add）固定使用当前模型，忽略 POST 里的 node_type。
            // 仅接受已存在且非虚拟的模型，防止任意类型入库。
            $postedType = trim((string) Request::post('node_type', ''));
            if ($this->nodeType === 'default' && $postedType !== '' && $postedType !== 'default'
                && !NodeType::isVirtual($postedType) && NodeType::getID($postedType)) {
                $this->nodeType = $postedType;
                $typeTitle = NodeType::getTitle($this->nodeType);
                $typeTitle and $this->setTitle($typeTitle);
            }
            $node['node_type'] = $this->nodeType;
            $node['add_time'] = time();
            // slug：优先使用手动输入，否则从标题生成
            if (!empty($node['slug'])) {
                $node['slug'] = SlugHelper::generate($node['slug']);
            } else {
                $node['slug'] = SlugHelper::generate($node['title']);
            }
            // slug 唯一性校验
            $exist = DB::table('node')->where('slug',$node['slug'])->fetchColumn();
            if ($exist) {
                $node['slug'] = $node['slug'] . '-' . time();
            }
            $node['update_time'] = time();
            $node['content'] = HtmlXss::clean($node['content']);
            $node['pub_time']  = strtotime($node['pub_time']) ?: time();
            $node = apply_filters('node_add',$node);
            $nodeModel = Node::create($node);
            // 保存自定义字段（node_meta）
            $nodeModel->saveMeta(Request::post('meta', []));
            // 保存标签（全量替换）
            \zapcms\services\Tag::saveNodeTags((int)$nodeModel->id, $this->parseTags(Request::post('tags', '')));
            // 保存多语言译文（translations）
            $this->saveTranslations((int) $nodeModel->id);
            // 栏目关联按内容模型过滤（服务端兜底，防止跨类型混选入库）
            if ($catalogArray !== []) {
                $validCatalogs = [];
                // 分类项是 node（node_type='category'），内容模型存在 mime_type
                $rows = DB::table('node')
                    ->where('node_type', 'category')
                    ->whereIn('id', array_map('intval', array_keys($catalogArray)))
                    ->fetchAll();
                foreach ((array) $rows as $row) {
                    if ((string) ($row['mime_type'] ?? '') === $this->nodeType) {
                        $cid = (int) $row['id'];
                        $validCatalogs[$cid] = (int) ($catalogArray[$cid] ?? $row['level'] ?? 1);
                    }
                }
                $catalogArray = $validCatalogs;
            }
            foreach ($catalogArray as $catalog_id=>$level){
                NodeRelation::create(['node_id'=>$nodeModel->id,'catalog_id'=>$catalog_id,'level'=>$level]);
            }
            // 审计日志：新增内容（覆盖文章/单页/产品/FAQ 等所有节点类型）
            AdminLog::info(
                AdminLog::CATEGORY_CONTENT,
                '新增' . $this->title,
                '标题：' . ($node['title'] ?? '') . '（' . $this->nodeType . ' #' . $nodeModel->id . '）'
            );
            // redirect_to：新建成功后前端用 history.replaceState 把地址切到 edit/{id}，
            // 不再整体跳转，表单原地变成“修改”状态，连续保存不会产生重复内容；
            // 通用发布（default 控制器）下按实际入库的内容模型跳转，直接进入对应模型的编辑表单
            $editController = ($node['node_type'] !== '' && $node['node_type'] !== 'default')
                ? $node['node_type'] : $this->controller;
            Response::json([
                'code'=>0,
                'msg'=> $this->title . '创建成功',
                'id'=>$nodeModel->id,
                'redirect_to'=>url_action("Node@{$editController}/edit/{$nodeModel->id}"),
                'slug'=>$node['slug'] ?? '',
                'update_time'=>$node['update_time'] ?? time(),
                'title'=>$this->getTitle("修改%s"),
            ]);

        }
        $data['title'] = $this->title;
        $data['sub_title'] = $this->getTitle("添加%s");
        $data['node'] = new Node();
        // 添加时若 URL 带 cid 参数（如 ?cid=30），自动勾选对应分类及其全部父级
        $data['node_relations'] = [];
        if ($this->catalogId) {
            $catalogRow = DB::table('node')->where('id', $this->catalogId)->first();
            if ($catalogRow) {
                // path 形如 "28,29,40,"，包含祖先链 + 自身，全部解析出来勾选
                $ids = array_filter(explode(',', (string)$catalogRow['path']));
                $ids = array_map('intval', $ids);
                if (empty($ids) || !in_array($this->catalogId, $ids)) {
                    $ids[] = (int)$this->catalogId;
                }
                $rows = DB::table('node')->whereIn('id', $ids)->get();
                foreach ($rows as $r) {
                    $data['node_relations'][(int)$r['id']] = (int)($r['level'] ?? 1);
                }
            }
        }
        $data['catalogList'] = Catalog::instance()->getTreeArray(
            ['mime_type'=>$this->nodeType],
            ['columns' => ['title', 'level']]
        );
        $data['nodeTagTitles'] = [];
        $this->display($data,'form');
    }

    /**
     * 保存表单提交的多语言译文
     *
     * @param int $nodeId
     */
    protected function saveTranslations(int $nodeId): void
    {
        if ($nodeId <= 0 || !Locale::enabled()) {
            return;
        }

        $posted = Request::post('translation', []);
        if (!is_array($posted)) {
            return;
        }

        foreach (Locale::available() as $code) {
            // 默认语言的内容存在主表里，子表只保存其它语言
            if ($code === Locale::defaultLocale()) {
                continue;
            }
            $row = $posted[$code] ?? [];
            if (!is_array($row)) {
                continue;
            }
            if (!empty($row['content'])) {
                $row['content'] = HtmlXss::clean($row['content']);
            }
            Translator::save(Translator::TYPE_NODE, $nodeId, $code, $row);
        }
    }

    /**
     * 读取当节点前已有的全部译文
     *
     * @param mixed $node Node 模型或数组
     * @return array locale => 译文行
     */
    protected function loadTranslations($node = null): array
    {
        $nodeId = (int) ($node['id'] ?? 0);
        if ($nodeId <= 0 || !Locale::enabled()) {
            return [];
        }
        return Translator::getAll(Translator::TYPE_NODE, $nodeId);
    }

    function remove(){
        $id = intval(Request::post('id'));
        if(Request::isPost() && $id){
            // 删除自定义字段（node_meta）
            $nodeModel = Node::findById($id);
            if ($nodeModel) {
                $nodeModel->deleteMeta();
            }
            // 删除多语言译文（translations）
            Translator::deleteAll(Translator::TYPE_NODE, $id);
            // 清理栏目关联（node_relation），避免悬空行
            NodeRelation::delete(['node_id' => $id]);
            $affId = Node::delete($id);
            if($affId){
                session()->flash('success', $this->title . '删除成功');
                AdminLog::info(
                    AdminLog::CATEGORY_CONTENT,
                    '删除' . $this->title,
                    $this->title . ' #' . $id
                );
                Response::json(['code'=>0,'msg'=>$this->title . '删除成功']);
            }else{
                Response::json(['code'=>1,'msg'=>$this->title . '删除失败，ID不存在']);
            }
        }
        Response::json(['code'=>1,'msg'=>$this->title . '删除失败，ID不存在']);
    }



    protected function display($data = [], $name = null){

        $controller = strtolower($this->controller);
        $action = strtolower($this->action);
        $data['_controller'] = $this->controller;
        $data['_action'] = $this->action;
        $data['catalogId'] = $this->catalogId;
        $data['modTitle'] = $this->title;
        $data['catalogPaths'] = $this->getCurrentCatalogPath();
//        print_r(Catalog::instance()->getCatalogPathById($this->catalogId));
//        print_r( $data['catalogPaths'] );die;
        if($data['catalogPaths']){
            $lastKey = array_key_last($data['catalogPaths']);
            foreach ($data['catalogPaths'] as $key => $catalog){
                BreadCrumb::instance()->add($catalog['title'],url_action("Node@{$this->controller}",req()->get()), $lastKey == $key);
            }
        }else{
            //获取catalog path

        }
        $data['breadcrumbs'] = BreadCrumb::instance()->toArray();
        $data['menu'] = Catalog::instance();
        $data['node_type'] = $this->nodeType;
        // 列表页布局标题使用“当前视图”（全部内容/文章/单页…），面包屑仍保留“内容管理”
        $viewTitle = '内容';
        $baseType = (string) $this->nodeType;
        if ($baseType !== 'default') {
            $viewTitle = NodeType::getTitle($baseType) ?: $this->title;
        } else {
            $typeFilter = trim((string) Request::get('type', ''));
            if ($typeFilter !== '' && NodeType::getID($typeFilter)) {
                $viewTitle = NodeType::getTitle($typeFilter) ?: $viewTitle;
            }
        }
        if (!empty($data['catalogPaths'])) {
            $lastCatalog = end($data['catalogPaths']);
            $viewTitle = (string) ($lastCatalog['title'] ?? $viewTitle);
        }
        $data['view_title'] = $viewTitle;
        if (($name ?? $action) === 'index') {
            // 视图内改 $page_title 不会影响布局（布局重新 extract 参数），需在这里覆盖
            $data['page_title'] = $viewTitle;
        }
        $data['node_fields'] = NodeType::getFields((string)$this->nodeType);
        // 多语言翻译数据（表单里的「多语言翻译」区块使用）
        $data['translations'] = $this->loadTranslations($data['node'] ?? null);

        // 内容管理页把栏目导航注入后台内容头部（布局通过 hook 渲染）
        static $catalogHeaderHookRegistered = false;
        if (!$catalogHeaderHookRegistered) {
            $catalogHeaderHookRegistered = true;
            Hook::on('admin_content_header_after', function () use ($data) {
                return View::render('default/sidebar', array_merge($data, ['catalog_nav_mode' => 'top']), true);
            });
        }

        $template = $name ?? $action;
        $node = $data['node'] ?? null;
        $isShow = ($template === 'show');
        $isList = ($template === 'index');
        if ($isShow) { Hook::fire('node_show_before', $node); }
        if ($isList) { Hook::fire('node_list_before', $node); }

        ob_start();
        try{
            View::render("{$controller}.{$template}",$data);
        }catch (ViewNotFoundException $e){
            View::render("default.{$template}",$data);
        }
        $html = ob_get_clean();
        if ($isShow) { $html = apply_filters('node_show_html', $html, $node); }
        if ($isList) { $html = apply_filters('node_list_html', $html, $node); }
        echo $html;

        if ($isShow) { Hook::fire('node_show_after', $node); }
        if ($isList) { Hook::fire('node_list_after', $node); }

    }

    protected function getCatalogById($id)
    {
        return Catalog::instance()->get($id);
    }

    protected function getCurrentCatalogPath(){
        if(!$this->catalogId && !empty($this->nodeId)){
            $this->catalogId = NodeRelation::find(['node_id'=>$this->nodeId])
                ->select('catalog_id')->orderBy('catalog_id desc')->fetchColumn();
        }
        if(!$this->catalogId){
            return [];
        }
        return Catalog::instance()->getCatalogPathById($this->catalogId);
    }

    /**
     * 列表排序列白名单（列头排序），未匹配回退 ID 倒序
     */
    protected function resolveOrderBy(string $sort): string
    {
        $map = [
            'id'          => 'n.id desc',
            'id_asc'      => 'n.id asc',
            'pub_time'    => 'n.pub_time desc',
            'pub_time_asc'=> 'n.pub_time asc',
        ];
        return $map[$sort] ?? 'n.id desc';
    }

    /**
     * 批量操作：发布 / 下架(转草稿) / 移到回收站 / 删除 / 移动到栏目
     *
     * POST: batch_action=publish|draft|trash|delete|move, ids=[...], catalog_id(仅 move)
     */
    function batch(){
        if (!Request::isPost()) {
            Response::json(['code' => 1, 'msg' => '非法请求']);
            return;
        }
        $action = (string)Request::post('batch_action', '');
        $ids = array_values(array_filter(array_unique(array_map('intval', (array)Request::post('ids', [])))));
        if ($ids === []) {
            Response::json(['code' => 1, 'msg' => '请先勾选要操作的内容']);
            return;
        }

        // 只允许操作当前模型的节点（防止跨模型误伤），一次查询完成过滤
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $valid = array_map('intval', array_column(
            DB::query("SELECT id FROM {node} WHERE id IN ({$ph}) AND node_type = ?", array_merge($ids, [$this->nodeType]))->fetchAll(),
            'id'
        ));
        if ($valid === []) {
            Response::json(['code' => 1, 'msg' => '所选内容不存在或类型不符']);
            return;
        }
        $ph = implode(',', array_fill(0, count($valid), '?'));
        $now = time();

        switch ($action) {
            case 'trash':
                DB::query(
                    "UPDATE {node} SET status = ?, update_time = ? WHERE id IN ({$ph})",
                    array_merge([Node::STATUS_TRASH, $now], $valid)
                );
                AdminLog::info(
                    AdminLog::CATEGORY_CONTENT,
                    '批量移至回收站' . $this->title,
                    'IDs: ' . implode(',', $valid)
                );
                Response::json(['code' => 0, 'msg' => '已移至回收站 ' . count($valid) . ' 条']);
                return;

            case 'publish':
            case 'draft':
                $status = $action === 'publish' ? Node::STATUS_PUBLISH : Node::STATUS_DRAFT;
                DB::query(
                    "UPDATE {node} SET status = ?, update_time = ? WHERE id IN ({$ph})",
                    array_merge([$status, $now], $valid)
                );
                AdminLog::info(
                    AdminLog::CATEGORY_CONTENT,
                    '批量' . ($action === 'publish' ? '发布' : '下架') . $this->title,
                    'IDs: ' . implode(',', $valid)
                );
                Response::json(['code' => 0, 'msg' => '已批量' . ($action === 'publish' ? '发布' : '下架') . ' ' . count($valid) . ' 条']);
                return;

            case 'delete':
                $delPh = implode(',', array_fill(0, count($valid), '?'));
                DB::query("DELETE FROM {node_meta} WHERE object_id IN ({$delPh})", $valid);
                foreach ($valid as $id) {
                    Translator::deleteAll(Translator::TYPE_NODE, $id);
                }
                DB::query("DELETE FROM {node_relation} WHERE node_id IN ({$delPh})", $valid);
                DB::query("DELETE FROM {node} WHERE id IN ({$delPh})", $valid);
                AdminLog::warning(
                    AdminLog::CATEGORY_CONTENT,
                    '批量删除' . $this->title,
                    'IDs: ' . implode(',', $valid)
                );
                Response::json(['code' => 0, 'msg' => '已批量删除 ' . count($valid) . ' 条']);
                return;

            case 'move':
                $catalogId = intval(Request::post('catalog_id', 0));
                $catalog = Catalog::instance()->get($catalogId);
                if (empty($catalog)) {
                    Response::json(['code' => 1, 'msg' => '目标栏目不存在']);
                    return;
                }
                $level = (int)($catalog['level'] ?? 1);
                DB::query("DELETE FROM {node_relation} WHERE node_id IN ({$ph})", $valid);
                foreach ($valid as $id) {
                    NodeRelation::create(['node_id' => $id, 'catalog_id' => $catalogId, 'level' => $level]);
                }
                AdminLog::info(
                    AdminLog::CATEGORY_CONTENT,
                    '批量移动' . $this->title,
                    'IDs: ' . implode(',', $valid) . " → 栏目「{$catalog['title']}」(#{$catalogId})"
                );
                Response::json(['code' => 0, 'msg' => '已移动 ' . count($valid) . ' 条到「' . $catalog['title'] . '」']);
                return;
        }

        Response::json(['code' => 1, 'msg' => '未知的批量操作']);
    }

    /**
     * 快捷置顶 / 取消置顶（后台列表开关调用）
     * POST: id, is_top(0|1)
     */
    public function top()
    {
        if (!Request::isPost()) {
            Response::json(['code' => 1, 'msg' => '非法请求']);
            return;
        }
        $id = (int)Request::post('id', 0);
        if ($id <= 0) {
            Response::json(['code' => 1, 'msg' => '参数错误']);
            return;
        }
        $val = Request::post('is_top') ? 1 : 0;
        Node::updateAll(['is_top' => $val], ['id' => $id]);
        AdminLog::info(AdminLog::CATEGORY_CONTENT, '置顶' . $this->title, 'ID: ' . $id . ' → ' . $val);
        Response::json(['code' => 0, 'msg' => '操作成功', 'is_top' => $val]);
    }

    /**
     * 移到回收站（列表标题悬浮快捷操作）
     * POST: id
     */
    public function trash()
    {
        if (!Request::isPost()) {
            Response::json(['code' => 1, 'msg' => '非法请求']);
            return;
        }
        $id = (int)Request::post('id', 0);
        if ($id <= 0) {
            Response::json(['code' => 1, 'msg' => '参数错误']);
            return;
        }
        $node = DB::table('node')->where('id', $id)->fetch();
        if (!$node) {
            Response::json(['code' => 1, 'msg' => '内容不存在']);
            return;
        }
        Node::updateAll(['status' => Node::STATUS_TRASH, 'update_time' => time()], ['id' => $id]);
        AdminLog::info(AdminLog::CATEGORY_CONTENT, '移至回收站' . $this->title, 'ID: ' . $id);
        Response::json(['code' => 0, 'msg' => '已移至回收站', 'id' => $id]);
    }

    /**
     * 快速编辑（列表标题悬浮快捷操作）：仅修改标题 / 状态 / 别名
     * POST: id, title, status, slug
     */
    public function quickEdit()
    {
        if (!Request::isPost()) {
            Response::json(['code' => 1, 'msg' => '非法请求']);
            return;
        }
        $id = (int)Request::post('id', 0);
        if ($id <= 0) {
            Response::json(['code' => 1, 'msg' => '参数错误']);
            return;
        }
        $node = DB::table('node')->where('id', $id)->fetch();
        if (!$node) {
            Response::json(['code' => 1, 'msg' => '内容不存在']);
            return;
        }
        $title = trim((string)Request::post('title', ''));
        if ($title === '') {
            Response::json(['code' => 1, 'msg' => '标题不能为空']);
            return;
        }
        $status = (string)Request::post('status', '');
        $update = [
            'title'       => $title,
            'update_time' => time(),
        ];
        if (in_array($status, [Node::STATUS_PUBLISH, Node::STATUS_DRAFT, Node::STATUS_TRASH], true)) {
            $update['status'] = $status;
        }
        $slug = trim((string)Request::post('slug', ''));
        $update['slug'] = $slug !== '' ? SlugHelper::generate($slug) : $slug;
        Node::updateAll($update, ['id' => $id]);
        AdminLog::info(AdminLog::CATEGORY_CONTENT, '快速编辑' . $this->title, '标题：' . $title . '（#' . $id . '）');
        Response::json([
            'code'   => 0,
            'msg'    => '保存成功',
            'id'     => $id,
            'title'  => $title,
            'status' => $update['status'] ?? $node['status'],
        ]);
    }

    protected function usePageHelper($total,$pageKeyName = 'page',$limit = 20 , $query = null): Pagination
    {
        $queryParams = $query ?? Request::get();
        $this->pageHelper = new Pagination(intval(Request::get($pageKeyName,1)),$limit, $queryParams);
        $this->pageHelper->setTotal($total);

        // Pagination 在 path 没有 ? 时会生成 "/N"；后台路由不认这种形式。
        // 显式用当前地址 + ?page={page} 作为基准，同时保留其它筛选参数。
        unset($queryParams['page']);
        $queryString = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);
        $base = current_url() . '?' . ($queryString !== '' ? $queryString . '&' : '') . $pageKeyName . '={page}';
        $this->pageHelper->withPath($base);

        View::share('page',$this->pageHelper);
        return $this->pageHelper;
    }

    protected function getTotalRows($conditions)
    {
        if($this->catalogId){
            $query = DB::table('node_relation','nr')
                ->leftJoin(['node','n'],'nr.node_id=n.id')
                ->select('count(DISTINCT n.id) as rowcount');
        }else{
            $query = DB::table('node','n')->select('count(n.id) as rowcount');
//            $query->where('author_id','!=',0);
        }

        $this->prepareConditions($query,$conditions);
        return $query->fetchColumn();
    }

    protected function getAll($conditions)
    {
        if($this->catalogId){
            $query = DB::table('node_relation','nr')
                ->leftJoin(['node','n'],'nr.node_id=n.id');
        }else{
            $query = DB::table('node','n');
//            $query->where('author_id','!=',0);
        }
        $query->distinct()->select('n.*');
        $this->prepareConditions($query,$conditions);
       return $query->get(FETCH_ASSOC);
    }

    protected function getNodeRelationships($node_id){
        return NodeRelation::find(['node_id'=>$node_id])
            ->select('catalog_id,node_id')
            ->get(FETCH_KEY_PAIR);
    }

    protected function getNodeByCatalogId($catalog_id){
        return NodeRelation::createQuery()
            ->leftJoin('node','node.id=node_relation.node_id')
            ->where('node_relation.catalog_id',$catalog_id)
            ->fetch(FETCH_ASSOC);
    }

    protected function prepareConditions(Query $query,$conditions){
        foreach (Arr::get($conditions,'where',[]) as $name=>$value){
            if(is_int($name)){
                $query->where(...$value);
            }else{
                $query->where($name,$value);
            }
        }
        empty($conditions['orderBy']) or $query->orderBy($conditions['orderBy']);
        if(!empty($conditions['limit'])){
            if(is_int($conditions['limit'])) $conditions['limit'] = [$conditions['limit']];
            $query->limit(...$conditions['limit']);
        }
    }

    protected function redirectTo($action, $query = null, $message = NULL, $flashKey = 'info'){
        if ($this->isAjax) {
            Response::json(['code'=> $flashKey === 'success' ? 0 : -1, 'msg' => $message]);
            return;
        }
        $redirect = Response::redirect(url_action($action, $query));
        if ($message !== NULL) {
            $redirect->with($flashKey, $message);
        }
    }


    public function getNodeType(): int
    {
        return $this->nodeType;
    }

    public function setNodeType(string $nodeType): void
    {
        $this->nodeType = $nodeType;
    }

    public function getCatalogId(): int
    {
        return $this->catalogId;
    }

    public function setCatalogId(int $catalogId): void
    {
        $this->catalogId = $catalogId;
    }

    public function getTitle($msg = null): string
    {
        return $msg ? sprintf($msg,$this->title) : $this->title;
    }

    public function setTitle($title)
    {
        $this->title = $title;
    }

}