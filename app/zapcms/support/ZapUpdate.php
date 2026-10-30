<?php

namespace zapcms\support;

/**
 * ZAP CMS 更新管理器
 * 负责检查系统核心和插件的版本更新
 */
class ZapUpdate
{
    /** 更新镜像根地址 */
    public const MIRROR = 'https://mirrors.zap.cn/zapcms';

    /** 版本清单文件名（也可直接写完整 URL） */
    public const MANIFEST = 'latest.json';

    /** 更新包命名规则，{version} 会被替换为版本号；清单中 download_url 优先 */
    public const PACKAGE_PATTERN = 'zapcms-{version}.zip';

    /** 版本清单缓存时间（秒），0 表示不缓存 */
    public const CACHE_TTL = 600;

    /** 请求超时（秒），实际下载超时 = TIMEOUT * 3 */
    public const TIMEOUT = 15;

    /** 是否校验更新包 sha256（清单提供 sha256 时生效） */
    public const VERIFY_HASH = true;

    /** 是否校验镜像 HTTPS 证书 */
    public const VERIFY_SSL = true;

    /** PHP 缺少 CA 根证书导致校验失败时，是否降级为不校验重试 */
    public const INSECURE_FALLBACK = true;

    protected ZapPackageManager $pm;

    /** 版本清单缓存文件 */
    protected string $manifestCacheFile;

    public function __construct()
    {
        $this->pm = new ZapPackageManager();

        $this->pm->setTimeout(self::TIMEOUT);
        $this->pm->setSslVerify(self::VERIFY_SSL);
        $this->pm->setInsecureFallback(self::INSECURE_FALLBACK);

        $this->manifestCacheFile = APP_ROOT . '/var/cache/update_manifest.json';
    }

    // ==================== 核心更新（镜像源） ====================

    /**
     * 更新源根地址，如 https://mirrors.zap.cn/zapcms
     */
    public function getMirror(): string
    {
        return rtrim(self::MIRROR, '/');
    }

    /**
     * 版本清单地址，如 https://mirrors.zap.cn/zapcms/latest.json
     */
    public function getManifestUrl(): string
    {
        $manifest = self::MANIFEST;
        if (preg_match('#^https?://#i', $manifest)) {
            return $manifest;
        }
        return $this->getMirror() . '/' . ltrim($manifest, '/');
    }

    /**
     * 检查系统核心是否有新版本
     *
     * @param bool $force true 时忽略本地缓存，强制请求镜像
     * @return array|null 返回 null 表示检查失败（网络/格式问题）
     */
    public function checkCoreUpdate(bool $force = false): ?array
    {
        $manifest = $this->fetchManifest($force);
        if (!$manifest) {
            return null;
        }

        $info = $this->buildCoreUpdateInfo($manifest);
        return $info;
    }

    /**
     * 获取最新版本清单（带文件缓存）
     */
    public function fetchManifest(bool $force = false): ?array
    {
        $ttl = self::CACHE_TTL;

        if (!$force && $ttl > 0 && is_file($this->manifestCacheFile)
            && (time() - (int)filemtime($this->manifestCacheFile)) < $ttl) {
            $cached = json_decode((string)@file_get_contents($this->manifestCacheFile), true);
            if (is_array($cached) && !empty($cached['data']) && is_array($cached['data'])) {
                return $cached['data'];
            }
        }

        $data = $this->pm->httpGet($this->getManifestUrl());
        if (!is_array($data) || empty($data)) {
            // 请求失败时，若存在过期缓存则继续使用，避免后台页面因网络抖动显示“检查失败”
            if (is_file($this->manifestCacheFile)) {
                $cached = json_decode((string)@file_get_contents($this->manifestCacheFile), true);
                if (is_array($cached) && !empty($cached['data']) && is_array($cached['data'])) {
                    return $cached['data'];
                }
            }
            return null;
        }

        // 兼容 { "data": {...} } / { "latest": {...} } 包装
        if (isset($data['data']) && is_array($data['data'])) {
            $data = $data['data'];
        } elseif (isset($data['latest']) && is_array($data['latest'])) {
            $data = $data['latest'];
        }

        // 至少要有版本号，否则视为无效清单
        if ($this->normalizeVersion($data['version'] ?? $data['latest_version'] ?? '') === '') {
            return null;
        }

        $this->writeManifestCache($data);

        return $data;
    }

