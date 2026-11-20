<?php $this->layout('layout'); ?>

<div class="install-card card">
    <div class="card-header">
        <span class="check-pass me-2">&#10003;</span> 安装完成
    </div>
    <div class="card-body text-center">
        <div class="mb-3">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#198754" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
        </div>
        <h5 class="fw-bold text-success mb-3">ZAP CMS 安装成功！</h5>

        <div class="done-info text-start mx-auto" style="max-width: 360px;">
            <dl class="mb-0">
                <dt>后台地址</dt>
                <dd>
                    <a href="../<?= htmlspecialchars(Z_ADMIN_PREFIX) ?>" target="_blank">
                        <?= htmlspecialchars($adminUrl) ?>
                    </a>
                </dd>
                <dt>用户名</dt>
                <dd><code><?= htmlspecialchars($username) ?></code></dd>
            </dl>
        </div>

        <div class="alert alert-warning mt-3 mb-0 text-start small" role="alert">
            <strong>&#9888; 安全提示：</strong>
            建议通过 FTP 或服务器管理面板删除 <code>/install/</code> 目录，防止被他人利用重复安装。
        </div>
    </div>
    <div class="card-footer d-flex justify-content-center gap-2">
        <a href="../<?= htmlspecialchars(Z_ADMIN_PREFIX) ?>" target="_blank" class="btn btn-success px-4">
            进入后台
        </a>
        <a href="../" target="_blank" class="btn btn-outline-secondary px-4">
            访问网站首页
        </a>
    </div>
</div>
