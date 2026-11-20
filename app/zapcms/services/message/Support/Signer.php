<?php

namespace zapcms\services\message\Support;

/**
 * 云服务商 API 签名（腾讯云 TC3-HMAC-SHA256 / 阿里云 ROA HMAC-SHA1）。
 *
 * 邮件（腾讯云 SES、阿里云 DirectMail）与后续短信（腾讯云 SMS、阿里云 SMS）
 * 共用同一套签名逻辑，避免重复实现。
 */
class Signer
{
    /**
     * 腾讯云 API v3 签名（TC3-HMAC-SHA256）。
     *
     * @param string $service   服务名（邮件=ses，短信=sms）
     * @param string $host      域名（如 ses.tencentcloudapi.com）
     * @param string $action    Action（如 SendEmail）
     * @param string $version   Version（如 2020-10-02）
     * @param string $region    地域（如 ap-hongkong）
     * @param string $payload   JSON 请求体
     * @return array 应附加的请求头
     */
    public static function tencent(
        string $service,
        string $host,
        string $action,
        string $version,
        string $region,
        string $payload,
        string $secretId,
        string $secretKey
    ): array {
        $algorithm       = 'TC3-HMAC-SHA256';
        $timestamp       = time();
        $date            = gmdate('Y-m-d', $timestamp);
        $hashedPayload   = hash('sha256', $payload);

        $canonicalHeaders = sprintf(
            "content-type:application/json; charset=utf-8\nhost:%s\nx-tc-action:%s\nx-tc-timestamp:%d\n",
            $host,
            $action,
            $timestamp
        );
        $signedHeaders    = 'content-type;host;x-tc-action;x-tc-timestamp';
        $canonicalRequest = "POST\n/\n\n" . $canonicalHeaders . "\n" . $signedHeaders . "\n" . $hashedPayload;

        $credentialScope = $date . '/' . $service . '/tc3_request';
        $stringToSign    = $algorithm . "\n" . $timestamp . "\n" . $credentialScope . "\n" . hash('sha256', $canonicalRequest);

        $secretDate     = hash_hmac('sha256', $date, 'TC3' . $secretKey, true);
        $secretService  = hash_hmac('sha256', $service, $secretDate, true);
        $secretSigning  = hash_hmac('sha256', 'tc3_request', $secretService, true);
        $signature      = hash_hmac('sha256', $stringToSign, $secretSigning);

        return [
            'Authorization'   => $algorithm . ' Credential=' . $secretId . '/' . $credentialScope
                . ', SignedHeaders=' . $signedHeaders . ', Signature=' . $signature,
            'Content-Type'    => 'application/json; charset=utf-8',
            'Host'            => $host,
            'X-TC-Action'     => $action,
            'X-TC-Version'    => $version,
            'X-TC-Timestamp'  => (string) $timestamp,
            'X-TC-Region'     => $region,
        ];
    }

    /**
     * 阿里云开放 API 签名（ROA，HMAC-SHA1）。
     *
     * @param array  $params        业务参数（含 Action / 各接口参数），会并入公共参数并返回带 Signature 的完整参数
     * @param string $accessKeyId
     * @param string $accessKeySecret
     * @param string $method        GET / POST（默认 POST）
     * @return array 可直接作为表单（或查询）提交的完整参数
     */
    public static function aliyun(array $params, string $accessKeyId, string $accessKeySecret, string $method = 'POST'): array
    {
        $params = array_merge([
            'Format'           => 'JSON',
            'Version'          => '2015-11-23',
            'AccessKeyId'      => $accessKeyId,
            'SignatureMethod'  => 'HMAC-SHA1',
            'Timestamp'        => gmdate('Y-m-d\TH:i:s\Z'),
            'SignatureVersion' => '1.0',
        ], $params);

        ksort($params);

        $canonicalized = '';
        foreach ($params as $k => $v) {
            $canonicalized .= '&' . self::aliyunEncode($k) . '=' . self::aliyunEncode($v);
        }
        $canonicalized = substr($canonicalized, 1);

        $stringToSign   = $method . '&' . self::aliyunEncode('/') . '&' . self::aliyunEncode($canonicalized);
        $params['Signature'] = base64_encode(hash_hmac('sha1', $stringToSign, $accessKeySecret . '&', true));

        return $params;
    }

    protected static function aliyunEncode(string $str): string
    {
        return str_replace(['+', '*', '%7E'], ['%20', '%2A', '~'], urlencode($str));
    }
}
