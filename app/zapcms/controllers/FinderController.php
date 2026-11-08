<?php

namespace zapcms\controllers;


use zapcms\controllers\AdminController;
use zapcms\helpers\ThumbHelper;
use zapcms\services\storage\StorageManager;
use zap\helpers\Pagination;
use zap\util\Str;
use zap\view\View;

class FinderController extends AdminController
{
    /** 单层目录树请求最多返回的目录节点数，防止超大目录拖慢弹窗 */
    private const TREE_LEVEL_BUDGET = 200;

    /** 目录树最大递归深度 */
    private const TREE_MAX_DEPTH = 8;

    function list(){
        // size 格式校验：仅允许 "宽x高" 或 original
        $size = (string)req()->get('size','');
        if(preg_match('/^(\d+)x(\d+)$/', $size, $m)){
            $width = (int)$m[1];
            $height = (int)$m[2];
        }else{
            $width = 136;
            $height = 136;
        }
        $path = trim((string)req()->get('path',''),'/');
        $path = str_replace(['..'],'',$path);
        $search = (string)req()->get('search','');

        try {
            $listing = StorageManager::list($path);
        } catch (\Throwable $e) {
            \response(['code' => 1, 'msg' => '读取存储失败：' . $e->getMessage()])->withJson();
            return;
        }

        $entries = [];
        foreach ($listing['dirs'] as $dir) {
            if ($search !== '' && stripos($dir['name'], $search) === false) {
                continue;
            }
            $entries[] = [
                'filename' => $dir['name'],
                'type'     => 'dir',
                'ext'      => '',
                'is_image' => false,
                'thumb_url'=> '',
                'path'     => $path !== '' ? "{$path}/{$dir['name']}" : $dir['name'],
                'perms'    => '0755',
                'icon'     => 'fa fa-folder',
            ];
        }
        foreach ($listing['files'] as $f) {
            if ($search !== '' && stripos($f['name'], $search) === false) {
                continue;
            }
            $isImage  = $this->isImage($f['ext']);
            $filePath = $path !== '' ? "{$path}/{$f['name']}" : $f['name'];
            $thumbUrl = '';
            if ($isImage && !Str::startsWith($path, 'thumbs')) {
                $thumbUrl = ThumbHelper::thumb($filePath, $width, $height);
            } elseif ($isImage) {
                $thumbUrl = storage_url($filePath);
            }
            $entries[] = [
                'filename' => $f['name'],
                'type'     => 'file',
                'ext'      => $f['ext'],
                'is_image' => $isImage,
                'thumb_url'=> $thumbUrl,
                'path'     => $filePath,
                'perms'    => '0644',
                'icon'     => $this->getFileIcon($f['ext']),
            ];
        }

        // 目录在前，各自按自然名称排序
        usort($entries, static function (array $a, array $b): int {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'dir' ? -1 : 1;
            }
            return strnatcasecmp($a['filename'], $b['filename']);
        });

        $total = count($entries);

        // 分页 URL：保留 path/search/target 等查询参数，剔除 page 与 initialize。
        $query = req()->get();
        unset($query['page'], $query['initialize']);

        $pageHelper = new Pagination((int)req()->get('page', 1), 10, $query);
        $pageHelper->withPath(url_action('Finder@list') . ($query ? '?' . http_build_query($query) : ''));
        $pageHelper->setTotal($total);
        $pageHelper->setClasses(['nav'=>'pagination pagination-sm justify-content-center mb-0']);
        $pageHelper->setCurrentPage($pageHelper->currentPage());
        $data = array_slice($entries, $pageHelper->getOffset(), $pageHelper->getLimit());

        $parent_path = dirname($path);

        // 回调仅允许安全的全局函数名，防止 URL 参数注入到前端 JS
        $callback = (string)req()->get('callback','');
        if(!preg_match('/^[a-zA-Z_$][a-zA-Z0-9_$]*$/', $callback)){
            $callback = '';
        }
        // target 仅允许 #id 或 .class 形式的选择器
        $target = array_values(array_filter(
            array_map('trim', explode('|',(string)req()->get('target',''))),
            fn($t) => preg_match('/^[#.][a-zA-Z0-9_-]+$/', $t) === 1
        ));

