<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS
 */

namespace zapcms\services;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

class Mailer
{
    /**
     * 创建并配置 PHPMailer 实例
     *
     * @return PHPMailer
     * @throws Exception
     */
    public static function configure()
    {
        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';

        // SMTP 配置
        $mail->isSMTP();
        $mail->Host       = option('website.smtp_host', '');
        $mail->Port       = intval(option('website.smtp_port', 587));
        $mail->SMTPAuth   = true;
        $mail->Username   = option('website.smtp_user', '');
        $mail->Password   = option('website.smtp_pass', '');

        $encryption = option('website.smtp_encryption', 'tls');
        if (!empty($encryption) && $encryption !== 'none') {
            $mail->SMTPSecure = $encryption;
        } else {
            // PHPMailer 7 中 SMTPSecure 为 string，'' 表示不使用加密（等价于旧的 false）
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        }

        // 发件人
        $from     = option('website.smtp_from', '');
        $fromName = option('website.smtp_from_name', '');
        if ($from) {
            $mail->setFrom($from, $fromName);
        }

        return $mail;
    }

    /**
     * 发送邮件
     *
     * @param string|array $to       收件人地址，或 [地址 => 名称] 数组
     * @param string       $subject  邮件主题
     * @param string       $body     邮件正文
     * @param bool         $isHtml   是否为 HTML 格式
     * @return bool
     * @throws Exception
     */
    public static function send($to, $subject, $body, $isHtml = true)
    {
        $mail = self::configure();

        if (is_array($to)) {
            foreach ($to as $address => $name) {
                if (is_int($address)) {
                    $mail->addAddress($name);
                } else {
                    $mail->addAddress($address, $name);
                }
            }
        } else {
            $mail->addAddress($to);
        }

        $mail->Subject = $subject;

        if ($isHtml) {
            $mail->isHTML(true);
            $mail->Body    = $body;
            $mail->AltBody = strip_tags($body);
        } else {
            $mail->Body = $body;
        }

        return $mail->send();
    }
}