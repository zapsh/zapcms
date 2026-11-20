<?php

namespace zapcms\services\queue\jobs;

use zapcms\services\Mailer;
use zapcms\services\queue\Job;

/**
 * 示例任务：异步发送邮件。
 *
 * 仅作为队列框架的参考示例；如何使用：
 *   use zapcms\services\queue\Queue;
 *   use zapcms\services\queue\jobs\SendMailJob;
 *   Queue::push(new SendMailJob(['to' => 'a@b.com', 'subject' => '标题', 'body' => '正文']));
 *
 * 注意：本示例未改动现有同步 Mailer::send() 调用点；是否将业务邮件改为入队，
 *       属于「接入具体业务任务」阶段，留待后续按需替换。
 */
class SendMailJob extends Job
{
    public function handle(): void
    {
        $to = $this->data['to'] ?? null;
        if (empty($to)) {
            return;
        }

        Mailer::send(
            $to,
            (string) ($this->data['subject'] ?? ''),
            (string) ($this->data['body'] ?? ''),
            (bool) ($this->data['isHtml'] ?? true)
        );
    }
}
