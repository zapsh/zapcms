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
     * SSL 证书校验失败时是否允许降级重试（部分 PHP 环境缺少 CA 根证书）
     */
    /**
     * SSL 证书校验失败时是否允许降级重试。
     *
     * 默认关闭：不校验证书等于允许中间人替换更新包，而更新包会被解压覆盖
     * 站点代码。确有环境缺少 CA 根证书时，再显式调用 allowInsecureFallback() 开启。
     */
    protected bool $insecureFallback = false;

    /**
     * 最近一次请求是否发生了 SSL 降级
     */
    protected bool $lastRequestInsecure = false;

    /**
     * cURL超时时间(秒)
     */
    protected int $timeout = 30;

    /**
     * 允许下载更新包的主机白名单（小写，不含端口）
     *
     * 后台「website.api_url」可被管理员修改，若不加限制，一旦被篡改
     * （或遭遇 CSRF/XSS 组合利用）就会从任意地址下载压缩包并覆盖站点代码。
     */
    protected array $allowedHosts = ['mirrors.zap.cn'];

    public function __construct()
    {
        $this->tmpDir    = APP_ROOT . '/var/tmp';
        $this->backupDir = APP_ROOT . '/var/backup';
        $this->modsDir   = APP_ROOT . '/mods';
        $this->apiUrl    = Option::get('website.api_url', 'https://mirrors.zap.cn/zapcms');

        // 合并配置文件中额外允许的更新源（自建镜像场景）
        $extra = config('security.allowed_update_hosts', []);
        if (is_array($extra) && $extra !== []) {
            $this->allowedHosts = array_values(array_unique(array_merge(
                $this->allowedHosts,
                array_map(static fn($h) => strtolower(trim((string)$h)), $extra)
            )));
        }

        $this->ensureDirectory($this->tmpDir);
        $this->ensureDirectory($this->backupDir);
    }

    /**
     * 设置API地址
     *
     * 只允许白名单内的主机，避免更新源被指向任意地址后下载并覆盖站点代码。
     *
     * @throws \InvalidArgumentException 主机不在白名单时
     */
    public function setApiUrl(string $url): self
    {
        $url = rtrim($url, '/');

        if (!$this->isAllowedSource($url)) {
            error_log('ZapPackageManager: 拒绝设置不在白名单内的更新源: ' . $url);
            throw new \InvalidArgumentException(
                '更新源不在允许列表中（' . implode(', ', $this->allowedHosts) . '），'
                . '如需使用自建镜像请配置 security.allowed_update_hosts'
            );
        }

        $this->apiUrl = $url;

        return $this;
    }

    /**
     * 允许的更新源主机列表
     */
    public function getAllowedHosts(): array
    {
        return $this->allowedHosts;
    }

    /**
     * 判断下载地址是否来自受信任的更新源
     */
    public function isAllowedSource(string $url): bool
    {
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        if ($host === '') {
            return false;
        }

        return in_array($host, $this->allowedHosts, true);
    }

    /**
     * 允许 SSL 校验失败时降级重试（仅在目标环境缺少 CA 根证书时使用）
     */
    public function allowInsecureFallback(bool $allow = true): self
    {
        $this->insecureFallback = $allow;

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

    /**
     * 是否允许 SSL 校验失败时降级重试
     */
    public function setInsecureFallback(bool $allow): self
    {
        $this->insecureFallback = $allow;
        return $this;
    }

    /**
     * 最近一次请求是否因证书问题降级为不校验
     */
    public function lastRequestWasInsecure(): bool
    {
        return $this->lastRequestInsecure;
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
     * HTTP GET 请求（返回原始文本）
     */
    public function httpGetRaw(string $url): ?string
    {
        $this->lastRequestInsecure = false;

        $result = $this->curlGetRequest($url, $this->sslVerify);

        if ($result['error'] !== '' && $this->sslVerify && $this->insecureFallback
            && $this->isSslError($result['errno'], $result['error'])) {
            // 部分 PHP 环境未配置 CA 根证书，降级重试避免更新功能完全不可用
            $this->lastRequestInsecure = true;
            error_log('ZapPackageManager: SSL 校验失败，已降级为不校验重试（建议在 php.ini 配置 curl.cainfo）：' . $result['error']);
            $result = $this->curlGetRequest($url, false);
        }

        if ($result['error'] !== '') {
            error_log("ZapPackageManager HTTP GET error: {$result['error']}");
            return null;
        }

        if ($result['code'] >= 400) {
            error_log("ZapPackageManager HTTP GET failed with code: {$result['code']}");
            return null;
        }

        // 兼容带 BOM 的文件（部分静态镜像会输出 UTF-8 BOM）
        return ltrim($result['body'], "\xEF\xBB\xBF");
    }

    /**
     * 执行一次 GET 请求
     *
     * @return array{body:string,code:int,errno:int,error:string}
     */
    protected function curlGetRequest(string $url, bool $verify): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => min(10, $this->timeout),
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => $verify,
            CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0,
            CURLOPT_USERAGENT      => 'ZAPCMS/' . ZAP_CMS_VERSION,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'X-ZAPCMS-VERSION: ' . ZAP_CMS_VERSION,
                'X-ZAPCMS-SITE: ' . ($_SERVER['HTTP_HOST'] ?? ''),
            ],
        ]);

        $body  = curl_exec($ch);
        $code  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        // PHP 8.0+ 起 curl_close() 已无实际作用，8.5 中会触发 deprecated 日志，直接省略

        return [
            'body'  => $body === false ? '' : (string)$body,
            'code'  => $code,
            'errno' => $errno,
            'error' => $error,
        ];
    }

    /**
     * 判断错误是否为 SSL 证书相关
     */
    protected function isSslError(int $errno, string $error): bool
    {
        // 35 连接失败 / 51 对端证书校验失败 / 58 证书问题 / 59 加密套件 / 60 CA 校验失败
        // 53 引擎 / 54 引擎设置 / 66 引擎初始化 / 77 CA 文件 / 80 关闭失败 / 83 签发者 / 90-91 校验状态
        $sslErrnos = [35, 51, 53, 54, 58, 59, 60, 66, 77, 80, 82, 83, 90, 91];

        if (in_array($errno, $sslErrnos, true)) {
            return true;
        }

        return stripos($error, 'SSL') !== false
            || stripos($error, 'certificate') !== false
            || stripos($error, 'cacert') !== false;
    }

    /**
     * HTTP GET 请求并解析 JSON
     */
    public function httpGet(string $url): ?array
    {
        $raw = $this->httpGetRaw($url);
        if ($raw === null) {
            return null;
        }

        return $this->decodeJson($raw);
    }

    /**
     * 宽松解析 JSON：先按标准 JSON，失败则兼容未加引号的键 / 单引号写法
     */
    public function decodeJson(string $raw): ?array
    {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // 兼容 { version: '1.0.0' } 这类 JS 对象字面量写法
        $normalized = preg_replace('/([,{])\s*([A-Za-z_][A-Za-z0-9_\-]*)\s*:/', '$1"$2":', $raw);
        if ($normalized !== null) {
            $normalized = str_replace("'", '"', $normalized);
            $decoded    = json_decode($normalized, true);
        }

        return is_array($decoded) ? $decoded : null;
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
    public function download(string $url, string $filename = '', ?string $expectedSha256 = null)
    {
        // 供应链防护：更新包会被解压覆盖站点代码，来源必须是受信任的主机
        if (!$this->isAllowedSource($url)) {
            error_log('ZapPackageManager: 拒绝从非白名单地址下载更新包: ' . $url);
            return false;
        }

        if (empty($filename)) {
            $filename = basename((string)parse_url($url, PHP_URL_PATH));
        }

        $tmpFile = $this->tmpDir . '/' . $filename;
        $this->lastRequestInsecure = false;

        $verifyAttempts = [$this->sslVerify];
        if ($this->sslVerify && $this->insecureFallback) {
            $verifyAttempts[] = false;
        }

        foreach ($verifyAttempts as $index => $verify) {
            $fp = fopen($tmpFile, 'w+');
            if (!$fp) {
                return false;
            }

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_FILE           => $fp,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => min(15, $this->timeout),
                CURLOPT_TIMEOUT        => $this->timeout * 3,
                CURLOPT_SSL_VERIFYPEER => $verify,
                CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0,
                CURLOPT_USERAGENT      => 'ZAPCMS/' . ZAP_CMS_VERSION,
            ]);

            $success = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $errno    = curl_errno($ch);
            $error    = curl_error($ch);
            fclose($fp);

            if ($success && $httpCode < 400 && $error === '') {
                // 提供了期望哈希时做完整性校验：即便传输被劫持也无法通过
                if ($expectedSha256 !== null && !$this->verifyChecksum($tmpFile, $expectedSha256)) {
                    error_log('ZapPackageManager: 更新包 SHA256 校验失败，已丢弃: ' . $url);
                    @unlink($tmpFile);
                    return false;
                }

                return $tmpFile;
            }

            // 证书问题且还有一次不带校验的机会，清理半成品后重试
            if ($index === 0 && isset($verifyAttempts[1]) && $this->isSslError($errno, $error)) {
                $this->lastRequestInsecure = true;
                error_log('ZapPackageManager: 下载包 SSL 校验失败，已降级为不校验重试：' . $error);
                @unlink($tmpFile);
                continue;
            }

            error_log("ZapPackageManager download failed: code={$httpCode} error={$error}");
            @unlink($tmpFile);
            return false;
        }

        return false;
    }

    /**
     * 校验文件的 SHA256 是否与期望值一致（大小写不敏感，恒定时间比较）
     */
    public function verifyChecksum(string $file, string $expectedSha256): bool
    {
        if (!is_file($file)) {
            return false;
        }

        $expected = strtolower(trim($expectedSha256));
        if (!preg_match('/^[0-9a-f]{64}$/', $expected)) {
            error_log('ZapPackageManager: 期望的 SHA256 格式不合法，拒绝放行');
            return false;
        }

        return hash_equals($expected, (string)hash_file('sha256', $file));
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
            // 清理解压产生的半成品目录，避免残留文件被误当作已安装模块加载
            $this->deleteDirectory($pluginDir);
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

        // 10. 重新生成模块路由配置（config/module.php）
        $this->syncModuleRoutes();

        return ['success' => true, 'message' => '插件安装成功'];
    }

    /**
     * 卸载（或注销）插件
     *
     * @param string $name      插件标识名
     * @param bool   $keepData  true 保留数据表，仅清理菜单与登记记录
     * @param bool   $keepFiles true 保留模块目录，之后在模块列表点“注册”即可重新激活
     * @return array
     */
    public function uninstallPlugin(string $name, bool $keepData = true, bool $keepFiles = true): array
    {
        $plugin = DB::table('plugin')->where('name', $name)->first();
        if (!$plugin) {
            return ['success' => false, 'message' => '插件不存在'];
        }

        $name = $this->sanitizePluginName($name);

        // 0. 清理模块注册的后台菜单
        try {
            ModMenu::remove($name);
        } catch (\Throwable $e) {
            // 菜单清理失败不阻塞卸载
        }

        // 1. 运行插件卸载脚本（保留数据时不删表）
        $this->runPluginUninstallScript($name, $keepData);

        // 2. 删除插件目录（保留文件时跳过，便于之后直接重新注册激活）
        if (!$keepFiles) {
            $pluginDir = $this->modsDir . '/' . $name;
            if (is_dir($pluginDir)) {
                $this->deleteDirectory($pluginDir);
            }
        }

        // 3. 从数据库删除登记
        DB::table('plugin')->where('name', $name)->delete();

        // 4. 删除 options 记录
        $this->removeInstalledModOption($name);

        // 5. 重新生成模块路由配置（config/module.php）
        $this->syncModuleRoutes();

        if ($keepFiles) {
            $message = $keepData
                ? '已注销：数据与模块文件均保留，可在模块列表点“注册”重新激活'
                : '已注销：模块文件已保留，数据表已删除，可在模块列表点“注册”重新激活';
        } else {
            $message = $keepData ? '插件已卸载，数据表已保留' : '插件已彻底卸载，数据表已删除';
        }

        return ['success' => true, 'message' => $message];
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

        // 7.1 重新生成模块路由配置（config/module.php）
        $this->syncModuleRoutes();

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

        // 重新生成模块路由配置（config/module.php）
        $this->syncModuleRoutes();

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
    public function runPluginInstallScript(string $name): void
    {
        // 模块安装/卸载脚本常以 defined('IN_ZAPCMS') 守卫防止被直接访问；
        // 但此处是在后台（仅定义 IN_ZAPCMS_ADMIN）上下文中 require，需补齐该常量，否则会 die('No permission to access')。
        defined('IN_ZAPCMS') or define('IN_ZAPCMS', true);
        defined('IN_ZAPCMS_ADMIN') or define('IN_ZAPCMS_ADMIN', true);

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
     *
     * 脚本内可通过局部变量 $keepData 或 $GLOBALS['zap_mod_uninstall_keep_data']
     * 判断是否保留数据表：为 true 时只做菜单/登记清理，不删除业务数据。
     */
    public function runPluginUninstallScript(string $name, bool $keepData = true): void
    {
        defined('IN_ZAPCMS') or define('IN_ZAPCMS', true);
        defined('IN_ZAPCMS_ADMIN') or define('IN_ZAPCMS_ADMIN', true);

        $uninstallScript = $this->modsDir . '/' . $name . '/uninstall.php';
        // 供被 require 的脚本读取（require 共享当前作用域；GLOBALS 便于跨函数取用）
        $GLOBALS['zap_mod_uninstall_keep_data'] = $keepData;
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
        defined('IN_ZAPCMS') or define('IN_ZAPCMS', true);
        defined('IN_ZAPCMS_ADMIN') or define('IN_ZAPCMS_ADMIN', true);

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
        // 必须在备份开始前放开执行时间限制，否则大站点可能中途被中断
        @set_time_limit(0);
        @ignore_user_abort(true);

        // var 目录必须排除：备份文件写在 var/backup 下，若把 var 一起复制会造成
        // “备份包含备份”的无限递归（曾经把站点复制成几十万个文件直到卡死）
        $defaultExclude = ['storage', 'var', 'node_modules', '.git', 'mods', 'themes', 'dist'];
        $excludeDirs    = array_merge($defaultExclude, $excludeDirs);
        $backupName     = 'system_backup_' . date('Ymd_His') . '_' . ZAP_CMS_VERSION;
        $backupPath     = $this->backupDir . '/' . $backupName;

        $this->ensureDirectory($backupPath);

        $backupReal = realpath($this->backupDir);
        $backupReal = $backupReal === false ? $this->backupDir : rtrim(str_replace('\\', '/', $backupReal), '/');

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

            // 双保险：跳过备份目录自身（防止自定义 backupDir 位于项目内时递归）
            $srcReal = realpath($src);
            if ($srcReal !== false) {
                $srcReal = rtrim(str_replace('\\', '/', $srcReal), '/');
                if ($srcReal === $backupReal || str_starts_with($srcReal . '/', $backupReal . '/')) {
                    continue;
                }
            }

            if (is_dir($src)) {
                $this->copyDirectory($src, $dest);
            } else {
                @copy($src, $dest);
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
        // 备份 + 解压涉及大量文件操作，避免被 max_execution_time 或客户端断开中断
        @set_time_limit(0);
        @ignore_user_abort(true);

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

        // 验证更新包是否包含必要文件（检查包内容，而不是当前站点文件）
        foreach (['index.php', 'app/bootstrap.php'] as $requiredFile) {
            if (!file_exists($testDir . '/' . $requiredFile)) {
                $this->deleteDirectory($testDir);
                return ['success' => false, 'message' => "更新包内容不完整（缺少 {$requiredFile}），更新已取消"];
            }
        }
        $this->deleteDirectory($testDir);

        // 3. 保留站点自身的数据库配置（更新包不应覆盖已安装站点的连接信息）
        $protectedFiles = [];
        foreach (['config/database.php', 'config/backup.database.php'] as $relativePath) {
            $absolutePath = APP_ROOT . '/' . $relativePath;
            if (is_file($absolutePath)) {
                $protectedFiles[$relativePath] = (string)file_get_contents($absolutePath);
            }
        }

        // 4. 直接解压覆盖到根目录
        $extractResult = $this->extract($zipFile, APP_ROOT);

        if (!$extractResult) {
            // 恢复备份
            $this->restoreBackup($backupPath);
            return ['success' => false, 'message' => '文件覆盖失败，已恢复旧版本'];
        }

        // 4.1 还原被更新包覆盖的数据库配置
        foreach ($protectedFiles as $relativePath => $content) {
            @file_put_contents(APP_ROOT . '/' . $relativePath, $content);
        }

        // 6. 校验解压后的核心版本号，避免包与清单不匹配
        $packedVersion = $this->readCoreVersion();
        if ($packedVersion !== '' && version_compare($packedVersion, ltrim($newVersion, 'vV'), '!=')) {
            $this->restoreBackup($backupPath);
            return [
                'success' => false,
                'message' => "更新包版本不匹配（包内 v{$packedVersion}，清单 v{$newVersion}），已回滚",
            ];
        }

        // 7. 执行数据库迁移（app/zapcms/migrations 中本包新增的迁移）
        $migrationWarning = $this->runCoreMigrations();

        // 7.1 执行可选的数据库/数据升级脚本（包根目录 update.php，兼容旧机制）
        $upgradeWarning = $this->runCoreUpgradeScript($newVersion);

        // 8. 清理缓存
        $this->clearCache();

        // 9. 记录更新历史
        $historyStatus = ($migrationWarning === '' && $upgradeWarning === '') ? 'success' : 'warning';
        $this->recordUpdateHistory('core', ZAP_CMS_VERSION, $newVersion, $historyStatus);

        // 10. 清理备份（保留最近N个）
        $this->cleanOldBackups(5);

        $message = '系统更新成功，版本: ' . $newVersion;

        $warnings = array_filter([$migrationWarning, $upgradeWarning], static fn($v) => $v !== '');
        if (!empty($warnings)) {
            $message .= '（升级警告：' . implode('；', $warnings) . '）';
        }

        return ['success' => true, 'message' => $message];
    }

    /**
     * 执行核心数据库迁移（app/zapcms/migrations）。
     *
     * 迁移记录存在于 migrations 表，升级时只会执行更新包中新加入的迁移文件。
     *
     * @return string 警告信息，空字符串表示无异常
     */
    protected function runCoreMigrations(): string
    {
        try {
            $migrator = new \zapcms\database\Migrator();
            $result   = $migrator->run();

            if (!empty($result['error'])) {
                return '数据库迁移失败：' . $result['error'];
            }

            return '';
        } catch (\Throwable $e) {
            return '数据库迁移异常：' . $e->getMessage();
        }
    }

    /**
     * 读取项目内的核心版本号（app/bootstrap.php 中的 ZAP_CMS_VERSION）
     */
    public function readCoreVersion(): string
    {
        $file = APP_ROOT . '/app/bootstrap.php';
        if (!is_file($file)) {
            return '';
        }
        $content = (string)@file_get_contents($file);
        if (preg_match('/ZAP_CMS_VERSION\s*=\s*([\'"])([0-9a-zA-Z.\-_]+)\1/', $content, $m)) {
            return $m[2];
        }
        return '';
    }

    /**
     * 执行更新包内的升级脚本（update.php），执行成功后删除该一次性脚本
     *
     * @return string 警告信息，空字符串表示无异常
     */
    protected function runCoreUpgradeScript(string $toVersion): string
    {
        $script = APP_ROOT . '/update.php';
        if (!is_file($script)) {
            return '';
        }

        $fromVersion = ZAP_CMS_VERSION;
        try {
            require $script;
            @unlink($script);
            return '';
        } catch (\Throwable $e) {
            error_log("Core upgrade script error [{$fromVersion} -> {$toVersion}]: " . $e->getMessage());
            return $e->getMessage();
        }
    }

    /**
     * 写入更新历史
     */
    public function recordUpdateHistory(string $target, string $fromVersion, string $toVersion, string $status = 'success', string $changelog = ''): void
    {
        try {
            DB::table('update_history')->insert([
                'target'       => $target,
                'from_version' => $fromVersion,
                'to_version'   => $toVersion,
                'changelog'    => $changelog,
                'status'       => $status,
                'created_at'   => time(),
            ]);
        } catch (\Throwable $e) {
            error_log('recordUpdateHistory failed: ' . $e->getMessage());
        }
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

        // Twig 编译缓存（更新后模板可能已变化，必须一并清理）
        $twigCacheDir = APP_ROOT . '/var/cache/twig';
        if (is_dir($twigCacheDir)) {
            $this->deleteDirectory($twigCacheDir);
            $this->ensureDirectory($twigCacheDir);
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
     *
     * @param int $depth 递归深度，超过限制直接返回，防止符号链接/异常结构造成死循环
     */
    public function copyDirectory(string $src, string $dst, int $depth = 0): bool
    {
        if (!is_dir($src) || $depth > 32) {
            return false;
        }

        // 不跟随符号链接 / junction，避免复制到自身或无限展开
        if (is_link($src)) {
            return false;
        }

        $this->ensureDirectory($dst);

        $dir = opendir($src);
        if ($dir === false) {
            return false;
        }

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $srcFile = $src . '/' . $file;
            $dstFile = $dst . '/' . $file;

            if (is_link($srcFile)) {
                continue;
            }

            if (is_dir($srcFile)) {
                $this->copyDirectory($srcFile, $dstFile, $depth + 1);
            } else {
                @copy($srcFile, $dstFile);
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

    // ==================== 模块路由自动注册 ====================

    /**
     * 根据已启用模块的 mod.json "route" 声明，自动生成 config/module.php。
     *
     * 触发时机：模块安装 / 卸载 / 启用 / 禁用 / 更新之后调用。
     *
     * @return array 生成的 prefixes 映射（URL 前缀 => 模块目录名）
     */
    public function syncModuleRoutes(): array
    {
        $prefixes = $this->collectModuleRoutes();
        $this->writeModuleConfig($prefixes);
        return $prefixes;
    }

    /**
     * 从已启用（plugin.status=1）的模块收集路由映射。
     */
    protected function collectModuleRoutes(): array
    {
        $prefixes = [];
        try {
            $plugins = DB::table('plugin')->where('status', 1)->get();
        } catch (\Throwable $e) {
            // plugin 表尚不存在（如首次安装尚未建表）时，返回空集合
            return $prefixes;
        }

        foreach ($plugins as $plugin) {
            $name = (string)($plugin['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $info   = $this->readModInfoFromDir($name);
            $routes = $this->normalizeRoute($info['route'] ?? null, $name);
            foreach ($routes as $prefix => $target) {
                $prefix = $this->sanitizeRoutePrefix($prefix);
                $target = $this->sanitizePluginName($target);
                if ($prefix !== '' && $target !== '') {
                    $prefixes[$prefix] = $target;
                }
            }
        }

        ksort($prefixes);
        return $prefixes;
    }

    /**
     * 将 mod.json 的 route 字段归一化为 [URL前缀 => 模块目录]。
     *
     * 支持以下写法：
     *   "route": "shop"                                         // 前缀即模块目录
     *   "route": {"prefix": "shop"}                             // 单前缀
     *   "route": {"prefix": "shop", "aliases": ["mall","cart"]} // 多前缀指向同一模块
     *   "route": {"map": {"mall":"shopv2","cart":"shopv2"}}     // 显式映射（可指向其它目录）
     */
    protected function normalizeRoute($route, string $moduleDir): array
    {
        $out = [];
        if (is_string($route) && trim($route) !== '') {
            $out[trim($route)] = $moduleDir;
            return $out;
        }
        if (!is_array($route)) {
            return $out;
        }
        if (isset($route['map']) && is_array($route['map'])) {
            foreach ($route['map'] as $prefix => $target) {
                $out[(string)$prefix] = (string)$target;
            }
            return $out;
        }
        $prefix = (string)($route['prefix'] ?? '');
        if ($prefix !== '') {
            $out[$prefix] = $moduleDir;
        }
        foreach ((array)($route['aliases'] ?? []) as $alias) {
            $alias = (string)$alias;
            if ($alias !== '') {
                $out[$alias] = $moduleDir;
            }
        }
        return $out;
    }

    /**
     * 清洗 URL 前缀（仅保留小写字母/数字/连字符/下划线，去首尾斜杠）。
     */
    protected function sanitizeRoutePrefix(string $prefix): string
    {
        $prefix = strtolower(trim(trim($prefix), '/'));
        return (string)preg_replace('/[^a-z0-9\-_]/', '', $prefix);
    }

    /**
     * 原子写入 config/module.php（含可选的 config/module.overrides.php 运行时合并）。
     */
    protected function writeModuleConfig(array $prefixes): bool
    {
        $export = var_export($prefixes, true);
        $now    = date('Y-m-d H:i:s');

        $content = <<<'PHP'
<?php
/**
 * 模块路由配置 —— 由系统根据已启用模块的 mod.json "route" 自动生成。
 * 生成时间：__NOW__
 *
 * 请勿手改本文件：模块安装 / 卸载 / 启用 / 禁用 / 更新时会重新生成。
 * 需要手动新增或覆盖前缀映射时，请创建 config/module.overrides.php：
 *
 *   <?php
 *   return ['mall' => 'shopv2', 'cart' => 'shopv2'];
 *
 * prefixes 语义：URL 前缀 => 模块目录名。
 */

$prefixes = __PREFIXES__;

// 站点级手动覆盖（可选），优先级高于自动生成结果
$overrideFile = __DIR__ . '/module.overrides.php';
if (is_file($overrideFile)) {
    $overrides = include $overrideFile;
    if (is_array($overrides)) {
        $prefixes = array_merge($prefixes, $overrides);
    }
}

return ['prefixes' => $prefixes];
PHP;

        $content = str_replace(['__NOW__', '__PREFIXES__'], [$now, $export], $content);

        $file = APP_ROOT . '/config/module.php';
        $tmp  = $file . '.tmp.' . getmypid();
        if (@file_put_contents($tmp, $content) === false) {
            return false;
        }
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
            return false;
        }
        return true;
    }
}
