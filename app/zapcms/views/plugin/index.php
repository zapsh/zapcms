<?php
/*
 * 插件管理 - 已安装列表
 */
!IS_AJAX && $this->layout('layouts/common');

use zap\facades\Url;

$total        = count($plugins);
$activeCount  = count(array_filter($plugins, fn($p) => ($p['status'] ?? 0) == 1));
$disabledCnt  = $total - $activeCount;

// loaded_mods 中每个元素是 ['name' => ..., 'title' => ..., ...]
$loadedModNames = array_column($loaded_mods, 'name');
$unregistered   = count(array_diff($loadedModNames, $registered_names));

// 判断插件是否已注册到数据库
$isRegistered = function($mod) use ($registered_names) {
    return in_array($mod['name'], $registered_names);
};

// 获取插件状态
$getStatus = function($plugin) {
    return ($plugin['status'] ?? 0) == 1 ? 1 : 0;
};
?>

<div class="container-fluid p-0">
    <!-- 统计卡片 -->
    <div class="row g-3 mb-3">
        <div class="col-xl-3 col-md-6">
            <div class="card border-start border-success border-3 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="flex-shrink-0 me-3">
                        <span class="fa-stack fa-lg text-success">
                            <i class="fa fa-circle fa-stack-2x opacity-25"></i>
                            <i class="fa fa-play fa-stack-1x"></i>
                        </span>
                    </div>
                    <div class="flex-grow-1">
                        <div class="text-muted small text-uppercase fw-semibold">已启用</div>
                        <div class="fs-3 fw-bold text-success"><?= $activeCount ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-start border-warning border-3 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="flex-shrink-0 me-3">
                        <span class="fa-stack fa-lg text-warning">
                            <i class="fa fa-circle fa-stack-2x opacity-25"></i>
                            <i class="fa fa-pause fa-stack-1x"></i>
                        </span>
                    </div>
                    <div class="flex-grow-1">
                        <div class="text-muted small text-uppercase fw-semibold">已禁用</div>
                        <div class="fs-3 fw-bold text-warning"><?= $disabledCnt ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-start border-secondary border-3 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="flex-shrink-0 me-3">
                        <span class="fa-stack fa-lg text-secondary">
                            <i class="fa fa-circle fa-stack-2x opacity-25"></i>
                            <i class="fa fa-folder-open fa-stack-1x"></i>
                        </span>
                    </div>
                    <div class="flex-grow-1">
                        <div class="text-muted small text-uppercase fw-semibold">未注册</div>
                        <div class="fs-3 fw-bold text-secondary"><?= $unregistered ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-start border-info border-3 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="flex-shrink-0 me-3">
                        <span class="fa-stack fa-lg text-info">
                            <i class="fa fa-circle fa-stack-2x opacity-25"></i>
                            <i class="fa fa-plug fa-stack-1x"></i>
                        </span>
                    </div>
                    <div class="flex-grow-1">
                        <div class="text-muted small text-uppercase fw-semibold">插件总数</div>
                        <div class="fs-3 fw-bold text-info"><?= $total ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 插件管理卡片 -->
    <div class="card shadow-sm">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h5 class="card-title mb-0">
                <i class="fa fa-puzzle-piece me-2 text-primary"></i>已安装插件
                <small class="text-muted fw-normal">(<?= $total ?>)</small>
            </h5>
            <div class="btn-group">
                <button class="btn btn-success btn-sm" onclick="uploadPlugin()" title="上传插件 ZIP 包安装">
                    <i class="fa fa-upload me-1"></i>上传安装
                </button>
                <a href="<?= Url::action('Plugin@market') ?>" class="btn btn-primary btn-sm ajax-link" title="前往插件市场">
                    <i class="fa fa-shopping-cart me-1"></i>插件市场
                </a>
                <button class="btn btn-outline-secondary btn-sm" onclick="refreshPlugins()" title="刷新列表">
                    <i class="fa fa-refresh"></i>
                </button>
            </div>
        </div>
        <div class="card-body p-3">
            <?php if (empty($plugins) && empty($loaded_mods)): ?>
                <!-- 空状态 -->
                <div class="text-center py-5 text-muted">
                    <i class="fa fa-puzzle-piece fa-4x mb-3 d-block opacity-25"></i>
                    <h5>还没有安装任何插件</h5>
                    <p class="mb-3">前往插件市场发现和安装插件，或上传本地插件包</p>
                    <div class="d-flex justify-content-center gap-2">
                        <a href="<?= Url::action('Plugin@market') ?>" class="btn btn-primary ajax-link">
                            <i class="fa fa-shopping-cart me-1"></i>浏览插件市场
                        </a>
                        <button class="btn btn-outline-success" onclick="uploadPlugin()">
                            <i class="fa fa-upload me-1"></i>上传安装
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <!-- 视图切换按钮 -->
                <div class="d-flex justify-content-end mb-3">
                    <div class="btn-group btn-group-sm" role="group">
                        <button class="btn btn-outline-secondary active" id="viewGrid" onclick="switchView('grid')">
                            <i class="fa fa-th-large"></i>
                        </button>
                        <button class="btn btn-outline-secondary" id="viewList" onclick="switchView('list')">
                            <i class="fa fa-list"></i>
                        </button>
                    </div>
                </div>

                <!-- 卡片网格视图 -->
                <div class="row g-3" id="pluginGrid">
                    <?php foreach ($plugins as $plugin): ?>
                        <?= renderPluginCard($plugin, true) ?>
                    <?php endforeach; ?>
                    <?php foreach ($loaded_mods as $mod): ?>
                        <?php if (!$isRegistered($mod)): ?>
                            <?= renderPluginCard(['name' => $mod['name'], 'title' => $mod['title'], 'version' => $mod['version'] ?? '—', 'author' => $mod['author'] ?? '—', 'description' => $mod['description'] ?? '插件目录存在但未注册到数据库', 'status' => 0], false) ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <!-- 表格列表视图（默认隐藏） -->
                <div class="table-responsive d-none" id="pluginTable">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px"></th>
                                <th>插件名称</th>
                                <th>版本</th>
                                <th>作者</th>
                                <th>状态</th>
                                <th style="width:200px">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($plugins as $plugin): ?>
                            <tr>
                                <td class="text-center">
                                    <span class="avatar avatar-sm rounded-circle d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary fw-bold" style="width:36px;height:36px;font-size:14px;">
                                        <?= mb_strtoupper(mb_substr($plugin['title'] ?? $plugin['name'], 0, 1)) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($plugin['title'] ?? $plugin['name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($plugin['name']) ?></small>
                                </td>
                                <td><span class="badge bg-light text-dark">v<?= htmlspecialchars($plugin['version'] ?? '—') ?></span></td>
                                <td><?= htmlspecialchars($plugin['author'] ?? '—') ?></td>
                                <td>
                                    <?php if ($getStatus($plugin)): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success"><i class="fa fa-check-circle me-1"></i>已启用</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning bg-opacity-10 text-warning"><i class="fa fa-pause-circle me-1"></i>已禁用</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= renderActionButtons($plugin['name'], $getStatus($plugin)) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php foreach ($loaded_mods as $mod): ?>
                            <?php if (!$isRegistered($mod)): ?>
                            <tr class="table-secondary">
                                <td class="text-center">
                                    <span class="avatar avatar-sm rounded-circle d-inline-flex align-items-center justify-content-center bg-secondary text-white fw-bold" style="width:36px;height:36px;font-size:14px;">
                                        <?= mb_strtoupper(mb_substr($mod['name'], 0, 1)) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-semibold text-muted"><?= htmlspecialchars($mod['name']) ?></div>
                                    <small class="text-muted">未注册到数据库</small>
                                </td>
                                <td><span class="badge bg-light text-muted">—</span></td>
                                <td class="text-muted">—</td>
                                <td><span class="badge bg-secondary bg-opacity-10 text-secondary"><i class="fa fa-question-circle me-1"></i>未注册</span></td>
                                <td>
                                    <button class="btn btn-outline-info btn-sm" onclick="registerPlugin('<?= htmlspecialchars($mod['name']) ?>')">
                                        <i class="fa fa-plus me-1"></i>注册
                                    </button>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 确认卸载 Modal -->
<div class="modal fade" id="uninstallModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fa fa-exclamation-triangle me-2"></i>确认卸载</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>确定要卸载插件 <strong id="uninstallPluginName">—</strong> 吗？</p>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="keepUninstallData" checked>
                    <label class="form-check-label" for="keepUninstallData">
                        保留数据（不删除模块创建的数据表）
                    </label>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="keepUninstallFiles" checked>
                    <label class="form-check-label" for="keepUninstallFiles">
                        保留模块文件（不删除 mods 目录，之后可直接注册激活）
                    </label>
                </div>
                <div class="alert alert-warning mb-0" id="uninstallTip">
                    <i class="fa fa-exclamation-circle me-1"></i>
                    模块会从已安装列表移除并清理后台菜单，数据和模块文件均保留，之后在“模块”列表点“注册”即可重新激活。
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                <button type="button" class="btn btn-danger" id="confirmUninstall">
                    <i class="fa fa-trash me-1"></i>确认卸载
                </button>
            </div>
        </div>
    </div>
</div>

<!-- 上传插件 Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fa fa-upload me-2"></i>上传安装插件</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="uploadForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">选择插件 ZIP 包</label>
                        <input type="file" class="form-control" name="plugin_zip" accept=".zip" required>
                        <div class="form-text">支持 ZIP 格式的插件包，包内需包含 plugin.json 配置文件</div>
                    </div>
                    <div class="progress d-none" style="height:6px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" style="width:100%"></div>
                    </div>
                    <div id="uploadMsg" class="mt-2"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-success" id="uploadBtn">
                        <i class="fa fa-upload me-1"></i>上传并安装
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// ==============================
//  插件管理 - 前端交互逻辑
// ==============================

let currentUninstallName = '';

// —— 确认卸载对话框 ——
function confirmUninstall(name) {
    currentUninstallName = name;
    document.getElementById('uninstallPluginName').textContent = name;
    // 每次打开都回到“保留数据 + 保留文件”的安全默认
    document.getElementById('keepUninstallData').checked = true;
    document.getElementById('keepUninstallFiles').checked = true;
    updateUninstallTip();
    const modal = new bootstrap.Modal('#uninstallModal');
    modal.show();
}

function updateUninstallTip() {
    const keepData  = document.getElementById('keepUninstallData').checked;
    const keepFiles = document.getElementById('keepUninstallFiles').checked;
    const tip = document.getElementById('uninstallTip');
    const btn = document.getElementById('confirmUninstall');

    if (keepData && keepFiles) {
        tip.className = 'alert alert-warning mb-0';
        tip.innerHTML = '<i class="fa fa-exclamation-circle me-1"></i>相当于“注销”：清理后台菜单与安装登记，数据表和模块文件都保留，之后在“模块”列表点“注册”即可重新激活。';
        btn.className = 'btn btn-warning';
        btn.innerHTML = '<i class="fa fa-power-off me-1"></i>注销（保留数据与文件）';
    } else if (keepData) {
        tip.className = 'alert alert-warning mb-0';
        tip.innerHTML = '<i class="fa fa-exclamation-circle me-1"></i>模块文件会被删除，数据表保留；重新启用需重新上传模块包，原有数据不会丢失。';
        btn.className = 'btn btn-warning';
        btn.innerHTML = '<i class="fa fa-trash me-1"></i>卸载（保留数据）';
    } else if (keepFiles) {
        tip.className = 'alert alert-danger mb-0';
        tip.innerHTML = '<i class="fa fa-exclamation-circle me-1"></i><strong>注意：</strong>该模块创建的数据表会被删除且无法恢复，模块文件保留，可直接注册重新激活。';
        btn.className = 'btn btn-danger';
        btn.innerHTML = '<i class="fa fa-trash me-1"></i>注销（删除数据）';
    } else {
        tip.className = 'alert alert-danger mb-0';
        tip.innerHTML = '<i class="fa fa-exclamation-circle me-1"></i><strong>危险：</strong>模块文件、后台菜单以及该模块创建的数据表都会被删除，数据无法恢复。';
        btn.className = 'btn btn-danger';
        btn.innerHTML = '<i class="fa fa-trash me-1"></i>彻底卸载（删除文件和数据）';
    }
}

document.getElementById('keepUninstallData').addEventListener('change', updateUninstallTip);
document.getElementById('keepUninstallFiles').addEventListener('change', updateUninstallTip);

document.getElementById('confirmUninstall').addEventListener('click', function() {
    const btn = this;
    const keepData  = document.getElementById('keepUninstallData').checked ? 1 : 0;
    const keepFiles = document.getElementById('keepUninstallFiles').checked ? 1 : 0;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>卸载中…';

    Zap.AjaxPost({
        url: '<?= Url::action('Plugin@uninstall') ?>',
        data: { name: currentUninstallName, keep_data: keepData, keep_files: keepFiles },
        dataType: 'json',
        success: function(res) {
            bootstrap.Modal.getInstance('#uninstallModal').hide();
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-trash me-1"></i>确认卸载';
            if (res.code === 0) {
                ZapToast.alert(res.msg || '卸载成功', { bgColor: bgSuccess });
                setTimeout(function() { location.reload(); }, 600);
            } else {
                ZapToast.alert(res.msg || '卸载失败', { bgColor: bgDanger });
            }
        },
        error: function() {
            bootstrap.Modal.getInstance('#uninstallModal').hide();
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-trash me-1"></i>确认卸载';
            ZapToast.alert('网络请求失败', { bgColor: bgDanger });
        }
    });
});

// —— 启用/禁用 ——
function toggleStatus(name, currentStatus) {
    const newStatus = currentStatus ? 0 : 1;
    const actionText = newStatus ? '启用' : '禁用';

    Zap.AjaxPost({
        url: '<?= Url::action('Plugin@toggleStatus') ?>',
        data: { name: name, status: newStatus },
        dataType: 'json',
        success: function(res) {
            if (res.code === 0) {
                ZapToast.alert((newStatus ? '已启用：' : '已禁用：') + name, { bgColor: bgSuccess });
                setTimeout(function() { location.reload(); }, 500);
            } else {
                ZapToast.alert(res.msg || '操作失败', { bgColor: bgDanger });
            }
        },
        error: function() {
            ZapToast.alert('网络请求失败', { bgColor: bgDanger });
        }
    });
}

// —— 注册未注册插件 ——
function registerPlugin(name) {
    Zap.AjaxPost({
        url: '<?= Url::action('Plugin@uploadInstall') ?>',
        data: { name: name },
        dataType: 'json',
        success: function(res) {
            if (res.code === 0) {
                ZapToast.alert('注册成功：' + name, { bgColor: bgSuccess });
                setTimeout(function() { location.reload(); }, 500);
            } else {
                ZapToast.alert(res.msg || '注册失败，请尝试手动上传安装', { bgColor: bgWarning });
            }
        },
        error: function() {
            ZapToast.alert('网络请求失败', { bgColor: bgDanger });
        }
    });
}

// —— 上传安装 ——
function uploadPlugin() {
    const modal = new bootstrap.Modal('#uploadModal');
    modal.show();
}

document.getElementById('uploadForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const btn = document.getElementById('uploadBtn');
    const msgEl = document.getElementById('uploadMsg');
    const progress = this.querySelector('.progress');

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>安装中…';
    progress.classList.remove('d-none');
    msgEl.innerHTML = '';

    Zap.AjaxPost({
        url: '<?= Url::action('Plugin@uploadInstall') ?>',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(res) {
            progress.classList.add('d-none');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-upload me-1"></i>上传并安装';
            if (res.code === 0) {
                msgEl.innerHTML = '<div class="alert alert-success py-2 mb-0"><i class="fa fa-check-circle me-1"></i>' + res.msg + '</div>';
                ZapToast.alert('安装成功', { bgColor: bgSuccess });
                setTimeout(function() { location.reload(); }, 800);
            } else {
                msgEl.innerHTML = '<div class="alert alert-danger py-2 mb-0"><i class="fa fa-times-circle me-1"></i>' + (res.msg || '安装失败') + '</div>';
                ZapToast.alert(res.msg || '安装失败', { bgColor: bgDanger });
            }
        },
        error: function() {
            progress.classList.add('d-none');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-upload me-1"></i>上传并安装';
            msgEl.innerHTML = '<div class="alert alert-danger py-2 mb-0">网络请求失败</div>';
            ZapToast.alert('网络请求失败', { bgColor: bgDanger });
        }
    });
});

// —— 视图切换（卡片 / 列表） ——
function switchView(mode) {
    document.getElementById('viewGrid').classList.toggle('active', mode === 'grid');
    document.getElementById('viewList').classList.toggle('active', mode === 'list');
    document.getElementById('pluginGrid').classList.toggle('d-none', mode !== 'grid');
    document.getElementById('pluginTable').classList.toggle('d-none', mode !== 'list');
}

// —— 刷新列表 ——
function refreshPlugins() {
    location.reload();
}
</script>

<?php
// ==============================
//  辅助函数：渲染插件卡片
// ==============================
function renderPluginCard(array $plugin, bool $isRegistered): string
{
    $name    = htmlspecialchars($plugin['name']);
    $title   = htmlspecialchars($plugin['title'] ?? $plugin['name']);
    $version = htmlspecialchars($plugin['version'] ?? '—');
    $author  = htmlspecialchars($plugin['author'] ?? '—');
    $desc    = htmlspecialchars($plugin['description'] ?? '');
    $status  = ($plugin['status'] ?? 0) == 1;

    $iconLetter  = mb_strtoupper(mb_substr($title, 0, 1));
    $statusBadge = $status
        ? '<span class="badge bg-success bg-opacity-10 text-success"><i class="fa fa-check-circle me-1"></i>已启用</span>'
        : '<span class="badge bg-warning bg-opacity-10 text-warning"><i class="fa fa-pause-circle me-1"></i>已禁用</span>';

    $actions = renderActionButtons($plugin['name'], $status);

    if (!$isRegistered) {
        $iconLetter  = mb_strtoupper(mb_substr($plugin['name'], 0, 1));
        $statusBadge = '<span class="badge bg-secondary"><i class="fa fa-question-circle me-1"></i>未注册</span>';
        $actions     = '<button class="btn btn-outline-info btn-sm" onclick="registerPlugin(\'' . htmlspecialchars($plugin['name']) . '\')"><i class="fa fa-plus me-1"></i>注册</button>';
    }

    return <<<CARD
    <div class="col-xl-4 col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-start mb-2">
                    <span class="avatar rounded-circle d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary fw-bold me-3 flex-shrink-0" style="width:44px;height:44px;font-size:16px;">
                        {$iconLetter}
                    </span>
                    <div class="flex-grow-1 min-w-0">
                        <h6 class="mb-1 text-truncate" title="{$title}">{$title}</h6>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="badge bg-light text-dark">v{$version}</span>
                            <small class="text-muted text-truncate" title="{$author}"><i class="fa fa-user-o me-1"></i>{$author}</small>
                            {$statusBadge}
                        </div>
                    </div>
                </div>
                <p class="text-muted small mb-3 text-truncate-2" style="min-height:2.5em;" title="{$desc}">{$desc}</p>
                <div class="d-flex gap-1 flex-wrap">
                    {$actions}
                </div>
            </div>
        </div>
    </div>
    CARD;
}

/**
 * 渲染操作按钮
 */
function renderActionButtons(string $name, bool $isActive): string
{
    $n = htmlspecialchars($name);
    $html = '';

    if ($isActive) {
        $html .= '<button class="btn btn-outline-warning btn-sm" onclick="toggleStatus(\'' . $n . '\', 1)" title="禁用插件"><i class="fa fa-pause me-1"></i>禁用</button>';
    } else {
        $html .= '<button class="btn btn-outline-success btn-sm" onclick="toggleStatus(\'' . $n . '\', 0)" title="启用插件"><i class="fa fa-play me-1"></i>启用</button>';
    }

    $html .= ' <button class="btn btn-outline-danger btn-sm" onclick="confirmUninstall(\'' . $n . '\')" title="卸载插件"><i class="fa fa-trash me-1"></i>卸载</button>';

    return $html;
}
?>
