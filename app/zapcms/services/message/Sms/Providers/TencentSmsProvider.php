<?php

namespace zapcms\services\message\Sms\Providers;

use zapcms\services\message\Contracts\ProviderInterface;
use zapcms\services\message\Support\HttpClient;
use zapcms\services\message\Support\Result;
use zapcms\services\message\Support\Signer;
use zapcms\services\storage\Secret;

/**
 * 腾讯云短信（SMS，SendSms，HTTP API，不加载 SDK）。
 *
 * 腾讯云短信为模板 + 签名模式：需先在控制台创建签名与模板，再传 TemplateId 与参数。
 */
class TencentSmsProvider implements ProviderInterface
{
    public function getName(): string
    {
        return 'tencent';
    }

    public function send(array $message): Result
    {
        $secretId  = (string) (Secret::get('sms.tencent_secret_id', '') ?: option('sms.tencent_secret_id', ''));
        $secretKey = (string) (Secret::get('sms.tencent_secret_key', '') ?: option('sms.tencent_secret_key', ''));
        $appId     = (string) option('sms.tencent_app_id', '');
        $sign      = $message['sign'] ?? option('sms.tencent_sign', '');
        $region    = (string) option('sms.tencent_region', 'ap-guangzhou');
        if ($secretId === '' || $secretKey === '' || $appId === '' || $sign === '') {
            return Result::fail('tencent', '未配置腾讯云 SecretId / SecretKey / SmsSdkAppId / 签名');
        }

        $phones   = (array) ($message['phone'] ?? []);
        $numbers  = array_map(static fn($p) => strpos((string) $p, '+') === 0 ? (string) $p : ('+86' . $p), $phones);
        $params   = $message['params'] ?? [];
        $paramSet = array_map('strval', array_values($params));

        $payload = json_encode([
            'PhoneNumberSet'    => $numbers,
            'SmsSdkAppId'       => $appId,
            'SignName'          => $sign,
            'TemplateId'        => (string) ($message['template'] ?? ''),
            'TemplateParamSet'  => $paramSet,
        ], JSON_UNESCAPED_UNICODE);

        $host    = 'sms.tencentcloudapi.com';
        $headers = Signer::tencent('sms', $host, 'SendSms', '2021-01-11', $region, $payload, $secretId, $secretKey);

        $res = HttpClient::postJson('https://' . $host, $payload, $headers);

        if ($res['error'] !== null) {
            return Result::fail('tencent', $res['error']);
        }

        $json = HttpClient::decode($res['body']);
        $resp = $json['Response'] ?? [];
        if (isset($resp['Error'])) {
            return Result::fail('tencent', $resp['Error']['Message'] ?? 'error', $resp);
        }

        // 部分号码失败也算失败
        foreach (($resp['SendStatusSet'] ?? []) as $s) {
            if (($s['Code'] ?? '') !== 'Ok') {
                return Result::fail('tencent', $s['Message'] ?? 'send failed', $resp);
            }
        }

        return Result::ok('tencent', $resp['RequestId'] ?? 'sent', $resp);
    }
}
