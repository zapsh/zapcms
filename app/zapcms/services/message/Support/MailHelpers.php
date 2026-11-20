<?php

namespace zapcms\services\message\Support;

/**
 * 邮件收件人归一化工具。
 */
class MailHelpers
{
    /**
     * 将 $to（字符串或 [地址=>名称] 数组）归一化为 [['email'=>, 'name'=>], ...]。
     */
    public static function recipients($to): array
    {
        $list = [];

        if (is_array($to)) {
            foreach ($to as $address => $name) {
                if (is_int($address)) {
                    $list[] = ['email' => (string) $name, 'name' => ''];
                } else {
                    $list[] = ['email' => (string) $address, 'name' => (string) $name];
                }
            }
        } else {
            $list[] = ['email' => (string) $to, 'name' => ''];
        }

        return $list;
    }

    /**
     * 提取纯邮箱数组。
     */
    public static function emails($to): array
    {
        return array_map(static fn($r) => $r['email'], self::recipients($to));
    }

    /**
     * 逗号拼接的邮箱串（Mailgun / 阿里云用）。
     */
    public static function emailsCsv($to): string
    {
        return implode(',', self::emails($to));
    }
}
