<?php

namespace zapcms\services\message\Mail\Providers;

use PHPMailer\PHPMailer\PHPMailer;
use zapcms\services\message\Contracts\ProviderInterface;
use zapcms\services\message\Support\Result;
use zapcms\services\storage\Secret;

/**
 * SMTP 邮件（保留原有 PHPMailer 实现，作为默认服务商）。
 */
class SmtpProvider implements ProviderInterface
{
    /**
     * 构建并配置 PHPMailer 实例（供 Mailer::configure() 复用，单一来源）。
     */
    public static function buildMailer(): PHPMailer
    {
        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';

        $mail->isSMTP();
        $mail->Host       = option('website.smtp_host', '');
        $mail->Port       = (int) option('website.smtp_port', 587);
        $mail->SMTPAuth   = true;
        $mail->Username   = option('website.smtp_user', '');
        $mail->Password   = (string) (Secret::get('mail.smtp_pass', '') ?: option('website.smtp_pass', ''));

        $encryption = option('website.smtp_encryption', 'tls');
        if (!empty($encryption) && $encryption !== 'none') {
            $mail->SMTPSecure = $encryption;
        } else {
            $mail->SMTPSecure  = '';
            $mail->SMTPAutoTLS = false;
        }

        $from     = option('website.smtp_from', '');
        $fromName = option('website.smtp_from_name', '');
        if ($from) {
            $mail->setFrom($from, $fromName);
        }

        return $mail;
    }

    public function getName(): string
    {
        return 'smtp';
    }

    public function send(array $message): Result
    {
        try {
            $mail = self::buildMailer();

            $from     = $message['from'] ?? option('website.smtp_from', '');
            $fromName = $message['fromName'] ?? option('website.smtp_from_name', '');
            if ($from && $mail->From === '') {
                $mail->setFrom($from, $fromName);
            }

            $to = $message['to'];
            if (is_array($to)) {
                foreach ($to as $address => $name) {
                    is_int($address) ? $mail->addAddress($name) : $mail->addAddress($address, $name);
                }
            } else {
                $mail->addAddress($to);
            }

            $mail->Subject = (string) ($message['subject'] ?? '');
            if (!empty($message['isHtml'])) {
                $mail->isHTML(true);
                $mail->Body    = (string) ($message['body'] ?? '');
                $mail->AltBody = strip_tags($message['body'] ?? '');
            } else {
                $mail->Body = (string) ($message['body'] ?? '');
            }

            foreach (($message['attachments'] ?? []) as $att) {
                if (is_array($att)) {
                    $mail->addAttachment($att['path'], $att['name'] ?? '');
                } else {
                    $mail->addAttachment($att);
                }
            }

            $mail->send();

            return Result::ok('smtp', 'sent');
        } catch (\Throwable $e) {
            return Result::fail('smtp', $e->getMessage());
        }
    }
}
