<?php

namespace zapcms\controllers;

use FilesystemIterator;
use zapcms\controllers\AdminController;
use zapcms\models\AdminLog;
use zap\view\View;

class DevelopmentController extends AdminController
{
    public function index()
    {
        $path = '/' . trim(req()->get('path','/'),'/');
        View::render('development.index',[
            'path' => $path,
            'page_title' => '代码编辑器',
            'page_subtitle' => '文件浏览与代码编辑器',
            'breadcrumbs' => [
                ['title' => '控制台', 'url' => \zap\facades\Url::action('Index')],
                ['title' => '代码编辑器', 'url' => \zap\facades\Url::action('Development')],
            ],
            'body_class' => 'layout-fixed sidebar-expand-lg sidebar-mini sidebar-collapse bg-body-tertiary app-loaded',
        ]);
    }

    public function getDir(){
        $path = '/' . trim(req()->get('path','/'),'/');
        $realPath = realpath(app()->basePath($path) );
        if(is_file($realPath) && is_readable($realPath)){
            response(['code'=>0,'msg'=>'','path'=>$path , 'type'=>'content' ,'filename'=>basename($realPath) , 'content'=>file_get_contents($realPath)])->withJson();
        }
        $data = [];
        $fsIter = new FilesystemIterator($realPath,FilesystemIterator::KEY_AS_PATHNAME|FilesystemIterator::CURRENT_AS_FILEINFO|FilesystemIterator::SKIP_DOTS);
        while($fsIter->valid()){
            $data[] = [
                'filename'=>$fsIter->getFilename(),
                'type'=>$fsIter->getType(),
                'ext'=>$fsIter->getExtension(),
                'path'=> $path !== '/'  ? "{$path}/{$fsIter->getFilename()}" : '/'  . $fsIter->getFilename() ,
                'perms'=> substr(sprintf('%o', $fsIter->getPerms()), -4),
                'icon'=> ($fsIter->getType() === 'dir') ? 'fa fa-folder' : $this->getFileIcon($fsIter->getExtension())
            ];
            $fsIter->next();
        }
        $fileNames = array_column($data,'filename');
        $fileTypes = array_column($data,'type');
        array_multisort($fileTypes,SORT_ASC,$fileNames,SORT_ASC ,$data);

//        foreach ((new ZapFilesystemIterator($path))->sortByType()->limit(0, 100) AS $file)
//        {
//            print $file->getFilename() . "<br>\n";
//            $data[] = [
//                'filename'=>$file->getFilename(),
//                'type'=>$file->getType()
//            ];
//        }
        response(['code'=>0,'msg'=>'','path'=>$path,'type'=>'list','data'=>$data])->withJson();
    }

    public function saveFile()
    {
        if (!req()->isPost()) {
            response(['code' => 1, 'msg' => '无效的请求方式'])->withJson();
            return;
        }

        $path = trim(req()->post('path', ''));
        $content = req()->post('content', '');

        if (empty($path)) {
            response(['code' => 1, 'msg' => '文件路径不能为空'])->withJson();
            return;
        }

        $realPath = realpath(app()->basePath($path));

        // 安全检查：确保文件在项目目录内
        $basePath = realpath(app()->basePath('/'));
        if ($realPath === false) {
            // 文件不存在，检查父目录是否在项目内
            $parentDir = realpath(dirname(app()->basePath($path)));
            if ($parentDir === false || strpos($parentDir, $basePath) !== 0) {
                response(['code' => 1, 'msg' => '不允许访问该路径'])->withJson();
                return;
            }
            $realPath = app()->basePath($path);
        } else {
            if (strpos($realPath, $basePath) !== 0) {
                response(['code' => 1, 'msg' => '不允许访问该路径'])->withJson();
                return;
            }
        }

        // 不允许编辑 PHP 的 vendor 目录或 .git 目录
        if (strpos($realPath, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) !== false ||
            strpos($realPath, DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR) !== false) {
            response(['code' => 1, 'msg' => '不允许编辑系统核心文件'])->withJson();
            return;
        }

        if (!is_writable(dirname($realPath))) {
            response(['code' => 1, 'msg' => '目录不可写'])->withJson();
            return;
        }

        if (is_file($realPath) && !is_writable($realPath)) {
            response(['code' => 1, 'msg' => '文件不可写'])->withJson();
            return;
        }

        $result = file_put_contents($realPath, $content);
        if ($result === false) {
            response(['code' => 1, 'msg' => '文件保存失败'])->withJson();
            return;
        }

        AdminLog::warning(AdminLog::CATEGORY_SECURITY, '编辑代码文件', '路径：' . $path . '（' . $result . ' 字节）');
        response(['code' => 0, 'msg' => '文件保存成功', 'path' => $path, 'size' => $result])->withJson();
    }

    private function getFileIcon($ext): string
    {
        switch ($ext){
            case 'html':
            case 'htm':
                return 'fa-brands fa-html5';
            case 'css':
                return 'fa-brands fa-css3-alt';
            case 'js':
                return 'fa-brands fa-js';
            case 'php':
                return 'fa-brands fa-php';
            default:
                return 'fa-solid fa-file-code';
        }
    }

}