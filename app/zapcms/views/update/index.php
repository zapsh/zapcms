<?php

use zap\facades\Url;

!IS_AJAX && $this->layout('layouts/common');

$this->view->page_title = '系统更新';
$this->view->page_subtitle = '检查更新 & 一键升级 & 更新历史';
$this->view->breadcrumbs = [
    ['title' => '系统', 'url' => '#'],
    ['title' => '系统更新'],
];
?>

<!--begin::Status Cards-->
<div class="row">
    <!-- 当前版本 -->
    <div class="col-lg-4 col-sm-6">
        <div class="position-relative rounded shadow-sm p-3 mb-3 text-white overflow-hidden" style="background-color:<?php echo ($update_info['has_update'] ?? false) ? '#ffc107' : '#28a745'; ?>;min-height:110px;">
            <div class="pe-5">
                <h4 class="fw-bold mb-1">v<?php echo htmlspecialchars(ZAP_CMS_VERSION); ?></h4>
                <p class="mb-2 opacity-75 small">
                    <?php if ($update_info && $update_info['has_update']): ?>
                        新版本 v<?php echo htmlspecialchars($update_info['latest_version']); ?>
                        <?php if ($update_info['is_critical'] ?? false): ?>
                            <span class="badge bg-danger ms-1">安全更新</span>
                        <?php endif; ?>
                    <?php elseif ($update_info): ?>
                        已是最新版本
                    <?php else: ?>
                        检查更新失败
                    <?php endif; ?>
                </p>
            </div>
            <i class="fa-solid fa-code-branch position-absolute opacity-25" style="font-size:4rem;right:15px;top:10px;"></i>
            <?php if (($update_info['has_update'] ?? false) && ($update_info['download_url'] ?? false) && ($update_info['php_ok'] ?? true)): ?>
            <a href="#" class="stretched-link rounded-bottom d-block text-center text-decoration-none small py-1 text-black" onclick="showUpdateConfirm();return false;"
               style="background:rgba(0,0,0,.1);margin:0 -1rem -1rem -1rem;">
                立即更新 <i class="fa-solid fa-arrow-right ms-1"></i>
            </a>
            <?php else: ?>
            <div class="rounded-bottom d-block text-center small py-1 text-white-50" onclick="checkUpdate();"
                 style="background:rgba(0,0,0,.1);margin:0 -1rem -1rem -1rem;cursor:pointer;">
                <i class="fa-solid fa-arrows-rotate me-1"></i>重新检查
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 文件权限 -->
    <div class="col-lg-4 col-sm-6">
        <div class="position-relative rounded shadow-sm p-3 mb-3 text-white overflow-hidden" style="background-color:<?php echo $file_permissions_ok ? '#20c997' : '#dc3545'; ?>;min-height:110px;">
            <div class="pe-5">
                <h4 class="fw-bold mb-2"><?php echo $file_permissions_ok ? '可写' : '不可写'; ?></h4>
                <p class="mb-0 opacity-75 small">文件系统权限</p>
            </div>
            <i class="fa-solid fa-folder-tree position-absolute opacity-25" style="font-size:4rem;right:15px;top:10px;"></i>
        </div>
    </div>

    <!-- 磁盘空间 -->
    <div class="col-lg-4 col-sm-6">
        <div class="position-relative rounded shadow-sm p-3 mb-3 text-white overflow-hidden" style="background-color:<?php echo $disk_space_ok ? '#17a2b8' : '#dc3545'; ?>;min-height:110px;">
            <div class="pe-5">
                <h4 class="fw-bold mb-2"><?php echo $disk_space_ok ? '充足' : '不足'; ?></h4>
                <p class="mb-0 opacity-75 small">磁盘空间</p>
            </div>
            <i class="fa-solid fa-hard-drive position-absolute opacity-25" style="font-size:4rem;right:15px;top:10px;"></i>
        </div>
    </div>
</div>
<!--end::Status Cards-->

<!-- 更新不可用提示 -->
<?php if ($update_info && !$update_info['has_update']): ?>
<div class="alert border-start border-4 border-success shadow-sm mb-4 bg-white">
    <h6 class="mb-1"><i class="fa-solid fa-circle-check text-success me-2"></i>系统已是最新版本</h6>
    <p class="mb-0 text-muted small">当前版本 v<?php echo htmlspecialchars(ZAP_CMS_VERSION); ?> 已经是最新版本，请保持关注后续更新。</p>
