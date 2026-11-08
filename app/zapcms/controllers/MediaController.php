<?php

namespace zapcms\controllers;

use zapcms\controllers\UploadController;
use zapcms\helpers\ThumbHelper;
use zapcms\models\AdminLog;
use zapcms\services\storage\StorageManager;
use zap\http\Request;
use zap\http\Response;

/**
 * 媒体库控制器
 *
 * 通过统一存储抽象层（StorageManager）管理本地 / OSS / COS / R2 上的文件：
 *  - 目录树、文件网格/列表浏览
 *  - 新建文件夹、重命名、删除、移动、批量操作
 *  - 图片缩略图、预览、下载
 *  - 上传（复用 UploadController 能力）
 */
class MediaController extends UploadController
{
    /** 系统缩略图缓存目录名（禁止操作） */
    private const THUMBS_DIR = 'thumbs';

    /**
     * 媒体库主页
     */
    public function index()
    {
        view('media.index', []);
    }

    /**
     * 目录树 JSON（左侧文件夹树）
     */
    public function tree()
    {
        Response::json(['code' => 0, 'data' => $this->buildTree(StorageManager::list(''), 0)]);
    }

    /**
     * 浏览指定目录，返回目录与文件列表
     */
    public function browse()
    {
        $relPath = $this->cleanPath((string) Request::post('path', ''));
        $search  = trim((string) Request::post('search', ''));

        $listing = StorageManager::list($relPath);

        $dirs = [];
        foreach ($listing['dirs'] as $dir) {
            if ($dir['name'] === self::THUMBS_DIR && $relPath === '') {
                continue; // 缩略图缓存目录不展示
            }
            if ($search !== '' && stripos($dir['name'], $search) === false) {
                continue;
            }
            $dirs[] = [
                'name'  => $dir['name'],
                'path'  => $dir['path'],
                'count' => $this->countFilesIn($dir['path']),
            ];
        }

        $files = [];
        foreach ($listing['files'] as $f) {
            if ($search !== '' && stripos($f['name'], $search) === false) {
                continue;
            }
            $ext = $f['ext'];
            $files[] = [
                'name'     => $f['name'],
                'path'     => $f['path'],
                'ext'      => $ext,
                'is_image' => $this->isImage($ext),
                'size'     => $this->formatSize($f['size']),
                'bytes'    => $f['size'],
                'mtime'    => $f['mtime'],
                'date'     => $f['mtime'] > 0 ? date('Y-m-d H:i', $f['mtime']) : '',
                'url'      => storage_url($f['path']),
                'thumb'    => $this->isImage($ext) ? ThumbHelper::thumb($f['path'], 300, 200) : '',
            ];
        }

        // 目录在前，各自按名称排序
        usort($dirs, static fn($a, $b) => strcasecmp($a['name'], $b['name']));
        usort($files, static fn($a, $b) => strcasecmp($a['name'], $b['name']));

        // 面包屑
        $crumbs = [];
        if ($relPath !== '') {
            $parts = explode('/', $relPath);
            $acc   = '';
            foreach ($parts as $i => $part) {
                $acc = $acc === '' ? $part : $acc . '/' . $part;
                $crumbs[] = ['name' => $part, 'path' => $acc, 'last' => $i === count($parts) - 1];
            }
        }

        Response::json([
            'code'   => 0,
            'path'   => $relPath,
            'dirs'   => $dirs,
            'files'  => $files,
            'crumbs' => $crumbs,
        ]);
    }

