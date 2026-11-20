<?php
/*
 * 插件市场
 * 支持搜索、分类筛选、分页浏览、安装
 * 
 */
!IS_AJAX && $this->layout('layouts/common');

use zap\facades\Url;
?>

<div class="container-fluid p-0">
    <!-- 搜索 & 筛选卡片 -->
    <div class="card shadow-sm mb-3">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h5 class="card-title mb-0">
                <i class="fa fa-store me-2 text-primary"></i>插件市场
            </h5>
            <div class="d-flex flex-wrap gap-2">
                <form class="d-flex" onsubmit="searchPlugins(event)" style="min-width:260px;">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="fa fa-search text-muted"></i></span>
                        <input id="searchInput" class="form-control" type="search" placeholder="搜索插件名称或描述…"
                               value="<?= htmlspecialchars($search) ?>">
                        <button class="btn btn-primary" type="submit">搜索</button>
                        <?php if (!empty($search)): ?>
                        <button class="btn btn-outline-secondary" type="button" onclick="clearSearch()" title="清除搜索">
                            <i class="fa fa-times"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </form>
                <a href="<?= Url::action('Plugin') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="fa fa-arrow-left me-1"></i>返回插件管理
                </a>
            </div>
        </div>
    </div>

    <!-- 市场统计 -->
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card border-start border-info border-3 shadow-sm h-100">
                <div class="card-body py-2 d-flex align-items-center">
                    <i class="fa fa-cubes fa-lg text-info me-3"></i>
                    <div>
                        <small class="text-muted text-uppercase">市场插件</small>
                        <div class="fw-bold" id="totalCount">—</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-start border-success border-3 shadow-sm h-100">
                <div class="card-body py-2 d-flex align-items-center">
                    <i class="fa fa-check-circle fa-lg text-success me-3"></i>
                    <div>
                        <small class="text-muted text-uppercase">已安装</small>
                        <div class="fw-bold" id="installedCount"><?= count($installed ?: []) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-start border-primary border-3 shadow-sm h-100">
                <div class="card-body py-2 d-flex align-items-center">
                    <i class="fa fa-cloud-download fa-lg text-primary me-3"></i>
                    <div>
                        <small class="text-muted text-uppercase">当前页</small>
                        <div class="fw-bold" id="currentPageLabel">第 <?= $page ?> 页</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 加载/错误/空状态 -->
    <div id="loadingState" class="text-center py-5 d-none">
        <div class="spinner-border text-primary mb-3" role="status" style="width:3rem;height:3rem;">
            <span class="visually-hidden">加载中...</span>
        </div>
        <p class="text-muted">正在从市场获取插件列表…</p>
    </div>

    <div id="errorState" class="text-center py-5 d-none">
        <i class="fa fa-plug-circle-xmark fa-4x text-danger opacity-50 mb-3 d-block"></i>
        <h5 class="text-muted">无法连接到插件市场</h5>
        <p class="text-muted small">请检查 API 地址配置或稍后重试</p>
        <button class="btn btn-outline-primary mt-2" onclick="loadPlugins()">
            <i class="fa fa-refresh me-1"></i>重试
        </button>
    </div>

    <div id="emptyState" class="text-center py-5 d-none">
        <i class="fa fa-box-open fa-4x text-muted opacity-25 mb-3 d-block"></i>
        <h5 class="text-muted">
            <?php if (!empty($search)): ?>
                没有找到匹配 "<?= htmlspecialchars($search) ?>" 的插件
            <?php else: ?>
                暂无可用的插件
            <?php endif; ?>
        </h5>
        <?php if (!empty($search)): ?>
        <button class="btn btn-outline-secondary btn-sm mt-2" onclick="clearSearch()">
            <i class="fa fa-times me-1"></i>清除搜索
        </button>
        <?php endif; ?>
    </div>

    <!-- 插件列表 -->
    <div class="row g-3" id="pluginList"></div>

    <!-- 分页 -->
    <div id="pagination" class="d-flex justify-content-center mt-4 d-none">
        <nav>
            <ul class="pagination pagination-sm mb-0" id="pageButtons"></ul>
        </nav>
    </div>

    <!-- 插件详情 Modal -->
    <div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-info bg-opacity-10">
                    <h5 class="modal-title"><i class="fa fa-info-circle me-2 text-info"></i>插件详情</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2 text-muted">加载中…</p>
                    </div>
                </div>
                <div class="modal-footer" id="detailFooter"></div>
            </div>
        </div>
    </div>

    <!-- 安装确认 Modal -->
    <div class="modal fade" id="installModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fa fa-download me-2"></i>安装插件</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex align-items-center mb-3">
                        <i class="fa fa-puzzle-piece fa-2x text-primary me-3"></i>
                        <div>
                            <h6 class="mb-1" id="installPluginTitle">—</h6>
                            <code class="small text-muted" id="installPluginPackage">—</code>
                        </div>
                    </div>
                    <div class="alert alert-info mb-0">
                        <i class="fa fa-info-circle me-1"></i>
                        安装过程中将从市场下载插件包并自动解压到 mods 目录。
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="button" class="btn btn-primary" id="confirmInstall">
                        <i class="fa fa-download me-1"></i>确认安装
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentPage = <?= $page ?: 1 ?>;
let currentSearch = '<?= addslashes($search) ?>';
const installedPackages = <?= json_encode($installed ?? []) ?>;
let allPlugins = []; // 缓存当前列表用于详情展示
let installPkg = null;
let installTitle = null;

