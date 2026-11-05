<?php

namespace zapcms\controllers;

use zapcms\controllers\AdminController;
use zapcms\models\AdminLog;
use zapcms\support\IOUtils;
use zapcms\services\Option;
use zapcms\services\Theme;
use zap\facades\Cache;
use zap\http\Request;
use zap\http\Response;
use zap\view\View;

class ThemeController extends AdminController
{

    public function index(){
        config_set('cache.status','disabled');
        $themes = Theme::instance()->getAllThemesInfo();
        $website_options = Option::getArray('website','REGEXP');
        view('theme.index',['themes'=>$themes,'website_options'=>$website_options]);
    }


    public function activationTheme(){
        if(Request::isPost()){
            $theme = trim(req()->post('theme'));
            if(preg_match('/^[a-z0-9]{1,255}$/i',$theme) === false){
                Response::json(['code'=>1,'msg'=>'主题名字不合法']);
            }
            if(!is_dir(themes_path($theme))){
                Response::json(['code'=>1,'msg'=>'主题不存在']);
            }
            Option::update('website.theme',$theme);
            Theme::instance()->saveThemeOptions($theme);
            Cache::delete('options_website');
            Cache::delete('_opts_website');
            Cache::delete('options_'.$theme);
            Cache::delete('_opts_'.$theme);

            AdminLog::info(AdminLog::CATEGORY_SYSTEM, '切换主题', '主题：' . $theme);

            // 返回重新渲染的卡片片段，前端拿到后直接替换 #themeGrid，无需整页刷新
            $html = View::make('theme._grid', [
                'themes'          => Theme::instance()->getAllThemesInfo(),
                'website_options' => Option::getArray('website', 'REGEXP'),
            ])->fetch();

            Response::json(['code'=>0,'msg'=>'主题设置成功','html'=>$html]);
        }
    }



    public function settings(){
        $theme = Option::get('website.theme','basic');
        $themeFile = themes_path("{$theme}/theme.json");
        if(!is_file($themeFile)){
            Response::redirect(url_action('theme'))->with('info', '当前主题不支持自定义');
        }

        if (req()->isPost()) {
            // 自包含式主题（如 basic / zap2026）的保存由主题视图文件处理，
            // 此处统一记录审计日志（不写在主题视图里）
            AdminLog::info(AdminLog::CATEGORY_SYSTEM, '保存主题设置', '主题：' . $theme);
        }

        $customSettings = IOUtils::readJsonFile($themeFile,true) ?:[];
        $options = Theme::instance()->getOptions($theme);
        view('theme.settings',[
            'settings'=>$customSettings,
            'options'=>$options
        ]);
    }

    public function saveSettings()
    {
        if(req()->isPost()){
            $settings = req()->post('settings') ?:[];
            $theme = Option::get('website.theme','basic');
            Theme::instance()->saveOptions($theme, $settings);
            Cache::delete('options_website');
            Cache::delete('_opts_website');
            Cache::delete('options_'.$theme);
            Cache::delete('_opts_'.$theme);
            AdminLog::info(AdminLog::CATEGORY_SYSTEM, '保存主题设置', '主题：' . $theme);
        }
        \response(['code'=>0,'msg'=>'保存成功'])->withJson();
    }


    public function callCustomPage($method){
        $theme = Option::get('website.theme','basic');
        $themeFile = themes_path("{$theme}/theme.json");
        if(!is_file($themeFile)){
            Response::redirect(url_action('theme'))->with('info', '当前主题不支持自定义');
        }

        $themeSettings = Option::getArray($theme,'REGEXP');
        $customSettings = IOUtils::readJsonFile($themeFile,true) ?:[];
        $themeSettings = array_merge($customSettings['options'] ?? [],$themeSettings);
        view('theme.custom_page',[
            'page'=>$method,
            'settings'=>$customSettings,
            'options'=>$themeSettings
        ]);
    }

}