    /**
     * 清除更新清单缓存（更新完成后调用）
     */
    public function clearManifestCache(): void
    {
        if (is_file($this->manifestCacheFile)) {
            @unlink($this->manifestCacheFile);
        }
    }

    /**
     * 写入清单缓存
     */
    protected function writeManifestCache(array $data): void
    {
        $dir = dirname($this->manifestCacheFile);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return;
        }
        @file_put_contents(
            $this->manifestCacheFile,
            json_encode(['time' => time(), 'mirror' => $this->getMirror(), 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * 把镜像清单转换成统一的更新信息结构
     */
    public function buildCoreUpdateInfo(array $data): ?array
    {
        $latest = $this->normalizeVersion($data['version'] ?? $data['latest_version'] ?? '');
        if ($latest === '') {
            return null;
        }

        $current = $this->normalizeVersion(ZAP_CMS_VERSION);

        $changelog = $data['changelog'] ?? $data['notes'] ?? '';
        if (is_array($changelog)) {
            $changelog = implode("\n", array_map('strval', $changelog));
        }

        $minPhp = (string)($data['min_php'] ?? $data['php'] ?? '');
        $minPhp = ltrim(trim($minPhp), '>=');
        $phpOk  = $minPhp === '' || version_compare(PHP_VERSION, $minPhp, '>=');

        return [
            'has_update'      => version_compare($latest, $current, '>'),
            'current_version' => $current,
            'latest_version'  => $latest,
            'download_url'    => $this->resolveDownloadUrl($data, $latest),
            'changelog'       => trim((string)$changelog),
            'release_date'    => (string)($data['release_date'] ?? $data['date'] ?? ''),
            'package_size'    => (int)($data['size'] ?? $data['package_size'] ?? 0),
            'sha256'          => strtolower(trim((string)($data['sha256'] ?? $data['checksum'] ?? ''))),
            'is_critical'     => (bool)($data['critical'] ?? $data['is_critical'] ?? false),
            'min_php'         => $minPhp,
            'php_ok'          => $phpOk,
            'manifest_url'    => $this->getManifestUrl(),
        ];
    }

    /**
     * 解析更新包下载地址：清单 download_url → packages[版本] → 文件名规则
     */
    protected function resolveDownloadUrl(array $data, string $version): string
    {
        $url = (string)($data['download_url'] ?? $data['download'] ?? $data['url'] ?? '');

        if ($url === '' && !empty($data['packages']) && is_array($data['packages'])) {
            $package = $data['packages'][$version] ?? null;
            if (is_array($package)) {
                $url = (string)($package['url'] ?? $package['download_url'] ?? '');
            } elseif (is_string($package)) {
                $url = $package;
            }
        }

        if ($url === '') {
            $url = str_replace('{version}', $version, self::PACKAGE_PATTERN);
        }

        // 相对路径拼到镜像根地址上
        if ($url !== '' && !preg_match('#^https?://#i', $url)) {
            $url = $this->getMirror() . '/' . ltrim($url, '/');
        }

        return $url;
    }

    /**
     * 规范化版本号：去空白、去前缀 v/V
     */
    protected function normalizeVersion(string $version): string
    {
        $version = trim($version);
        if ($version !== '' && ($version[0] === 'v' || $version[0] === 'V')) {
            $version = substr($version, 1);
        }
        return $version;
    }

    /**
     * 执行系统核心更新
     *
     * @param string $downloadUrl 下载地址（通常来自 latest.json）
     * @param string $newVersion  新版本号
     * @param string $sha256      更新包 sha256（可空，为空则不校验）
     * @return array
     */
    public function doCoreUpdate(string $downloadUrl, string $newVersion, string $sha256 = ''): array
    {
        $newVersion = $this->normalizeVersion($newVersion);
        $downloadUrl = trim($downloadUrl);

        if ($newVersion === '') {
            return ['success' => false, 'message' => '目标版本号无效'];
        }

        if ($downloadUrl === '' || !preg_match('#^https?://#i', $downloadUrl)) {
            return ['success' => false, 'message' => '更新地址无效'];
        }

        if (!$this->checkFilePermissions()) {
            return ['success' => false, 'message' => '文件系统权限不足，请确保项目目录可写'];
        }

        if (!$this->checkDiskSpace()) {
            return ['success' => false, 'message' => '磁盘空间不足（需要至少50MB可用空间）'];
        }

        $zipFile = $this->pm->download($downloadUrl, 'zapcms_update_' . $newVersion . '.zip');
        if (!$zipFile) {
            return ['success' => false, 'message' => '更新包下载失败，请检查网络连接或镜像地址'];
        }

        // 校验更新包完整性
        if ($sha256 !== '' && self::VERIFY_HASH) {
            $actual = @hash_file('sha256', $zipFile);
            if ($actual === false || !hash_equals($sha256, strtolower($actual))) {
                @unlink($zipFile);
                return ['success' => false, 'message' => '更新包校验失败（sha256 不匹配），已终止更新'];
            }
        }

        $result = $this->pm->applySystemUpdate($zipFile, $newVersion);

        @unlink($zipFile);
        $this->clearManifestCache();

        if (empty($result['success'])) {
            $this->pm->recordUpdateHistory('core', ZAP_CMS_VERSION, $newVersion, 'failed');
        }

        return $result;
    }

    /**
     * 手动上传更新包进行更新
     *
     * @param string $zipFilePath 上传的ZIP文件路径
     * @param string $newVersion  新版本号，留空则自动从包内 app/bootstrap.php 识别
     * @return array
     */
    public function doManualUpdate(string $zipFilePath, string $newVersion = ''): array
    {
        if (!file_exists($zipFilePath)) {
            return ['success' => false, 'message' => '更新包文件不存在'];
        }

        if ($newVersion === '') {
            $newVersion = $this->detectPackageVersion($zipFilePath);
        }

        if ($newVersion === '') {
            return ['success' => false, 'message' => '无法识别更新包版本号（包内缺少 app/bootstrap.php 的 ZAP_CMS_VERSION）'];
        }

        if (!$this->checkFilePermissions()) {
            return ['success' => false, 'message' => '文件系统权限不足'];
        }

        $result = $this->pm->applySystemUpdate($zipFilePath, $newVersion);

        if (empty($result['success'])) {
            $this->pm->recordUpdateHistory('core', ZAP_CMS_VERSION, $newVersion, 'failed');
        }

        return $result;
    }

    /**
     * 从更新包中读取版本号（app/bootstrap.php 的 ZAP_CMS_VERSION）
     *
     * 兼容扁平包与“单层根目录”包（如 zapcms-1.0.2/app/bootstrap.php）。
     */
    public function detectPackageVersion(string $zipFile): string
    {
        if (!class_exists('ZipArchive')) {
            return '';
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipFile) !== true) {
            return '';
        }

        $content = $zip->getFromName('app/bootstrap.php');

        if ($content === false) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string)$zip->getNameIndex($i);
                if (preg_match('#^[^/]+/app/bootstrap\.php$#', $name)) {
                    $content = $zip->getFromName($name);
                    break;
                }
            }
        }

