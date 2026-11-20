<?php

namespace zapcms\services\message\Mail\Providers;

use zapcms\services\message\Contracts\ProviderInterface;
use zapcms\services\message\Support\HttpClient;
use zapcms\services\message\Support\MailHelpers;
use zapcms\services\message\Support\Result;
use zapcms\services\storage\Secret;

/**
 * Resend 邮件（HTTP API，不加载 SDK）。
 */
class ResendProvider implements ProviderInterface
{
    public function getName(): string
    {
        return 'resend';
    }

    public function send(array $message): Result
    {
        $key  = (string) (Secret::get('mail.resend_key', '') ?: option('mail.resend_key', ''));
        $from = $message['from'] ?? option('mail.resend_from', '');
        if ($key === '' || $from === '') {
            return Result::fail('resend', '未配置 Resend API Key 或发件人');
        }

        $isHtml = !empty($message['isHtml']);
        $data   = [
            'from'    => $from,
            'to'      => MailHelpers::emails($message['to']),
            'subject' => (string) ($message['subject'] ?? ''),
        ];
        if ($isHtml) {
            $data['html'] = (string) ($message['body'] ?? '');
        } else {
            $data['text'] = (string) ($message['body'] ?? '');
        }

        $res = HttpClient::postJson(
            'https://api.resend.com/emails',
            $data,
            ['Authorization' => 'Bearer ' . $key]
        );

        if ($res['error'] !== null) {
            return Result::fail('resend', $res['error']);
        }
        if ($res['status'] >= 200 && $res['status'] < 300) {
            return Result::ok('resend', 'sent', HttpClient::decode($res['body']) ?? []);
        }

        return Result::fail('resend', 'HTTP ' . $res['status'] . ': ' . $res['body']);
    }
}