$(function () {
    loadPlugins();
});

// —— 安装确认 ——
function installPlugin(packageName, title) {
    installPkg = packageName;
    installTitle = title;
    document.getElementById('installPluginTitle').textContent = title;
    document.getElementById('installPluginPackage').textContent = '包名: ' + packageName;
    new bootstrap.Modal('#installModal').show();
}

document.getElementById('confirmInstall').addEventListener('click', function() {
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>安装中…';

    $.ajax({
        url: '<?= Url::action('Plugin@install') ?>',
        method: 'POST',
        dataType: 'json',
        data: { package: installPkg },
        success: function(data) {
            bootstrap.Modal.getInstance('#installModal').hide();
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-download me-1"></i>确认安装';
            if (data.code === 0) {
                ZapToast.alert('安装成功：' + installTitle, { bgColor: bgSuccess });
                installedPackages.push(installPkg);
                setTimeout(function() { location.reload(); }, 800);
            } else {
                ZapToast.alert(data.msg || '安装失败', { bgColor: bgDanger });
            }
        },
        error: function() {
            bootstrap.Modal.getInstance('#installModal').hide();
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-download me-1"></i>确认安装';
            ZapToast.alert('网络请求失败', { bgColor: bgDanger });
        }
    });
});

// —— 搜索 ——
function searchPlugins(e) {
    e.preventDefault();
    currentSearch = document.getElementById('searchInput').value.trim();
    currentPage = 1;
    loadPlugins();
}

function clearSearch() {
    document.getElementById('searchInput').value = '';
    currentSearch = '';
    currentPage = 1;
    loadPlugins();
}

// —— 加载插件列表 ——
function loadPlugins() {
    showState('loading');
    $.ajax({
        url: '<?= Url::action('Plugin@ajaxMarketList') ?>',
        method: 'GET',
        dataType: 'json',
        data: { page: currentPage, search: currentSearch },
        success: function(data) {
            if (data.code === 0 && data.data && data.data.plugins && data.data.plugins.length > 0) {
                allPlugins = data.data.plugins;
                renderPlugins(data.data.plugins, data.data);
                hideAllStates();
            } else {
                showState('empty');
            }
        },
        error: function() {
            showState('error');
        }
    });
}

