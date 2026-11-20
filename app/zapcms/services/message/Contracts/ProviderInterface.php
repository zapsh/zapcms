<?php

namespace zapcms\services\message\Contracts;

use zapcms\services\message\Support\Result;

/**
 * 具体发送网关（SMTP / SendGrid / Mailgun / Resend / 腾讯云 / 阿里云 …）契约。
 */
interface ProviderInterface
{
    /**
     * 服务商标识（smtp / sendgrid / mailgun / resend / tencent / aliyun）。
     */
    public function getName(): string;

    /**
     * 发送一条消息。
     *
     * @param array $message
     *   ['to'=>string|array, 'subject'=>string, 'body'=>string,
     *    'isHtml'=>bool, 'from'=>?string, 'fromName'=>?string, 'attachments'=>?array]
     * @return Result
     */
    public function send(array $message): Result;
}
