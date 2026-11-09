<?php

namespace zapcms\controllers;

use zapcms\controllers\AdminController;
use zapcms\models\AdminLog;
use zapcms\services\AdminMenu;
use zap\DB;
use zap\http\Request;
use zap\http\Response;
use zap\view\View;

/*
 * 栏目
 */
class AdminMenuController extends AdminController
{
    function index(){
        $this->requirePerm('view');
        $data = [];
        $data['menu'] = AdminMenu::instance();
        View::render("admin_menu.index",$data);
    }

    public function save()
    {
        $this->requirePerm('edit');
        $catalog = Request::post('admin_menu',[]);
        foreach ($catalog as $id=>$row){
            DB::update('admin_menu',$row,['id'=>$id]);
        }
        Response::json(['code'=>0,'msg'=>'保存成功']);
    }

    public function saveAdminMenu(){
        if(Request::isPost()){
            $this->requirePerm('edit');
            $data = Request::post('zap_data',[]);
            $menu_id = intval(Request::post('menu_id'));
            $data['icon'] = $data['icon'] ?? 'fa fa-circle-notch';
            $data['show_position'] = join(',', $data['show_position'] ?? []);

            // 校验：菜单名称必填
            if (empty(trim((string)($data['title'] ?? '')))) {
                Response::json(['code' => 1, 'msg' => '请填写菜单名称']);
                return;
            }

            // 校验：上级菜单合法性
            $pid = intval($data['pid'] ?? 0);
            if ($pid > 0) {
                if ($menu_id && $pid === $menu_id) {
                    Response::json(['code' => 1, 'msg' => '上级菜单不能选择自身']);
                    return;
                }
                $parent = AdminMenu::instance()->get($pid);
                if (empty($parent)) {
                    Response::json(['code' => 1, 'msg' => '所选上级菜单不存在']);
                    return;
                }
                if ($menu_id) {
                    $me = AdminMenu::instance()->get($menu_id);
                    if ($me && \zap\util\Str::startsWith($parent['path'], $me['path'])) {
                        Response::json(['code' => 1, 'msg' => '上级菜单不能是自身的子菜单']);
                        return;
                    }
                }
            }

            if($menu_id){
                AdminMenu::instance()->update($data,$menu_id);
            }else{
                // add() 只接收 $data，父级 id 由 $data['pid'] 提供
                AdminMenu::instance()->add($data);
            }

            AdminLog::info(
                AdminLog::CATEGORY_SYSTEM,
                $menu_id ? '编辑后台菜单' : '新增后台菜单',
                '菜单：' . ($data['title'] ?? '') . ($menu_id ? ' #' . $menu_id : '')
            );

            Response::json(['code'=>0,'msg'=>'保存成功']);
        }
    }

    public function remove()
    {
        $this->requirePerm('delete');
        $catalog = Request::post('admin_menu',[]);
        $menu = AdminMenu::instance();
        foreach ($catalog as $id=>$row){
            $menu->remove($id);
        }
        AdminLog::info(AdminLog::CATEGORY_SYSTEM, '删除后台菜单', '共 ' . count($catalog) . ' 个菜单');
        Response::json(['code'=>0,'msg'=>'系统菜单删除成功']);
    }

    public function form()
    {

        $data['pid'] = intval(Request::get('pid',0));
        $data['id'] = intval(Request::get('id',0));
        if($data['pid']){
            $data['parent'] = AdminMenu::instance()->get($data['pid']);
        }
        if($data['id']){
            $data['menu'] = AdminMenu::instance()->get($data['id']);
        }

        $data['menu'] = $data['menu'] ?? [];
        View::render('admin_menu.form',$data);
    }

}