// —— 渲染插件 ——
function renderPlugins(plugins, info) {
    let html = '';
    plugins.forEach(function(p) {
        const isInstalled = installedPackages.indexOf(p.package_name) !== -1;
        const iconLetter = (p.title || p.name).charAt(0).toUpperCase();

        html += `
        <div class="col-xl-3 col-lg-4 col-md-6">
            <div class="card shadow-sm h-100 ${isInstalled ? 'border-success' : ''}">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex align-items-start mb-2">
                        <span class="avatar rounded-circle d-inline-flex align-items-center justify-content-center bg-light text-primary fw-bold me-2 flex-shrink-0" style="width:40px;height:40px;font-size:15px;">
                            ${escapeHtml(iconLetter)}
                        </span>
                        <div class="flex-grow-1 min-w-0">
                            <h6 class="mb-1 text-truncate" title="${escapeHtml(p.title || p.name)}">${escapeHtml(p.title || p.name)}</h6>
                            <div class="d-flex align-items-center gap-1 flex-wrap">
                                <span class="badge bg-light text-dark small">v${escapeHtml(p.version || '—')}</span>
                                ${isInstalled ? '<span class="badge bg-success bg-opacity-10 text-success small"><i class="fa fa-check me-1"></i>已安装</span>' : ''}
                            </div>
                        </div>
                    </div>
                    <p class="text-muted small mb-3 flex-grow-1" style="min-height:2.5em;line-height:1.4;">
                        ${escapeHtml((p.description || '暂无描述').substring(0, 100))}${(p.description || '').length > 100 ? '…' : ''}
                    </p>
                    <div class="small text-muted mb-3">
                        ${p.author ? '<div><i class="fa fa-user-o me-1"></i>' + escapeHtml(p.author) + '</div>' : ''}
                        ${p.downloads ? '<div class="mt-1"><i class="fa fa-download me-1"></i>' + p.downloads + ' 次下载</div>' : ''}
                        ${p.category ? '<div class="mt-1"><span class="badge bg-secondary bg-opacity-10 text-secondary">' + escapeHtml(p.category) + '</span></div>' : ''}
                    </div>
                </div>
                <div class="card-footer bg-transparent border-top">
                    <div class="d-flex gap-1">
                        <button class="btn btn-outline-info btn-sm flex-fill" onclick="showDetail('${escapeHtml(p.package_name)}')" title="查看详情">
                            <i class="fa fa-info-circle me-1"></i>详情
                        </button>
                        ${isInstalled ? `
                        <button class="btn btn-success btn-sm flex-fill" disabled>
                            <i class="fa fa-check me-1"></i>已安装
                        </button>` : `
                        <button class="btn btn-outline-primary btn-sm flex-fill" onclick="installPlugin('${escapeHtml(p.package_name)}', '${escapeHtml(p.title || p.name)}')" title="安装插件">
                            <i class="fa fa-download me-1"></i>安装
                        </button>
                        `}
                    </div>
                </div>
            </div>
        </div>`;
    });

    document.getElementById('pluginList').innerHTML = html;

    // 更新统计
    document.getElementById('totalCount').textContent = info.total || plugins.length;
    document.getElementById('currentPageLabel').textContent = '第 ' + currentPage + ' 页';

    // 分页
    if (info.total_pages > 1) {
        let pageHtml = '';
        const totalPages = parseInt(info.total_pages);
        const start = Math.max(1, currentPage - 2);
        const end = Math.min(totalPages, currentPage + 2);

        if (currentPage > 1) {
            pageHtml += `<li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="goPage(${currentPage - 1})"><i class="fa fa-chevron-left"></i></a></li>`;
        }
        if (start > 1) {
            pageHtml += `<li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="goPage(1)">1</a></li>`;
            if (start > 2) pageHtml += `<li class="page-item disabled"><span class="page-link">…</span></li>`;
        }
        for (let i = start; i <= end; i++) {
            pageHtml += `<li class="page-item ${i === currentPage ? 'active' : ''}"><a class="page-link" href="javascript:void(0)" onclick="goPage(${i})">${i}</a></li>`;
        }
        if (end < totalPages) {
            if (end < totalPages - 1) pageHtml += `<li class="page-item disabled"><span class="page-link">…</span></li>`;
            pageHtml += `<li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="goPage(${totalPages})">${totalPages}</a></li>`;
        }
        if (currentPage < totalPages) {
            pageHtml += `<li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="goPage(${currentPage + 1})"><i class="fa fa-chevron-right"></i></a></li>`;
        }

        document.getElementById('pageButtons').innerHTML = pageHtml;
        document.getElementById('pagination').classList.remove('d-none');
    } else {
        document.getElementById('pagination').classList.add('d-none');
    }
}

