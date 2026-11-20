<?php

namespace zapcms\services\message\Sms;

use zapcms\services\message\Contracts\ChannelInterface;
use zapcms\services\message\Sms\Providers\AliyunSmsProvider;
use zapcms\services\message\Sms\Providers\TencentSmsProvider;
use zapcms\services\message\Support\Result;

/**
 * 短信通道：按配置顺序尝试服务商，前一个失败自动切到下一个（failover）。
 *
 * 配置（option / Secret）：
 *  - sms.default   主服务商（默认 tencent）；可选 tencent / aliyun
 *  - sms.failover  逗号分隔的备用服务商
 * 各服务商凭据见对应 Provider（如 sms.tencent_secret_id、sms.aliyun_access_key …）。
 */
class SmsChannel implements ChannelInterface
{
    protected array $map = [
        'tencent' => TencentSmsProvider::class,
        'aliyun'  => AliyunSmsProvider::class,
    ];

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

        return $last ?? Result::fail('sms', '未配置任何短信服务商');
    }

    protected function order(): array
    {
        $default  = trim((string) option('sms.default', 'tencent'));
        $failover = array_filter(array_map('trim', explode(',', (string) option('sms.failover', ''))));

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
            $order = ['tencent'];
        }

        return $order;
    }
}
