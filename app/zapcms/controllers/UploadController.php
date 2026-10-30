<?php

namespace zapcms\controllers;


use zapcms\controllers\AdminController;
use zap\http\Request;
use zap\http\Response;

class UploadController extends AdminController
{
    /**
     * 允许上传的扩展名白名单（小写）
     *
     * 注意：svg 不在其中 —— 它是 XML 文档而非位图，可内嵌 <script>，
     * 且 storage/ 目录会被 Web 服务器直接输出，等同于存储型 XSS。
     */
    private const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'ico',
        'zip', 'rar', '7z', 'tar', 'gz', 'pdf', 'doc', 'docx',
        'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'md', 'csv',
        'mp3', 'mp4', 'avi', 'mov', 'wmv', 'flv', 'webm',
        'json', 'xml',
    ];

    /** 需要做图片内容嗅探的扩展名 */
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'ico'];

    /**
     * 黑名单：即使后台「upload.extensions」里显式配置了这些后缀也不予放行，
     * 避免管理员配置失误导致可执行脚本/活动内容被写入可直接访问的目录。
     */
    private const FORBIDDEN_EXTENSIONS = [
        'svg', 'htm', 'html', 'js', 'mjs', 'htaccess',
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'pht',
        'asp', 'aspx', 'jsp', 'cgi', 'pl', 'py', 'rb',
        'exe', 'dll', 'sh', 'bat', 'cmd', 'com',
    ];

    function image()
    {
        if (Request::isPost()) {
            $this->saveUpload('images', '', '', true);
        }
    }


    function file()
    {
        if (Request::isPost()) {
            // fullPath：拖拽上传目录时由前端传入（形如 /dir/sub/file.txt），用于保留目录结构
            $this->saveUpload('', (string)Request::post('path'), (string)Request::post('fullPath', ''));
        }
    }

    /**
     * 统一处理文件上传：校验扩展名白名单、大小限制、防路径穿越、按规则生成文件名
     * @param string $subDir 子目录（如 images），为空时使用 path 参数
     * @param string $path   文件管理器指定目录
     * @param string $fullPath 目录上传时的相对完整路径（仅用于提取子目录结构）
     * @param bool   $imageOnly 是否强制要求上传内容为真实图片
     */
    private function saveUpload(string $subDir, string $path = '', string $fullPath = '', bool $imageOnly = false)
    {
        $file = Request::file('file');
        if (!isset($file['error']) || $file['error'] != UPLOAD_ERR_OK) {
            Response::json(['code' => 1, 'msg' => $this->errorMessage($file['error'] ?? UPLOAD_ERR_NO_FILE)]);
            return;
        }

        // 扩展名白名单校验（取原文件名最后一个点之后的部分）
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $this->allowedExtensions(), true)) {
            Response::json(['code' => 1, 'msg' => '不支持的文件类型: .' . $ext]);
            return;
        }

        // 内容校验：只看扩展名不足以判断文件真实类型
        if ($imageOnly || in_array($ext, self::IMAGE_EXTENSIONS, true)) {
            if (@getimagesize($file['tmp_name']) === false) {
                Response::json(['code' => 1, 'msg' => '文件内容不是有效的图片']);
                return;
            }
        }

        // 单文件大小限制（后台「文件上传」配置，单位 MB）
        $maxSizeMb = $this->maxFileSizeMb();
        if ($file['size'] > $maxSizeMb * 1024 * 1024) {
            Response::json(['code' => 1, 'msg' => '文件大小超过限制，单个文件最大 ' . $maxSizeMb . ' MB']);
            return;
        }

        // 防路径穿越 + 统一目录分隔
        $path = trim(str_replace(['..', '\\'], '/', (string)$path), '/');
        $relPath = $subDir !== '' ? trim($subDir, '/') : $path;

        // 目录上传：从 fullPath（形如 /dir/sub/file.txt）提取子目录结构并拼接到目标目录
        if ($fullPath !== '') {
            $fullPath = trim(str_replace(['..', '\\'], '/', (string)$fullPath), '/');
            $dirPart = dirname($fullPath);
            if ($dirPart !== '.' && $dirPart !== '/') {
                $relSubDir = trim($dirPart, '/');
                $relPath = $relPath === '' ? $relSubDir : $relPath . '/' . $relSubDir;
            }
        }

        // 系统缩略图缓存目录（thumbs）禁止上传，该目录由系统自动生成，不允许写入
        if ($relPath === 'thumbs' || str_starts_with($relPath, 'thumbs/')) {
            Response::json(['code' => 1, 'msg' => '系统缩略图缓存目录禁止上传文件']);
            return;
        }

        $targetDir = $relPath === '' ? storage_path() : storage_path($relPath);
        if (!is_dir($targetDir)) {
            // 0775 而非 0777：目录只需属主与属组可写，不应开放 other-write
            mkdir($targetDir, 0775, true);
            @chmod($targetDir, 0775);
        }

        // 按后台配置的命名规则生成文件名（original 规则下会自动规避重名）
        $filename = $this->buildFilename($file['name'], $ext, $targetDir);

        if (!move_uploaded_file($file['tmp_name'], $targetDir . '/' . $filename)) {
            Response::json(['code' => 1, 'msg' => '文件保存失败']);
            return;
        }

        $urlPath = $relPath === '' ? $filename : $relPath . '/' . $filename;
        Response::json(['code' => 0, 'url' => storage_url($urlPath)]);
    }

    /**
     * 允许上传的扩展名白名单（小写），来自后台「文件上传」配置 upload.extensions
     * 配置为空时回退到系统默认白名单
     */
    private function allowedExtensions(): array
    {
        $raw = trim((string)option('upload.extensions', ''));
        if ($raw !== '') {
            $exts = array_values(array_filter(
                array_map('strtolower', array_map('trim', explode(',', $raw))),
                static fn($v) => $v !== ''
            ));
            if (!empty($exts)) {
                // 黑名单优先级高于后台配置
                return array_values(array_diff($exts, self::FORBIDDEN_EXTENSIONS));
            }
        }
        return self::ALLOWED_EXTENSIONS;
    }

    /**
     * 单文件大小上限（MB），来自后台「文件上传」配置 upload.max_size，最小 1 MB
     */
    private function maxFileSizeMb(): int
    {
        return max(1, (int)option('upload.max_size', 20));
    }

    /**
     * 按后台「文件上传」配置 upload.name_rule 生成文件名
     * @param string $originalName 原始文件名
     * @param string $ext          扩展名（小写，不含点）
     * @param string $targetDir    目标目录（original 规则用于判断重名）
     */
    private function buildFilename(string $originalName, string $ext, string $targetDir): string
    {
        $rule = (string)option('upload.name_rule', 'random');
        switch ($rule) {
            case 'original':
                // 保留原文件名（清理非法字符），同名时自动追加 _1/_2 后缀避免覆盖
                $base = preg_replace('/\.[^.]+$/', '', $originalName);
                $base = (string)preg_replace('/[\/\\\\:*?"<>|\r\n\t]+/u', '-', (string)$base);
                $base = trim($base, ' .');
                $base = $base === '' ? 'file' : $base;
                $base = mb_substr($base, 0, 80);
                $candidate = $base . '.' . $ext;
                $i = 1;
                while (file_exists($targetDir . '/' . $candidate)) {
                    $candidate = $base . '_' . $i . '.' . $ext;
                    $i++;
                }
                return $candidate;
            case 'date':
                // 日期 + 随机后缀，便于按时间归类排序
                return date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            case 'random':
            default:
                // 文件名必须不可预测，避免攻击者枚举已上传文件
                return bin2hex(random_bytes(12)) . '.' . $ext;
        }
    }

    private function errorMessage($code): string
    {
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
                $message = "The uploaded file exceeds the upload_max_filesize directive in php.ini";
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $message = "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form";
                break;
            case UPLOAD_ERR_PARTIAL:
                $message = "The uploaded file was only partially uploaded";
                break;
            case UPLOAD_ERR_NO_FILE:
                $message = "No file was uploaded";
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $message = "Missing a temporary folder";
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $message = "Failed to write file to disk";
                break;
            case UPLOAD_ERR_EXTENSION:
                $message = "File upload stopped by extension";
                break;
            default:
                $message = "Unknown upload error";
                break;
        }

        return $message;

    }

}