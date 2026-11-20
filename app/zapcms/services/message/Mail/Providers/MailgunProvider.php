<?php

namespace zapcms\services\message\Mail\Providers;

use zapcms\services\message\Contracts\ProviderInterface;
use zapcms\services\message\Support\HttpClient;
use zapcms\services\message\Support\MailHelpers;
use zapcms\services\message\Support\Result;
use zapcms\services\storage\Secret;

/**
 * Mailgun 邮件（HTTP API，不加载 SDK）。
 */
class MailgunProvider implements ProviderInterface
{
    public function getName(): string
    {
        return 'mailgun';
    }

    public function send(array $message): Result
    {
        $key    = (string) (Secret::get('mail.mailgun_key', '') ?: option('mail.mailgun_key', ''));
        $domain = (string) option('mail.mailgun_domain', '');
        $from   = $message['from'] ?? option('mail.mailgun_from', '');
        $region = (string) option('mail.mailgun_region', 'us');
        if ($key === '' || $domain === '' || $from === '') {
            return Result::fail('mailgun', '未配置 Mailgun API Key / Domain / 发件人');
        }

        $fromName = $message['fromName'] ?? '';
        $form     = [
            'from'    => $fromName !== '' ? ($fromName . ' <' . $from . '>') : $from,
            'to'      => MailHelpers::emailsCsv($message['to']),
            'subject' => (string) ($message['subject'] ?? ''),
        ];
        if (!empty($message['isHtml'])) {
            $form['html'] = (string) ($message['body'] ?? '');
        } else {
            $form['text'] = (string) ($message['body'] ?? '');
        }

        $base = $region === 'eu' ? 'https://api.eu.mailgun.net' : 'https://api.mailgun.net';
        $res  = HttpClient::request('POST', $base . '/v3/' . $domain . '/messages', [
            'form'    => $form,
            'headers' => ['Authorization' => 'Basic ' . base64_encode('api:' . $key)],
        ]);

        if ($res['error'] !== null) {
            return Result::fail('mailgun', $res['error']);
        }
        if ($res['status'] >= 200 && $res['status'] < 300) {
            return Result::ok('mailgun', 'sent', HttpClient::decode($res['body']) ?? []);
        }

        return Result::fail('mailgun', 'HTTP ' . $res['status'] . ': ' . $res['body']);
    }
}
