<?php

namespace zapcms\services\storage\drivers;

use zap\net\Requests;
use zapcms\services\storage\StorageException;
use zapcms\services\storage\StorageInterface;
use zapcms\services\storage\StorageResult;

/**
 * 阿里云 OSS 对象存储（Signature V1 自签，零 composer 依赖）
 *
 * 复用框架 zap\net\Requests，实现 PUT / GET / DELETE / HEAD 与 ListObjects。
 * 要求 bucket 为公共读（私有桶签名 URL 会过期，不能写入 DB）。
 * 密钥来自 config/storage.php / 环境变量；其余参数来自后台 upload.oss_* 选项。
 */
class OssStorage implements StorageInterface
{
    use ParsesObjectListings;

    private string $bucket;
    private string $endpoint;   // 纯 host，如 oss-cn-hangzhou.aliyuncs.com
    private string $scheme;
    private string $baseUrl;    // 自定义 CDN 域名，留空则用 bucket.endpoint
    private string $prefix;
    private string $accessKeyId;
    private string $accessKeySecret;
    private string $securityToken;
    private int $timeout;

    public function __construct()
    {
        $rawEndpoint = trim((string) option('upload.oss_endpoint', ''));
        $this->scheme = str_starts_with($rawEndpoint, 'http://') ? 'http' : 'https';
        $this->endpoint = preg_replace('#^https?://#i', '', $rawEndpoint);
        $this->endpoint = rtrim($this->endpoint, '/');

        $this->bucket   = trim((string) option('upload.oss_bucket', ''));
        $this->baseUrl  = rtrim(trim((string) option('upload.oss_base_url', '')), '/');
        $this->prefix   = trim((string) option('upload.oss_prefix', ''), '/');

        $conf = (array) config('storage.oss', []);
        $this->accessKeyId     = (string) ($conf['access_key_id'] ?? '');
        $this->accessKeySecret = (string) ($conf['access_key_secret'] ?? '');
        $this->securityToken   = (string) ($conf['security_token'] ?? '');
        $this->timeout         = max(5, (int) ($conf['timeout'] ?? 60));
    }

    public function isConfigured(): bool
    {
        return $this->bucket !== ''
            && $this->endpoint !== ''
            && $this->accessKeyId !== ''
            && $this->accessKeySecret !== '';
    }

