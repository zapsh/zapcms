<?php
/*
 * 扩展管理 - 已安装列表
 * 模块(mod)与插件(plugin)使用相同的分发方式，统一在此以「扩展」展示。
 */
!IS_AJAX && $this->layout('layouts/common');

use zap\facades\Url;

$extensions  = $extensions ?? [];
$total       = count($extensions);
$activeCount = count(array_filter($extensions, fn($e) => ($e['status'] ?? 0) == 1));
$disabledCnt = $total - $activeCount;
$unregistered = count(array_filter($extensions, fn($e) => empty($e['registered'])));
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
                            <i class="fa fa-puzzle-piece fa-stack-1x"></i>
                        </span>
                    </div>
                    <div class="flex-grow-1">
                        <div class="text-muted small text-uppercase fw-semibold">扩展总数</div>
                        <div class="fs-3 fw-bold text-info"><?= $total ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 扩展管理卡片 -->
    <div class="card shadow-sm">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h5 class="card-title mb-0">
                <i class="fa fa-puzzle-piece me-2 text-primary"></i>已安装扩展
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
            <?php if (empty($extensions)): ?>
                <!-- 空状态 -->
                <div class="text-center py-5 text-muted">
                    <i class="fa fa-puzzle-piece fa-4x mb-3 d-block opacity-25"></i>
                    <h5>还没有安装任何扩展</h5>
                    <p class="mb-3">前往插件市场发现和安装，或上传本地扩展包</p>
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
                <!-- 视图切换 + 类型过滤 -->
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div class="btn-group btn-group-sm" role="group">
                        <button class="btn btn-outline-secondary active" id="viewGrid" onclick="switchView('grid')">
                            <i class="fa fa-th-large"></i>
                        </button>
                        <button class="btn btn-outline-secondary" id="viewList" onclick="switchView('list')">
                            <i class="fa fa-list"></i>
                        </button>
                    </div>
                    <div class="btn-group btn-group-sm" id="typeFilter" role="group">
                        <button class="btn btn-outline-secondary active" data-type="all">全部</button>
                        <button class="btn btn-outline-secondary" data-type="module">模块</button>
                        <button class="btn btn-outline-secondary" data-type="plugin">插件</button>
                    </div>
                </div>

                <!-- 卡片网格视图 -->
                <div class="row g-3" id="extGrid">
                    <?php foreach ($extensions as $ext): ?>
                        <?= renderExtCard($ext) ?>
                    <?php endforeach; ?>
                </div>

                <!-- 表格列表视图（默认隐藏） -->
                <div class="table-responsive d-none" id="extTable">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px"></th>
                                <th>名称</th>
                                <th>版本</th>
                                <th>作者</th>
                                <th>状态</th>
                                <th style="width:200px">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($extensions as $ext): ?>
                                <?= renderExtRow($ext) ?>
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
                <p>确定要卸载扩展 <strong id="uninstallPluginName">—</strong> 吗？</p>
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
                <h5 class="modal-title"><i class="fa fa-upload me-2"></i>上传安装扩展</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="uploadForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">选择扩展 ZIP 包</label>
                        <input type="file" class="form-control" name="plugin_zip" accept=".zip" required>
                        <div class="form-text">支持 ZIP 格式的扩展包，包内需包含 plugin.json 或 mod.json 配置文件</div>
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
//  扩展管理 - 前端交互逻辑
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
        tip.innerHTML = '<i class="fa fa-exclamation-circle me-1"></i>相当于“注销”：清理后台菜单与安装登记，数据表和模块文件都保留，之后在列表点“注册”即可重新激活。';
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

// —— 注册未注册扩展（目录已存在，仅缺失登记） ——
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
    document.getElementById('extGrid').classList.toggle('d-none', mode !== 'grid');
    document.getElementById('extTable').classList.toggle('d-none', mode !== 'list');
}

// —— 类型过滤（全部 / 模块 / 插件） ——
document.querySelectorAll('#typeFilter button').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.querySelectorAll('#typeFilter button').forEach(function(b) { b.classList.remove('active'); });
        btn.classList.add('active');
        const t = btn.dataset.type;
        document.querySelectorAll('#extGrid [data-type], #extTable tr[data-type]').forEach(function(el) {
            el.classList.toggle('d-none', t !== 'all' && el.dataset.type !== t);
        });
    });
});

// —— 刷新列表 ——
function refreshPlugins() {
    location.reload();
}
</script>

<?php
// ==============================
//  辅助函数：渲染扩展卡片 / 表格行
// ==============================

