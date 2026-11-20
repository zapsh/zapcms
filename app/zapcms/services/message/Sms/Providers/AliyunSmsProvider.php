<?php

namespace zapcms\services\message\Sms\Providers;

use zapcms\services\message\Contracts\ProviderInterface;
use zapcms\services\message\Support\HttpClient;
use zapcms\services\message\Support\Result;
use zapcms\services\message\Support\Signer;
use zapcms\services\storage\Secret;

/**
 * 阿里云短信（SendSms，HTTP API，不加载 SDK）。
 *
 * 同样为模板 + 签名模式：SignName + TemplateCode + TemplateParam(JSON)。
 */
class AliyunSmsProvider implements ProviderInterface
{
    public function getName(): string
    {
        return 'aliyun';
    }

    public function send(array $message): Result
    {
        $accessKeyId  = (string) (Secret::get('sms.aliyun_access_key', '') ?: option('sms.aliyun_access_key', ''));
        $accessSecret = (string) (Secret::get('sms.aliyun_access_secret', '') ?: option('sms.aliyun_access_secret', ''));
        $sign         = $message['sign'] ?? option('sms.aliyun_sign', '');
        if ($accessKeyId === '' || $accessSecret === '' || $sign === '') {
            return Result::fail('aliyun', '未配置阿里云 AccessKeyId / AccessSecret / 签名');
        }

        $phones = (array) ($message['phone'] ?? []);
        $params = $message['params'] ?? [];

        $paramsApi = [
            'Action'        => 'SendSms',
            'PhoneNumbers'  => implode(',', $phones),
            'SignName'      => $sign,
            'TemplateCode'  => (string) ($message['template'] ?? ''),
            'TemplateParam' => json_encode($params, JSON_UNESCAPED_UNICODE),
        ];

        $signed = Signer::aliyun($paramsApi, $accessKeyId, $accessSecret, 'POST');
        $res    = HttpClient::request('POST', 'https://dysmsapi.aliyuncs.com', ['form' => $signed]);

        if ($res['error'] !== null) {
            return Result::fail('aliyun', $res['error']);
        }

        $json = HttpClient::decode($res['body']);
        if (isset($json['Code']) && $json['Code'] === 'OK') {
            return Result::ok('aliyun', $json['RequestId'] ?? 'sent', $json);
        }

        return Result::fail('aliyun', $json['Message'] ?? ('HTTP ' . $res['status'] . ': ' . $res['body']), $json);
    }
}