    public function put(string $key, string $tmpFile, array $meta = []): StorageResult
    {
        if (!$this->isConfigured()) {
            throw new StorageException('OSS 未配置完整（bucket / endpoint / 密钥）');
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
            $this->signedHeaders('PUT', $objectKey, $mime),
            ['timeout' => $this->timeout]
        );
        if (!$response->ok()) {
            throw new StorageException('OSS 上传失败 HTTP ' . $response->status() . '：' . $this->errorMessage($response));
        }

        return new StorageResult($key, $this->url($key), 'oss', strlen($body), $mime);
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
            $response = Requests::head(
                $this->requestUrl($this->objectKey($key)),
                $this->signedHeaders('HEAD', $this->objectKey($key), ''),
                ['timeout' => min(15, $this->timeout)]
            );
            return $response->ok();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function url(string $key): string
    {
        $base = $this->baseUrl !== ''
            ? $this->baseUrl
            : $this->scheme . '://' . $this->bucket . '.' . $this->endpoint;
        return $base . '/' . $this->encodeKey($this->objectKey($key));
    }

    public function keyFromUrl(string $url): ?string
    {
        $url = preg_replace('/[?#].*$/', '', trim($url));
        $bases = [];
        if ($this->baseUrl !== '') {
            $bases[] = $this->baseUrl;
        }
        $bases[] = $this->scheme . '://' . $this->bucket . '.' . $this->endpoint;
        foreach ($bases as $base) {
            $base = rtrim($base, '/');
            if ($base === '' || $url === $base) {
                continue;
            }
            if (str_starts_with($url, $base . '/')) {
                $rest = ltrim(substr($url, strlen($base) + 1), '/');
                return $this->relativeKey(rawurldecode($rest));
            }
        }
        return null;
    }

    /** 阿里云图片处理参数，无需本地 GD、不消耗服务器带宽 */
    public function thumbUrl(string $key, int $width, int $height): string
    {
        $process = 'x-oss-process=image/resize,m_lfit,w_' . max(1, $width) . ',h_' . max(1, $height);
        $url = $this->url($key);
        return $url . (str_contains($url, '?') ? '&' : '?') . $process;
    }

    public function list(string $prefix = ''): array
    {
        $prefix = ltrim(str_replace('\\', '/', $prefix), '/');
        $fullPrefix = $this->objectKey($prefix);
        if ($fullPrefix !== '') {
            $fullPrefix .= '/';
        }

        $parsed = $this->parseObjectListXml($this->listRaw($fullPrefix, '/'));

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
                continue; // 目录标记对象不展示为文件
            }
            $files[] = [
                'name'  => basename($rel),
                'path'  => $rel,
                'size'  => $o['size'],
                'mtime' => $o['mtime'],
                'ext'   => strtolower(pathinfo($rel, PATHINFO_EXTENSION)),
            ];
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
                $this->signedHeaders('PUT', $marker, 'application/x-directory'),
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

        // 目录：$from/ 前缀下存在对象
        $children = $this->listObjectKeys($this->objectKey($from) . '/');
        if ($children !== []) {
            return $this->renameDirectory($from, $to, $children);
        }

        // 文件：下载 → 上传 → 删除源
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
                $this->signedHeaders('GET', $fullKey, ''),
                ['timeout' => $this->timeout]
            );
        } catch (\Throwable $e) {
            return null;
        }
        if (!$response->ok()) {
            return null;
        }
        $dest = $dest !== null ? $dest : @tempnam(sys_get_temp_dir(), 'zapossdl_');
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
                'msg' => 'OSS 配置不完整：请检查 bucket / endpoint，以及 config/storage.php 或环境变量中的密钥',
            ];
        }
        $key = '__zap_storage_test/' . bin2hex(random_bytes(6)) . '.txt';
        $tmp = @tempnam(sys_get_temp_dir(), 'zaposs_');
        if ($tmp === false) {
            return ['ok' => false, 'msg' => '无法创建临时文件'];
        }
        file_put_contents($tmp, 'zapcms storage test');
        try {
            $this->put($key, $tmp, ['mime' => 'text/plain']);
            $this->delete($key);
            return ['ok' => true, 'msg' => 'OSS 连接正常（PUT / DELETE 均成功）'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'msg' => $e->getMessage()];
        } finally {
            @unlink($tmp);
        }
    }

    /** OSS 对象的完整 key = 配置前缀 + 站内相对 key */
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

    private function requestHost(): string
    {
        return str_starts_with($this->endpoint, $this->bucket . '.')
            ? $this->endpoint
            : $this->bucket . '.' . $this->endpoint;
    }

    private function requestUrl(string $objectKey): string
    {
        return $this->scheme . '://' . $this->requestHost() . '/' . $this->encodeKey($objectKey);
    }

    private function encodeKey(string $objectKey): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $objectKey)));
    }

    /** 列出某个前缀下的所有对象 key（不含 delimiter，用于删除/移动） */
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
        $url      = $this->scheme . '://' . $this->requestHost() . '/?' . http_build_query($query);
        $headers  = $this->signedHeaders('GET', '', '', $query);
        $response = Requests::get($url, [], $headers, ['timeout' => $this->timeout]);
        if (!$response->ok()) {
            throw new StorageException('OSS 列举失败 HTTP ' . $response->status() . '：' . $this->errorMessage($response));
        }
        return $response->body();
    }

    private function deleteObject(string $fullKey): bool
    {
        try {
            $response = Requests::delete(
                $this->requestUrl($fullKey),
                [],
                $this->signedHeaders('DELETE', $fullKey, ''),
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
            $tmp = @tempnam(sys_get_temp_dir(), 'zaposscp_');
            if ($tmp === false) {
                $ok = false;
                continue;
            }
            try {
                $response = Requests::get(
                    $this->requestUrl($srcFull),
                    [],
                    $this->signedHeaders('GET', $srcFull, ''),
                    ['timeout' => $this->timeout]
                );
                if (!$response->ok()) {
                    $ok = false;
                    continue;
                }
                file_put_contents($tmp, $response->body());
                $dst = $this->objectKey($to) . '/' . $rel;
                $res = Requests::put(
                    $this->requestUrl($dst),
                    $response->body(),
                    $this->signedHeaders('PUT', $dst, ''),
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
     * OSS Signature V1：
     * StringToSign = VERB + "\n" + Content-MD5 + "\n" + Content-Type + "\n"
     *              + Date + "\n" + CanonicalizedOSSHeaders + CanonicalizedResource
     */
    private function signedHeaders(string $method, string $objectKey, string $contentType, array $subResources = []): array
    {
        $date     = gmdate('D, d M Y H:i:s \G\M\T');
        $resource = '/' . $this->bucket . '/' . $objectKey;
        if ($subResources !== []) {
            ksort($subResources);
            $parts = [];
            foreach ($subResources as $k => $v) {
                $parts[] = $k . '=' . $v;
            }
            $resource .= '?' . implode('&', $parts);
        }

        $ossHeaders = [];
        if ($this->securityToken !== '') {
            $ossHeaders['x-oss-security-token'] = $this->securityToken;
        }
        ksort($ossHeaders);
        $canonical = '';
        foreach ($ossHeaders as $name => $value) {
            $canonical .= $name . ':' . trim((string) $value) . "\n";
        }

        $stringToSign = strtoupper($method) . "\n\n" . $contentType . "\n" . $date . "\n"
            . $canonical . $resource;
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $this->accessKeySecret, true));

        $headers = [
            'Date: ' . $date,
            'Authorization: OSS ' . $this->accessKeyId . ':' . $signature,
        ];
        if ($contentType !== '') {
            $headers[] = 'Content-Type: ' . $contentType;
        }
        foreach ($ossHeaders as $name => $value) {
            $headers[] = $name . ': ' . $value;
        }
        return $headers;
    }

    private function errorMessage(\zap\net\Response $response): string
    {
        $body = trim((string) $response->body());
        return $body !== '' ? substr($body, 0, 300) : '无响应内容';
    }
}