    /**
     * 新建文件夹（支持多级目录，如 test1/test2/test3）
     */
    public function createDir()
    {
        if (!Request::isPost()) {
            Response::json(['code' => 1, 'msg' => '请求方式错误']);
            return;
        }
        $path = $this->cleanPath((string) Request::post('path', ''));

        // 允许用 / 分隔层级；反斜杠统一转换，去掉首尾与重复斜杠
        $dirName = str_replace('\\', '/', (string) Request::post('dir_name', ''));
        $dirName = (string) preg_replace('#/+#', '/', $dirName);
        $dirName = trim($dirName, "/ \t\n\r\0\x0B");

        if ($dirName === '') {
            Response::json(['code' => 1, 'msg' => '请输入文件夹名称']);
            return;
        }

        // 逐级校验目录名
        foreach (explode('/', $dirName) as $segment) {
            if ($segment === '.' || $segment === '..') {
                Response::json(['code' => 1, 'msg' => '文件夹名称不合法']);
                return;
            }
            if (preg_match('/[:*?"<>|]/', $segment)) {
                Response::json(['code' => 1, 'msg' => '名称不能包含 : * ? " < > | 等字符']);
                return;
            }
            if (mb_strlen($segment) > 100) {
                Response::json(['code' => 1, 'msg' => '单级目录名过长（最多 100 字符）']);
                return;
            }
        }

        $relTarget = $path === '' ? $dirName : $path . '/' . $dirName;
        if ($this->isForbiddenPath($relTarget)) {
            Response::json(['code' => 1, 'msg' => '系统缩略图缓存目录禁止操作']);
            return;
        }
        if ($this->pathExists($relTarget)) {
            Response::json(['code' => 1, 'msg' => '同名文件或文件夹已存在']);
            return;
        }
        if (!StorageManager::makeDirectory($relTarget)) {
            Response::json(['code' => 1, 'msg' => '创建失败，请检查目录权限']);
            return;
        }
        AdminLog::info(AdminLog::CATEGORY_CONTENT, '新建文件夹', '路径：' . $relTarget);
        Response::json(['code' => 0, 'msg' => '创建成功', 'path' => $relTarget]);
    }

    /**
     * 重命名文件/文件夹
     */
    public function rename()
    {
        if (!Request::isPost()) {
            Response::json(['code' => 1, 'msg' => '请求方式错误']);
            return;
        }
        $path    = $this->cleanPath((string) Request::post('path', ''));
        $oldName = trim((string) Request::post('old_name', ''));
        $newName = trim((string) Request::post('new_name', ''));

        if ($oldName === '' || $newName === '') {
            Response::json(['code' => 1, 'msg' => '名称不能为空']);
            return;
        }
        if (preg_match('/[\/\\\\:*?"<>|]/', $newName)) {
            Response::json(['code' => 1, 'msg' => '名称不能包含 \\ / : * ? " < > | 等字符']);
            return;
        }
        if ($this->isForbiddenPath($path)) {
            Response::json(['code' => 1, 'msg' => '系统缩略图缓存目录禁止操作']);
            return;
        }
        $oldRel = $path === '' ? $oldName : $path . '/' . $oldName;
        $newRel = $path === '' ? $newName : $path . '/' . $newName;
        if (!$this->pathExists($oldRel)) {
            Response::json(['code' => 1, 'msg' => '原文件或文件夹不存在']);
            return;
        }
        if ($this->pathExists($newRel)) {
            Response::json(['code' => 1, 'msg' => '同名文件或文件夹已存在']);
            return;
        }
        if (!StorageManager::rename($oldRel, $newRel)) {
            Response::json(['code' => 1, 'msg' => '重命名失败，请检查目录权限']);
            return;
        }
        AdminLog::info(AdminLog::CATEGORY_CONTENT, '重命名文件', $path === '' ? $oldName . ' → ' . $newName : $path . '/' . $oldName . ' → ' . $newName);
        Response::json(['code' => 0, 'msg' => '重命名成功']);
    }

    /**
     * 删除文件/文件夹（支持批量）
     */
    public function delete()
    {
        if (!Request::isPost()) {
            Response::json(['code' => 1, 'msg' => '请求方式错误']);
            return;
        }
        $path = $this->cleanPath((string) Request::post('path', ''));
        if ($this->isForbiddenPath($path)) {
            Response::json(['code' => 1, 'msg' => '系统缩略图缓存目录禁止操作']);
            return;
        }
        $names = (array) Request::post('names', []);
        if (empty($names)) {
            Response::json(['code' => 1, 'msg' => '请选择要删除的文件或文件夹']);
            return;
        }
        foreach ($names as $name) {
            $name = basename(trim((string) $name));
            if ($name === '' || $name === self::THUMBS_DIR) {
                continue;
            }
            $rel = $path === '' ? $name : $path . '/' . $name;
            StorageManager::delete($rel);
        }
        AdminLog::info(AdminLog::CATEGORY_CONTENT, '删除文件', '路径：' . ($path === '' ? '/' : $path) . '，共 ' . count($names) . ' 项');
        Response::json(['code' => 0, 'msg' => '删除成功']);
    }

