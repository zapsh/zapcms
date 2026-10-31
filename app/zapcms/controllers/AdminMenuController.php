<?php

namespace zapcms\controllers;

use zapcms\controllers\AdminController;
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
        $data = [];
        $data['menu'] = AdminMenu::instance();
        View::render("admin_menu.index",$data);
    }

    public function save()
    {
        $catalog = Request::post('admin_menu',[]);
        foreach ($catalog as $id=>$row){
            DB::update('admin_menu',$row,['id'=>$id]);
        }
        Response::json(['code'=>0,'msg'=>'保存成功']);
    }

    public function saveAdminMenu(){
        if(Request::isPost()){
            $data = Request::post('zap_data',[]);
            $menu_id = intval(Request::post('menu_id'));
            $data['icon'] = $data['icon'] ?? 'fa fa-circle-notch';
            $data['show_position'] = join(',', $data['show_position'] ?? []);
            if($menu_id){
                AdminMenu::instance()->update($data,$menu_id);
            }else{
                // add() 只接收 $data，父级 id 由 $data['pid'] 提供
                AdminMenu::instance()->add($data);
            }

            Response::json(['code'=>0,'msg'=>'保存成功']);
        }
    }

    public function remove()
    {
        $catalog = Request::post('admin_menu',[]);
        $menu = AdminMenu::instance();
        foreach ($catalog as $id=>$row){
            $menu->remove($id);
        }
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
//        $data['catalog'] = Catalog::instance()->getTreeArray();
//        print_r($data['parent']);
//        echo ltrim($data['parent']['path'],'0,');

        View::render('admin_menu.form',$data);
    }

}