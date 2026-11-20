<?php

namespace zapcms\services\message\Contracts;

use zapcms\services\message\Support\Result;

/**
 * 通道（邮件 / 短信）契约：对外暴露统一的 send()。
 */
interface ChannelInterface
{
    public function send(array $message): Result;
}