function renderExtCard(array $ext): string
{
    $name    = htmlspecialchars($ext['name']);
    $title   = htmlspecialchars($ext['title'] ?? $ext['name']);
    $version = htmlspecialchars($ext['version'] ?? '—');
    $author  = htmlspecialchars($ext['author'] ?? '—');
    $desc    = htmlspecialchars($ext['description'] ?? '');
    $type    = $ext['type'] ?? 'module';
    $status  = ($ext['status'] ?? 0) == 1;
    $registered = !empty($ext['registered']);
    $missing    = !empty($ext['missing']);

    $iconLetter = mb_strtoupper(mb_substr($title, 0, 1));
    $typeBadge  = $type === 'plugin'
        ? '<span class="badge bg-info bg-opacity-10 text-info"><i class="fa fa-plug me-1"></i>插件</span>'
        : '<span class="badge bg-secondary bg-opacity-10 text-secondary"><i class="fa fa-cube me-1"></i>模块</span>';

    if ($missing) {
        $statusBadge = '<span class="badge bg-danger bg-opacity-10 text-danger"><i class="fa fa-exclamation-triangle me-1"></i>文件缺失</span>';
        $actions = '<button class="btn btn-outline-danger btn-sm" onclick="confirmUninstall(\'' . $name . '\')"><i class="fa fa-trash me-1"></i>卸载</button>';
    } elseif (!$registered) {
        $statusBadge = '<span class="badge bg-secondary"><i class="fa fa-question-circle me-1"></i>未注册</span>';
        $actions = '<button class="btn btn-outline-info btn-sm" onclick="registerPlugin(\'' . $name . '\')"><i class="fa fa-plus me-1"></i>注册</button>';
    } else {
        $statusBadge = $status
            ? '<span class="badge bg-success bg-opacity-10 text-success"><i class="fa fa-check-circle me-1"></i>已启用</span>'
            : '<span class="badge bg-warning bg-opacity-10 text-warning"><i class="fa fa-pause-circle me-1"></i>已禁用</span>';
        $actions = renderExtActions($name, $status);
    }

    return <<<CARD
    <div class="col-xl-4 col-lg-6" data-type="{$type}">
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
                            {$typeBadge}
                        </div>
                    </div>
                </div>
                <p class="text-muted small mb-3 text-truncate-2" style="min-height:2.5em;" title="{$desc}">{$desc}</p>
                <div class="d-flex gap-1 flex-wrap align-items-center">
                    {$statusBadge}
                    <span class="ms-auto"></span>
                    {$actions}
                </div>
            </div>
        </div>
    </div>
CARD;
}

function renderExtRow(array $ext): string
{
    $name    = htmlspecialchars($ext['name']);
    $title   = htmlspecialchars($ext['title'] ?? $ext['name']);
    $version = htmlspecialchars($ext['version'] ?? '—');
    $author  = htmlspecialchars($ext['author'] ?? '—');
    $type    = $ext['type'] ?? 'module';
    $status  = ($ext['status'] ?? 0) == 1;
    $registered = !empty($ext['registered']);
    $missing    = !empty($ext['missing']);
    $iconLetter = mb_strtoupper(mb_substr($title, 0, 1));

    $typeBadge = $type === 'plugin'
        ? '<span class="badge bg-info bg-opacity-10 text-info"><i class="fa fa-plug me-1"></i>插件</span>'
        : '<span class="badge bg-secondary bg-opacity-10 text-secondary"><i class="fa fa-cube me-1"></i>模块</span>';

    if ($missing) {
        $statusBadge = '<span class="badge bg-danger bg-opacity-10 text-danger"><i class="fa fa-exclamation-triangle me-1"></i>文件缺失</span>';
        $actions = '<button class="btn btn-outline-danger btn-sm" onclick="confirmUninstall(\'' . $name . '\')"><i class="fa fa-trash me-1"></i>卸载</button>';
    } elseif (!$registered) {
        $statusBadge = '<span class="badge bg-secondary"><i class="fa fa-question-circle me-1"></i>未注册</span>';
        $actions = '<button class="btn btn-outline-info btn-sm" onclick="registerPlugin(\'' . $name . '\')"><i class="fa fa-plus me-1"></i>注册</button>';
    } else {
        $statusBadge = $status
            ? '<span class="badge bg-success bg-opacity-10 text-success"><i class="fa fa-check-circle me-1"></i>已启用</span>'
            : '<span class="badge bg-warning bg-opacity-10 text-warning"><i class="fa fa-pause-circle me-1"></i>已禁用</span>';
        $actions = renderExtActions($name, $status);
    }

    return <<<ROW
        <tr data-type="{$type}">
            <td class="text-center">
                <span class="avatar avatar-sm rounded-circle d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary fw-bold" style="width:36px;height:36px;font-size:14px;">
                    {$iconLetter}
                </span>
            </td>
            <td>
                <div class="fw-semibold">{$title}</div>
                <small class="text-muted">{$name} {$typeBadge}</small>
            </td>
            <td><span class="badge bg-light text-dark">v{$version}</span></td>
            <td>{$author}</td>
            <td>{$statusBadge}</td>
            <td>{$actions}</td>
        </tr>
ROW;
}

function renderExtActions(string $name, bool $isActive): string
{
    $n = htmlspecialchars($name);
    $html = '';

    if ($isActive) {
        $html .= '<button class="btn btn-outline-warning btn-sm" onclick="toggleStatus(\'' . $n . '\', 1)" title="禁用"><i class="fa fa-pause me-1"></i>禁用</button>';
    } else {
        $html .= '<button class="btn btn-outline-success btn-sm" onclick="toggleStatus(\'' . $n . '\', 0)" title="启用"><i class="fa fa-play me-1"></i>启用</button>';
    }

    $html .= ' <button class="btn btn-outline-danger btn-sm" onclick="confirmUninstall(\'' . $n . '\')" title="卸载"><i class="fa fa-trash me-1"></i>卸载</button>';

    return $html;
}
