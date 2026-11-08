<?php

namespace zapcms\services\storage\drivers;

use zap\net\Requests;
use zapcms\services\storage\StorageException;
use zapcms\services\storage\StorageInterface;
use zapcms\services\storage\StorageResult;

/**
 * 腾讯云 COS 对象存储（经典 HMAC-SHA1 自签，零 composer 依赖）
 *
 * 复用框架 zap\net\Requests，实现 PUT / GET / DELETE / HEAD 与 GET Bucket。
 * 要求 bucket 为公共读。密钥来自 config/storage.php / 环境变量；
 * 其余参数来自后台 upload.cos_* 选项。
 */
class CosStorage implements StorageInterface
{
    use ParsesObjectListings;

    private string $bucket;
    private string $region;
    private string $host;      // {bucket}.cos.{region}.myqcloud.com
    private string $baseUrl;   // 自定义 CDN 域名，留空则用 COS 默认域名
    private string $prefix;
    private string $secretId;
    private string $secretKey;
    private int $timeout;
    private string $thumbMode; // none | ci（数据万象图片处理）

    public function __construct()
    {
        $this->bucket = trim((string) option('upload.cos_bucket', ''));
        $this->region = trim((string) option('upload.cos_region', ''));
        $this->host   = $this->bucket !== '' && $this->region !== ''
            ? $this->bucket . '.cos.' . $this->region . '.myqcloud.com'
            : '';
        $this->baseUrl = $this->normalizeBaseUrl((string) option('upload.cos_base_url', ''));
        $this->prefix    = trim((string) option('upload.cos_prefix', ''), '/');
        $this->thumbMode = (string) option('upload.cos_thumb', 'none');

        $conf = (array) config('storage.cos', []);
        $this->secretId  = (string) ($conf['secret_id'] ?? '');
        $this->secretKey = (string) ($conf['secret_key'] ?? '');
        $this->timeout   = max(5, (int) ($conf['timeout'] ?? 60));
    }

    public function isConfigured(): bool
    {
        return $this->bucket !== ''
            && $this->region !== ''
            && $this->secretId !== ''
            && $this->secretKey !== '';
    }

    public function put(string $key, string $tmpFile, array $meta = []): StorageResult
    {
        if (!$this->isConfigured()) {
            throw new StorageException('COS 未配置完整（bucket / region / 密钥）');
        }
        if (!is_file($tmpFile)) {
            throw new StorageException('待上传文件不存在：' . $key);
        }
        $body = @file_get_contents($tmpFile);
        if ($body === false) {
            throw new StorageException('读取待上传文件失败：' . $key);
        }

        $mime      = (string) ($meta['mime'] ?? '') ?: (function_exists('mime_content_type')
            ? (mime_content_type($tmpFile) ?: 'application/octet-stream')
            : 'application/octet-stream');
        $objectKey = $this->objectKey($key);
        $response  = Requests::put(
            $this->requestUrl($objectKey),
            $body,
            $this->sign('PUT', $this->uriPath($objectKey), [], $mime),
            ['timeout' => $this->timeout]
        );
        if (!$response->ok()) {
            throw new StorageException('COS 上传失败 HTTP ' . $response->status() . '：' . $this->errorMessage($response));
        }

        return new StorageResult($key, $this->url($key), 'cos', strlen($body), $mime);
    }

