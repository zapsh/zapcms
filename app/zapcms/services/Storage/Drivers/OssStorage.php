<?php

namespace zapcms\services\Storage\Drivers;

use zap\net\Requests;
use zapcms\services\Storage\StorageException;
use zapcms\services\Storage\StorageInterface;
use zapcms\services\Storage\StorageResult;

/**
 * 阿里云 OSS 对象存储（Signature V1 自签，零 composer 依赖）
 *
 * 只实现 PUT / DELETE / HEAD 三种请求，复用框架 zap\net\Requests。
 * 要求 bucket 为公共读（一期不支持私有桶签名 URL，签名 URL 会过期，不能写入 DB）。
 * 密钥来自 config/storage.php / 环境变量；其余参数来自后台 upload.oss_* 选项。
 */
class OssStorage implements StorageInterface
{
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
        $objectKey = $this->objectKey($key);
        try {
            $response = Requests::delete(
                $this->requestUrl($objectKey),
                [],
                $this->signedHeaders('DELETE', $objectKey, ''),
                ['timeout' => $this->timeout]
            );
            return $response->ok();
        } catch (\Throwable $e) {
            return false;
        }
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

    /** 阿里云图片处理参数，无需本地 GD、不消耗服务器带宽 */
    public function thumbUrl(string $key, int $width, int $height): string
    {
        $process = 'x-oss-process=image/resize,m_lfit,w_' . max(1, $width) . ',h_' . max(1, $height);
        $url = $this->url($key);
        return $url . (str_contains($url, '?') ? '&' : '?') . $process;
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

    private function requestUrl(string $objectKey): string
    {
        $host = str_starts_with($this->endpoint, $this->bucket . '.')
            ? $this->endpoint
            : $this->bucket . '.' . $this->endpoint;
        return $this->scheme . '://' . $host . '/' . $this->encodeKey($objectKey);
    }

    private function encodeKey(string $objectKey): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $objectKey)));
    }

    /**
     * OSS Signature V1：
     * StringToSign = VERB + "\n" + Content-MD5 + "\n" + Content-Type + "\n"
     *              + Date + "\n" + CanonicalizedOSSHeaders + CanonicalizedResource
     */
    private function signedHeaders(string $method, string $objectKey, string $contentType): array
    {
        $date     = gmdate('D, d M Y H:i:s \G\M\T');
        $resource = '/' . $this->bucket . '/' . $objectKey;

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
