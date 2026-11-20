<?php

namespace zapcms\services\message\Support;

/**
 * 轻量 HTTP 客户端（基于 curl，不依赖任何 SDK）。
 *
 * 用于各邮件 / 短信服务商的 API 调用。
 */
class HttpClient
{
    /**
     * @param string $method  GET/POST/PUT/DELETE
     * @param string $url
     * @param array  $options
     *   - headers array  额外请求头
     *   - json    mixed  作为 JSON 发送（自动 Content-Type: application/json）
     *   - form    array  作为 application/x-www-form-urlencoded 发送
     *   - body    string 原始请求体
     *   - timeout int    超时秒数（默认 10）
     *   - verify  bool   是否校验 SSL（默认 true）
     * @return array ['status' => int, 'body' => string, 'error' => ?string]
     */
    public static function request(string $method, string $url, array $options = []): array
    {
        $method  = strtoupper($method);
        $timeout = (int) ($options['timeout'] ?? 10);
        $verify  = (bool) ($options['verify'] ?? true);

        $headerLines = [];
        foreach ($options['headers'] ?? [] as $k => $v) {
            $headerLines[] = $k . ': ' . $v;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, max(3, intdiv($timeout, 2)));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verify);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verify ? 2 : 0);

        if ($method !== 'GET' && $method !== 'HEAD') {
            if (isset($options['json'])) {
                $body        = is_string($options['json']) ? $options['json'] : json_encode($options['json'], JSON_UNESCAPED_UNICODE);
                $headerLines[] = 'Content-Type: application/json';
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            } elseif (isset($options['form'])) {
                $headerLines[] = 'Content-Type: application/x-www-form-urlencoded';
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($options['form']));
            } elseif (isset($options['body'])) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, (string) $options['body']);
            }
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headerLines);

        $raw   = curl_exec($ch);
        $error = curl_errno($ch) ? curl_error($ch) : null;
        $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $code,
            'body'   => $raw === false ? '' : (string) $raw,
            'error'  => $error,
        ];
    }

    /**
     * 发送 JSON 请求（Bearer 等鉴权头通过 $headers 传入）。
     */
    public static function postJson(string $url, $data, array $headers = [], array $options = []): array
    {
        $options['json']    = $data;
        $options['headers'] = array_merge($options['headers'] ?? [], $headers);

        return self::request('POST', $url, $options);
    }

    /**
     * 解析 JSON 响应，失败返回 null。
     */
    public static function decode(?string $body): ?array
    {
        if ($body === null || $body === '') {
            return null;
        }
        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : null;
    }
}