    public function delete(string $key): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }
        $key  = ltrim(str_replace('\\', '/', $key), '/');
        $full = $this->objectKey($key);
        $ok   = true;

        $ok = $this->deleteObject($full) && $ok;
        $ok = $this->deleteObject($full . '/') && $ok;
        foreach ($this->listObjectKeys($full . '/') as $child) {
            $ok = $this->deleteObject($child) && $ok;
        }
        return $ok;
    }

    public function exists(string $key): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }
        try {
            $fullKey  = $this->objectKey($key);
            $response = Requests::head(
                $this->requestUrl($fullKey),
                $this->sign('HEAD', $this->uriPath($fullKey), [], ''),
                ['timeout' => min(15, $this->timeout)]
            );
            return $response->ok();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function url(string $key): string
    {
        $base = $this->baseUrl !== '' ? $this->baseUrl : 'https://' . $this->host;
        return $base . '/' . $this->encodeKey($this->objectKey($key));
    }

    public function keyFromUrl(string $url): ?string
    {
        $url  = preg_replace('/[?#].*$/', '', trim($url));
        $base = rtrim($this->baseUrl !== '' ? $this->baseUrl : 'https://' . $this->host, '/');
        if ($base === '' || $url === $base) {
            return null;
        }
        if (str_starts_with($url, $base . '/')) {
            $rest = ltrim(substr($url, strlen($base) + 1), '/');
            return $this->relativeKey(rawurldecode($rest));
        }
        return null;
    }

    /**
     * 缩略图：默认返回原图；后台开启「数据万象（CI）」后走 COS 图片处理参数，
     * 无需本地 GD、不消耗服务器带宽。
     */
    public function thumbUrl(string $key, int $width, int $height): string
    {
        $url = $this->url($key);
        if ($this->thumbMode !== 'ci') {
            return $url;
        }
        // 数据万象：等比缩放、宽高不超限、不放大（! 前缀 + r 后缀）
        return $url . (str_contains($url, '?') ? '&' : '?')
            . 'imageMogr2/thumbnail/!' . max(1, $width) . 'x' . max(1, $height) . 'r';
    }

    public function list(string $prefix = ''): array
    {
        $prefix = ltrim(str_replace('\\', '/', $prefix), '/');
        $fullPrefix = $this->listingPrefix($prefix);

        $xml    = $this->listRaw($fullPrefix, '/');
        $parsed = $this->parseObjectListXml($xml);

        $dirs = [];
        foreach ($parsed['prefixes'] as $p) {
            $rel = $this->relativeKey($p);
            if ($rel === '') {
                continue;
            }
            $dirs[] = ['name' => basename($rel), 'path' => $rel];
        }

        $files = [];
        foreach ($parsed['objects'] as $o) {
            $rel = $this->relativeKey($o['key']);
            if ($rel === '' || str_ends_with($rel, '/')) {
                continue;
            }
            $files[] = [
                'name'  => basename($rel),
                'path'  => $rel,
                'size'  => $o['size'],
                'mtime' => $o['mtime'],
                'ext'   => strtolower(pathinfo($rel, PATHINFO_EXTENSION)),
            ];
        }

        if ($dirs === [] && $files === [] && !$parsed['truncated']) {
            $this->logEmptyListing($xml);
        }
        return ['dirs' => $dirs, 'files' => $files];
    }

    public function makeDirectory(string $key): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }
        $key    = ltrim(str_replace('\\', '/', $key), '/');
        $marker = $this->objectKey($key) . '/';
        try {
            $response = Requests::put(
                $this->requestUrl($marker),
                '',
                $this->sign('PUT', $this->uriPath($marker), [], 'application/x-directory'),
                ['timeout' => $this->timeout]
            );
            return $response->ok();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function rename(string $from, string $to): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }
        $from = ltrim(str_replace('\\', '/', $from), '/');
        $to   = ltrim(str_replace('\\', '/', $to), '/');
        if ($from === '' || $from === $to) {
            return false;
        }

        $children = $this->listObjectKeys($this->objectKey($from) . '/');
        if ($children !== []) {
            return $this->renameDirectory($from, $to, $children);
        }

        $tmp = $this->download($from);
        if ($tmp === null) {
            return false;
        }
        try {
            $this->put($to, $tmp);
            @unlink($tmp);
            $this->delete($from);
            return true;
        } catch (\Throwable $e) {
            @unlink($tmp);
            return false;
        }
    }

    public function download(string $key, ?string $dest = null): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }
        $key     = ltrim(str_replace('\\', '/', $key), '/');
        $fullKey = $this->objectKey($key);
        try {
            $response = Requests::get(
                $this->requestUrl($fullKey),
                [],
                $this->sign('GET', $this->uriPath($fullKey), [], ''),
                ['timeout' => $this->timeout]
            );
        } catch (\Throwable $e) {
            return null;
        }
        if (!$response->ok()) {
            return null;
        }
        $dest = $dest !== null ? $dest : @tempnam(sys_get_temp_dir(), 'zapcosdl_');
        if ($dest === false || $dest === '') {
            return null;
        }
        return @file_put_contents($dest, $response->body()) !== false ? $dest : null;
    }

    /** 连接测试：写入一个临时小对象再删除 */
    public function test(): array
    {
        if (!$this->isConfigured()) {
            return [
                'ok'  => false,
                'msg' => 'COS 配置不完整：请检查 bucket / region，以及 config/storage.php 或环境变量中的密钥',
            ];
        }
        $key = '__zap_storage_test/' . bin2hex(random_bytes(6)) . '.txt';
        $tmp = @tempnam(sys_get_temp_dir(), 'zapcos_');
        if ($tmp === false) {
            return ['ok' => false, 'msg' => '无法创建临时文件'];
        }
        file_put_contents($tmp, 'zapcms storage test');
        try {
            $this->put($key, $tmp, ['mime' => 'text/plain']);

            // 列举自检：验证 GetBucket 能列出刚写入的测试对象（媒体库浏览依赖列举）
            $listed = $this->listObjectKeys($this->objectKey('__zap_storage_test') . '/');

            $this->delete($key);
            if (!in_array($this->objectKey($key), $listed, true)) {
                return ['ok' => false, 'msg' => '写入成功，但列举异常：__zap_storage_test/ 下未找到测试对象'
                    . '（列举到 ' . count($listed) . ' 个），媒体库可能无法浏览文件'];
            }
            return ['ok' => true, 'msg' => 'COS 连接正常（PUT / LIST / DELETE 均成功）'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'msg' => $e->getMessage()];
        } finally {
            @unlink($tmp);
        }
    }

    /** COS 对象的完整 key = 配置前缀 + 站内相对 key */
    private function objectKey(string $key): string
    {
        $key = ltrim(str_replace('\\', '/', $key), '/');
        return $this->prefix === '' ? $key : $this->prefix . '/' . $key;
    }

    /** 去掉配置前缀，还原站内相对 key */
    private function relativeKey(string $fullKey): string
    {
        if ($this->prefix !== '' && str_starts_with($fullKey, $this->prefix . '/')) {
            $fullKey = substr($fullKey, strlen($this->prefix) + 1);
        }
        return ltrim($fullKey, '/');
    }

    /**
     * 签名用 URI 路径。腾讯云 COS 按「解码后的原始路径」校验签名，
     * 因此这里不做 URL 编码（中文等原样）；实际请求 URL 仍由 requestUrl() 编码。
     */
    private function uriPath(string $objectKey): string
    {
        return '/' . $objectKey;
    }

    private function requestUrl(string $objectKey): string
    {
        return 'https://' . $this->host . '/' . $this->encodeKey($objectKey);
    }

    private function encodeKey(string $objectKey): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $objectKey)));
    }

    /** 排序并 RFC 3986 编码查询串（签名与实际 URL 共用，保证一致） */
    private function queryString(array $query): string
    {
        $query = array_filter($query, static fn($v) => $v !== null && $v !== '');
        ksort($query);
        $parts = [];
        foreach ($query as $k => $v) {
            $parts[] = rawurlencode((string) $k) . '=' . rawurlencode((string) $v);
        }
        return implode('&', $parts);
    }

    private function listObjectKeys(string $fullPrefix): array
    {
        $parsed = $this->parseObjectListXml($this->listRaw($fullPrefix, ''));
        return array_column($parsed['objects'], 'key');
    }

    private function listRaw(string $prefix, string $delimiter): string
    {
        $query = ['prefix' => $prefix, 'max-keys' => '1000'];
        if ($delimiter !== '') {
            $query['delimiter'] = $delimiter;
        }
        $url      = 'https://' . $this->host . '/?' . $this->queryString($query);
        $headers  = $this->sign('GET', '/', $query, '');
        $response = Requests::get($url, [], $headers, ['timeout' => $this->timeout]);
        if (!$response->ok()) {
            throw new StorageException('COS 列举失败 HTTP ' . $response->status() . '：' . $this->errorMessage($response));
        }
        return $response->body();
    }

    private function deleteObject(string $fullKey): bool
    {
        try {
            $response = Requests::delete(
                $this->requestUrl($fullKey),
                [],
                $this->sign('DELETE', $this->uriPath($fullKey), [], ''),
                ['timeout' => $this->timeout]
            );
            return $response->ok() || $response->status() === 404;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function renameDirectory(string $from, string $to, array $children): bool
    {
        $prefix = $this->objectKey($from) . '/';
        $ok     = true;
        foreach ($children as $srcFull) {
            $rel = substr($srcFull, strlen($prefix));
            if ($rel === '') {
                continue;
            }
            $tmp = @tempnam(sys_get_temp_dir(), 'zapcoscp_');
            if ($tmp === false) {
                $ok = false;
                continue;
            }
            try {
                $response = Requests::get(
                    $this->requestUrl($srcFull),
                    [],
                    $this->sign('GET', $this->uriPath($srcFull), [], ''),
                    ['timeout' => $this->timeout]
                );
                if (!$response->ok()) {
                    $ok = false;
                    continue;
                }
                $dst = $this->objectKey($to) . '/' . $rel;
                $res = Requests::put(
                    $this->requestUrl($dst),
                    $response->body(),
                    $this->sign('PUT', $this->uriPath($dst), [], ''),
                    ['timeout' => $this->timeout]
                );
                if (!$res->ok()) {
                    $ok = false;
                }
            } catch (\Throwable $e) {
                $ok = false;
            } finally {
                @unlink($tmp);
            }
        }
        if ($ok) {
            $this->delete($from);
        }
        return $ok;
    }

    /**
     * COS 经典签名（q-sign-algorithm=sha1）：
     * SignKey = HMAC-SHA1(SecretKey, KeyTime)
     * StringToSign = "sha1\n" + KeyTime + "\n" + SHA1(HttpString) + "\n"
     */
    private function sign(string $method, string $uriPath, array $query, string $contentType): array
    {
        $now    = time();
        $expire = $now + 600;
        $keyTime = $now . ';' . $expire;
        $signKey = hash_hmac('sha1', $keyTime, $this->secretKey);

        $query = array_filter($query, static fn($v) => $v !== null && $v !== '');
        ksort($query);
        $httpParams = $this->queryString($query);

        $headers = ['host' => $this->host];
        if ($contentType !== '') {
            $headers['content-type'] = $contentType;
        }
        ksort($headers);
        $headerList  = [];
        $httpHeaders = '';
        foreach ($headers as $k => $v) {
            $headerList[] = $k;
            $httpHeaders .= $k . '=' . rawurlencode($v) . '&';
        }
        $httpHeaders = rtrim($httpHeaders, '&');

        $httpString = strtolower($method) . "\n" . $uriPath . "\n" . $httpParams . "\n" . $httpHeaders . "\n";
        $stringToSign = "sha1\n" . $keyTime . "\n" . sha1($httpString) . "\n";
        $signature = hash_hmac('sha1', $stringToSign, $signKey);

        $auth = 'q-sign-algorithm=sha1'
            . '&q-ak=' . $this->secretId
            . '&q-sign-time=' . $keyTime
            . '&q-key-time=' . $keyTime
            . '&q-header-list=' . implode(';', $headerList)
            . '&q-url-param-list=' . implode(';', array_keys($query))
            . '&q-signature=' . $signature;

        $result = [
            'Authorization: ' . $auth,
            'Host: ' . $this->host,
        ];
        if ($contentType !== '') {
            $result[] = 'Content-Type: ' . $contentType;
        }
        return $result;
    }

    private function errorMessage(\zap\net\Response $response): string
    {
        $body = trim((string) $response->body());
        return $body !== '' ? substr($body, 0, 300) : '无响应内容';
    }
}
