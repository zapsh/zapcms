<?php

namespace zapcms\services\storage\drivers;

use zap\net\Requests;
use zapcms\services\storage\StorageException;
use zapcms\services\storage\StorageInterface;
use zapcms\services\storage\StorageResult;

/**
 * Cloudflare R2 对象存储（S3 兼容，AWS Signature V4 自签，零 composer 依赖）
 *
 * 复用框架 zap\net\Requests，实现 PUT / GET / DELETE / HEAD 与 ListObjectsV2。
 * R2 无内置图片处理，缩略图直接返回原图。公开访问需自定义域名或 r2.dev 公共地址，
 * 请配置 upload.r2_base_url。密钥来自 config/storage.php / 环境变量。
 */
class R2Storage implements StorageInterface
{
    use ParsesObjectListings;

    private string $accountId;
    private string $bucket;
    private string $host;      // {account_id}.r2.cloudflarestorage.com
    private string $baseUrl;   // 公开访问地址（自定义域名或 r2.dev）
    private string $prefix;
    private string $region;    // R2 固定为 auto
    private string $accessKeyId;
    private string $secretAccessKey;
    private int $timeout;

    public function __construct()
    {
        $this->accountId = trim((string) option('upload.r2_account_id', ''));
        $this->bucket    = trim((string) option('upload.r2_bucket', ''));
        $endpoint        = trim((string) option('upload.r2_endpoint', ''));
        $this->host      = $endpoint !== ''
            ? rtrim(preg_replace('#^https?://#i', '', $endpoint), '/')
            : ($this->accountId !== '' ? $this->accountId . '.r2.cloudflarestorage.com' : '');
        $this->baseUrl = rtrim(trim((string) option('upload.r2_base_url', '')), '/');
        $this->prefix  = trim((string) option('upload.r2_prefix', ''), '/');
        $this->region  = 'auto';

        $conf = (array) config('storage.r2', []);
        $this->accessKeyId     = (string) ($conf['access_key_id'] ?? '');
        $this->secretAccessKey = (string) ($conf['secret_access_key'] ?? '');
        $this->timeout         = max(5, (int) ($conf['timeout'] ?? 60));
    }

    public function isConfigured(): bool
    {
        return $this->bucket !== ''
            && $this->host !== ''
            && $this->accessKeyId !== ''
            && $this->secretAccessKey !== '';
    }

    public function put(string $key, string $tmpFile, array $meta = []): StorageResult
    {
        if (!$this->isConfigured()) {
            throw new StorageException('R2 未配置完整（bucket / endpoint / 密钥）');
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
            $this->sign('PUT', $this->uriPath($objectKey), [], $body, $mime),
            ['timeout' => $this->timeout]
        );
        if (!$response->ok()) {
            throw new StorageException('R2 上传失败 HTTP ' . $response->status() . '：' . $this->errorMessage($response));
        }

        return new StorageResult($key, $this->url($key), 'r2', strlen($body), $mime);
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
                $this->sign('HEAD', $this->uriPath($fullKey), [], '', ''),
                ['timeout' => min(15, $this->timeout)]
            );
            return $response->ok();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function url(string $key): string
    {
        $base = $this->baseUrl !== '' ? $this->baseUrl : 'https://' . $this->host . '/' . $this->bucket;
        return $base . '/' . $this->encodeKey($this->objectKey($key));
    }

    public function keyFromUrl(string $url): ?string
    {
        $url  = preg_replace('/[?#].*$/', '', trim($url));
        $base = rtrim($this->baseUrl !== '' ? $this->baseUrl : 'https://' . $this->host . '/' . $this->bucket, '/');
        if ($base === '' || $url === $base) {
            return null;
        }
        if (str_starts_with($url, $base . '/')) {
            $rest = ltrim(substr($url, strlen($base) + 1), '/');
            return $this->relativeKey(rawurldecode($rest));
        }
        return null;
    }