    /**
     * 移动文件/文件夹到目标目录（支持批量）
     */
    public function move()
    {
        if (!Request::isPost()) {
            Response::json(['code' => 1, 'msg' => '请求方式错误']);
            return;
        }
        $path   = $this->cleanPath((string) Request::post('path', ''));
        $target = $this->cleanPath((string) Request::post('target', ''));
        $names  = (array) Request::post('names', []);

        if (empty($names)) {
            Response::json(['code' => 1, 'msg' => '请选择要移动的文件或文件夹']);
            return;
        }
        if ($target === $path) {
            Response::json(['code' => 1, 'msg' => '目标目录与当前目录相同']);
            return;
        }
        if ($this->isForbiddenPath($path) || $this->isForbiddenPath($target)) {
            Response::json(['code' => 1, 'msg' => '系统缩略图缓存目录禁止操作']);
            return;
        }
        if (!$this->isDirectory($target)) {
            Response::json(['code' => 1, 'msg' => '目标目录不存在']);
            return;
        }
        foreach ($names as $name) {
            $name = basename(trim((string) $name));
            if ($name === '' || $name === self::THUMBS_DIR) {
                continue;
            }
            $src = $path === '' ? $name : $path . '/' . $name;
            $dst = $target === '' ? $name : $target . '/' . $name;
            if ($this->pathExists($dst)) {
                continue;
            }
            StorageManager::move($src, $dst);
        }
        AdminLog::info(AdminLog::CATEGORY_CONTENT, '移动文件', ($path === '' ? '/' : $path) . ' → ' . ($target === '' ? '/' : $target) . '，共 ' . count($names) . ' 项');
        Response::json(['code' => 0, 'msg' => '移动成功']);
    }

    /**
     * 下载单个文件
     */
    public function download()
    {
        $path = $this->cleanPath((string) Request::get('path', ''));
        if ($path === '' || $this->isForbiddenPath($path)) {
            http_response_code(403);
            exit('Forbidden');
        }
        $tmp = StorageManager::download($path);
        if ($tmp === null) {
            http_response_code(404);
            exit('Not Found');
        }
        Response::download($tmp, basename($path));
    }

    /**
     * 递归构建目录树（排除 thumbs），每个目录只列举一次
     */
    private function buildTree(array $listing, int $depth): array
    {
        if ($depth >= 10) {
            return [];
        }
        $nodes = [];
        usort($listing['dirs'], static fn($a, $b) => strnatcasecmp($a['name'], $b['name']));
        foreach ($listing['dirs'] as $dir) {
            if ($dir['name'] === self::THUMBS_DIR && $depth === 0) {
                continue;
            }
            $sub = StorageManager::list($dir['path']);
            $nodes[] = [
                'name'     => $dir['name'],
                'path'     => $dir['path'],
                'count'    => count($sub['files']),
                'children' => $this->buildTree($sub, $depth + 1),
            ];
        }
        return $nodes;
    }

    /**
     * 统计目录内直属文件数（不含子目录与 thumbs）
     */
    private function countFilesIn(string $path): int
    {
        return count(StorageManager::list($path)['files']);
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

    /**
     * 判断相对 key 是否为目录
     */
    private function isDirectory(string $key): bool
    {
        $key = ltrim($key, '/');
        if ($key === '') {
            return true; // 根目录
        }
        $parent = dirname($key);
        $name   = basename($key);
        $listing = ($parent === '.' || $parent === '') ? StorageManager::list('') : StorageManager::list($parent);
        foreach ($listing['dirs'] as $dir) {
            if ($dir['name'] === $name) {
                return true;
            }
        }
        return false;
    }

    /**
     * 清理路径：去 ..、反斜杠转正斜杠、去除首尾斜杠
     */
    private function cleanPath(string $path): string
    {
        $path = str_replace(['..', '\\'], '/', $path);
        $path = preg_replace('#/+#', '/', $path);
        return trim((string) $path, '/');
    }

    /**
     * 是否为系统缩略图缓存目录（禁止操作）
     */
    private function isForbiddenPath(string $path): bool
    {
        return $path === self::THUMBS_DIR || str_starts_with($path, self::THUMBS_DIR . '/');
    }

    /**
     * 是否为图片扩展名
     */
    private function isImage(string $ext): bool
    {
        return in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico'], true);
    }

    /**
     * 文件大小人性化显示
     */
    private function formatSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }
}