// —— 分页跳转 ——
function goPage(page) {
    currentPage = page;
    loadPlugins();
    document.querySelector('.app-main')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// —— 插件详情 ——
function showDetail(packageName) {
    const modal = new bootstrap.Modal('#detailModal');
    modal.show();

    // 从缓存数据中查找
    const plugin = allPlugins.find(function(p) { return p.package_name === packageName; });

    if (plugin) {
        const isInstalled = installedPackages.indexOf(packageName) !== -1;
        document.getElementById('detailContent').innerHTML = `
            <div class="text-center mb-3">
                <span class="avatar rounded-circle d-inline-flex align-items-center justify-content-center bg-primary text-white fw-bold mb-2" style="width:56px;height:56px;font-size:22px;">
                    ${escapeHtml((plugin.title || plugin.name).charAt(0).toUpperCase())}
                </span>
                <h5 class="mb-1">${escapeHtml(plugin.title || plugin.name)}</h5>
                <code class="text-muted">${escapeHtml(packageName)}</code>
            </div>
            <hr>
            <div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <small class="text-muted d-block">版本</small>
                    <span class="badge bg-light text-dark">v${escapeHtml(plugin.version || '—')}</span>
                </div>
                <div class="col-sm-6">
                    <small class="text-muted d-block">作者</small>
                    <span>${escapeHtml(plugin.author || '—')}</span>
                </div>
                ${plugin.homepage ? `
                <div class="col-12">
                    <small class="text-muted d-block">主页</small>
                    <a href="${escapeHtml(plugin.homepage)}" target="_blank" rel="noopener">${escapeHtml(plugin.homepage)} <i class="fa fa-external-link ms-1"></i></a>
                </div>` : ''}
                ${plugin.downloads ? `
                <div class="col-sm-6">
                    <small class="text-muted d-block">下载量</small>
                    <span><i class="fa fa-download me-1 text-muted"></i>${plugin.downloads}</span>
                </div>` : ''}
                ${plugin.category ? `
                <div class="col-sm-6">
                    <small class="text-muted d-block">分类</small>
                    <span class="badge bg-secondary">${escapeHtml(plugin.category)}</span>
                </div>` : ''}
            </div>
            ${plugin.description ? `
            <hr>
            <h6 class="text-muted">描述</h6>
            <p class="mb-0">${escapeHtml(plugin.description)}</p>` : ''}
        `;

        const footer = document.getElementById('detailFooter');
        if (!isInstalled) {
            footer.innerHTML = `<button class="btn btn-primary" onclick="installPlugin('${escapeHtml(packageName)}', '${escapeHtml(plugin.title || plugin.name)}'); bootstrap.Modal.getInstance('#detailModal').hide();">
                <i class="fa fa-download me-1"></i>安装此插件
            </button>
            <button class="btn btn-secondary" data-bs-dismiss="modal">关闭</button>`;
        } else {
            footer.innerHTML = `<span class="text-success me-auto"><i class="fa fa-check-circle me-1"></i>此插件已安装</span>
            <button class="btn btn-secondary" data-bs-dismiss="modal">关闭</button>`;
        }
    } else {
        document.getElementById('detailContent').innerHTML = `
            <div class="text-center py-3">
                <i class="fa fa-info-circle fa-2x text-muted mb-2 d-block"></i>
                <p class="text-muted mb-1">插件详情不可用</p>
                <code class="small">${escapeHtml(packageName)}</code>
            </div>`;
        document.getElementById('detailFooter').innerHTML = `<button class="btn btn-secondary" data-bs-dismiss="modal">关闭</button>`;
    }
}

// —— 状态切换 ——
function showState(state) {
    document.getElementById('pluginList').innerHTML = '';
    hideAllStates();
    switch(state) {
        case 'loading': document.getElementById('loadingState').classList.remove('d-none'); break;
        case 'error':   document.getElementById('errorState').classList.remove('d-none'); break;
        case 'empty':   document.getElementById('emptyState').classList.remove('d-none'); break;
    }
}

function hideAllStates() {
    ['loadingState', 'errorState', 'emptyState'].forEach(function(id) {
        document.getElementById(id).classList.add('d-none');
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}
</script>
