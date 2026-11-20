<?php

namespace zapcms\services;

use zapcms\services\message\Sms\SmsChannel;
use zapcms\services\queue\Queue;
use zapcms\services\queue\jobs\SendSmsJob;

/**
 * 短信门面。
 *
 * 内部走 SmsChannel，支持腾讯云 / 阿里云，并可配置 failover（见
 * app/zapcms/services/message/Sms/）。短信均为「模板 + 签名」模式。
 *
 * 用法：
 *   Sms::send('13800138000', 'SMS_123456', ['code' => '1234']);
 *   Sms::queue(['13800138000','13900139000'], 'SMS_123456', ['code' => '1234']); // 入队异步
 */
class Sms
{
    /**
     * 同步发送短信。
     *
     * @param string|array $phone   单个手机号或手机号数组
     * @param string       $template 模板 ID / Code
     * @param array        $params   模板参数（腾讯云按值顺序，阿里云为关联数组）
     * @param array        $options  可选：['sign' => '自定义签名']
     * @return bool
     */
    public static function send($phone, string $template, array $params = [], array $options = []): bool
    {
        $channel = new SmsChannel();
        $message = array_merge([
            'phone'     => $phone,
            'template'  => $template,
            'params'    => $params,
        ], $options);

        $result = $channel->send($message);

        return $result->success;
    }

    /**
     * 异步入队发送（由队列 worker 执行）。
     *
     * @return mixed 入队任务 id
     */
    public static function queue($phone, string $template, array $params = [], array $options = [])
    {
        return Queue::push(new SendSmsJob([
            'phone'     => $phone,
            'template'  => $template,
            'params'    => $params,
            'options'   => $options,
        ]));
    }
}
