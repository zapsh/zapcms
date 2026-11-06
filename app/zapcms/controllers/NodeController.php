<?php

namespace zapcms\controllers;

use zapcms\controllers\AdminController;
use zapcms\models\AdminLog;
use zapcms\services\NodeType;
use zap\DB;
use zap\http\Response;
use zap\http\Router;
use zapcms\node\AbstractNodeType;
use zap\view\View;
use zapcms\services\BreadCrumb;

class NodeController extends AdminController
{

    public function _invoke(string $method, array $params = [])
    {
        View::paths(base_path("app/zapcms/node/views"));
        if($method == 'index'){$method = 'default';}
        if(in_array($method , ['types','typesForm','typesConfig','typesConfigSave'])){
            $this->$method();
        }else{
            $action = array_shift($params) ?? 'index';
            $controller = Router::convertToName($method);
            $action = Router::convertToName($action);

            $class = "\\zapcms\\node\\controllers\\{$controller}Controller";
            if(!class_exists($class)){
                $nodeTypeClass = NodeType::getClass($method);
                $class = class_exists($nodeTypeClass) ? $nodeTypeClass : AbstractNodeType::class;
            }
            if(!method_exists($class,$action)){
//                trigger_error("{$class}::{$action} - Method does not exist!!",E_USER_ERROR);
                $respondData = ['controller'=>$controller,'method'=>$action];
                $respondData['code'] = -1;
                $respondData['error'] = "{$class}::{$action} - Method does not exist!!";
                IS_AJAX ? Response::json($respondData) : View::render('node.notfound',$respondData);
                return false;
            }
            $typeName = strtolower($method);
            $zapController = new $class();
            $zapController->controller = $controller;
            $zapController->action = $action;
//            $nodeTypeId =  NodeType::getID($controller);
//            is_null($nodeTypeId) or $zapController->setNodeType($nodeTypeId);
            $zapController->setTitle(NodeType::getTitle($typeName));
            $zapController->setNodeType($typeName);
            $zapController->setCatalogId(req()->get('cid',0));
            $zapController->__init();
            $zapController->$action(...$params);

//            call_user_func_array(array($zapController, $action), $params);
        }
    }

