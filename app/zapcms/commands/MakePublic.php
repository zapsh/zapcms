<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS
 *
 * 该命令用于搭建「public/index.php」部署模式所需的目录与软链接。
 *
 *   php console zapcms:MakePublic                 # 创建 public/ + 入口 + 资源软链接
 *   php console zapcms:MakePublic --htaccess      # 额外生成 public/.htaccess（Apache public 模式）
 *   php console zapcms:MakePublic -n              # 只打印将要执行的操作，不实际创建
 *   php console zapcms:MakePublic --force         # 覆盖已存在的 public/index.php 与 .htaccess
 *
 * 注意：切换为 public 模式时，还需把 app/bootstrap.php 中的
 *   require "vendor/autoload.php";
 * 改为
 *   require __DIR__ . '/../vendor/autoload.php';
 * 并把 Web 服务器 docroot 指向 public/（同时不再暴露根目录的 index.php）。
 */

namespace zapcms\commands;

use zap\console\Command;
use zap\console\Input;
use zap\console\Output;

class MakePublic extends Command
{
    protected string $description = '创建 public/ 目录与资源软链接，用于 public/index.php 部署模式';

    /** 默认需要软链到 public/ 的资源目录（均为项目根下、需对外提供访问的目录） */
    protected array $defaultLinks = ['themes', 'storage', 'assets', 'mods'];

    public function configure(): void
    {
        $this->setName('zapcms:MakePublic')
             ->setDescription($this->description);

        $this->addOption('dry-run',  'n', '只打印将要执行的操作，不实际创建');
        $this->addOption('force',    'f', '覆盖已存在的 public/index.php 与 .htaccess');
        $this->addOption('htaccess', '',  '同时生成 public/.htaccess（Apache public 模式所需）');
        $this->addOption('no-links', '',  '跳过资源软链接，仅创建 public/ 与入口 index.php');
        $this->addOption('links',    '',  '自定义要软链的资源目录，逗号分隔，如 --links=themes,storage');
    }

    public function execute(Input $input, Output $output): int
    {
        $dryRun  = $input->hasParam('dry-run') || $input->hasParam('n');
        $force   = $input->hasParam('force')   || $input->hasParam('f');
        $htaccess = $input->hasParam('htaccess');
        $noLinks = $input->hasParam('no-links');

        $links = $this->defaultLinks;
        $custom = $input->getParam('links');
        if (is_string($custom) && $custom !== '') {
            $links = array_filter(array_map('trim', explode(',', $custom)), static fn($v) => $v !== '');
        }

        $baseDir    = rtrim(APP_ROOT, '/\\');
        $publicDir  = $baseDir . DIRECTORY_SEPARATOR . 'public';

        $output->writeln('');
        $output->writeln('  <info>ZapCMS · 初始化 public/ 部署目录</info>');
        $output->writeln("  <comment>项目根:</comment> {$baseDir}");
        $output->writeln("  <comment>目标目录:</comment> {$publicDir}");
        if ($dryRun) {
            $output->writeln('  <comment>模式:</comment> <warning>dry-run（仅打印，不写入）</warning>');
        }
        $output->writeln('');

        // 1) 创建 public/ 目录
        if (!is_dir($publicDir)) {
            if ($dryRun) {
                $output->writeln("<info>[dry-run]</info> 将创建目录 public/");
            } else {
                if (!mkdir($publicDir, 0755, true) && !is_dir($publicDir)) {
                    $output->error("无法创建目录: {$publicDir}");
                    return self::FAILURE;
                }
                $output->success("已创建目录 public/");
            }
        } else {
            $output->writelnV("<comment>已存在，跳过:</comment> public/");
        }

        // 2) 入口 public/index.php
        $indexPath    = $publicDir . DIRECTORY_SEPARATOR . 'index.php';
        $indexContent = $this->entryStub();
        if (file_exists($indexPath) && !$force) {
            $output->writelnV("<comment>已存在，跳过:</comment> public/index.php（使用 --force 覆盖）");
        } else {
            if ($dryRun) {
                $output->writeln("<info>[dry-run]</info> 将创建 public/index.php");
            } else {
                file_put_contents($indexPath, $indexContent);
                $output->success("已创建 public/index.php");
            }
        }

        // 3) 资源软链接
        if (!$noLinks) {
            $output->writeln('');
            $output->writeln('  <comment>资源软链接:</comment>');
            foreach ($links as $name) {
                $this->makeLink($publicDir, $name, $dryRun, $output);
            }
        }

        // 4) 可选：public/.htaccess
        if ($htaccess) {
            $htPath    = $publicDir . DIRECTORY_SEPARATOR . '.htaccess';
            $htContent = $this->htaccessStub();
            if (file_exists($htPath) && !$force) {
                $output->writelnV("<comment>已存在，跳过:</comment> public/.htaccess（使用 --force 覆盖）");
            } else {
                if ($dryRun) {
                    $output->writeln("<info>[dry-run]</info> 将创建 public/.htaccess");
                } else {
                    file_put_contents($htPath, $htContent);
                    $output->success("已创建 public/.htaccess");
                }
            }
        }

        $output->writeln('');
        if ($dryRun) {
            $output->writeln('  <warning>以上为 dry-run 预览，未做任何改动。去掉 -n 参数重新执行即可生效。</warning>');
        } else {
            $output->writeln('  <success>完成。</success> 下一步：把 Web 服务器 docroot 指向 public/，');
            $output->writeln('  并将 app/bootstrap.php 的 autoload require 改为基于 __DIR__ 的写法。');
        }
        $output->writeln('');

        return self::SUCCESS;
    }

