<?php

namespace zapcms\node;

use zapcms\services\BreadCrumb;
use zapcms\services\Catalog;
use zapcms\services\NodeMeta;
use zapcms\services\NodeType;
use zapcms\services\SlugHelper;
use zapcms\support\HtmlXss;
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
        $conditions = [
            'where'=>[
                'n.node_type'=>$this->nodeType,
//                'n.author_id'=>Auth::user('id'),
            ]
        ];
        $this->catalogId && $conditions['where']['nr.catalog_id']= $this->catalogId;
        $conditions = apply_filters('node_total_conditions',$conditions);
        $page = $this->usePageHelper($this->getTotalRows($conditions));
        $conditions['orderBy'] = 'id desc';
        $conditions['limit'] = [$page->getLimit(),$page->getOffset()];
        $conditions = apply_filters('node_get_all_conditions',$conditions);
        $data['data'] = $this->getAll($conditions);
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
        $data['catalogList'] = Catalog::instance()->getTreeArray(['node_type'=>$data['node']['node_type']]);
        $this->display($data,'form');
    }

    public function add()
    {
        if(Request::isPost()){
            $node = Request::post('node');
            $catalogArray = Request::post('catalog',[]);
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
            foreach ($catalogArray as $catalog_id=>$level){
                NodeRelation::create(['node_id'=>$nodeModel->id,'catalog_id'=>$catalog_id,'level'=>$level]);
            }
            // redirect_to：新建成功后前端用 history.replaceState 把地址切到 edit/{id}，
            // 不再整体跳转，表单原地变成“修改”状态，连续保存不会产生重复内容
            Response::json([
                'code'=>0,
                'msg'=> $this->title . '创建成功',
                'id'=>$nodeModel->id,
                'redirect_to'=>url_action("Node@{$this->controller}/edit/{$nodeModel->id}",$_GET),
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
            $catalogRow = DB::table('catalog')->where('id', $this->catalogId)->first();
            if ($catalogRow) {
                // path 形如 "28,29,40,"，包含祖先链 + 自身，全部解析出来勾选
                $ids = array_filter(explode(',', (string)$catalogRow['path']));
                $ids = array_map('intval', $ids);
                if (empty($ids) || !in_array($this->catalogId, $ids)) {
                    $ids[] = (int)$this->catalogId;
                }
                $rows = DB::table('catalog')->whereIn('id', $ids)->get();
                foreach ($rows as $r) {
                    $data['node_relations'][(int)$r['id']] = (int)($r['level'] ?? 1);
                }
            }
        }
        $data['catalogList'] = Catalog::instance()->getTreeArray(['node_type'=>$this->nodeType]);
        $this->display($data,'form');
    }

    function remove(){
        $id = intval(Request::post('id'));
        if(Request::isPost() && $id){
            // 删除自定义字段（node_meta）
            $nodeModel = Node::findById($id);
            if ($nodeModel) {
                $nodeModel->deleteMeta();
            }
            $affId = Node::delete($id);
            if($affId){
                session()->flash('success', $this->title . '删除成功');
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
        $data['node_fields'] = NodeType::getFields((string)$this->nodeType);
        try{
            View::render("{$controller}.". ($name ?? $action),$data);
        }catch (ViewNotFoundException $e){
            View::render("default.". ($name ?? $action),$data);
        }

    }

    protected function getCatalogById($id)
    {
        return Catalog::instance()->get($id);
    }

    protected function getCurrentCatalogPath(){
        if(!$this->catalogId){
            $this->catalogId = NodeRelation::find(['node_id'=>$this->nodeId])
                ->select('catalog_id')->orderBy('catalog_id desc')->fetchColumn();
        }
        return Catalog::instance()->getCatalogPathById($this->catalogId);
    }

    protected function usePageHelper($total,$pageKeyName = 'page',$limit = 20 , $query = null): Pagination
    {
        $this->pageHelper = new Pagination(intval(Request::get($pageKeyName,1)),$limit, $query ?? Request::get());
        $this->pageHelper->setTotal($total);
        View::share('page',$this->pageHelper);
        return $this->pageHelper;
    }

    protected function getTotalRows($conditions)
    {
        if($this->catalogId){
            $query = DB::table('node_relation','nr')
                ->leftJoin(['node','n'],'nr.node_id=n.id')
                ->select('count(n.id) as rowcount');
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
        $query->select('n.*');
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