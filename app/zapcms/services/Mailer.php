<?php

/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS
 */

namespace zapcms\services;

use PHPMailer\PHPMailer\PHPMailer;
use zapcms\services\message\Mail\MailChannel;
use zapcms\services\message\Mail\Providers\SmtpProvider;
use zapcms\services\queue\Queue;
use zapcms\services\queue\jobs\SendMailJob;

/**
 * 邮件门面（兼容旧调用）。
 *
 * 内部统一走 MailChannel：默认 SMTP，可配置多服务商（SendGrid / Mailgun /
 * Resend / 腾讯云 SES / 阿里云 DirectMail）并自动 failover。
 * 见 app/zapcms/services/message/。
 */
class Mailer
{
    /**
     * 构建 SMTP 的 PHPMailer 实例（保留旧接口，供需要直接操作 PHPMailer 的场景）。
     *
     * @return PHPMailer
     */
    public static function configure()
    {
        return SmtpProvider::buildMailer();
    }

    /**
     * 同步发送邮件（经 MailChannel，失败返回 false）。
     *
     * @param string|array $to       收件人地址，或 [地址 => 名称] 数组
     * @param string       $subject  邮件主题
     * @param string       $body     邮件正文
     * @param bool         $isHtml   是否为 HTML 格式
     * @return bool
     */
    public static function send($to, $subject, $body, $isHtml = true)
    {
        $channel = new MailChannel();
        $result  = $channel->send([
            'to'      => $to,
            'subject' => $subject,
            'body'    => $body,
            'isHtml'  => $isHtml,
        ]);

        return $result->success;
    }

    /**
     * 异步入队发送（由队列 worker 执行，避免阻塞请求）。
     *
     * @return mixed 入队任务 id
     */
    public static function queue($to, $subject, $body, $isHtml = true)
    {
        return Queue::push(new SendMailJob([
            'to'      => $to,
            'subject' => $subject,
            'body'    => $body,
            'isHtml'  => $isHtml,
        ]));
    }
}