    /**
     * 创建目录软链接（相对路径）。仅使用 symlink()，不依赖 exec 等常被禁用的函数；
     * 若创建失败，提示用户手动创建软链接或将目录移动到 public/ 下。
     */
    private function makeLink(string $publicDir, string $name, bool $dryRun, Output $output): void
    {
        $link      = $publicDir . DIRECTORY_SEPARATOR . $name;
        $targetAbs = rtrim(APP_ROOT, '/\\') . DIRECTORY_SEPARATOR . $name;
        $targetRel = '..' . DIRECTORY_SEPARATOR . $name;

        if (!is_dir($targetAbs) && !is_link($targetAbs)) {
            // storage 等运行时目录若不存在则先建立，其余源目录缺失则跳过
            if ($name === 'storage') {
                if (!$dryRun) {
                    mkdir($targetAbs, 0755, true);
                }
                $output->writelnV("<comment>创建源目录:</comment> " . APP_ROOT . "/{$name}");
            } else {
                $output->warning("跳过 {$name}：源目录不存在（" . APP_ROOT . "/{$name}）");
                return;
            }
        }

        if (is_link($link) || file_exists($link)) {
            $output->writelnV("<comment>已存在，跳过:</comment> public/{$name}");
            return;
        }

        if ($dryRun) {
            $output->writeln("<info>[dry-run]</info> 将创建软链接 public/{$name} -> {$targetRel}");
            return;
        }

        if (@symlink($targetRel, $link)) {
            $output->success("软链接已创建: public/{$name} -> {$targetRel}");
            return;
        }

        // symlink 失败（常见于 Windows 缺少创建符号链接权限，或运行环境禁用了相关能力）
        $manual = PHP_OS_FAMILY === 'Windows'
            ? "mklink /D \"{$link}\" \"{$targetAbs}\""
            : "ln -s \"{$targetAbs}\" \"{$link}\"";
        $output->warning("无法自动创建软链接 public/{$name}，请手动处理以下任一方式：");
        $output->writeln("  1) 以管理员/开发者模式运行命令创建软链接： {$manual}");
        $output->writeln("  2) 将目录 " . APP_ROOT . "/{$name} 直接移动到 public/{$name} 下");
    }

    private function entryStub(): string
    {
        return <<<'PHP'
<?php
/**
 * ZapCMS public 模式入口
 *
 * Web 服务器 docroot 指向本目录（public/），由本文件引导应用。
 * 切换为 public 模式前，请将 app/bootstrap.php 中的
 *   require "vendor/autoload.php";
 * 改为
 *   require __DIR__ . '/../vendor/autoload.php';
 */

require __DIR__ . '/../app/bootstrap.php';

PHP;
    }

    private function htaccessStub(): string
    {
        return <<<'HTACCESS'
Options +FollowSymLinks -Indexes
RewriteEngine On

# public 模式下，禁止上传/模块目录执行 PHP（这些目录通过软链接暴露到 Web 根）
RewriteCond %{REQUEST_URI} ^/(storage|mods)/.*\.(php|phtml|phar)$ [NC]
RewriteRule ^ - [F]

# 透传 Authorization 头
RewriteCond %{HTTP:Authorization} .
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

# 非真实文件/目录一律交由入口处理
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [QSA,PT,L]

HTACCESS;
    }
}