</div>
<?php elseif (!$update_info && empty($update_info)): ?>
<div class="alert border-start border-4 border-warning shadow-sm mb-4 bg-white">
    <h6 class="mb-1"><i class="fa-solid fa-triangle-exclamation text-warning me-2"></i>无法获取更新信息</h6>
    <p class="mb-0 text-muted small">可能原因：更新镜像（latest.json）无法访问，或当前网络环境受限。您可以尝试 <a href="#" onclick="checkUpdate();return false;" class="text-decoration-underline">重新检查</a>，或使用手动上传更新包。</p>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">

        <!-- 在线更新 -->
        <?php if ($update_info && $update_info['has_update']): ?>
        <div class="card shadow-sm mb-4" style="border-top:3px solid #ffc107;">
            <div class="card-header bg-transparent d-flex align-items-center">
                <h5 class="mb-0">
                    <i class="fa-solid fa-cloud-download-alt text-warning me-2"></i>在线更新
                </h5>
                <div class="ms-auto">
                    <?php if ($update_info['is_critical'] ?? false): ?>
                    <span class="badge bg-danger me-1">重要</span>
                    <?php endif; ?>
                    <span class="badge bg-warning text-dark">推荐</span>
                </div>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center flex-wrap mb-3">
                    <span class="fw-bold text-muted me-2">更新路径：</span>
                    <code class="bg-light px-2 py-1 rounded me-2">v<?php echo htmlspecialchars($update_info['current_version']); ?></code>
                    <i class="fa-solid fa-arrow-right-long text-success mx-2"></i>
                    <code class="bg-success text-white px-2 py-1 rounded">v<?php echo htmlspecialchars($update_info['latest_version']); ?></code>
                </div>

                <?php if ($update_info['changelog']): ?>
                <div class="mb-3">
                    <h6 class="fw-bold border-bottom pb-1">
                        <i class="fa-solid fa-clipboard-list me-1"></i>更新日志
                    </h6>
                    <div class="border rounded p-3 bg-light" style="max-height:240px;overflow-y:auto;font-size:0.85rem;line-height:1.8;">
                        <?php echo nl2br(htmlspecialchars($update_info['changelog'])); ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($update_info['release_date']): ?>
                <p class="text-muted small mb-2">
                    <i class="fa-regular fa-calendar me-1"></i>发布日期：<?php echo htmlspecialchars($update_info['release_date']); ?>
                </p>
                <?php endif; ?>

                <p class="text-muted small mb-3">
                    <?php if (!empty($update_info['package_size'])): ?>
                    <span class="me-3"><i class="fa-solid fa-file-zipper me-1"></i>更新包：<?php echo number_format($update_info['package_size'] / 1048576, 2); ?> MB</span>
                    <?php endif; ?>
                    <?php if (!empty($update_info['sha256'])): ?>
                    <span class="me-3" title="<?php echo htmlspecialchars($update_info['sha256']); ?>">
                        <i class="fa-solid fa-shield-halved me-1"></i>SHA256：<code><?php echo htmlspecialchars(substr($update_info['sha256'], 0, 12)); ?>…</code>
                    </span>
                    <?php endif; ?>
                    <?php if (!empty($update_info['manifest_url'])): ?>
                    <span title="<?php echo htmlspecialchars($update_info['manifest_url']); ?>">
                        <i class="fa-solid fa-server me-1"></i>更新源：<?php echo htmlspecialchars(parse_url($update_info['manifest_url'], PHP_URL_HOST) ?: '—'); ?>
                    </span>
                    <?php endif; ?>
                </p>

                <?php if (($update_info['php_ok'] ?? true) === false): ?>
                <div class="alert alert-danger py-2 small mb-3">
                    <i class="fa-solid fa-circle-exclamation me-1"></i>
                    当前 PHP <?php echo PHP_VERSION; ?> 低于新版本要求（>= <?php echo htmlspecialchars($update_info['min_php']); ?>），请先升级 PHP 再更新。
                </div>
                <?php endif; ?>

                <button class="btn btn-warning btn-lg w-100" onclick="showUpdateConfirm()" <?php echo ($update_info['php_ok'] ?? true) ? '' : 'disabled'; ?>>
                    <i class="fa-solid fa-rocket me-1"></i>一键更新到 v<?php echo htmlspecialchars($update_info['latest_version']); ?>
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- 手动上传更新 -->
        <div class="card shadow-sm mb-4" style="border-top:3px solid #6c757d;">
            <div class="card-header bg-transparent d-flex align-items-center">
                <h5 class="mb-0">
                    <i class="fa-solid fa-file-zipper text-secondary me-2"></i>手动上传更新包
                </h5>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    <i class="fa-solid fa-circle-info me-1"></i>如果在线更新不可用，请从官网下载更新 ZIP 包后选择文件上传，版本号会自动从包内识别。
                </p>
                <form id="manualUpdateForm" enctype="multipart/form-data" onsubmit="return false;">
                    <div class="row g-3">
                        <div class="col-md-9">
                            <div class="input-group">
                                <label class="input-group-text" for="updateZip"><i class="fa-solid fa-file-zipper"></i></label>
                                <input class="form-control" type="file" id="updateZip" name="update_zip" accept=".zip">
                            </div>
                            <div class="form-text">支持 .zip 格式；包内需包含 app/bootstrap.php（内含 ZAP_CMS_VERSION）</div>
                        </div>
                        <div class="col-md-3">
                            <button type="button" class="btn btn-outline-primary w-100" onclick="manualUpdate()">
                                <i class="fa-solid fa-upload me-1"></i>上传并更新
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- 插件更新 -->
        <div class="card shadow-sm mb-4" style="border-top:3px solid #6610f2;">
            <div class="card-header bg-transparent d-flex align-items-center">
                <h5 class="mb-0">
                    <i class="fa-solid fa-puzzle-piece text-primary me-2"></i>插件更新
                </h5>
                <div class="ms-auto">
                    <button class="btn btn-sm btn-outline-secondary" onclick="checkPluginUpdates()">
                        <i class="fa-solid fa-arrows-rotate me-1"></i>检查插件更新
                    </button>
                </div>
            </div>
            <div class="card-body" id="pluginUpdateWrapper">
                <div id="pluginUpdateArea">
                    <?php if (!empty($plugin_updates)): ?>
                        <div class="alert border-start border-4 border-info shadow-sm mb-3 bg-white py-2">
                            <i class="fa-solid fa-bell text-info me-1"></i>发现 <strong><?php echo count($plugin_updates); ?></strong> 个插件有可用更新
                        </div>
                        <?php foreach ($plugin_updates as $pu): ?>
                        <div class="d-flex align-items-center p-3 border rounded mb-2 bg-white shadow-sm">
                            <div class="flex-shrink-0 me-3">
                                <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" style="width:44px;height:44px;">
                                    <i class="fa-solid fa-puzzle-piece text-primary fa-lg"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <strong class="d-block text-truncate"><?php echo htmlspecialchars($pu['title']); ?></strong>
                                <span class="small">
                                    <code><?php echo htmlspecialchars($pu['current_version']); ?></code>
                                    <i class="fa-solid fa-arrow-right-long text-muted mx-1"></i>
                                    <code class="text-success fw-bold"><?php echo htmlspecialchars($pu['latest_version']); ?></code>
                                </span>
                            </div>
                            <div class="flex-shrink-0 ms-3">
                                <button class="btn btn-sm btn-outline-primary"
                                        onclick="doPluginUpdate('<?php echo htmlspecialchars($pu['name']); ?>', '<?php echo htmlspecialchars($pu['download_url']); ?>', '<?php echo htmlspecialchars($pu['latest_version']); ?>')">
                                    <i class="fa-solid fa-download me-1"></i>更新
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fa-solid fa-cube fa-2x mb-2 d-block opacity-50"></i>
                            <p class="mb-0">点击上方的「检查插件更新」按钮查看可用的插件更新</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

    <div class="col-lg-4">

        <!-- 更新历史 -->
        <div class="card shadow-sm mb-4" style="border-top:3px solid #0d6efd;">
            <div class="card-header bg-transparent">
                <h5 class="mb-0">
                    <i class="fa-solid fa-clock-rotate-left text-primary me-2"></i>更新历史
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($update_history)): ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3" style="width:70px;">目标</th>
                                <th style="width:88px;">版本变更</th>
                                <th style="width:52px;">状态</th>
                                <th class="pe-3" style="width:100px;">时间</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($update_history as $h): ?>
                            <tr>
                                <td class="ps-3">
                                    <?php if ($h['target'] === 'core'): ?>
                                    <span class="badge bg-primary"><i class="fa-solid fa-gear me-1"></i>核心</span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary"><i class="fa-solid fa-puzzle-piece me-1"></i><?php echo htmlspecialchars($h['target']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-1 small">
                                        <code class="text-muted"><?php echo htmlspecialchars($h['from_version']); ?></code>
                                        <i class="fa-solid fa-arrow-right-long text-muted"></i>
                                        <code class="text-success"><?php echo htmlspecialchars($h['to_version']); ?></code>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($h['status'] === 'success'): ?>
                                    <i class="fa-solid fa-circle-check text-success" title="更新成功"></i>
                                    <?php else: ?>
                                    <i class="fa-solid fa-circle-xmark text-danger" title="更新失败"></i>
                                    <?php endif; ?>
                                </td>
                                <td class="pe-3 small text-muted text-nowrap"><?php echo date('m-d H:i', $h['created_at']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <i class="fa-solid fa-inbox fa-2x mb-2 d-block opacity-50"></i>
                    <p class="small mb-0">暂无更新记录</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 系统环境信息 -->
        <div class="card shadow-sm mb-4" style="border-top:3px solid #198754;">
            <div class="card-header bg-transparent d-flex align-items-center" style="cursor:pointer;"
                 data-bs-toggle="collapse" data-bs-target="#sysInfoCollapse" role="button">
                <h5 class="mb-0">
                    <i class="fa-solid fa-circle-info text-success me-2"></i>系统环境
                </h5>
                <i class="fa-solid fa-chevron-down ms-auto small text-muted"></i>
            </div>
            <div id="sysInfoCollapse" class="collapse">
                <div class="card-body p-0">
                    <table class="table table-sm table-striped small mb-0">
                        <tbody>
                            <?php foreach ($system_info as $key => $value): ?>
                            <tr>
                                <td class="fw-bold ps-3 text-muted" style="width:40%;"><?php echo htmlspecialchars($key); ?></td>
                                <td class="pe-3 text-break"><?php echo is_array($value) ? htmlspecialchars(implode(', ', array_keys(array_filter($value)))) : htmlspecialchars($value); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- 更新确认模态框 -->
<div class="modal fade" id="updateConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <div class="modal-header bg-warning bg-opacity-10">
                <h5 class="modal-title">
                    <i class="fa-solid fa-triangle-exclamation text-warning me-2"></i>确认系统更新
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="border-start border-4 border-warning rounded p-3 mb-3 bg-warning bg-opacity-10">
                    <h6 class="mb-2"><i class="fa-solid fa-exclamation-circle me-1"></i>更新前请确认：</h6>
                    <ul class="mb-0 ps-3 small">
                        <li>更新包将自动下载并解压覆盖现有文件</li>
                        <li>更新期间网站将短暂不可访问</li>
                        <li>建议先在非高峰时段执行更新</li>
                        <li>强烈建议提前备份数据库与文件</li>
                    </ul>
                </div>
                <div class="d-flex align-items-center gap-2 mb-3">
                    <span class="fw-bold text-muted small">目标版本：</span>
                    <span id="confirmVersion" class="badge bg-success fs-6">
                        v<?php echo htmlspecialchars($update_info['latest_version'] ?? ''); ?>
                    </span>
                </div>
                <div id="updateProgress" class="d-none">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        <span id="updateStatusText" class="small">正在准备更新...</span>
                    </div>
                    <div class="progress" style="height:6px;">
                        <div id="updateProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-warning"
                             style="width:0%"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                <button type="button" class="btn btn-warning" id="confirmUpdateBtn" onclick="doSystemUpdate()">
                    <i class="fa-solid fa-check me-1"></i>确认更新
                </button>
            </div>
        </div>
    </div>
</div>

<!-- 插件更新确认模态框 -->
<div class="modal fade" id="pluginUpdateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content shadow-lg">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-puzzle-piece me-2"></i>插件更新</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <p class="mb-0">确定要更新插件 <strong id="pluginUpdateName"></strong> 到版本 <strong id="pluginUpdateVersion" class="text-success"></strong> 吗？</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">取消</button>
                <button type="button" class="btn btn-primary btn-sm" id="confirmPluginUpdateBtn">
                    <i class="fa-solid fa-download me-1"></i>确定更新
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // 存储待更新的插件信息
    let pendingPlugin = null;

    function showUpdateConfirm() {
        const modal = new bootstrap.Modal('#updateConfirmModal');
        modal.show();
    }

    function checkUpdate() {
        const load = Zap.loading('正在检查核心更新...');

        $.ajax({
            url: '<?php echo Url::action('Update@ajaxCheckCore'); ?>',
            method: 'GET',
            dataType: 'json',
            success: function (data) {
                if (data.code === 0 && data.data && data.data.has_update) {
                    ZapToast.alert('发现新版本 v' + data.data.latest_version + '，页面即将刷新',
                        {bgColor: bgWarning, position: Toast_Pos_Center});
                    setTimeout(() => location.reload(), 1500);
                } else {
                    ZapToast.alert('当前已是最新版本', {bgColor: bgSuccess, position: Toast_Pos_Center});
                }
            },
            error: function () {
                ZapToast.alert('检查更新失败，请稍后重试', {bgColor: bgDanger, position: Toast_Pos_Center});
            }
        }).always(function () {
            load.dispose();
        });
    }

    function doSystemUpdate() {
        const btn = $('#confirmUpdateBtn');
        const progressDiv = $('#updateProgress');
        const progressBar = $('#updateProgressBar');
        const statusText = $('#updateStatusText');

        btn.prop('disabled', true).addClass('d-none');
        progressDiv.removeClass('d-none');

        // 模拟进度
        let progress = 0;
        const messages = ['正在准备更新...', '正在下载更新包...', '正在解压文件...', '正在安装更新...', '更新即将完成...'];
        let msgIdx = 0;
        const progressTimer = setInterval(function () {
            progress += Math.random() * 12;
            if (progress > 85) progress = 85;
            progressBar.css('width', progress + '%');
            if (progress > (msgIdx + 1) * 18 && msgIdx < messages.length - 1) {
                msgIdx++;
                statusText.text(messages[msgIdx]);
            }
        }, 800);

        statusText.text(messages[0]);

        $.ajax({
            url: '<?php echo Url::action('Update@doUpdate'); ?>',
            method: 'POST',
            dataType: 'json',
            data: {
                download_url: '<?php echo addslashes($update_info['download_url'] ?? ''); ?>',
                version: '<?php echo addslashes($update_info['latest_version'] ?? ''); ?>'
            },
            success: function (data) {
                clearInterval(progressTimer);
                if (data.code === 0) {
                    progressBar.css('width', '100%').removeClass('bg-warning').addClass('bg-success');
                    statusText.text('更新成功！');
                    const modal = bootstrap.Modal.getInstance('#updateConfirmModal');
                    setTimeout(function () {
                        modal.hide();
                        ZapToast.alert('系统更新成功，页面即将刷新', {bgColor: bgSuccess, position: Toast_Pos_Center});
                        setTimeout(() => location.reload(), 2000);
                    }, 800);
                } else {
                    progressBar.removeClass('progress-bar-animated bg-warning').addClass('bg-danger');
                    statusText.text('更新失败');
                    ZapToast.alert(data.msg || '更新失败，请查看日志', {bgColor: bgDanger, position: Toast_Pos_Center});
                    btn.prop('disabled', false).removeClass('d-none');
                    progressDiv.addClass('d-none');
                    progressBar.css('width', '0%').removeClass('bg-danger').addClass('progress-bar-animated bg-warning');
                }
            },
            error: function () {
                clearInterval(progressTimer);
                progressBar.removeClass('progress-bar-animated bg-warning').addClass('bg-danger');
                statusText.text('请求失败');
                ZapToast.alert('网络请求失败，请检查网络连接', {bgColor: bgDanger, position: Toast_Pos_Center});
                btn.prop('disabled', false).removeClass('d-none');
                progressDiv.addClass('d-none');
                progressBar.css('width', '0%').removeClass('bg-danger').addClass('progress-bar-animated bg-warning');
            }
        });
    }

    function manualUpdate() {
        const fileInput = document.getElementById('updateZip');

        if (!fileInput.files.length) {
            ZapToast.alert('请选择 ZIP 更新包', {bgColor: bgWarning, position: Toast_Pos_Center});
            return;
        }

        const load = Zap.loading('正在上传并安装更新，请稍后...');
        const formData = new FormData(document.getElementById('manualUpdateForm'));

        $.ajax({
            url: '<?php echo Url::action('Update@manualUpdate'); ?>',
            method: 'POST',
            data: formData,
            dataType: 'json',
            processData: false,
            contentType: false,
            success: function (data) {
                if (data.code === 0) {
                    ZapToast.alert('更新成功！页面即将刷新', {bgColor: bgSuccess, position: Toast_Pos_Center});
                    setTimeout(() => location.reload(), 3000);
                } else {
                    ZapToast.alert(data.msg || '更新失败', {bgColor: bgDanger, position: Toast_Pos_Center});
                }
            },
            error: function () {
                ZapToast.alert('上传失败，网络异常', {bgColor: bgDanger, position: Toast_Pos_Center});
            }
        }).always(function () {
            load.dispose();
        });
    }

    function checkPluginUpdates() {
        const wrapper = $('#pluginUpdateWrapper');
        const load = Zap.loading('正在检查插件更新...');

        $.ajax({
            url: '<?php echo Url::action('Update@ajaxCheckPlugins'); ?>',
            method: 'GET',
            dataType: 'json',
            success: function (data) {
                let html = '';
                if (data.code === 0 && data.data.length > 0) {
                    html += '<div class="alert border-start border-4 border-info shadow-sm mb-3 py-2 bg-white"><i class="fa-solid fa-bell text-info me-1"></i>发现 <strong>' +
                        data.data.length + '</strong> 个插件有可用更新</div>';
                    data.data.forEach(function (item) {
                        html +=
                            '<div class="d-flex align-items-center p-3 border rounded mb-2 bg-white shadow-sm">' +
                            '<div class="flex-shrink-0 me-3">' +
                            '<div class="bg-light rounded-circle d-flex align-items-center justify-content-center" style="width:44px;height:44px;">' +
                            '<i class="fa-solid fa-puzzle-piece text-primary fa-lg"></i>' +
                            '</div></div>' +
                            '<div class="flex-grow-1 min-w-0">' +
                            '<strong class="d-block text-truncate">' + escapeHtml(item.title) + '</strong>' +
                            '<span class="small">' +
                            '<code>' + escapeHtml(item.current_version) + '</code>' +
                            ' <i class="fa-solid fa-arrow-right-long text-muted mx-1"></i> ' +
                            '<code class="text-success fw-bold">' + escapeHtml(item.latest_version) + '</code>' +
                            '</span></div>' +
                            '<div class="flex-shrink-0 ms-3">' +
                            '<button class="btn btn-sm btn-outline-primary" onclick="doPluginUpdate(\'' +
                            escapeHtml(item.name) + '\', \'' + escapeHtml(item.download_url) + '\', \'' +
                            escapeHtml(item.latest_version) + '\')">' +
                            '<i class="fa-solid fa-download me-1"></i>更新</button>' +
                            '</div></div>';
                    });
                } else {
                    html =
                        '<div class="text-center py-4 text-muted"><i class="fa-solid fa-circle-check text-success fa-2x mb-2 d-block"></i><p class="mb-0">所有插件都是最新版本</p></div>';
                }
                $('#pluginUpdateArea').html(html);
            },
            error: function () {
                $('#pluginUpdateArea').html(
                    '<div class="text-center py-4 text-danger"><i class="fa-solid fa-circle-exclamation fa-2x mb-2 d-block"></i><p class="mb-0">检查失败，请稍后重试</p></div>'
                    );
            }
        }).always(function () {
            load.dispose();
        });
    }

    function doPluginUpdate(name, downloadUrl, version) {
        // 使用模态框确认（更友好的体验）
        pendingPlugin = {name: name, downloadUrl: downloadUrl, version: version};
        $('#pluginUpdateName').text(name);
        $('#pluginUpdateVersion').text('v' + version);

        const pluginModal = new bootstrap.Modal('#pluginUpdateModal');
        pluginModal.show();

        // 绑定确认按钮（先解绑再绑定，防止多次绑定）
        $('#confirmPluginUpdateBtn').off('click').on('click', function () {
            pluginModal.hide();
            executePluginUpdate();
        });
    }

    function executePluginUpdate() {
        if (!pendingPlugin) return;

        const load = Zap.loading('正在更新插件...');

        $.ajax({
            url: '<?php echo Url::action('Plugin@update'); ?>',
            method: 'POST',
            dataType: 'json',
            data: {
                name: pendingPlugin.name,
                download_url: pendingPlugin.downloadUrl,
                version: pendingPlugin.version
            },
            success: function (data) {
                if (data.code === 0) {
                    ZapToast.alert('插件更新成功！', {bgColor: bgSuccess, position: Toast_Pos_Center});
                    setTimeout(() => location.reload(), 1500);
                } else {
                    ZapToast.alert(data.msg || '插件更新失败', {bgColor: bgDanger, position: Toast_Pos_Center});
                }
            },
            error: function () {
                ZapToast.alert('请求失败，请稍后重试', {bgColor: bgDanger, position: Toast_Pos_Center});
            }
        }).always(function () {
            load.dispose();
            pendingPlugin = null;
        });
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    $(function () {
        // 更新历史表格启用 tooltip（如有需要）
    });
</script>
