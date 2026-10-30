<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>安装向导 — ZAP CMS</title>
    <link rel="icon" href="../assets/admin/img/zap_logo_green.svg" type="image/svg+xml">
    <link rel="stylesheet" href="../assets/admin/css/bootstrap.css">
    <script src="../assets/jquery/jquery-3.7.1.min.js"></script>
    <style>
        :root {
            --zap-green: #198754;
            --zap-green-light: #d1e7dd;
        }
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            min-height: 100vh;
        }
        .install-header {
            padding: 2rem 0 1rem;
        }
        .install-header img {
            filter: drop-shadow(0 2px 4px rgba(0,0,0,.08));
        }
        /* ── 步骤条 ── */
        .step-indicator {
            display: flex;
            justify-content: center;
            gap: 0;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        .step-item {
            display: flex;
            align-items: center;
            padding: .5rem 1rem;
            font-size: .875rem;
            color: #6c757d;
            position: relative;
            transition: color .2s;
        }
        .step-item::after {
            content: '';
            display: inline-block;
            width: 32px;
            height: 1px;
            background: #dee2e6;
            margin-left: 1rem;
            transition: background .3s;
        }
        .step-item:last-child::after {
            display: none;
        }
        .step-num {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #dee2e6;
            color: #6c757d;
            font-weight: 700;
            font-size: .8rem;
            margin-right: .5rem;
            transition: all .3s;
        }
        .step-item.active { color: var(--zap-green); font-weight: 600; }
        .step-item.active .step-num { background: var(--zap-green); color: #fff; }
        .step-item.done { color: #198754; }
        .step-item.done .step-num { background: #198754; color: #fff; }
        .step-item.done::after { background: #198754; }
        /* ── 卡片 ── */
        .install-card {
            border: none;
            border-radius: .75rem;
            box-shadow: 0 4px 24px rgba(0,0,0,.06);
            overflow: hidden;
        }
        .install-card .card-header {
            background: #fff;
            border-bottom: 1px solid rgba(0,0,0,.05);
            font-weight: 600;
            padding: 1rem 1.25rem;
        }
        .install-card .card-footer {
            background: #f8f9fa;
            border-top: 1px solid rgba(0,0,0,.05);
            padding: 1rem 1.25rem;
        }
        /* ── 环境检测 ── */
        .check-table td:first-child { font-weight: 500; }
        .check-pass { color: #198754; font-size: 1.25rem; }
        .check-fail { color: #dc3545; font-size: 1.25rem; }
        /* ── 安装控制台 ── */
        .install-console {
            background: #1e1e1e;
            color: #d4d4d4;
            border-radius: .5rem;
            padding: .75rem 1rem;
            font-family: 'SF Mono', 'Fira Code', 'Consolas', monospace;
            font-size: .8125rem;
            max-height: 240px;
            overflow-y: auto;
            line-height: 1.7;
        }
        .console-line { padding: 1px 0; }
        .console-line .time { color: #569cd6; margin-right: .5rem; }
        .console-line.ok { color: #4ec9b0; }
        .console-line.err { color: #f44747; }
        .console-line.info { color: #dcdcaa; }
        .console-line.end { color: #ce9178; font-weight: bold; }
        /* ── 完成页 ── */
        .done-info {
            background: var(--zap-green-light);
            border: 1px solid #badbcc;
            border-radius: .5rem;
            padding: 1.25rem;
        }
        .done-info dt { font-size: .8rem; color: #6c757d; margin-bottom: .15rem; }
        .done-info dd { font-weight: 600; margin-bottom: .75rem; }
        .done-info dd:last-child { margin-bottom: 0; }
        /* ── footer ── */
        .install-footer { color: #adb5bd; font-size: .8rem; }
    </style>
</head>
<body>
<div class="container" style="max-width: 720px;">

    <!-- 头部 -->
    <div class="install-header text-center">
        <img src="../assets/admin/img/zap_logo_green.svg" alt="ZAP CMS" width="120" class="mb-3">
        <h5 class="fw-bold text-secondary">安装向导</h5>
    </div>

    <!-- 步骤指示器 -->
    <?php if (isset($step)): ?>
    <div class="step-indicator">
        <?php
        $steps = [
            1 => '使用协议',
            2 => '环境检测',
            3 => '数据库配置',
            4 => '安装完成',
        ];
        foreach ($steps as $i => $label):
            $cls = '';
            if ($i < $step) $cls = 'done';
            elseif ($i === $step) $cls = 'active';
        ?>
            <div class="step-item <?= $cls ?>">
                <span class="step-num"><?= $i < $step ? '✓' : $i ?></span>
                <?= $label ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- 主体内容 -->
    <main>
        <?php echo $this->block('content'); ?>
    </main>

    <!-- 页脚 -->
    <footer class="install-footer text-center my-4">
        &copy; <?= date('Y') ?> ZAP.CN &nbsp;|&nbsp; ZAP CMS v1.0.2
    </footer>
</div>

<script src="../assets/admin/js/bootstrap.bundle.js"></script>
</body>
</html>