        // 缩略图缓存目录（thumbs）禁止上传
        $forbidUpload = $path === 'thumbs' || str_starts_with($path, 'thumbs/');

        View::render('finder.list',[
            'initialize'=>req()->get('initialize',false),
            'path'=>$path,
            'parent_path'=>$parent_path === '.' ? '':$parent_path,
            'type'=>'list',
            'data'=>$data,
            'target'=>$target,
            'callback'=>$callback,
            'size'=>$size,
            'total'=>$total,
            'pagination' => $pageHelper->render(),
            'forbid_upload' => $forbidUpload
        ]);
    }

    /**
     * 左侧目录树数据（仅目录节点，不含文件）
     */
    public function tree()
    {
        try {
            \response(['code' => 0, 'data' => $this->buildFinderTree(StorageManager::list(''), '', 0)])->withJson();
        } catch (\Throwable $e) {
            \response(['code' => 1, 'msg' => '读取存储失败：' . $e->getMessage()])->withJson();
        }
    }

    /**
     * 递归构建目录树。每个目录只列举一次，限制深度与单层节点数，避免超大目录拖慢弹窗。
     */
    private function buildFinderTree(array $listing, string $relPath, int $depth): array
    {
        if ($depth >= self::TREE_MAX_DEPTH) {
            return [];
        }
        $nodes = [];
        foreach ($listing['dirs'] as $dir) {
            if (count($nodes) >= self::TREE_LEVEL_BUDGET) {
                break;
            }
            // thumbs 为系统缩略图缓存：保留节点便于浏览，但不递归遍历、禁止改名/删除
            $isSystem = $relPath === '' && $dir['name'] === 'thumbs';
            $sub = $isSystem ? ['dirs' => [], 'files' => []] : StorageManager::list($dir['path']);
            $nodes[] = [
                'name'     => $dir['name'],
                'path'     => $dir['path'],
                'count'    => count($sub['files']),
                'children' => $isSystem ? [] : $this->buildFinderTree($sub, $dir['path'], $depth + 1),
                'system'   => $isSystem,
            ];
        }
        usort($nodes, static fn(array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));
        return $nodes;
    }

    public function createDir()
    {
        if(req()->isPost()){
            $dirName = trim((string)req()->post('dir_name'));
            $path = trim((string)req()->post('path'),' \\/');
            $dirName = str_replace(['..'],'',$dirName);
            $path = str_replace(['..'],'',$path);

            if($dirName === ''){
                \response(['code'=>1,'msg'=>'目录名称不能为空'])->withJson();
                return;
            }
            // 非法字符校验（Windows 文件系统不允许）
            if(preg_match('/[\/\\\\:*?"<>|]/', $dirName)){
                \response(['code'=>1,'msg'=>'目录名包含非法字符 \\ / : * ? " < > |'])->withJson();
                return;
            }
            // Windows 保留设备名（含 con.txt 这类变体）
            if(preg_match('/^(con|prn|aux|nul|com[1-9]|lpt[1-9])(\..*)?$/i', $dirName)){
                \response(['code'=>1,'msg'=>'该目录名称不可用'])->withJson();
                return;
            }

            $target = ($path !== '' ? $path . '/' : '') . $dirName;
            if($this->pathExists($target)){
                \response(['code'=>1,'msg'=>'创建失败，目录已存在'])->withJson();
                return;
            }
            if(StorageManager::makeDirectory($target)){
                \response(['code'=>0,'msg'=>'目录创建成功'])->withJson();
                return;
            }
            \response(['code'=>1,'msg'=>'创建失败，请检查目录写入权限'])->withJson();
        }
    }

    public function delete()
    {
        if(req()->isPost()){
            $finderItem = req()->post('finder_item');
            // 未选择时直接提示，避免空提交
            if(empty($finderItem) || !is_array($finderItem)){
                \response(['code'=>1,'msg'=>'请先选择要删除的文件或目录'])->withJson();
                return;
            }
            $path = trim((string)req()->post('path'),' \\/');
            $path = str_replace(['..'],'',$path);

            $success = 0;
            foreach ($finderItem as $item){
                // 仅保留纯文件名，防止路径穿越删除 storage 之外的文件
                $name = basename(str_replace('\\','/',trim((string)$item,' /')));
                if($name === '' || $name === '.' || $name === '..'){
                    continue;
                }
                if($name === 'thumbs' && $path === ''){
                    continue; // 系统缩略图缓存目录禁止删除
                }
                $rel = ($path !== '' ? $path . '/' : '') . $name;
                if(StorageManager::delete($rel)){
                    $success++;
                }
            }

            if($success > 0){
                \response(['code'=>0,'msg'=>"成功删除 {$success} 项"])->withJson();
                return;
            }
            \response(['code'=>1,'msg'=>'没有可删除的文件'])->withJson();
        }
    }

    /**
     * 重命名文件或目录
     */
    public function rename()
    {
        if(!req()->isPost()){
            return;
        }
        $oldName = basename(str_replace('\\','/',trim((string)req()->post('old_name'),' /')));
        $newName = trim((string)req()->post('new_name'));
        $path = trim((string)req()->post('path'),' \\/');
        $newName = str_replace(['..'],'',$newName);
        $path = str_replace(['..'],'',$path);

        if($oldName === '' || $oldName === '.' || $oldName === '..'){
            \response(['code'=>1,'msg'=>'请选择要重命名的文件'])->withJson();
            return;
        }
        if($newName === ''){
            \response(['code'=>1,'msg'=>'新名称不能为空'])->withJson();
            return;
        }
        // 非法字符校验（Windows 文件系统不允许）
        if(preg_match('/[\/\\\\:*?"<>|]/', $newName)){
            \response(['code'=>1,'msg'=>'名称包含非法字符 \\ / : * ? " < > |'])->withJson();
            return;
        }
        // Windows 保留设备名（含 con.txt 这类变体）
        if(preg_match('/^(con|prn|aux|nul|com[1-9]|lpt[1-9])(\..*)?$/i', $newName)){
            \response(['code'=>1,'msg'=>'该名称不可用'])->withJson();
            return;
        }
        if(strtolower($oldName) === strtolower($newName)){
            \response(['code'=>1,'msg'=>'新名称与原名相同'])->withJson();
            return;
        }

        $oldRel = ($path !== '' ? $path . '/' : '') . $oldName;
        $newRel = ($path !== '' ? $path . '/' : '') . $newName;

        if(!$this->pathExists($oldRel)){
            \response(['code'=>1,'msg'=>'原文件不存在或已被移动'])->withJson();
            return;
        }
        if($this->pathExists($newRel)){
            \response(['code'=>1,'msg'=>'重命名失败，已存在同名文件或目录'])->withJson();
            return;
        }
        if(StorageManager::rename($oldRel, $newRel)){
            \response(['code'=>0,'msg'=>'重命名成功'])->withJson();
            return;
        }
        \response(['code'=>1,'msg'=>'重命名失败，请检查目录写入权限'])->withJson();
    }



    function faIcons(){
        View::render('finder.faicons');
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


    private function isImage($ext)
    {
        // 仅包含 Image 库（GD）支持的格式，避免缩略图生成异常
        return in_array(strtolower((string)$ext), ['png','jpg','jpeg','gif','webp'], true);
    }

    /**
     * 判断相对 key 对应的文件或目录是否存在（比较父目录下的同名条目）
     */
    private function pathExists(string $key): bool
    {
        $key = ltrim($key, '/');
        if ($key === '') {
            return true;
        }
        $parent = dirname($key);
        $name   = basename($key);
        $listing = ($parent === '.' || $parent === '') ? StorageManager::list('') : StorageManager::list($parent);
        foreach (array_merge($listing['dirs'], $listing['files']) as $item) {
            if ($item['name'] === $name) {
                return true;
            }
        }
        return false;
    }


}
