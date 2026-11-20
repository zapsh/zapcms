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
            // 'body_class' => 'layout-fixed sidebar-expand-lg sidebar-mini sidebar-collapse bg-body-tertiary app-loaded',
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
        $this->requirePerm('edit');
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

    /**
     * 新建文件
     */
    public function createFile()
    {
        $this->requirePerm('edit');
        if (!req()->isPost()) {
            response(['code' => 1, 'msg' => '无效的请求方式'])->withJson();
            return;
        }
        $dir  = (string) req()->post('path', '');
        $name = trim((string) req()->post('filename', ''));
        if (!$this->isValidName($name)) {
            response(['code' => 1, 'msg' => '文件名不合法'])->withJson();
            return;
        }

        $rel    = $this->normalizeRel($dir . '/' . $name);
        $target = $this->resolvePath($rel, false);
        if ($target === null) {
            response(['code' => 1, 'msg' => '不允许访问该路径'])->withJson();
            return;
        }
        if (file_exists($target)) {
            response(['code' => 1, 'msg' => '同名文件或文件夹已存在'])->withJson();
            return;
        }
        $parent = dirname($target);
        if (!is_dir($parent)) {
            response(['code' => 1, 'msg' => '目录不存在'])->withJson();
            return;
        }
        if (!is_writable($parent)) {
            response(['code' => 1, 'msg' => '目录不可写'])->withJson();
            return;
        }
        if (@file_put_contents($target, '') === false) {
            response(['code' => 1, 'msg' => '创建失败，请检查目录权限'])->withJson();
            return;
        }

        $display = '/' . $rel;
        AdminLog::warning(AdminLog::CATEGORY_SECURITY, '新建代码文件', '路径：' . $display);
        response(['code' => 0, 'msg' => '创建成功', 'path' => $display])->withJson();
    }

    /**
     * 新建文件夹
     */
    public function createDir()
    {
        $this->requirePerm('edit');
        if (!req()->isPost()) {
            response(['code' => 1, 'msg' => '无效的请求方式'])->withJson();
            return;
        }
        $dir  = (string) req()->post('path', '');
        $name = trim((string) req()->post('dirname', ''));
        if (!$this->isValidName($name)) {
            response(['code' => 1, 'msg' => '文件夹名称不合法'])->withJson();
            return;
        }

        $rel    = $this->normalizeRel($dir . '/' . $name);
        $target = $this->resolvePath($rel, false);
        if ($target === null) {
            response(['code' => 1, 'msg' => '不允许访问该路径'])->withJson();
            return;
        }
        if (file_exists($target)) {
            response(['code' => 1, 'msg' => '同名文件或文件夹已存在'])->withJson();
            return;
        }
        if (!@mkdir($target, 0775, true)) {
            response(['code' => 1, 'msg' => '创建失败，请检查目录权限'])->withJson();
            return;
        }
        @chmod($target, 0775);

        $display = '/' . $rel;
        AdminLog::warning(AdminLog::CATEGORY_SECURITY, '新建代码目录', '路径：' . $display);
        response(['code' => 0, 'msg' => '创建成功', 'path' => $display])->withJson();
    }

    /**
     * 重命名文件/文件夹
     */
    public function rename()
    {
        $this->requirePerm('edit');
        if (!req()->isPost()) {
            response(['code' => 1, 'msg' => '无效的请求方式'])->withJson();
            return;
        }
        $dir = (string) req()->post('path', '');
        $old = trim((string) req()->post('old_name', ''));
        $new = trim((string) req()->post('new_name', ''));

        if (!$this->isValidName($old) || !$this->isValidName($new)) {
            response(['code' => 1, 'msg' => '名称不合法'])->withJson();
            return;
        }

        $relOld = $this->normalizeRel($dir . '/' . $old);
        $relNew = $this->normalizeRel($dir . '/' . $new);

        $absOld = $this->resolvePath($relOld, true);
        $absNew = $this->resolvePath($relNew, false);
        if ($absOld === null || $absNew === null) {
            response(['code' => 1, 'msg' => '不允许访问该路径'])->withJson();
            return;
        }
        if (!file_exists($absOld)) {
            response(['code' => 1, 'msg' => '原文件或文件夹不存在'])->withJson();
            return;
        }
        if ($relOld === $relNew) {
            response(['code' => 0, 'msg' => '重命名成功', 'path' => '/' . $relNew])->withJson();
            return;
        }
        if (file_exists($absNew)) {
            response(['code' => 1, 'msg' => '同名文件或文件夹已存在'])->withJson();
            return;
        }
        if (!is_writable(dirname($absOld))) {
            response(['code' => 1, 'msg' => '目录不可写'])->withJson();
            return;
        }
        if (!@rename($absOld, $absNew)) {
            response(['code' => 1, 'msg' => '重命名失败，请检查目录权限'])->withJson();
            return;
        }

        AdminLog::warning(
            AdminLog::CATEGORY_SECURITY,
            '重命名代码文件',
            '/' . $relOld . ' → /' . $relNew
        );
        response(['code' => 0, 'msg' => '重命名成功', 'path' => '/' . $relNew])->withJson();
    }

    /**
     * 删除文件/文件夹（支持批量，目录递归删除）
     */
    public function delete()
    {
        $this->requirePerm('delete');
        if (!req()->isPost()) {
            response(['code' => 1, 'msg' => '无效的请求方式'])->withJson();
            return;
        }
        $dir   = (string) req()->post('path', '');
        $names = (array) req()->post('names', []);
        if (empty($names)) {
            response(['code' => 1, 'msg' => '请选择要删除的文件或文件夹'])->withJson();
            return;
        }

        $deleted = 0;
        $base    = $this->baseDir();
        foreach ($names as $name) {
            $name = basename(trim((string) $name));
            if (!$this->isValidName($name)) {
                continue;
            }
            $rel    = $this->normalizeRel($dir . '/' . $name);
            $target = $this->resolvePath($rel, true);
            if ($target === null || $target === $base || !file_exists($target)) {
                continue;
            }
            if ($this->removePath($target)) {
                $deleted++;
            }
        }

        if ($deleted === 0) {
            response(['code' => 1, 'msg' => '删除失败，文件不存在或不可删除'])->withJson();
            return;
        }

        AdminLog::warning(
            AdminLog::CATEGORY_SECURITY,
            '删除代码文件',
            '目录：/' . $this->normalizeRel($dir) . '，共 ' . $deleted . ' 项'
        );
        response(['code' => 0, 'msg' => '删除成功'])->withJson();
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

    // ─── 路径与文件操作辅助 ─────────────────────────────────────

    /** 项目根目录真实路径（统一正斜杠） */
    private function baseDir(): string
    {
        return rtrim(str_replace('\\', '/', (string) realpath(app()->basePath('/'))), '/');
    }

    /** 规范化相对路径：统一分隔符、去除首尾斜杠、合并重复斜杠 */
    private function normalizeRel(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = (string) preg_replace('#/+#', '/', $path);
        return trim($path, '/');
    }

    /** 校验单级文件/目录名是否合法 */
    private function isValidName(string $name): bool
    {
        if ($name === '' || $name === '.' || $name === '..') {
            return false;
        }
        if (strlen($name) > 255) {
            return false;
        }
        return !preg_match('#[/\\\\:*?"<>|]#', $name);
    }

    /**
     * 将相对路径解析为绝对路径，并确保位于项目根目录内、且不属于受保护目录。
     *
     * @param string $rel       相对路径（不含前导斜杠）
     * @param bool   $mustExist 目标是否必须已存在
     * @return string|null      绝对路径；非法时返回 null
     */
    private function resolvePath(string $rel, bool $mustExist = true): ?string
    {
        $rel = $this->normalizeRel($rel);
        foreach ($rel === '' ? [] : explode('/', $rel) as $seg) {
            if ($seg === '' || $seg === '.' || $seg === '..') {
                return null;
            }
        }

        $base = $this->baseDir();
        $abs  = $base . ($rel === '' ? '' : '/' . $rel);

        // 受保护目录：vendor / .git（与 saveFile 保持一致）
        $probe = '/' . $rel . '/';
        if (str_contains($probe, '/vendor/') || str_contains($probe, '/.git/')) {
            return null;
        }

        $check = $mustExist ? realpath($abs) : realpath(dirname($abs));
        if ($check === false) {
            return null;
        }
        $check = str_replace('\\', '/', $check);
        if ($check !== $base && !str_starts_with($check . '/', $base . '/')) {
            return null;
        }
        return $abs;
    }

    /** 递归删除文件/目录 */
    private function removePath(string $path): bool
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
            return @rmdir($path);
        }
        return @unlink($path);
    }

}