        $zip->close();

        if ($content === false || $content === '') {
            return '';
        }

        if (preg_match('/ZAP_CMS_VERSION\s*=\s*([\'"])([0-9a-zA-Z.\-_]+)\1/', (string)$content, $m)) {
            return $m[2];
        }

        return '';
    }

    // ==================== 插件更新检查 ====================

    /**
     * 检查所有已安装插件的更新
     *
     * @return array 需要更新的插件列表
     */
    public function checkPluginUpdates(): array
    {
        $plugins  = $this->pm->getInstalledPlugins();
        $updates  = [];

        if (empty($plugins)) {
            return $updates;
        }

        $packages = array_column($plugins, 'package_name');
        $versions = [];
        foreach ($plugins as $p) {
            $versions[$p['package_name']] = $p['version'];
        }

        $response = $this->pm->apiPost('plugin/check-updates', [
            'packages' => $packages,
            'versions' => $versions,
        ]);

        if (!$response || empty($response['updates'])) {
            return $updates;
        }

        foreach ($response['updates'] as $update) {
            foreach ($plugins as $plugin) {
                if ($plugin['package_name'] === $update['package_name']) {
                    $updates[] = [
                        'name'          => $plugin['name'],
                        'title'         => $plugin['title'],
                        'package_name'  => $plugin['package_name'],
                        'current_version' => $plugin['version'],
                        'latest_version'  => $update['latest_version'],
                        'download_url'    => $update['download_url'] ?? '',
                        'changelog'       => $update['changelog'] ?? '',
                    ];
                    break;
                }
            }
        }

        return $updates;
    }

    /**
     * 执行单个插件更新
     *
     * @param string $name        插件名
     * @param string $downloadUrl 下载地址
     * @param string $version     新版本
     * @return array
     */
    public function doPluginUpdate(string $name, string $downloadUrl, string $version): array
    {
        return $this->pm->updatePlugin($name, $downloadUrl, $version);
    }

    // ==================== 插件市场 ====================

    /**
     * 从远程获取插件市场列表
     *
     * @param int    $page     页码
     * @param int    $perPage  每页条数
     * @param string $search   搜索关键词
     * @param string $category 分类
     * @return array|null
     */
    public function getMarketPlugins(int $page = 1, int $perPage = 12, string $search = '', string $category = ''): ?array
    {
        return $this->pm->apiGet('plugin/list', [
            'page'     => $page,
            'per_page' => $perPage,
            'search'   => $search,
            'category' => $category,
            'version'  => ZAP_CMS_VERSION,
        ]);
    }

    /**
     * 获取单个市场插件详情
     */
    public function getMarketPluginDetail(string $packageName): ?array
    {
        return $this->pm->apiGet('plugin/detail', [
            'package' => $packageName,
        ]);
    }

    /**
     * 安装市场插件
     */
    public function installMarketPlugin(string $packageName): array
    {
        // 获取远程插件详情
        $detail = $this->getMarketPluginDetail($packageName);

        if (!$detail) {
            return ['success' => false, 'message' => '获取插件信息失败'];
        }

        if (empty($detail['download_url'])) {
            return ['success' => false, 'message' => '插件下载地址无效'];
        }

        return $this->pm->installPlugin(
            $detail['package_name'] ?? $packageName,
            $detail['download_url'],
            $detail['version'] ?? '1.0.0'
        );
    }

    // ==================== 工具方法 ====================

    /**
     * 检查关键文件和目录是否可写
     */
    public function checkFilePermissions(): bool
    {
        $checkPaths = [
            APP_ROOT,
            APP_ROOT . '/app',
            APP_ROOT . '/config',
            APP_ROOT . '/assets',
            APP_ROOT . '/storage',
            APP_ROOT . '/mods',
        ];

        foreach ($checkPaths as $path) {
            if (file_exists($path) && !is_writable($path)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 检查磁盘空间
     *
     * @param int $requiredMB 需要的磁盘空间(MB)
     * @return bool
     */
    public function checkDiskSpace(int $requiredMB = 50): bool
    {
        $freeSpace = disk_free_space(APP_ROOT);
        if ($freeSpace === false) {
            return true; // 无法检查则放行
        }
        return $freeSpace > ($requiredMB * 1024 * 1024);
    }

    /**
     * 获取当前数据库版本（SQLite / MySQL 通用）
     */
    protected function getDbVersion(): string
    {
        try {
            $pdo    = \zap\DB::connection();
            $driver = (string)$pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

            if ($driver === 'sqlite') {
                return 'SQLite ' . $pdo->query('SELECT sqlite_version()')->fetchColumn();
            }

            return $driver . ' ' . $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);
        } catch (\Throwable $e) {
            return 'unknown';
        }
    }

    /**
     * 获取系统环境信息（用于调试和提交反馈时）
     */
    public function getSystemInfo(): array
    {
        return [
            'zapcms_version' => ZAP_CMS_VERSION,
            'php_version'    => PHP_VERSION,
            'php_os'         => PHP_OS,
            'php_sapi'       => PHP_SAPI,
            'mysql_version'  => $this->getDbVersion(),
            'server'         => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
            'disk_free'      => $this->formatBytes(disk_free_space(APP_ROOT)),
            'memory_limit'   => ini_get('memory_limit'),
            'max_execution'  => ini_get('max_execution_time'),
            'upload_max'     => ini_get('upload_max_filesize'),
            'extensions'     => $this->getExtensions(),
        ];
    }

    /**
     * 获取已加载的关键PHP扩展
     */
    protected function getExtensions(): array
    {
        $keyExtensions = ['curl', 'zip', 'pdo', 'pdo_mysql', 'json', 'mbstring', 'gd', 'xml', 'openssl'];
        $loaded = [];
        foreach ($keyExtensions as $ext) {
            $loaded[$ext] = extension_loaded($ext);
        }
        return $loaded;
    }

    /**
     * 格式化字节数
     */
    protected function formatBytes(float $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow   = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow   = min($pow, count($units) - 1);
        return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
    }

    /**
     * 获取更新历史记录
     */
    public function getUpdateHistory(int $limit = 20): array
    {
        return $this->pm->getUpdateHistory($limit);
    }
}