    /** R2 无内置图片处理，缩略图直接返回原图地址 */
    public function thumbUrl(string $key, int $width, int $height): string
    {
        return $this->url($key);
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
                $this->sign('PUT', $this->uriPath($marker), [], '', 'application/x-directory'),
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
                $this->sign('GET', $this->uriPath($fullKey), [], '', ''),
                ['timeout' => $this->timeout]
            );
        } catch (\Throwable $e) {
            return null;
        }
        if (!$response->ok()) {
            return null;
        }
        $dest = $dest !== null ? $dest : @tempnam(sys_get_temp_dir(), 'zapr2dl_');
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
                'msg' => 'R2 配置不完整：请检查 bucket / endpoint，以及 config/storage.php 或环境变量中的密钥',
            ];
        }
        $key = '__zap_storage_test/' . bin2hex(random_bytes(6)) . '.txt';
        $tmp = @tempnam(sys_get_temp_dir(), 'zapr2_');
        if ($tmp === false) {
            return ['ok' => false, 'msg' => '无法创建临时文件'];
        }
        file_put_contents($tmp, 'zapcms storage test');
        try {
            $this->put($key, $tmp, ['mime' => 'text/plain']);
            $this->delete($key);
            return ['ok' => true, 'msg' => 'R2 连接正常（PUT / DELETE 均成功）'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'msg' => $e->getMessage()];
        } finally {
            @unlink($tmp);
        }
    }

    /** R2 对象的完整 key = 配置前缀 + 站内相对 key */
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

    /** 路径式访问：/{bucket}/{object} */
    private function uriPath(string $objectKey): string
    {
        return '/' . $this->bucket . '/' . $this->encodeKey($objectKey);
    }

    private function requestUrl(string $objectKey): string
    {
        return 'https://' . $this->host . '/' . $this->bucket . '/' . $this->encodeKey($objectKey);
    }

    private function encodeKey(string $objectKey): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $objectKey)));
    }

    private function listObjectKeys(string $fullPrefix): array
    {
        $parsed = $this->parseObjectListXml($this->listRaw($fullPrefix, ''));
        return array_column($parsed['objects'], 'key');
    }

    private function listRaw(string $prefix, string $delimiter): string
    {
        $query = ['list-type' => '2', 'prefix' => $prefix, 'max-keys' => '1000'];
        if ($delimiter !== '') {
            $query['delimiter'] = $delimiter;
        }
        $url      = 'https://' . $this->host . '/' . $this->bucket . '?' . $this->canonicalQuery($query);
        $headers  = $this->sign('GET', '/' . $this->bucket, $query, '', '');
        $response = Requests::get($url, [], $headers, ['timeout' => $this->timeout]);
        if (!$response->ok()) {
            throw new StorageException('R2 列举失败 HTTP ' . $response->status() . '：' . $this->errorMessage($response));
        }
        return $response->body();
    }

    private function deleteObject(string $fullKey): bool
    {
        try {
            $response = Requests::delete(
                $this->requestUrl($fullKey),
                [],
                $this->sign('DELETE', $this->uriPath($fullKey), [], '', ''),
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
            $tmp = @tempnam(sys_get_temp_dir(), 'zapr2cp_');
            if ($tmp === false) {
                $ok = false;
                continue;
            }
            try {
                $response = Requests::get(
                    $this->requestUrl($srcFull),
                    [],
                    $this->sign('GET', $this->uriPath($srcFull), [], '', ''),
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
                    $this->sign('PUT', $this->uriPath($dst), [], $response->body(), ''),
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

    /** AWS Signature V4 */
    private function sign(string $method, string $uriPath, array $query, string $payload, string $contentType): array
    {
        $now       = time();
        $amzDate   = gmdate('Ymd\THis\Z', $now);
        $dateStamp = gmdate('Ymd', $now);
        $service   = 's3';

        $canonicalQuery = $this->canonicalQuery($query);

        $headers = [
            'host'                 => $this->host,
            'x-amz-content-sha256' => hash('sha256', $payload),
            'x-amz-date'           => $amzDate,
        ];
        if ($contentType !== '') {
            $headers['content-type'] = $contentType;
        }
        ksort($headers);
        $canonicalHeaders = '';
        foreach ($headers as $k => $v) {
            $canonicalHeaders .= $k . ':' . trim($v) . "\n";
        }
        $signedHeaders = implode(';', array_keys($headers));

        $canonicalRequest = strtoupper($method) . "\n"
            . $uriPath . "\n"
            . $canonicalQuery . "\n"
            . $canonicalHeaders . "\n"
            . $signedHeaders . "\n"
            . $headers['x-amz-content-sha256'];

        $scope = $dateStamp . '/' . $this->region . '/' . $service . '/aws4_request';
        $stringToSign = "AWS4-HMAC-SHA256\n" . $amzDate . "\n" . $scope . "\n"
            . hash('sha256', $canonicalRequest);

        $kDate    = hash_hmac('sha256', $dateStamp, 'AWS4' . $this->secretAccessKey, true);
        $kRegion  = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $auth = 'AWS4-HMAC-SHA256 Credential=' . $this->accessKeyId . '/' . $scope
            . ', SignedHeaders=' . $signedHeaders . ', Signature=' . $signature;

        $result = [
            'Authorization: ' . $auth,
            'x-amz-date: ' . $amzDate,
            'x-amz-content-sha256: ' . $headers['x-amz-content-sha256'],
            'Host: ' . $this->host,
        ];
        if ($contentType !== '') {
            $result[] = 'Content-Type: ' . $contentType;
        }
        return $result;
    }

    private function canonicalQuery(array $query): string
    {
        ksort($query);
        $parts = [];
        foreach ($query as $k => $v) {
            if ($v === null || $v === '') {
                continue;
            }
            $parts[] = rawurlencode((string) $k) . '=' . rawurlencode((string) $v);
        }
        return implode('&', $parts);
    }

    private function errorMessage(\zap\net\Response $response): string
    {
        $body = trim((string) $response->body());
        return $body !== '' ? substr($body, 0, 300) : '无响应内容';
    }
}
