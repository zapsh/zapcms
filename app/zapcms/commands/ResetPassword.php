<?php
/*
 * Copyright (c) 2024.  ZAP.CN  - ZAP CMS
 */

namespace zapcms\commands;

use zap\console\Command;
use zap\console\Input;
use zap\console\Output;
use zap\DB;
use zap\util\Password;

/**
 * 重置后台管理员密码
 *
 * Usage:
 *   php console zap:ResetPassword -u admin -p 123456        # 按用户名重置
 *   php console zap:ResetPassword -i 1 -p 123456            # 按用户ID重置
 *   php console zap:ResetPassword -u admin                  # 自动生成随机密码
 *   php console zap:ResetPassword -l                        # 列出所有管理员
 */
class ResetPassword extends Command
{
    function execute(Input $input, Output $output): int
    {
        $list   = $this->input->hasParam('l');
        $uid    = $this->input->getParam('i');
        $uname  = $this->input->getParam('u');
        $passwd = $this->input->getParam('p');

        // 列出用户
        if ($list) {
            return $this->listUsers();
        }

        // 验证参数
        if (empty($uid) && empty($uname)) {
            $this->out->writeln('<error>请指定用户：-u 用户名 或 -i 用户ID</error>');
            $this->out->writeln('');
            $this->out->writeln('示例:');
            $this->out->writeln('  php console zap:ResetPassword -u admin -p 123456');
            $this->out->writeln('  php console zap:ResetPassword -l              # 查看所有用户');
            return self::FAILURE;
        }

        // 查找用户
        if ($uid) {
            $user = DB::table('admin')->where('id', (int)$uid)->first();
        } else {
            $user = DB::table('admin')->where('username', $uname)->first();
        }

        if (empty($user)) {
            $target = $uid ? "ID={$uid}" : "username={$uname}";
            $this->out->writeln("<error>未找到用户: {$target}</error>");
            return self::FAILURE;
        }

        // 自动生成密码
        if (empty($passwd)) {
            $passwd = $this->generatePassword(12);
        }

        // 更新密码
        $hashed = Password::hash($passwd);
        $rows = DB::update('admin', [
            'password'   => $hashed,
            'updated_at' => time(),
        ], ['id' => $user['id']]);

        if ($rows === 0) {
            $this->out->writeln('<error>密码更新失败，请检查数据库</error>');
            return self::FAILURE;
        }

        $this->out->writeln('');
        $this->out->writeln('<info>密码重置成功！</info>');
        $this->out->writeln('');
        $this->out->writeln(sprintf('  用户名:  <comment>%s</comment>', $user['username']));
        $this->out->writeln(sprintf('  新密码:  <comment>%s</comment>', $passwd));
        $this->out->writeln('');

        return self::SUCCESS;
    }

    /**
     * 列出所有管理员
     */
    private function listUsers(): int
    {
        $users = DB::table('admin')->select('id, username, full_name, email, status, last_access_time')->get();

        if (empty($users)) {
            $this->out->writeln('暂无管理员账号');
            return self::SUCCESS;
        }

        $this->out->writeln('');
        $this->out->writeln(sprintf('  %-4s %-16s %-12s %-24s %-10s %s',
            'ID', '用户名', '姓名', '邮箱', '状态', '最后登录'));
        $this->out->writeln('  ' . str_repeat('-', 76));

        foreach ($users as $user) {
            $lastLogin = $user['last_access_time']
                ? date('Y-m-d H:i', $user['last_access_time'])
                : '-';
            $this->out->writeln(sprintf('  %-4s %-16s %-12s %-24s %-10s %s',
                $user['id'],
                $user['username'],
                $user['full_name'] ?? '-',
                $user['email'] ?? '-',
                $user['status'],
                $lastLogin
            ));
        }

        $this->out->writeln('');
        $this->out->writeln(sprintf('  <comment>共 %d 个用户</comment>', count($users)));
        $this->out->writeln('');
        return self::SUCCESS;
    }

    /**
     * 生成随机密码
     */
    private function generatePassword(int $length = 12): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
        $password = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $max)];
        }
        return $password;
    }

    public function help(): int
    {
        $this->out->writeln('重置后台管理员密码');
        $this->out->writeln('');
        $this->out->writeln('Usage:');
        $this->out->writeln('  php console zap:ResetPassword -u <username> -p <password>');
        $this->out->writeln('  php console zap:ResetPassword -u <username>             # 自动生成密码');
        $this->out->writeln('  php console zap:ResetPassword -i <id> -p <password>');
        $this->out->writeln('  php console zap:ResetPassword -l                        # 列出所有用户');
        $this->out->writeln('');
        $this->out->writeln('Options:');
        $this->out->writeln('  -u   用户名');
        $this->out->writeln('  -i   用户ID');
        $this->out->writeln('  -p   新密码（不指定则自动生成 12 位随机密码）');
        $this->out->writeln('  -l   列出所有管理员');
        return self::SUCCESS;
    }
}
