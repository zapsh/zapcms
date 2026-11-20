<?php

namespace zapcms\commands;

use zap\console\Command;
use zap\console\Input;
use zap\console\Output;
use zap\DB;
use zapcms\services\ApiAuth;

/**
 * 为后台管理员签发 API Token（Bearer）。
 *
 * Usage:
 *   php console zapcms:IssueApiToken -u admin -t 86400          # 24 小时有效
 *   php console zapcms:IssueApiToken -i 1 -t 0                  # 永不过期
 *   php console zapcms:IssueApiToken -u admin -s node:read      # 指定 scope（可重复 -s）
 *   php console zapcms:IssueApiToken -u admin -r               # 吊销该用户全部 Token
 */
class IssueApiToken extends Command
{
    public function configure(): void
    {
        $this->setName('zapcms:IssueApiToken')
             ->setDescription('为后台管理员签发 / 吊销 API Bearer Token');
    }

    public function execute(Input $input, Output $output): int
    {
        $uid   = $this->input->getParam('i');
        $uname = $this->input->getParam('u');
        $ttl   = (int) ($this->input->getParam('t') ?? 0);
        $revoke = $this->input->hasParam('r');

        if (empty($uid) && empty($uname)) {
            $this->out->writeln('<error>请指定用户：-u 用户名 或 -i 用户ID</error>');
            $this->out->writeln('示例: php console zapcms:IssueApiToken -u admin -t 86400');
            return self::FAILURE;
        }

        $user = $uid
            ? DB::table('admin')->where('id', (int) $uid)->first()
            : DB::table('admin')->where('username', $uname)->first();

        if (empty($user)) {
            $this->out->writeln('<error>用户不存在</error>');
            return self::FAILURE;
        }

        $adminId = (int) $user['id'];

        if ($revoke) {
            $n = ApiAuth::revoke($adminId);
            $this->out->writeln("已吊销用户 [{$user['username']}] 的 {$n} 个 Token");
            return self::SUCCESS;
        }

        $scopes = [];
        if ($this->input->hasParam('s')) {
            $scopes = (array) $this->input->getParam('s');
        }

        $token = ApiAuth::issue($adminId, $scopes, $ttl);
        if ($token === null) {
            $this->out->writeln('<error>签发失败（api_tokens 表可能未创建，请先执行 php console zapcms:Migrate）</error>');
            return self::FAILURE;
        }

        $exp = $ttl > 0 ? date('Y-m-d H:i:s', time() + $ttl) : '永不过期';
        $this->out->writeln("已为用户 [{$user['username']}] 签发 Token（过期：{$exp}）");
        $this->out->writeln('');
        $this->out->writeln("<info>{$token}</info>");
        $this->out->writeln('');
        $this->out->writeln('调用示例：');
        $host = $_SERVER['HTTP_HOST'] ?? 'yoursite.com';
        $this->out->writeln("  curl -H 'Authorization: Bearer {$token}' https://{$host}/api/admin/node/list?node_type=article");
        return self::SUCCESS;
    }
}
