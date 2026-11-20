<?php

namespace zapcms\services\message\Mail\Providers;

use zapcms\services\message\Contracts\ProviderInterface;
use zapcms\services\message\Support\HttpClient;
use zapcms\services\message\Support\MailHelpers;
use zapcms\services\message\Support\Result;
use zapcms\services\message\Support\Signer;
use zapcms\services\storage\Secret;

/**
 * 阿里云邮件推送（DirectMail，SingleSendMail，HTTP API，不加载 SDK）。
 */
class AliyunMailProvider implements ProviderInterface
{
    public function getName(): string
    {
        return 'aliyun';
    }

    public function send(array $message): Result
    {
        $accessKeyId     = (string) (Secret::get('mail.aliyun_access_key', '') ?: option('mail.aliyun_access_key', ''));
        $accessSecret    = (string) (Secret::get('mail.aliyun_access_secret', '') ?: option('mail.aliyun_access_secret', ''));
        $from            = $message['from'] ?? option('mail.aliyun_from', '');
        if ($accessKeyId === '' || $accessSecret === '' || $from === '') {
            return Result::fail('aliyun', '未配置阿里云 AccessKeyId / AccessSecret / 发件人');
        }

        $params = [
            'Action'       => 'SingleSendMail',
            'AccountName'  => $from,
            'AddressType'  => '1',                       // 1=触发邮件（事务类）
            'ToAddress'    => MailHelpers::emailsCsv($message['to']),
            'Subject'      => (string) ($message['subject'] ?? ''),
            'ReplyToAddress' => 'false',
            'TagName'      => (string) option('mail.aliyun_tag', ''),
        ];
        if (!empty($message['isHtml'])) {
            $params['HtmlBody'] = (string) ($message['body'] ?? '');
        } else {
            $params['TextBody'] = (string) ($message['body'] ?? '');
        }

        $signed = Signer::aliyun($params, $accessKeyId, $accessSecret, 'POST');
        $res    = HttpClient::request('POST', 'https://dm.aliyuncs.com', ['form' => $signed]);

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
