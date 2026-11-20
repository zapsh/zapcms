<?php

namespace zapcms\services\message\Mail\Providers;

use zapcms\services\message\Contracts\ProviderInterface;
use zapcms\services\message\Support\HttpClient;
use zapcms\services\message\Support\MailHelpers;
use zapcms\services\message\Support\Result;
use zapcms\services\message\Support\Signer;
use zapcms\services\storage\Secret;

/**
 * 腾讯云邮件推送（SES，SendEmail，HTTP API，不加载 SDK）。
 */
class TencentMailProvider implements ProviderInterface
{
    public function getName(): string
    {
        return 'tencent';
    }

    public function send(array $message): Result
    {
        $secretId  = (string) (Secret::get('mail.tencent_secret_id', '') ?: option('mail.tencent_secret_id', ''));
        $secretKey = (string) (Secret::get('mail.tencent_secret_key', '') ?: option('mail.tencent_secret_key', ''));
        $from      = $message['from'] ?? option('mail.tencent_from', '');
        $region    = (string) option('mail.tencent_region', 'ap-hongkong');
        if ($secretId === '' || $secretKey === '' || $from === '') {
            return Result::fail('tencent', '未配置腾讯云 SecretId / SecretKey / 发件人');
        }

        $isHtml = !empty($message['isHtml']);
        $body   = [
            'Html' => ['Html' => (string) ($message['body'] ?? '')],
        ];
        if (!$isHtml) {
            $body = [
                'Text' => ['Text' => (string) ($message['body'] ?? '')],
            ];
        }

        $payload = json_encode([
            'FromEmailAddress' => $from,
            'Destination'      => MailHelpers::emails($message['to']),
            'Subject'          => (string) ($message['subject'] ?? ''),
            'Body'             => $body,
        ], JSON_UNESCAPED_UNICODE);

        $host    = 'ses.tencentcloudapi.com';
        $headers = Signer::tencent('ses', $host, 'SendEmail', '2020-10-02', $region, $payload, $secretId, $secretKey);

        $res = HttpClient::postJson('https://' . $host, $payload, $headers);

        if ($res['error'] !== null) {
            return Result::fail('tencent', $res['error']);
        }

        $json = HttpClient::decode($res['body']);
        $resp = $json['Response'] ?? [];
        if (isset($resp['Error'])) {
            return Result::fail('tencent', $resp['Error']['Message'] ?? 'error', $resp);
        }

        return Result::ok('tencent', $resp['RequestId'] ?? 'sent', $resp);
    }
}
