<?php

namespace zapcms\services\message\Mail;

use zapcms\services\message\Contracts\ChannelInterface;
use zapcms\services\message\Mail\Providers\AliyunMailProvider;
use zapcms\services\message\Mail\Providers\MailgunProvider;
use zapcms\services\message\Mail\Providers\ResendProvider;
use zapcms\services\message\Mail\Providers\SendGridProvider;
use zapcms\services\message\Mail\Providers\SmtpProvider;
use zapcms\services\message\Mail\Providers\TencentMailProvider;
use zapcms\services\message\Support\Result;

/**
 * 邮件通道：按配置顺序尝试服务商，前一个失败自动切到下一个（failover）。
 *
 * 配置（option / Secret）：
 *  - mail.default  主服务商（默认 smtp）；可选 smtp/sendgrid/mailgun/resend/tencent/aliyun
 *  - mail.failover 逗号分隔的备用服务商（主服务商失败时使用）
 * 各服务商凭据见对应 Provider（如 mail.sendgrid_key、mail.tencent_secret_id …）。
 */
class MailChannel implements ChannelInterface
{
    protected array $map = [
        'smtp'     => SmtpProvider::class,
        'sendgrid' => SendGridProvider::class,
        'mailgun'  => MailgunProvider::class,
        'resend'   => ResendProvider::class,
        'tencent'  => TencentMailProvider::class,
        'aliyun'   => AliyunMailProvider::class,
    ];

    /**
     * @return Result
     */
    public function send(array $message): Result
    {
        $order = $this->order();
        $last  = null;

        foreach ($order as $name) {
            if (!isset($this->map[$name])) {
                continue;
            }
            try {
                /** @var \zapcms\services\message\Contracts\ProviderInterface $provider */
                $provider = new $this->map[$name]();
                $result   = $provider->send($message);
            } catch (\Throwable $e) {
                $result = Result::fail($name, $e->getMessage());
            }

            if ($result->success) {
                return $result;
            }
            $last = $result;
        }

        return $last ?? Result::fail('mail', '未配置任何邮件服务商');
    }

    /**
     * 服务商尝试顺序：默认 + 备用（去重）。
     */
    protected function order(): array
    {
        $default  = trim((string) option('mail.default', 'smtp'));
        $failover = array_filter(array_map('trim', explode(',', (string) option('mail.failover', ''))));

        $order = [];
        if ($default !== '') {
            $order[] = $default;
        }
        foreach ($failover as $f) {
            if ($f !== '' && $f !== $default && !in_array($f, $order, true)) {
                $order[] = $f;
            }
        }

        if (empty($order)) {
            $order = ['smtp'];
        }

        return $order;
    }
}
