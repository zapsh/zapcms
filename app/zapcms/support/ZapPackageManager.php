<?php

namespace zapcms\support;

use zap\DB;
use zapcms\services\Option;

/**
 * ZAP CMS 包管理器
 * 负责插件的下载、安装、卸载、更新以及系统核心更新
 */
class ZapPackageManager
{
    /**
     * 临时目录
     */
    protected string $tmpDir;

    /**
     * 备份目录
     */
    protected string $backupDir;

    /**
     * 插件目录
     */
    protected string $modsDir;

    /**
     * 远程API地址
     */
    protected string $apiUrl;

    /**
     * 是否开启SSL验证
     */
    protected bool $sslVerify = true;

    /**
     * cURL超时时间(秒)
     */
    protected int $timeout = 30;

    public function __construct()
    {
        $this->tmpDir    = APP_ROOT . '/var/tmp';
        $this->backupDir = APP_ROOT . '/var/backup';
        $this->modsDir   = APP_ROOT . '/mods';
        $this->apiUrl    = Option::get('website.api_url', 'https://api.zap.cn/api/v1');

        $this->ensureDirectory($this->tmpDir);
        $this->ensureDirectory($this->backupDir);
    }

    /**
     * 设置API地址
     */
    public function setApiUrl(string $url): self
    {
        $this->apiUrl = rtrim($url, '/');
        return $this;
    }

    /**
     * 获取API地址
     */
    public function getApiUrl(): string
    {
        return $this->apiUrl;
    }

    /**
     * 设置SSL验证
     */
    public function setSslVerify(bool $verify): self
    {
        $this->sslVerify = $verify;
        return $this;
    }

