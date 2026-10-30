<?php

namespace zapcms\controllers;

use zapcms\controllers\UploadController;
use zapcms\helpers\ThumbHelper;
use zap\http\Request;
use zap\http\Response;

/**
 * 媒体库控制器
 *
 * 管理 storage 目录下的图片与文件，提供：
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
        $tree = $this->buildDirTree('');
        Response::json(['code' => 0, 'data' => $tree]);
    }

    /**
     * 浏览指定目录，返回目录与文件列表
     */
    public function browse()
    {
        $relPath = $this->cleanPath((string)Request::post('path', ''));
        $search  = trim((string)Request::post('search', ''));

        $absDir = $relPath === '' ? storage_path() : storage_path($relPath);
        if (!is_dir($absDir)) {
            Response::json(['code' => 1, 'msg' => '目录不存在']);
            return;
        }

        $dirs  = [];
        $files = [];
        $items = @scandir($absDir);
        if ($items === false) {
            Response::json(['code' => 1, 'msg' => '无法读取目录']);
            return;
        }

        foreach ($items as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            if ($name === self::THUMBS_DIR) {
                continue; // 缩略图缓存目录不展示
            }
            $itemRel = $relPath === '' ? $name : $relPath . '/' . $name;
            $full    = $absDir . '/' . $name;

            // 搜索过滤（仅匹配文件名）
            if ($search !== '' && stripos($name, $search) === false) {
                continue;
            }

            if (is_dir($full)) {
                $dirs[] = [
                    'name'  => $name,
                    'path'  => $itemRel,
                    'count' => $this->countFiles($full),
                ];
            } else {
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $files[] = [
                    'name'     => $name,
                    'path'     => $itemRel,
                    'ext'      => $ext,
                    'is_image' => $this->isImage($ext),
                    'size'     => $this->formatSize((int)@filesize($full)),
                    'bytes'    => (int)@filesize($full),
                    'mtime'    => (int)@filemtime($full),
                    'date'     => date('Y-m-d H:i', (int)@filemtime($full)),
                    'url'      => storage_url($itemRel),
                    'thumb'    => $this->isImage($ext)
                        ? ThumbHelper::thumb($itemRel, 300, 200)
                        : '',
                ];
            }
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
        $path = $this->cleanPath((string)Request::post('path', ''));

        // 允许用 / 分隔层级；反斜杠统一转换，去掉首尾与重复斜杠
        $dirName = str_replace('\\', '/', (string)Request::post('dir_name', ''));
        $dirName = (string)preg_replace('#/+#', '/', $dirName);
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

        $target = storage_path($relTarget);
        if (file_exists($target)) {
            Response::json(['code' => 1, 'msg' => '同名文件或文件夹已存在']);
            return;
        }
        if (!@mkdir($target, 0777, true)) {
            Response::json(['code' => 1, 'msg' => '创建失败，请检查目录权限']);
            return;
        }
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
        $path    = $this->cleanPath((string)Request::post('path', ''));
        $oldName = trim((string)Request::post('old_name', ''));
        $newName = trim((string)Request::post('new_name', ''));

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
        $oldPath = $path === '' ? storage_path($oldName) : storage_path($path . '/' . $oldName);
        $newPath = $path === '' ? storage_path($newName) : storage_path($path . '/' . $newName);
        if (!file_exists($oldPath)) {
            Response::json(['code' => 1, 'msg' => '原文件或文件夹不存在']);
            return;
        }
        if (file_exists($newPath)) {
            Response::json(['code' => 1, 'msg' => '同名文件或文件夹已存在']);
            return;
        }
        if (!@rename($oldPath, $newPath)) {
            Response::json(['code' => 1, 'msg' => '重命名失败，请检查目录权限']);
            return;
        }
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
        $path = $this->cleanPath((string)Request::post('path', ''));
        if ($this->isForbiddenPath($path)) {
            Response::json(['code' => 1, 'msg' => '系统缩略图缓存目录禁止操作']);
            return;
        }
        $names = (array)Request::post('names', []);
        if (empty($names)) {
            Response::json(['code' => 1, 'msg' => '请选择要删除的文件或文件夹']);
            return;
        }
        $base = $path === '' ? storage_path() : storage_path($path);
        foreach ($names as $name) {
            $name = basename(trim((string)$name));
            if ($name === '' || $name === self::THUMBS_DIR) {
                continue;
            }
            $this->removePath($base . '/' . $name);
        }
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
        $path   = $this->cleanPath((string)Request::post('path', ''));
        $target = $this->cleanPath((string)Request::post('target', ''));
        $names  = (array)Request::post('names', []);

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
        $targetAbs = $target === '' ? storage_path() : storage_path($target);
        if (!is_dir($targetAbs)) {
            Response::json(['code' => 1, 'msg' => '目标目录不存在']);
            return;
        }
        $base = $path === '' ? storage_path() : storage_path($path);
        foreach ($names as $name) {
            $name = basename(trim((string)$name));
            if ($name === '' || $name === self::THUMBS_DIR) {
                continue;
            }
            $src = $base . '/' . $name;
            $dst = $targetAbs . '/' . $name;
            if (file_exists($src) && !file_exists($dst)) {
                @rename($src, $dst);
            }
        }
        Response::json(['code' => 0, 'msg' => '移动成功']);
    }

    /**
     * 下载单个文件
     */
    public function download()
    {
        $path = $this->cleanPath((string)Request::get('path', ''));
        if ($path === '' || $this->isForbiddenPath($path)) {
            http_response_code(403);
            exit('Forbidden');
        }
        $full = storage_path($path);
        if (!is_file($full)) {
            http_response_code(404);
            exit('Not Found');
        }
        Response::download($full, basename($full));
    }

    /**
     * 递归构建目录树（排除 thumbs）
     */
    private function buildDirTree(string $relPath): array
    {
        $abs = $relPath === '' ? storage_path() : storage_path($relPath);
        $tree = [];
        $items = @scandir($abs);
        if ($items === false) {
            return $tree;
        }
        $dirs = [];
        foreach ($items as $name) {
            if ($name === '.' || $name === '..' || $name === self::THUMBS_DIR) {
                continue;
            }
            $full = $abs . '/' . $name;
            if (is_dir($full)) {
                $dirs[] = $name;
            }
        }
        sort($dirs, SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($dirs as $name) {
            $childRel = $relPath === '' ? $name : $relPath . '/' . $name;
            $children = $this->buildDirTree($childRel);
            $tree[] = [
                'name'     => $name,
                'path'     => $childRel,
                'count'    => $this->countFiles(storage_path($childRel)),
                'children' => $children,
            ];
        }
        return $tree;
    }

    /**
     * 统计目录内文件数（不含子目录与 thumbs）
     */
    private function countFiles(string $dir): int
    {
        $count = 0;
        $items = @scandir($dir);
        if ($items === false) {
            return 0;
        }
        foreach ($items as $name) {
            if ($name === '.' || $name === '..' || $name === self::THUMBS_DIR) {
                continue;
            }
            if (is_file($dir . '/' . $name)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * 递归删除文件/目录
     */
    private function removePath(string $path): void
    {
        if (is_dir($path) && !is_link($path)) {
            $items = @scandir($path);
            if ($items !== false) {
                foreach ($items as $name) {
                    if ($name === '.' || $name === '..') {
                        continue;
                    }
                    $this->removePath($path . '/' . $name);
                }
            }
            @rmdir($path);
        } else {
            @unlink($path);
        }
    }

    /**
     * 清理路径：去 ..、反斜杠转正斜杠、去除首尾斜杠
     */
    private function cleanPath(string $path): string
    {
        $path = str_replace(['..', '\\'], '/', $path);
        $path = preg_replace('#/+#', '/', $path);
        return trim((string)$path, '/');
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