    function types(){
        $search  = req()->get('search', '');
        $status  = req()->get('status', '');
        BreadCrumb::instance()->add('内容管理',url_action('Node'));
        BreadCrumb::instance()->add('内容模型');
        $query = DB::table('node_types')->whereNotIn('type_name', NodeType::VIRTUAL_TYPES);
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('type_name', 'LIKE', "%{$search}%")
                  ->orWhere('title', 'LIKE', "%{$search}%");
            });
        }
        if ($status !== '') {
            $query->where('status', (int)$status);
        }
        $query->orderBy('sort_order', 'ASC')->orderBy('type_id', 'DESC');

        $data = $query->fetchAll();

        // 统计各模型自定义字段数
        $fieldCount = [];
        $typeIds = array_column($data, 'type_id');
        if ($typeIds) {
            $fields = DB::table('node_type_field')->whereIn('node_type_id', $typeIds)->fetchAll();
            foreach ($fields as $f) {
                $fieldCount[(int)$f['node_type_id']] = ($fieldCount[(int)$f['node_type_id']] ?? 0) + 1;
            }
        }

        View::render("node.types", [
            'data'        => $data,
            'fieldCount'  => $fieldCount,
            'search'      => $search,
            'status'      => $status,
            'title'       => '内容模型管理',
            'page_title'  => '内容模型',
            'page_subtitle' => '管理网站的内容类型',
            'breadcrumbs' => BreadCrumb::instance()->toArray(),
        ]);
    }

    function typesForm(){
        $id  = req()->get('id', 0);
        $row = [];
        $fields = [];
        $groups = [];
        if ($id > 0) {
            $row = DB::table('node_types')->where('type_id', (int)$id)->first();
            if (!$row) {
                throw new \RuntimeException('模型不存在');
            }
            $fields = DB::table('node_type_field')->where('node_type_id', (int)$id)
                ->orderBy('sort_order', 'ASC')->orderBy('field_id', 'ASC')->fetchAll();
            $groups = NodeType::getFieldGroups((string)$row['type_name']);
        }
        BreadCrumb::instance()->add('内容管理', url_action('Node'));
        BreadCrumb::instance()->add($id > 0 ? '编辑模型' : '添加模型');
        View::render("node.types_form", [
            'row'         => $row,
            'fields'      => $fields,
            'groups'      => $groups,
            'id'          => (int)$id,
            'page_title'  => $id > 0 ? '编辑模型' : '添加模型',
            'breadcrumbs' => BreadCrumb::instance()->toArray(),
        ]);
    }

    function typesSave(){
        $id   = req()->post('type_id', 0);
        $data = [
            'type_name'   => trim(req()->post('type_name', '')),
            'title'       => trim(req()->post('title', '')),
            'description' => trim(req()->post('description', '')),
            'node_type'   => trim(req()->post('node_type', '')),
            'version'     => trim(req()->post('version', '0.0.0')),
            'sort_order'  => (int)req()->post('sort_order', 0),
            'status'      => (int)req()->post('status', 1),
        ];

        if (empty($data['type_name']) || empty($data['title'])) {
            Response::json(['code' => 1, 'msg' => '类型标识和标题不能为空']);
            return;
        }

        // 唯一性检查
        $existQuery = DB::table('node_types')->where('type_name', $data['type_name']);
        if ($id > 0) {
            $existQuery->where('type_id', '!=', (int)$id);
        }
        if ($existQuery->exists()) {
            Response::json(['code' => 1, 'msg' => '类型标识已存在']);
            return;
        }

        $now = time();
        if ($id > 0) {
            $data['updated_at'] = $now;
            DB::table('node_types')->where('type_id', (int)$id)->update($data);
            $typeId = (int)$id;
        } else {
            $data['created_at'] = $now;
            $data['updated_at'] = $now;
            $typeId = (int)DB::table('node_types')->insert($data);
        }

        // 保存自定义字段（全量替换）
        $this->saveTypeFields($typeId);

        // 保存字段分组（存 options 表，键 node_field_group.{type_name}）
        $groups = req()->post('field_group', []);
        NodeType::saveFieldGroups($data['type_name'], is_array($groups) ? $groups : []);

        AdminLog::info(
            AdminLog::CATEGORY_CONTENT,
            $id > 0 ? '编辑内容模型' : '新增内容模型',
            '模型：' . $data['title'] . '（' . $data['type_name'] . '）'
        );

        Response::json(['code' => 0, 'msg' => '保存成功']);
    }

    /**
     * 字段类型白名单
     */
    private const FIELD_TYPES = [
        'text', 'textarea', 'number', 'date', 'datetime',
        'select', 'radio', 'checkbox', 'switch', 'image',
    ];

    /**
     * 保存模型自定义字段（全量替换 node_type_field）
     */
    private function saveTypeFields(int $typeId): void
    {
        DB::table('node_type_field')->where('node_type_id', $typeId)->delete();

        $fields = req()->post('field', []);
        if (!is_array($fields)) {
            return;
        }

        $sort = 0;
        foreach ($fields as $field) {
            $name = trim((string)($field['field_name'] ?? ''));
            if ($name === '' || !preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
                continue;
            }
            $type = trim((string)($field['type'] ?? 'text'));
            if (!in_array($type, self::FIELD_TYPES, true)) {
                $type = 'text';
            }
            $sortOrder = isset($field['sort_order']) && $field['sort_order'] !== ''
                ? (int)$field['sort_order']
                : $sort;
            DB::table('node_type_field')->insert([
                'node_type_id' => $typeId,
                'field_name'   => $name,
                'field_label'  => trim((string)($field['field_label'] ?? $name)),
                'field_value'  => (string)($field['field_value'] ?? ''),
                'sort_order'   => $sortOrder,
                'type'         => $type,
                'placeholder'  => (string)($field['placeholder'] ?? ''),
                'required'     => !empty($field['required']) ? 1 : 0,
                'help'         => (string)($field['help'] ?? ''),
                'group_name'   => trim((string)($field['group_name'] ?? '')),
            ]);
            $sort = max($sort, $sortOrder + 1);
        }
    }

    /**
     * 内容模型显示配置页（动态列出所有 node_types，含禁用模型）
     */
    function typesConfig(){
        BreadCrumb::instance()->add('内容管理',url_action('Node'));
        BreadCrumb::instance()->add('内容模型',url_action('Node@types'));
        BreadCrumb::instance()->add('显示配置');

        $types = DB::table('node_types')->whereNotIn('type_name', NodeType::VIRTUAL_TYPES)
            ->orderBy('sort_order', 'ASC')->orderBy('type_id', 'DESC')->fetchAll();
        $configs = [];
        $fields  = [];
        foreach ($types as $type) {
            $typeName = (string)$type['type_name'];
            $configs[$typeName] = NodeType::getConfig($typeName);
            $fields[$typeName]  = NodeType::getFields($typeName);
        }

        View::render("node.types_config", [
            'types'       => $types,
            'configs'     => $configs,
            'fields'      => $fields,
            'title'       => '内容模型显示配置',
            'page_title'  => '显示配置',
            'page_subtitle' => '配置列表分页数量、图片尺寸与列表默认展示字段（按内容模型区分）',
            'breadcrumbs' => BreadCrumb::instance()->toArray(),
        ]);
    }

    /**
     * 保存内容模型显示配置
     */
    function typesConfigSave(){
        $configs = req()->post('config', []);
        if (!is_array($configs)) {
            Response::json(['code' => 1, 'msg' => '参数错误']);
            return;
        }
        foreach ($configs as $typeName => $cfg) {
            $typeName = trim((string)$typeName);
            if ($typeName === '' || !is_array($cfg)) {
                continue;
            }
            $clean = [];
            foreach (['list_per_page','list_image_width','list_image_height','detail_image_width','detail_image_height'] as $k) {
                if (isset($cfg[$k]) && $cfg[$k] !== '' && $cfg[$k] !== null) {
                    $clean[$k] = max(1, (int)$cfg[$k]);
                }
            }
            // 列表默认展示的自定义字段（node_meta 键），仅保留该模型真实存在的字段名
            $validNames = NodeType::getFieldNames($typeName);
            $columns = isset($cfg['list_columns']) && is_array($cfg['list_columns']) ? $cfg['list_columns'] : [];
            $clean['list_columns'] = array_values(array_intersect(
                array_map('trim', array_map('strval', $columns)),
                $validNames
            ));
            if ($clean) {
                NodeType::saveConfig($typeName, $clean);
            }
        }
        AdminLog::info(AdminLog::CATEGORY_CONTENT, '保存内容模型显示配置', '共 ' . count($configs) . ' 个模型');
        Response::json(['code' => 0, 'msg' => '保存成功']);
    }

    /**
     * 快速切换模型启用状态
     */
    function typesStatus(){
        $id     = (int)req()->post('id', 0);
        $status = (int)req()->post('status', 0);
        if ($id <= 0) {
            Response::json(['code' => 1, 'msg' => '参数错误']);
            return;
        }
        DB::table('node_types')->where('type_id', $id)->update([
            'status'     => $status ? 1 : 0,
            'updated_at' => time(),
        ]);
        AdminLog::info(
            AdminLog::CATEGORY_CONTENT,
            $status ? '启用内容模型' : '禁用内容模型',
            '模型 #' . $id
        );
        Response::json(['code' => 0, 'msg' => $status ? '已启用' : '已禁用']);
    }

    function typesDelete(){
        $id = req()->post('id', 0);
        if ($id <= 0) {
            Response::json(['code' => 1, 'msg' => '参数错误']);
            return;
        }
        $row = DB::table('node_types')->where('type_id', (int)$id)->first();
        // 同步清理该模型的字段配置、分组配置与显示配置
        DB::table('node_type_field')->where('node_type_id', (int)$id)->delete();
        if ($row) {
            NodeType::removeFieldGroups((string)$row['type_name']);
            NodeType::removeConfig((string)$row['type_name']);
        }
        DB::table('node_types')->where('type_id', (int)$id)->delete();
        AdminLog::info(AdminLog::CATEGORY_CONTENT, '删除内容模型', '模型 #' . $id);
        Response::json(['code' => 0, 'msg' => '删除成功']);
    }

}