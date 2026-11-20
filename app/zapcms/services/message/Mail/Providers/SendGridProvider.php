<?php

namespace zapcms\services\message\Mail\Providers;

use zapcms\services\message\Contracts\ProviderInterface;
use zapcms\services\message\Support\HttpClient;
use zapcms\services\message\Support\MailHelpers;
use zapcms\services\message\Support\Result;
use zapcms\services\storage\Secret;

/**
 * SendGrid 邮件（HTTP API，不加载 SDK）。
 */
class SendGridProvider implements ProviderInterface
{
    public function getName(): string
    {
        return 'sendgrid';
    }

    public function send(array $message): Result
    {
        $key  = (string) (Secret::get('mail.sendgrid_key', '') ?: option('mail.sendgrid_key', ''));
        $from = $message['from'] ?? option('mail.sendgrid_from', '');
        if ($key === '' || $from === '') {
            return Result::fail('sendgrid', '未配置 SendGrid API Key 或发件人');
        }

        $data = [
            'personalizations' => [['to' => MailHelpers::recipients($message['to'])]],
            'from'             => ['email' => $from],
            'subject'          => (string) ($message['subject'] ?? ''),
            'content'          => [
                [
                    'type'  => !empty($message['isHtml']) ? 'text/html' : 'text/plain',
                    'value' => (string) ($message['body'] ?? ''),
                ],
            ],
        ];

        $res = HttpClient::postJson(
            'https://api.sendgrid.com/v3/mail/send',
            $data,
            ['Authorization' => 'Bearer ' . $key]
        );

        if ($res['error'] !== null) {
            return Result::fail('sendgrid', $res['error']);
        }
        if ($res['status'] >= 200 && $res['status'] < 300) {
            return Result::ok('sendgrid', 'sent', ['status' => $res['status']]);
        }

        return Result::fail('sendgrid', 'HTTP ' . $res['status'] . ': ' . $res['body']);
    }
}
