<?php

namespace zapcms\services\queue\jobs;

use zapcms\services\queue\Job;
use zapcms\services\Sms;

/**
 * 异步发送短信任务。
 */
class SendSmsJob extends Job
{
    public function handle(): void
    {
        $data = $this->data;
        Sms::send(
            $data['phone']   ?? '',
            $data['template'] ?? '',
            $data['params']  ?? [],
            $data['options'] ?? []
        );
    }
}
