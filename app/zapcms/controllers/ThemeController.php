<?php

namespace zapcms\controllers;

use zapcms\controllers\AdminController;
use zapcms\models\AdminLog;
use zapcms\support\IOUtils;
use zapcms\services\Option;
use zapcms\services\Theme;
use zap\facades\Cache;
use zap\http\Request;
use zap\util\FileUtils;
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

            // 退出预览态（若存在），避免已打开的预览标签仍停留在旧预览主题
            if (session()->has('preview_theme')) {
                session()->remove('preview_theme');
                session()->remove('preview_theme_ts');
            }

            AdminLog::info(AdminLog::CATEGORY_SYSTEM, '切换主题', '主题：' . $theme);

            // 返回重新渲染的卡片片段，前端拿到后直接替换 #themeGrid，无需整页刷新
            $html = View::make('theme._grid', [
                'themes'          => Theme::instance()->getAllThemesInfo(),
                'website_options' => Option::getArray('website', 'REGEXP'),
            ])->fetch();

            Response::json(['code'=>0,'msg'=>'主题设置成功','html'=>$html]);
        }
    }


    /**
     * 删除主题（连同其目录与数据库选项）
     *
     * 安全约束：
     *  - 当前启用中的主题禁止删除；
     *  - basic 是系统回退主题（其它主题均复用其模板），禁止删除，避免站点整体不可用。
     */
    public function deleteTheme()
    {
        if (Request::isPost()) {
            $theme = trim(req()->post('theme'));
            if (preg_match('/^[a-z0-9_-]{1,255}$/i', $theme) === false) {
                Response::json(['code' => 1, 'msg' => '主题名字不合法']);
            }

            $currentTheme = Option::get('website.theme', 'basic');
            if ($theme === $currentTheme) {
                Response::json(['code' => 1, 'msg' => '当前启用中的主题不能删除']);
            }
            if ($theme === 'basic') {
                Response::json(['code' => 1, 'msg' => '基础主题（basic）为系统回退主题，不能删除']);
            }

            $themePath = themes_path($theme);
            if (!is_dir($themePath)) {
                Response::json(['code' => 1, 'msg' => '主题不存在']);
            }

            // 删除该主题在数据库中的全部选项（含归属标记键）
            $ownedKeys = Theme::instance()->ownedOptionKeys($theme);
            if (!empty($ownedKeys)) {
                Option::removeMany($ownedKeys);
            }

            // 递归删除主题目录
            FileUtils::deleteDir($themePath);

            // 清缓存
            Cache::delete('options_' . $theme);
            Cache::delete('_opts_' . $theme);
            Cache::delete('options_website');
            Cache::delete('_opts_website');

            AdminLog::info(AdminLog::CATEGORY_SYSTEM, '删除主题', '主题：' . $theme);

            // 返回重新渲染的卡片片段，前端直接替换 #themeGrid
            $html = View::make('theme._grid', [
                'themes'          => Theme::instance()->getAllThemesInfo(),
                'website_options' => Option::getArray('website', 'REGEXP'),
            ])->fetch();

            Response::json(['code' => 0, 'msg' => '主题已删除', 'html' => $html]);
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

        $customSettings = IOUtils::readJsonFile($themeFile, true, [], true);
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
        $customSettings = IOUtils::readJsonFile($themeFile, true, [], true);
        $themeSettings = array_merge($customSettings['options'] ?? [],$themeSettings);
        view('theme.custom_page',[
            'page'=>$method,
            'settings'=>$customSettings,
            'options'=>$themeSettings
        ]);
    }

}