    /**
     * 设置超时时间
     */
    public function setTimeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }

    // ==================== 远程API请求 ====================

    /**
     * 向远程API发送GET请求
     */
    public function apiGet(string $endpoint, array $params = []): ?array
    {
        $url = $this->apiUrl . '/' . ltrim($endpoint, '/');
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        return $this->httpGet($url);
    }

    /**
     * 向远程API发送POST请求
     */
    public function apiPost(string $endpoint, array $data = []): ?array
    {
        $url = $this->apiUrl . '/' . ltrim($endpoint, '/');
        return $this->httpPost($url, $data);
    }

    /**
     * HTTP GET 请求
     */
    public function httpGet(string $url): ?array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => $this->sslVerify,
            CURLOPT_SSL_VERIFYHOST => $this->sslVerify ? 2 : 0,
            CURLOPT_USERAGENT      => 'ZAPCMS/' . ZAP_CMS_VERSION,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'X-ZAPCMS-VERSION: ' . ZAP_CMS_VERSION,
                'X-ZAPCMS-SITE: ' . ($_SERVER['HTTP_HOST'] ?? ''),
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);

        if ($error) {
            error_log("ZapPackageManager HTTP GET error: {$error}");
            return null;
        }

        if ($httpCode >= 400) {
            error_log("ZapPackageManager HTTP GET failed with code: {$httpCode}");
            return null;
        }

        return json_decode($response, true);
    }

    /**
     * HTTP POST 请求
     */
    public function httpPost(string $url, array $data = []): ?array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($data),
            CURLOPT_SSL_VERIFYPEER => $this->sslVerify,
            CURLOPT_SSL_VERIFYHOST => $this->sslVerify ? 2 : 0,
            CURLOPT_USERAGENT      => 'ZAPCMS/' . ZAP_CMS_VERSION,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'X-ZAPCMS-VERSION: ' . ZAP_CMS_VERSION,
                'X-ZAPCMS-SITE: ' . ($_SERVER['HTTP_HOST'] ?? ''),
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);

        if ($error) {
            error_log("ZapPackageManager HTTP POST error: {$error}");
            return null;
        }

        if ($httpCode >= 400) {
            error_log("ZapPackageManager HTTP POST failed with code: {$httpCode}");
            return null;
        }

        return json_decode($response, true);
    }

    // ==================== 文件下载与解压 ====================

    /**
     * 下载文件到临时目录
     *
     * @param string $url      下载地址
     * @param string $filename 保存的文件名
     * @return string|false 临时文件路径，失败返回false
     */
    public function download(string $url, string $filename = '')
    {
        if (empty($filename)) {
            $filename = basename(parse_url($url, PHP_URL_PATH));
        }

        $tmpFile = $this->tmpDir . '/' . $filename;

        $fp = fopen($tmpFile, 'w+');
        if (!$fp) {
            return false;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_FILE           => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => $this->timeout * 3,
            CURLOPT_SSL_VERIFYPEER => $this->sslVerify,
            CURLOPT_SSL_VERIFYHOST => $this->sslVerify ? 2 : 0,
            CURLOPT_USERAGENT      => 'ZAPCMS/' . ZAP_CMS_VERSION,
        ]);

        $success  = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        fclose($fp);

        if (!$success || $httpCode >= 400 || $error) {
            @unlink($tmpFile);
            return false;
        }

        return $tmpFile;
    }

    /**
     * 解压ZIP文件到指定目录
     *
     * @param string $zipFile    ZIP文件路径
     * @param string $destDir    目标目录
     * @param bool   $stripRoot  是否去除根目录层级
     * @return bool
     */
    public function extract(string $zipFile, string $destDir, bool $stripRoot = true): bool
    {
        if (!class_exists('ZipArchive')) {
            error_log("ZapPackageManager: ZipArchive class not available");
            return false;
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipFile) !== true) {
            return false;
        }

        // 先解压到临时位置
        $extractTmp = $this->tmpDir . '/extract_' . uniqid();
        $this->ensureDirectory($extractTmp);

        $zip->extractTo($extractTmp);
        $zip->close();

        // 去除根目录层级
        $sourceDir = $extractTmp;
        if ($stripRoot) {
            $files = scandir($extractTmp);
            $files = array_diff($files, ['.', '..']);
            if (count($files) === 1) {
                $single = current($files);
                if (is_dir($extractTmp . '/' . $single)) {
                    $sourceDir = $extractTmp . '/' . $single;
                }
            }
        }

        // 复制到目标目录
        $this->ensureDirectory($destDir);
        $this->copyDirectory($sourceDir, $destDir);

        // 清理临时文件
        $this->deleteDirectory($extractTmp);

        return true;
    }

    // ==================== 插件操作 ====================

    /**
     * 安装插件（从远程下载并安装）
     *
     * @param string $packageName 插件包名
     * @param string $downloadUrl 下载地址
     * @param string $version     版本号
     * @return array
     */
    public function installPlugin(string $packageName, string $downloadUrl, string $version = '1.0.0'): array
    {
        // 1. 检查是否已安装
        $existing = DB::table('plugin')->where('package_name', $packageName)->first();
        if ($existing) {
            return ['success' => false, 'message' => '插件已安装，如需更新请使用更新功能'];
        }

        // 2. 下载
        $zipFile = $this->download($downloadUrl, $packageName . '_' . $version . '.zip');
        if (!$zipFile) {
            return ['success' => false, 'message' => '插件下载失败，请检查网络连接'];
        }

        // 3. 从ZIP读取模块信息（统一 mod.json）
        $pluginInfo = $this->readPluginInfoFromZip($zipFile);

        // 4. 生成模块名称
        $name = $pluginInfo['name'] ?? $packageName;
        $name = $this->sanitizePluginName($name);

        // 5. 校验依赖（zapcms 版本、依赖模块版本）
        $depError = $this->checkDependencies($pluginInfo);
        if ($depError !== null) {
            @unlink($zipFile);
            return ['success' => false, 'message' => $depError];
        }

        // 6. 解压到mods目录
        $pluginDir = $this->modsDir . '/' . $name;

        if (is_dir($pluginDir)) {
            @unlink($zipFile);
            return ['success' => false, 'message' => '插件目录已存在: ' . $name];
        }

        $extractResult = $this->extract($zipFile, $pluginDir);

        // 清理下载文件
        @unlink($zipFile);

        if (!$extractResult) {
            return ['success' => false, 'message' => '解压插件失败'];
        }

        // 7. 写入数据库
        $now = time();
        DB::table('plugin')->insert([
            'name'         => $name,
            'title'        => $pluginInfo['title'] ?? $packageName,
            'version'      => $pluginInfo['version'] ?? $version,
            'author'       => $pluginInfo['author'] ?? '',
            'description'  => $pluginInfo['description'] ?? '',
            'homepage'     => $pluginInfo['homepage'] ?? '',
            'package_name' => $packageName,
            'status'       => 1,
            'sort_order'   => 0,
            'installed_at' => $now,
            'updated_at'   => $now,
        ]);

        // 8. 写入 options 表（供后台启动加载 autoload 脚本）
        $this->writeInstalledModOption($name, $pluginInfo, 1);

        // 9. 运行模块安装脚本
        $this->runPluginInstallScript($name);

        return ['success' => true, 'message' => '插件安装成功'];
    }

    /**
     * 卸载插件
     *
     * @param string $name 插件标识名
     * @return array
     */
    public function uninstallPlugin(string $name): array
    {
        $plugin = DB::table('plugin')->where('name', $name)->first();
        if (!$plugin) {
            return ['success' => false, 'message' => '插件不存在'];
        }

        $name = $this->sanitizePluginName($name);

        // 1. 运行插件卸载脚本
        $this->runPluginUninstallScript($name);

        // 2. 删除插件目录
        $pluginDir = $this->modsDir . '/' . $name;
        if (is_dir($pluginDir)) {
            $this->deleteDirectory($pluginDir);
        }

        // 3. 从数据库删除
        DB::table('plugin')->where('name', $name)->delete();

        // 4. 删除 options 记录
        $this->removeInstalledModOption($name);

        return ['success' => true, 'message' => '插件卸载成功'];
    }

    /**
     * 更新插件
     *
     * @param string $name        插件标识名
     * @param string $downloadUrl 下载地址
     * @param string $version     新版本号
     * @return array
     */
    public function updatePlugin(string $name, string $downloadUrl, string $version): array
    {
        $plugin = DB::table('plugin')->where('name', $name)->first();
        if (!$plugin) {
            return ['success' => false, 'message' => '插件不存在'];
        }

        // 1. 创建备份
        $pluginDir   = $this->modsDir . '/' . $name;
        $backupPath  = $this->backupDir . '/plugin_' . $name . '_' . ($plugin['version'] ?? 'old') . '_' . time();
        if (is_dir($pluginDir)) {
            $this->copyDirectory($pluginDir, $backupPath);
        }

        // 2. 下载新版本
        $zipFile = $this->download($downloadUrl, $name . '_' . $version . '.zip');
        if (!$zipFile) {
            return ['success' => false, 'message' => '下载更新包失败'];
        }

        // 3. 清空旧文件（保留备份）
        if (is_dir($pluginDir)) {
            $this->deleteDirectory($pluginDir);
        }

        // 4. 解压新版本
        $extractResult = $this->extract($zipFile, $pluginDir);
        @unlink($zipFile);

        if (!$extractResult) {
            // 恢复备份
            if (is_dir($backupPath)) {
                $this->copyDirectory($backupPath, $pluginDir);
            }
            return ['success' => false, 'message' => '解压更新包失败，已恢复旧版本'];
        }

        // 5. 运行更新脚本
        $this->runPluginUpdateScript($name, $plugin['version'] ?? '0.0.0', $version);

        // 6. 更新数据库记录
        DB::table('plugin')->where('name', $name)->update([
            'version'    => $version,
            'updated_at' => time(),
        ]);

        // 7. 刷新 options 记录（读取新版 mod.json）
        $this->writeInstalledModOption($name, $this->readModInfoFromDir($name), (int)$plugin['status']);

        // 8. 清理备份
        if (is_dir($backupPath)) {
            $this->deleteDirectory($backupPath);
        }

        // 9. 记录更新历史
        DB::table('update_history')->insert([
            'target'       => 'plugin:' . $name,
            'from_version' => $plugin['version'] ?? '0.0.0',
            'to_version'   => $version,
            'status'       => 'success',
            'created_at'   => time(),
        ]);

        return ['success' => true, 'message' => '插件更新成功'];
    }

    /**
     * 切换插件启用/禁用状态
     */
    public function togglePluginStatus(string $name, int $status): array
    {
        $plugin = DB::table('plugin')->where('name', $name)->first();
        if (!$plugin) {
            return ['success' => false, 'message' => '插件不存在'];
        }

        DB::table('plugin')->where('name', $name)->update(['status' => $status, 'updated_at' => time()]);

        // 同步更新 options 记录状态
        $installed = $this->getInstalledModOption($name);
        if ($installed) {
            $installed['status']    = (int)$status;
            $installed['updated_at'] = time();
            $this->saveInstalledModOption($name, $installed);
        }

        $action = $status ? '启用' : '禁用';
        return ['success' => true, 'message' => "插件{$action}成功"];
    }

    /**
     * 从ZIP包读取模块信息（统一读取 mod.json，兼容旧格式 plugin.json）
     */
    protected function readPluginInfoFromZip(string $zipFile): array
    {
        $info = [];
        if (class_exists('ZipArchive')) {
            $zip = new \ZipArchive();
            if ($zip->open($zipFile) === true) {
                // 先尝试 mod.json，再回退 plugin.json（兼容旧包）
                $jsonStr = $this->readJsonFromZip($zip, ['mod.json', 'plugin.json']);
                if ($jsonStr !== false) {
                    $info = json_decode($jsonStr, true) ?: [];
                }
                $zip->close();
            }
        }
        return $info;
    }

    /**
     * 从ZIP中按候选文件名列表读取JSON内容（支持根目录或第一级子目录）
     *
     * @return string|false
     */
    protected function readJsonFromZip(\ZipArchive $zip, array $candidates)
    {
        foreach ($candidates as $candidate) {
            $jsonStr = $zip->getFromName($candidate);
            if ($jsonStr !== false) {
                return $jsonStr;
            }
        }
        // 尝试第一级子目录
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            foreach ($candidates as $candidate) {
                if (preg_match('#^[^/]+/' . preg_quote($candidate, '#') . '$#', $name)) {
                    return $zip->getFromName($name);
                }
            }
        }
        return false;
    }

    /**
     * 运行插件安装脚本
     */
    protected function runPluginInstallScript(string $name): void
    {
        $installScript = $this->modsDir . '/' . $name . '/install.php';
        if (file_exists($installScript)) {
            try {
                require $installScript;
            } catch (\Throwable $e) {
                error_log("Plugin install script error [{$name}]: " . $e->getMessage());
            }
        }
    }

    /**
     * 运行插件卸载脚本
     */
    protected function runPluginUninstallScript(string $name): void
    {
        $uninstallScript = $this->modsDir . '/' . $name . '/uninstall.php';
        if (file_exists($uninstallScript)) {
            try {
                require $uninstallScript;
            } catch (\Throwable $e) {
                error_log("Plugin uninstall script error [{$name}]: " . $e->getMessage());
            }
        }
    }

    /**
     * 运行插件更新脚本
     */
    protected function runPluginUpdateScript(string $name, string $fromVersion, string $toVersion): void
    {
        $updateScript = $this->modsDir . '/' . $name . '/update.php';
        if (file_exists($updateScript)) {
            try {
                require $updateScript;
            } catch (\Throwable $e) {
                error_log("Plugin update script error [{$name}]: " . $e->getMessage());
            }
        }
    }

    // ==================== 系统更新 ====================

    /**
     * 备份系统文件
     *
     * @param array $excludeDirs 排除的目录
     * @return string|false 备份目录路径
     */
    public function backupSystem(array $excludeDirs = [])
    {
        $defaultExclude = ['storage', 'node_modules', '.git', 'mods', 'themes'];
        $excludeDirs    = array_merge($defaultExclude, $excludeDirs);
        $backupName     = 'system_backup_' . date('Ymd_His') . '_' . ZAP_CMS_VERSION;
        $backupPath     = $this->backupDir . '/' . $backupName;

        $this->ensureDirectory($backupPath);

        $files = scandir(APP_ROOT);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            if (in_array($file, $excludeDirs)) {
                continue;
            }

            $src  = APP_ROOT . '/' . $file;
            $dest = $backupPath . '/' . $file;

            if (is_dir($src)) {
                $this->copyDirectory($src, $dest);
            } else {
                copy($src, $dest);
            }
        }

        return $backupPath;
    }

    /**
     * 应用系统更新
     *
     * @param string $zipFile    更新包ZIP文件路径
     * @param string $newVersion 新版本号
     * @return array
     */
    public function applySystemUpdate(string $zipFile, string $newVersion): array
    {
        // 1. 先备份
        $backupPath = $this->backupSystem();
        if (!$backupPath) {
            return ['success' => false, 'message' => '系统备份失败，更新已取消'];
        }

        // 2. 先尝试解压，确认格式正确
        $testDir = $this->tmpDir . '/update_test_' . uniqid();
        $testResult = $this->extract($zipFile, $testDir);
        if (!$testResult) {
            $this->deleteDirectory($testDir);
            return ['success' => false, 'message' => '更新包解压失败'];
        }

        // 验证更新包是否包含必要文件
        if (!file_exists(APP_ROOT . '/index.php')) {
            $this->deleteDirectory($testDir);
            return ['success' => false, 'message' => '系统文件异常，更新失败'];
        }
        $this->deleteDirectory($testDir);

        // 3. 直接解压覆盖到根目录
        $extractResult = $this->extract($zipFile, APP_ROOT);

        if (!$extractResult) {
            // 恢复备份
            $this->restoreBackup($backupPath);
            return ['success' => false, 'message' => '文件覆盖失败，已恢复旧版本'];
        }

        // 4. 清理缓存
        $this->clearCache();

        // 5. 记录更新历史
        DB::table('update_history')->insert([
            'target'       => 'system',
            'from_version' => ZAP_CMS_VERSION,
            'to_version'   => $newVersion,
            'status'       => 'success',
            'created_at'   => time(),
        ]);

        // 6. 清理备份（可选保留最近N个）
        $this->cleanOldBackups(5);

        return ['success' => true, 'message' => '系统更新成功，版本: ' . $newVersion];
    }

    /**
     * 从备份恢复系统
     */
    public function restoreBackup(string $backupPath): bool
    {
        if (!is_dir($backupPath)) {
            return false;
        }

        $files = scandir($backupPath);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $src  = $backupPath . '/' . $file;
            $dest = APP_ROOT . '/' . $file;

            if (is_dir($src)) {
                if (is_dir($dest)) {
                    $this->deleteDirectory($dest);
                }
                $this->copyDirectory($src, $dest);
            } else {
                copy($src, $dest);
            }
        }

        return true;
    }

    /**
     * 清理旧备份，只保留最近$keepCount个
     */
    public function cleanOldBackups(int $keepCount = 5): void
    {
        $dirs = [];
        $scan = scandir($this->backupDir);
        foreach ($scan as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $fullPath = $this->backupDir . '/' . $item;
            if (is_dir($fullPath)) {
                $dirs[] = [
                    'path' => $fullPath,
                    'time' => filemtime($fullPath),
                ];
            }
        }

        usort($dirs, function ($a, $b) {
            return $b['time'] - $a['time'];
        });

        $toDelete = array_slice($dirs, $keepCount);
        foreach ($toDelete as $dir) {
            $this->deleteDirectory($dir['path']);
        }
    }

    /**
     * 清理缓存
     */
    public function clearCache(): void
    {
        $cacheDir = APP_ROOT . '/storage/cache';
        if (is_dir($cacheDir)) {
            $this->deleteDirectory($cacheDir);
            $this->ensureDirectory($cacheDir);
        }
    }

    // ==================== 工具方法 ====================

    /**
     * 确保目录存在
     */
    public function ensureDirectory(string $dir, int $permissions = 0755): bool
    {
        if (!is_dir($dir)) {
            return mkdir($dir, $permissions, true);
        }
        return true;
    }

    /**
     * 递归复制目录
     */
    public function copyDirectory(string $src, string $dst): bool
    {
        if (!is_dir($src)) {
            return false;
        }

        $this->ensureDirectory($dst);

        $dir = opendir($src);
        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $srcFile = $src . '/' . $file;
            $dstFile = $dst . '/' . $file;

            if (is_dir($srcFile)) {
                $this->copyDirectory($srcFile, $dstFile);
            } else {
                copy($srcFile, $dstFile);
            }
        }
        closedir($dir);

        return true;
    }

    /**
     * 递归删除目录
     */
    public function deleteDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }

        return rmdir($dir);
    }

    /**
     * 清理插件名称（只保留字母、数字、连字符、下划线）
     */
    protected function sanitizePluginName(string $name): string
    {
        $name = preg_replace('/[^a-zA-Z0-9\-_]/', '', $name);
        return $name ?: 'plugin_' . uniqid();
    }

    /**
     * 获取所有已安装的插件
     */
    public function getInstalledPlugins(): array
    {
        return DB::table('plugin')->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->get();
    }

    /**
     * 获取单个插件信息
     */
    public function getPlugin(string $name): ?array
    {
        return DB::table('plugin')->where('name', $name)->first();
    }

    /**
     * 检查插件是否已安装
     */
    public function isPluginInstalled(string $name): bool
    {
        return DB::table('plugin')->where('name', $name)->exists();
    }

    /**
     * 获取所有已加载的mods（兼容现有ModController）
     */
    public function getLoadedMods(): array
    {
        $mods = [];
        if (!is_dir($this->modsDir)) {
            return $mods;
        }

        $scan = scandir($this->modsDir);
        foreach ($scan as $item) {
            if ($item === '.' || $item === '..' || $item[0] === '.') {
                continue;
            }

            $modDir = $this->modsDir . '/' . $item;
            if (!is_dir($modDir)) {
                continue;
            }

            // 读取mod.json
            $info = $this->readModInfoFromDir($item);

            $mods[] = [
                'name'         => $item,
                'title'        => $info['title'] ?? $item,
                'version'      => $info['version'] ?? '1.0.0',
                'type'         => $info['type'] ?? 'module',
                'author'       => $info['author'] ?? '',
                'description'  => $info['description'] ?? '',
                'autoload'     => $info['autoload'] ?? '',
                'dependencies' => $info['dependencies'] ?? [],
                'dir'          => $modDir,
                'enabled'      => $this->isPluginEnabled($item),
            ];
        }

        return $mods;
    }

    /**
     * 检查插件是否启用
     */
    public function isPluginEnabled(string $name): bool
    {
        $plugin = DB::table('plugin')->where('name', $name)->first();
        return $plugin && (int)$plugin['status'] === 1;
    }

    /**
     * 获取更新历史
     */
    public function getUpdateHistory(int $limit = 20): array
    {
        return DB::table('update_history')
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->get();
    }

    // ==================== 模块信息与 options 同步 ====================

    /**
     * 从 mods 目录读取 mod.json（兼容旧格式 plugin.json）
     */
    public function readModInfoFromDir(string $name): array
    {
        $info = [];
        $modDir = $this->modsDir . '/' . $name;
        foreach (['mod.json', 'plugin.json'] as $jsonFile) {
            $path = $modDir . '/' . $jsonFile;
            if (file_exists($path)) {
                $content = file_get_contents($path);
                $info    = json_decode($content, true) ?: [];
                break;
            }
        }
        return $info;
    }

    /**
     * 校验依赖：zapcms 版本 + 依赖的其他模块版本
     *
     * @param array $info mod.json 信息
     * @return string|null 错误信息，通过则返回 null
     */
    public function checkDependencies(array $info): ?string
    {
        $deps = $info['dependencies'] ?? [];
        if (empty($deps) && empty($info['min_zapcms'])) {
            return null;
        }

        // 1. zapcms 版本（兼容旧格式 min_zapcms）
        if (isset($deps['zapcms'])) {
            $constraint = trim($deps['zapcms']);
        } elseif (!empty($info['min_zapcms'])) {
            $constraint = '>=' . trim($info['min_zapcms']);
        }
        if (!empty($constraint)) {
            if (!$this->versionSatisfies(ZAP_CMS_VERSION, $constraint)) {
                return '当前 ZapCMS 版本 ' . ZAP_CMS_VERSION . ' 不满足依赖要求: zapcms ' . $constraint;
            }
        }

        // 2. 依赖的其他模块版本
        foreach ($deps as $mod => $constraint) {
            if ($mod === 'zapcms') {
                continue;
            }
            $installed = DB::table('plugin')->where('name', $mod)->first();
            if (!$installed) {
                return '缺少依赖模块: ' . $mod . ' (' . $constraint . ')';
            }
            if (!$this->versionSatisfies($installed['version'] ?? '', $constraint)) {
                return '依赖模块 ' . $mod . ' 版本不满足要求: ' . $constraint . '，当前 ' . ($installed['version'] ?? '未知');
            }
        }

        return null;
    }

    /**
     * 判断版本号是否满足约束条件（支持 >=, >, <=, <, =, ==, !=）
     */
    protected function versionSatisfies(string $version, string $constraint): bool
    {
        $constraint = trim($constraint);
        if (preg_match('/^(>=|<=|>|<|==|=|!=)?\s*(.+)$/', $constraint, $m)) {
            $op        = $m[1] ?: '>=';
            $target    = trim($m[2]);
            return version_compare($version, $target, $op);
        }
        return version_compare($version, $constraint, '>=');
    }

    /**
     * options 键名：mod.installed.{name}
     */
    protected function modOptionKey(string $name): string
    {
        return 'mod.installed.' . $name;
    }

    /**
     * 读取已安装模块在 options 中的记录
     */
    public function getInstalledModOption(string $name): ?array
    {
        $json = Option::get($this->modOptionKey($name));
        if (empty($json)) {
            return null;
        }
        $info = json_decode($json, true);
        return is_array($info) ? $info : null;
    }

    /**
     * 写入/更新已安装模块的 options 记录（autoload=1，供后台启动加载）
     */
    public function saveInstalledModOption(string $name, array $info): bool
    {
        $info['updated_at'] = time();
        Option::replace($this->modOptionKey($name), json_encode($info, JSON_UNESCAPED_UNICODE), 0, 1);
        return true;
    }

    /**
     * 安装后写入 options 记录（含 type/autoload/dependencies）
     */
    protected function writeInstalledModOption(string $name, array $info, int $status): bool
    {
        $record = [
            'name'         => $name,
            'type'         => $info['type'] ?? 'module',
            'title'        => $info['title'] ?? $name,
            'version'      => $info['version'] ?? '1.0.0',
            'author'       => $info['author'] ?? '',
            'description'  => $info['description'] ?? '',
            'homepage'     => $info['homepage'] ?? '',
            'autoload'     => $info['autoload'] ?? '',
            'dependencies' => $info['dependencies'] ?? [],
            'status'       => (int)$status,
            'installed_at' => time(),
            'updated_at'   => time(),
        ];
        Option::replace($this->modOptionKey($name), json_encode($record, JSON_UNESCAPED_UNICODE), 0, 1);
        return true;
    }

    /**
     * 删除已安装模块的 options 记录
     */
    protected function removeInstalledModOption(string $name): bool
    {
        Option::remove($this->modOptionKey($name));
        return true;
    }

    /**
     * 获取所有已安装（options 已登记）的模块列表
     */
    public function getInstalledMods(): array
    {
        $rows = Option::getArray('mod.installed.', 'REGEXP');
        $mods = [];
        foreach ($rows as $key => $json) {
            $name = substr($key, strlen('mod.installed.'));
            $info = json_decode($json, true) ?: [];
            $info['name'] = $name;
            $mods[] = $info;
        }
        return $mods;
    }
}
